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
 * One imported name. A statement like `use A, B;` holds two.
 */
final readonly class ImportItem
{
    /**
     * @param non-empty-list<string> $segments
     */
    public function __construct(
        public int $id,
        public array $segments,
        public string $local,
        public bool $explicitAlias,
        public ImportKind $kind,
    ) {
    }

    public function render(): string
    {
        return ImportRenderer::render($this->kind, $this->segments, $this->explicitAlias ? $this->local : null);
    }
}
