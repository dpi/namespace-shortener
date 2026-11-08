<?php

/*
 * This file is part of the dpi/namespace-shortener-core project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Core\Config;

/**
 * How a name should be written once its import root is imported.
 */
final readonly class RootMatch
{
    /**
     * @param non-empty-list<string> $root the namespace to import
     * @param list<string> $rest the segments which follow the local name at usages
     */
    public function __construct(
        public array $root,
        public string $local,
        public bool $explicitAlias,
        public array $rest,
    ) {
    }
}
