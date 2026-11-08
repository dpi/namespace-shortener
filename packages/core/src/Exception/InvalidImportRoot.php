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
 * A configured import root, or a file of them, cannot be used.
 */
final class InvalidImportRoot extends \InvalidArgumentException implements NamespaceShortenerException
{
}
