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
 * Where a name appears, which decides how PHP resolves it.
 */
enum NameContext
{
    case ClassLike;
    case FunctionOrConstant;
    case Docblock;
    case DocblockTag;
}
