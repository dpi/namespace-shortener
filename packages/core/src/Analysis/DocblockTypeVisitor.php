<?php

/*
 * This file is part of the dpi/namespace-shortener-core project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Core\Analysis;

use PHPStan\PhpDocParser\Ast\AbstractNodeVisitor;
use PHPStan\PhpDocParser\Ast\Attribute;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstFetchNode;
use PHPStan\PhpDocParser\Ast\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\InvalidTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TypeAliasTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeItemNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ObjectShapeItemNode;

/**
 * Collects the token indexes of type names from a parsed docblock.
 */
final class DocblockTypeVisitor extends AbstractNodeVisitor
{
    /**
     * @var list<int>
     */
    public array $nameIndexes = [];

    /**
     * @var list<string> names a docblock declares for itself, such as templates
     */
    public array $localTypes = [];

    /**
     * @var list<array{tagIndex: int, tag: string, value: string}> tags the parser could not understand
     */
    public array $invalidTags = [];

    /**
     * @var array<int, true>
     */
    private array $shapeKeys = [];

    public function enterNode(Node $node): ?Node
    {
        if ($node instanceof ArrayShapeItemNode || $node instanceof ObjectShapeItemNode) {
            if ($node->keyName instanceof IdentifierTypeNode) {
                $this->shapeKeys[spl_object_id($node->keyName)] = true;
            }
        } elseif ($node instanceof TemplateTagValueNode) {
            $this->localTypes[] = $node->name;
        } elseif ($node instanceof TypeAliasTagValueNode) {
            $this->localTypes[] = $node->alias;
        } elseif ($node instanceof PhpDocTagNode && $node->value instanceof InvalidTagValueNode) {
            $tagIndex = $node->getAttribute(Attribute::START_INDEX);

            if (\is_int($tagIndex)) {
                $this->invalidTags[] = ['tagIndex' => $tagIndex, 'tag' => $node->name, 'value' => $node->value->value];
            }
        } elseif ($node instanceof IdentifierTypeNode && !isset($this->shapeKeys[spl_object_id($node)])) {
            $this->remember($node);
        } elseif ($node instanceof ConstFetchNode && '' !== $node->className) {
            $this->remember($node);
        }

        return null;
    }

    private function remember(Node $node): void
    {
        $index = $node->getAttribute(Attribute::START_INDEX);

        if (\is_int($index)) {
            $this->nameIndexes[] = $index;
        }
    }
}
