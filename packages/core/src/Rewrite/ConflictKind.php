<?php

/*
 * This file is part of the dpi/namespace-shortener-core project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Core\Rewrite;

/**
 * Each case is explained in docs/guide/errors.md.
 */
enum ConflictKind
{
    case ReservedName;
    case KeywordAsBareName;
    case ImportCollision;
    case DeclaredClass;
    case ImplicitNamespaceName;
    case CompetingRoots;
}
