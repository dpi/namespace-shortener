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
 * What precedes the offset a new import is inserted at, when no import exists to follow.
 */
enum InsertStyle
{
    /**
     * A statement ending in a semicolon: `namespace App;` or `declare(strict_types=1);`.
     */
    case AfterStatement;
    /**
     * The opening brace of `namespace App {`.
     */
    case AfterBrace;
    /**
     * The opening tag, which normally includes the line break after it.
     */
    case AfterOpenTag;
}
