<?php

/*
 * This file is part of the dpi/namespace-shortener-core project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Core\Analysis;

/**
 * A namespace block, or a whole file with no namespace. PHP keeps separate imports for each.
 */
final readonly class Scope
{
    /**
     * @param list<string> $namespace empty for the global namespace
     * @param list<ImportStatement> $statements
     * @param list<NameOccurrence> $names
     * @param int $insertAt where a statement goes when there are no imports to follow
     * @param InsertStyle $insertStyle how a statement is laid out at that offset
     * @param string $indent indentation for statements in this scope
     */
    public function __construct(
        public array $namespace,
        public array $statements,
        public array $names,
        public int $insertAt,
        public InsertStyle $insertStyle,
        public string $indent,
    ) {
    }
}
