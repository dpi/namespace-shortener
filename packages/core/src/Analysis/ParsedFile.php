<?php

/*
 * This file is part of the dpi/namespace-shortener-core project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Core\Analysis;

final readonly class ParsedFile
{
    /**
     * @param list<Scope> $scopes
     * @param list<string> $declaredClasses lowercase fully qualified names of class-likes declared in the file
     */
    public function __construct(
        public array $scopes,
        public array $declaredClasses,
    ) {
    }

    /**
     * Every symbol referenced by the file, in order. A rewrite must leave this unchanged.
     *
     * @return list<string>
     */
    public function resolvedSymbols(): array
    {
        $symbols = [];

        foreach ($this->scopes as $scope) {
            foreach ($scope->names as $name) {
                $symbols[] = strtolower(implode('\\', $name->resolved));
            }
        }

        return $symbols;
    }
}
