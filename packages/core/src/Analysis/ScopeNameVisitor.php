<?php

/*
 * This file is part of the dpi/namespace-shortener-core project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Core\Analysis;

use PhpParser\Node;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

/**
 * Collects a scope's imports and names in source order, resolving names the way PHP compiles them:
 * an import only affects the names after it.
 */
final class ScopeNameVisitor extends NodeVisitorAbstract
{
    private const string NON_CLASS_NAME = 'dpiNamespaceShortenerNonClassName';

    /**
     * @var list<ImportStatement>
     */
    public array $statements = [];

    /**
     * @var list<NameOccurrence>
     */
    public array $names = [];

    /**
     * @var list<string>
     */
    public array $declaredClasses = [];

    /**
     * @var array<string, ImportItem>
     */
    private array $importsByLocal = [];

    private int $nextImportId = 0;

    /**
     * @param list<string> $namespace
     */
    public function __construct(
        private readonly array $namespace,
    ) {
    }

    /**
     * @return array<string, ImportItem>
     */
    public function importsByLocal(): array
    {
        return $this->importsByLocal;
    }

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Node\Stmt\Use_) {
            $this->recordStatement($node, [], $node->uses, $node->type);

            return NodeVisitor::DONT_TRAVERSE_CHILDREN;
        }

        if ($node instanceof Node\Stmt\GroupUse) {
            $this->recordStatement($node, $node->prefix->getParts(), $node->uses, $node->type);

            return NodeVisitor::DONT_TRAVERSE_CHILDREN;
        }

        if ($node instanceof Node\Stmt\ClassLike && null !== $node->name) {
            $this->declaredClasses[] = strtolower(implode('\\', [...$this->namespace, $node->name->toString()]));
        }

        if (($node instanceof Node\Expr\FuncCall || $node instanceof Node\Expr\ConstFetch) && $node->name instanceof Node\Name) {
            $node->name->setAttribute(self::NON_CLASS_NAME, true);
        }

        if ($node instanceof Node\Name) {
            $this->recordName($node);
        }

        return null;
    }

    /**
     * @param list<string> $prefix
     * @param array<Node\UseItem> $uses
     */
    private function recordStatement(Node\Stmt $statement, array $prefix, array $uses, int $statementType): void
    {
        $items = [];

        foreach ($uses as $use) {
            $type = Node\Stmt\Use_::TYPE_UNKNOWN === $statementType ? $use->type : $statementType;
            /** @var non-empty-list<string> $segments */
            $segments = [...$prefix, ...$use->name->getParts()];
            $item = new ImportItem(
                $this->nextImportId++,
                $segments,
                $use->getAlias()->toString(),
                null !== $use->alias,
                match ($type) {
                    Node\Stmt\Use_::TYPE_FUNCTION => ImportKind::Function,
                    Node\Stmt\Use_::TYPE_CONSTANT => ImportKind::Constant,
                    default => ImportKind::Normal,
                },
            );
            $items[] = $item;

            if (ImportKind::Normal === $item->kind) {
                $this->importsByLocal[strtolower($item->local)] = $item;
            }
        }

        if ([] !== $items) {
            $this->statements[] = new ImportStatement($statement->getStartFilePos(), $statement->getEndFilePos() + 1, $items);
        }
    }

    private function recordName(Node\Name $name): void
    {
        $isNonClass = true === $name->getAttribute(self::NON_CLASS_NAME);

        if (!$isNonClass && $name->isSpecialClassName()) {
            return;
        }

        $this->names[] = NameResolution::resolve(
            $name->getStartFilePos(),
            $name->toCodeString(),
            $isNonClass ? NameContext::FunctionOrConstant : NameContext::ClassLike,
            $this->namespace,
            $this->importsByLocal,
        );
    }
}
