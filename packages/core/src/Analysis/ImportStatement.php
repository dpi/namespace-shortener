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
 * A complete `use` statement, which may hold several imported names.
 */
final readonly class ImportStatement
{
    /**
     * @param int $start the offset of the first byte
     * @param int $end the offset after the last byte
     * @param non-empty-list<ImportItem> $items
     */
    public function __construct(
        public int $start,
        public int $end,
        public array $items,
    ) {
    }
}
