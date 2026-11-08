<?php

/*
 * This file is part of the dpi/namespace-shortener-core project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Core\Analysis;

use Dpi\NamespaceShortener\Core\Exception\UnparsableSource;
use Dpi\NamespaceShortener\Core\Php\ReservedNames;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Turns source code into scopes of imports and resolved names, with their byte offsets.
 */
final class SourceParser
{
    private readonly Parser $parser;

    private readonly DocblockNameExtractor $docblocks;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->docblocks = new DocblockNameExtractor();
    }

    /**
     * @throws UnparsableSource
     */
    public function parse(string $code): ParsedFile
    {
        try {
            $statements = $this->parser->parse($code) ?? [];
        } catch (Error $error) {
            throw new UnparsableSource($error->getMessage(), max(1, $error->getStartLine()), $error);
        }

        $docblocks = $this->extractDocblocks($code);
        $localTypes = array_merge(...array_column($docblocks, 'localTypes'), ...[[]]);
        $localTypes = array_map(strtolower(...), $localTypes);
        $namespaces = array_values(array_filter($statements, static fn (Node\Stmt $statement): bool => $statement instanceof Node\Stmt\Namespace_));

        $scopes = [];
        $declaredClasses = [];

        if ([] === $namespaces) {
            [$scope, $declared] = $this->parseScope($code, [], $statements, 0, \strlen($code), $docblocks, $localTypes, $this->globalInsertion($code, $statements));
            $scopes[] = $scope;
            $declaredClasses = $declared;
        }

        foreach ($namespaces as $index => $namespace) {
            $end = isset($namespaces[$index + 1]) ? $namespaces[$index + 1]->getStartFilePos() : \strlen($code);
            [$scope, $declared] = $this->parseScope(
                $code,
                $namespace->name?->getParts() ?? [],
                $namespace->stmts,
                $namespace->getStartFilePos(),
                $end,
                $docblocks,
                $localTypes,
                $this->namespaceInsertion($code, $namespace),
            );
            $scopes[] = $scope;
            $declaredClasses = [...$declaredClasses, ...$declared];
        }

        return new ParsedFile($scopes, $declaredClasses);
    }

    /**
     * @param list<string> $namespace
     * @param array<Node\Stmt> $statements
     * @param list<array{offset: int, names: list<DocblockName>, localTypes: list<string>}> $docblocks
     * @param list<string> $localTypes
     * @param array{0: int, 1: InsertStyle} $insertion
     *
     * @return array{0: Scope, 1: list<string>}
     */
    private function parseScope(string $code, array $namespace, array $statements, int $start, int $end, array $docblocks, array $localTypes, array $insertion): array
    {
        $visitor = new ScopeNameVisitor($namespace);
        (new NodeTraverser($visitor))->traverse($statements);

        $names = $visitor->names;
        $imports = $visitor->importsByLocal();

        foreach ($docblocks as $docblock) {
            if ($docblock['offset'] < $start || $docblock['offset'] >= $end) {
                continue;
            }

            foreach ($docblock['names'] as $name) {
                $occurrence = $this->resolveDocblockName($docblock['offset'], $name, $namespace, $imports, $localTypes);

                if (null !== $occurrence) {
                    $names[] = $occurrence;
                }
            }
        }

        return [
            new Scope($namespace, $visitor->statements, $names, $insertion[0], $insertion[1], $this->indentOf($code, $statements)),
            $visitor->declaredClasses,
        ];
    }

    /**
     * @param list<string> $namespace
     * @param array<string, ImportItem> $imports
     * @param list<string> $localTypes
     */
    private function resolveDocblockName(int $docblockOffset, DocblockName $name, array $namespace, array $imports, array $localTypes): ?NameOccurrence
    {
        $isBareName = !str_contains($name->text, '\\');

        if (!$name->isTag && $isBareName && (ReservedNames::isDocPseudoType($name->text) || \in_array(strtolower($name->text), $localTypes, true))) {
            return null;
        }

        $occurrence = NameResolution::resolve(
            $docblockOffset + $name->offset,
            $name->text,
            $name->isTag ? NameContext::DocblockTag : NameContext::Docblock,
            $namespace,
            $imports,
        );

        if (!$name->isTag) {
            return $occurrence;
        }

        // A tag such as @param is only a reference if it exactly matches an import, or names a class in full.
        if (NameForm::FullyQualified === $occurrence->form) {
            return $occurrence;
        }

        if (null === $occurrence->importId) {
            return null;
        }

        return $imports[strtolower($occurrence->firstSegment())]->local === $occurrence->firstSegment() ? $occurrence : null;
    }

    /**
     * @return list<array{offset: int, names: list<DocblockName>, localTypes: list<string>}>
     */
    private function extractDocblocks(string $code): array
    {
        $docblocks = [];

        foreach (\PhpToken::tokenize($code) as $token) {
            if ($token->is(\T_DOC_COMMENT)) {
                $docblocks[] = ['offset' => $token->pos, ...$this->docblocks->extract($token->text)];
            }
        }

        return $docblocks;
    }

    /**
     * @return array{0: int, 1: InsertStyle}
     */
    private function namespaceInsertion(string $code, Node\Stmt\Namespace_ $namespace): array
    {
        $from = null === $namespace->name ? $namespace->getStartFilePos() + \strlen('namespace') : $namespace->name->getEndFilePos() + 1;
        $offset = $from + strcspn($code, ';{', $from);

        return [$offset + 1, '{' === $code[$offset] ? InsertStyle::AfterBrace : InsertStyle::AfterStatement];
    }

    /**
     * @param array<Node\Stmt> $statements
     *
     * @return array{0: int, 1: InsertStyle}
     */
    private function globalInsertion(string $code, array $statements): array
    {
        $lastDeclare = null;

        foreach ($statements as $statement) {
            if (!$statement instanceof Node\Stmt\Declare_ || null !== $statement->stmts) {
                break;
            }

            $lastDeclare = $statement;
        }

        if (null !== $lastDeclare) {
            return [$lastDeclare->getEndFilePos() + 1, InsertStyle::AfterStatement];
        }

        foreach (\PhpToken::tokenize($code) as $token) {
            if ($token->is(\T_OPEN_TAG)) {
                return [$token->pos + \strlen($token->text), InsertStyle::AfterOpenTag];
            }
        }

        return [0, InsertStyle::AfterOpenTag];
    }

    /**
     * @param array<Node\Stmt> $statements
     */
    private function indentOf(string $code, array $statements): string
    {
        foreach ($statements as $statement) {
            if ($statement instanceof Node\Stmt\Declare_ || $statement instanceof Node\Stmt\InlineHTML) {
                continue;
            }

            $start = $statement->getStartFilePos();
            $lineStart = false === ($newline = strrpos(substr($code, 0, $start), "\n")) ? 0 : $newline + 1;
            $prefix = substr($code, $lineStart, $start - $lineStart);

            return '' === trim($prefix) ? $prefix : '';
        }

        return '';
    }
}
