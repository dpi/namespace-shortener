<?php

/*
 * This file is part of the dpi/namespace-shortener-core project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Core\Rewrite;

final readonly class ScopePlan
{
    /**
     * @param list<Edit> $edits
     * @param list<Conflict> $conflicts
     */
    public function __construct(
        public array $edits,
        public array $conflicts,
    ) {
    }
}
