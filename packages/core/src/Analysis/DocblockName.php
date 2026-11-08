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
 * A name found in a docblock.
 */
final readonly class DocblockName
{
    public function __construct(
        public int $offset,
        public string $text,
        public bool $isTag,
    ) {
    }
}
