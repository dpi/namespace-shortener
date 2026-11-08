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
 * The verified changes to make to a file. Apply all of them or none: they only keep the code's meaning together.
 */
final readonly class RewritePlan
{
    /**
     * @param list<Edit> $edits ordered last to first, so applying them in order keeps the remaining offsets valid
     */
    public function __construct(
        public string $code,
        public array $edits,
    ) {
    }

    public function apply(): string
    {
        $rewritten = $this->code;

        foreach ($this->edits as $edit) {
            $rewritten = substr_replace($rewritten, $edit->replacement, $edit->start, $edit->length);
        }

        return $rewritten;
    }
}
