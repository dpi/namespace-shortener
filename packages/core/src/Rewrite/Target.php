<?php

/*
 * This file is part of the dpi/namespace-shortener-core project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Core\Rewrite;

use Dpi\NamespaceShortener\Core\Analysis\ImportKind;
use Dpi\NamespaceShortener\Core\Analysis\ImportRenderer;

/**
 * An import a scope needs after rewriting.
 */
final readonly class Target
{
    /**
     * @param non-empty-list<string> $root
     */
    public function __construct(
        public array $root,
        public string $local,
        public bool $explicitAlias,
        public int $firstUsageStart,
    ) {
    }

    public function rootKey(): string
    {
        return strtolower(implode('\\', $this->root));
    }

    public function render(): string
    {
        return ImportRenderer::render(ImportKind::Normal, $this->root, $this->explicitAlias ? $this->local : null);
    }
}
