<?php

/*
 * This file is part of the dpi/namespace-shortener-core project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Core\Config;

use Dpi\NamespaceShortener\Core\Exception\InvalidImportRoot;

/**
 * Every configured rule, ordered so the most specific rule matches first.
 */
final readonly class ImportRootSet
{
    /**
     * @param non-empty-list<ImportRoot> $roots
     */
    private function __construct(
        private array $roots,
    ) {
    }

    /**
     * @param array<mixed> $definitions
     *
     * @throws InvalidImportRoot
     */
    public static function fromStrings(array $definitions): self
    {
        $roots = [];

        foreach ($definitions as $definition) {
            if (!\is_string($definition)) {
                throw new InvalidImportRoot(\sprintf('An import root must be a string, %s given.', get_debug_type($definition)));
            }

            $root = ImportRoot::fromString($definition);

            if (isset($roots[$root->key()])) {
                throw new InvalidImportRoot(\sprintf('Import root "%s" is configured more than once.', $definition));
            }

            $roots[$root->key()] = $root;
        }

        $roots = array_values($roots);

        if ([] === $roots) {
            throw new InvalidImportRoot('At least one import root is required.');
        }

        usort($roots, static fn (ImportRoot $a, ImportRoot $b): int => [\count($b->segments), $a->wildcard] <=> [\count($a->segments), $b->wildcard]);

        return new self($roots);
    }

    /**
     * @param non-empty-list<string> $name
     */
    public function match(array $name): ?RootMatch
    {
        foreach ($this->roots as $root) {
            $match = $root->match($name);

            if (null !== $match) {
                return $match;
            }
        }

        return null;
    }
}
