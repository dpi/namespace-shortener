<?php

/*
 * This file is part of the dpi/namespace-shortener-core project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Core\Exception;

/**
 * Source code could not be parsed, so its names cannot be analysed.
 */
final class UnparsableSource extends \RuntimeException implements NamespaceShortenerException
{
    public function __construct(
        string $message,
        public readonly int $sourceLine,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
