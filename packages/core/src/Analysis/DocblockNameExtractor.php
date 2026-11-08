<?php

/*
 * This file is part of the dpi/namespace-shortener-core project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Core\Analysis;

use PHPStan\PhpDocParser\Ast\NodeTraverser;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\ParserException;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;

use function Safe\preg_match;
use function Safe\preg_match_all;

/**
 * Finds the class names in a docblock. See docs/guide/what-gets-rewritten.md.
 */
final class DocblockNameExtractor
{
    private const string NAME = '\\\\?[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*(?:\\\\[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)*';

    private readonly Lexer $lexer;

    private readonly TypeParser $typeParser;

    private readonly PhpDocParser $parser;

    public function __construct()
    {
        $config = new ParserConfig(['lines' => false, 'indexes' => true, 'comments' => false]);
        $this->lexer = new Lexer($config);
        $constExprParser = new ConstExprParser($config);
        $this->typeParser = new TypeParser($config, $constExprParser);
        $this->parser = new PhpDocParser($config, $this->typeParser, $constExprParser);
    }

    /**
     * @return array{names: list<DocblockName>, localTypes: list<string>}
     */
    public function extract(string $docblock): array
    {
        $tokens = $this->lexer->tokenize($docblock);
        $offsets = [];
        $offset = 0;
        $names = [];

        foreach ($tokens as $index => $token) {
            $offsets[$index] = $offset;
            $offset += \strlen($token[Lexer::VALUE_OFFSET]);

            if (Lexer::TOKEN_PHPDOC_TAG === $token[Lexer::TYPE_OFFSET]) {
                $tag = substr($token[Lexer::VALUE_OFFSET], 1);

                if (1 === preg_match('/^' . self::NAME . '$/D', $tag)) {
                    $names[$offsets[$index] + 1] = new DocblockName($offsets[$index] + 1, $tag, true);
                }
            }
        }

        $visitor = new DocblockTypeVisitor();
        (new NodeTraverser([$visitor]))->traverse([$this->parser->parse(new TokenIterator($tokens))]);

        foreach ($this->typeNames($visitor, $tokens, 0) as $name) {
            $names[$name->offset] = $name;
        }

        // The parser rejects a tag like `@param Foo` for having no variable, but it still refers to Foo.
        foreach ($visitor->invalidTags as $invalid) {
            $valueOffset = strpos($docblock, $invalid['value'], $offsets[$invalid['tagIndex']] + \strlen($invalid['tag']));

            if (false !== $valueOffset && '' !== $invalid['value']) {
                foreach ($this->leadingTypeNames($invalid['value'], $valueOffset) as $name) {
                    $names[$name->offset] = $name;
                }
            }
        }

        // These tags hold free text for the parser, so references are matched directly.
        $references = '/(?:\{@(?:see|link)|@(?:see|uses|used-by|covers|coversDefaultClass))\s+\K' . self::NAME . '(?![a-zA-Z0-9_\x80-\xff\\\\(]|\.[a-zA-Z0-9_]|:(?!:))/';
        preg_match_all($references, $docblock, $matches, \PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as [$text, $matchOffset]) {
            $names[$matchOffset] = new DocblockName($matchOffset, $text, false);
        }

        ksort($names);

        return ['names' => array_values($names), 'localTypes' => $visitor->localTypes];
    }

    /**
     * @return list<DocblockName>
     */
    private function leadingTypeNames(string $value, int $baseOffset): array
    {
        $tokens = $this->lexer->tokenize($value);

        try {
            $type = $this->typeParser->parse(new TokenIterator($tokens));
        } catch (ParserException) {
            return [];
        }

        $visitor = new DocblockTypeVisitor();
        (new NodeTraverser([$visitor]))->traverse([$type]);

        return $this->typeNames($visitor, $tokens, $baseOffset);
    }

    /**
     * @param list<array{string, int, int}> $tokens
     *
     * @return list<DocblockName>
     */
    private function typeNames(DocblockTypeVisitor $visitor, array $tokens, int $baseOffset): array
    {
        $names = [];

        foreach ($visitor->nameIndexes as $nameIndex) {
            $text = $tokens[$nameIndex][Lexer::VALUE_OFFSET];

            if (1 === preg_match('/^' . self::NAME . '$/D', $text)) {
                $before = \array_slice($tokens, 0, $nameIndex);
                $offset = array_sum(array_map(static fn (array $token): int => \strlen($token[Lexer::VALUE_OFFSET]), $before));
                $names[] = new DocblockName($baseOffset + $offset, $text, false);
            }
        }

        return $names;
    }
}
