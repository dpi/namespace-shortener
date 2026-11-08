<?php

/*
 * This file is part of the dpi/namespace-shortener-phpcs project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\PhpCodeSniffer;

use Dpi\NamespaceShortener\Core\Rewrite\Edit;
use Dpi\NamespaceShortener\Core\Rewrite\RewritePlan;
use PHP_CodeSniffer\Fixer;

/**
 * Translates a plan's byte offsets to PHP_CodeSniffer's tokens, which is what its fixer works on.
 *
 * Changing only the tokens involved lets other sniffs fix the rest of the file in the same pass.
 */
final class TokenEdits
{
    /**
     * @var list<int> the offset of each token
     */
    private array $offsets = [];

    /**
     * @var list<string> the content of each token
     */
    private array $contents = [];

    public function __construct(
        private readonly Fixer $fixer,
        int $tokenCount,
    ) {
        $offset = 0;

        for ($token = 0; $token < $tokenCount; ++$token) {
            $content = $fixer->getTokenContent($token);
            $this->offsets[] = $offset;
            $this->contents[] = $content;
            $offset += \strlen($content);
        }
    }

    /**
     * The last token starting at or before the offset, skipping any empty ones.
     */
    public function tokenAt(int $offset): int
    {
        for ($token = \count($this->offsets) - 1; $token > 0; --$token) {
            if ($this->offsets[$token] <= $offset && '' !== $this->contents[$token]) {
                return $token;
            }
        }

        return 0;
    }

    /**
     * Applies every edit together: if another sniff already changed one of these tokens, the fixer's next pass tries again.
     */
    public function apply(RewritePlan $plan): void
    {
        $contents = $this->contents;

        foreach ($plan->edits as $edit) {
            $contents = $this->applyEdit($contents, $edit);
        }

        $this->fixer->beginChangeset();

        foreach ($contents as $token => $content) {
            if ($content !== $this->contents[$token]) {
                $this->fixer->replaceToken($token, $content);
            }
        }

        $this->fixer->endChangeset();
    }

    /**
     * Edits arrive last to first, so a token's content before this edit's end still matches its offsets.
     *
     * @param array<int, string> $contents
     *
     * @return array<int, string>
     */
    private function applyEdit(array $contents, Edit $edit): array
    {
        $end = $edit->start + $edit->length;
        $first = $this->tokenAt($edit->start);
        $last = 0 === $edit->length ? $first : $this->tokenAt($end - 1);

        $before = substr($contents[$first], 0, $edit->start - $this->offsets[$first]);
        $after = substr($contents[$last], $end - $this->offsets[$last]);

        for ($token = $first; $token <= $last; ++$token) {
            $contents[$token] = '';
        }

        $contents[$first] = $before . $edit->replacement . $after;

        return $contents;
    }
}
