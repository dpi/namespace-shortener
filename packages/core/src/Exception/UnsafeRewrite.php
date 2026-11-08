<?php

/*
 * This file is part of the dpi/namespace-shortener-core project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Core\Exception;

use Dpi\NamespaceShortener\Core\Rewrite\Conflict;

/**
 * A file needs rewriting, but doing so would break it or change its meaning.
 */
final class UnsafeRewrite extends \RuntimeException implements NamespaceShortenerException
{
    /**
     * @param non-empty-list<Conflict> $conflicts
     */
    public function __construct(
        public readonly array $conflicts,
    ) {
        parent::__construct(implode(' ', array_map(
            static fn (Conflict $conflict): string => \sprintf('Line %d: %s', $conflict->line, $conflict->message),
            $conflicts,
        )));
    }
}
