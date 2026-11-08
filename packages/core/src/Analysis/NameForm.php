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
 * The forms of a name, in the terms of the PHP manual.
 */
enum NameForm
{
    case Unqualified;
    case Qualified;
    case FullyQualified;
    case Relative;
}
