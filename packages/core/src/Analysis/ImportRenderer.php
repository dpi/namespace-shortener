<?php

/*
 * This file is part of the dpi/namespace-shortener-core project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Core\Analysis;

final class ImportRenderer
{
    /**
     * @param non-empty-list<string> $segments
     */
    public static function render(ImportKind $kind, array $segments, ?string $alias): string
    {
        return \sprintf(
            'use %s%s%s;',
            $kind->value,
            implode('\\', $segments),
            null === $alias ? '' : ' as ' . $alias,
        );
    }
}
