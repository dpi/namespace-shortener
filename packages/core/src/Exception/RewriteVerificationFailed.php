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
 * A rewrite was generated, but no longer resolves every name to the same symbol.
 */
final class RewriteVerificationFailed extends \RuntimeException implements NamespaceShortenerException
{
}
