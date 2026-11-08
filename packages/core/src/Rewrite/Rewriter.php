<?php

/*
 * This file is part of the dpi/namespace-shortener-core project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Core\Rewrite;

use Dpi\NamespaceShortener\Core\Analysis\SourceParser;
use Dpi\NamespaceShortener\Core\Config\ImportRootSet;
use Dpi\NamespaceShortener\Core\Exception\NamespaceShortenerException;
use Dpi\NamespaceShortener\Core\Exception\RewriteVerificationFailed;
use Dpi\NamespaceShortener\Core\Exception\UnparsableSource;
use Dpi\NamespaceShortener\Core\Exception\UnsafeRewrite;

/**
 * Rewrites source code so names go through the import roots, or fails without changing anything.
 */
final class Rewriter
{
    private readonly SourceParser $parser;

    public function __construct(
        private readonly ImportRootSet $roots,
    ) {
        $this->parser = new SourceParser();
    }

    /**
     * @throws NamespaceShortenerException
     */
    public function rewrite(string $code): string
    {
        return $this->plan($code)->apply();
    }

    /**
     * For a tool that reports each change, rather than taking the rewritten code as a whole.
     *
     * @throws NamespaceShortenerException
     */
    public function plan(string $code): RewritePlan
    {
        $file = $this->parser->parse($code);
        $edits = [];
        $conflicts = [];

        foreach ($file->scopes as $scope) {
            $plan = ScopePlanner::plan($code, $scope, $this->roots, $file->declaredClasses);
            $edits = [...$edits, ...$plan->edits];
            $conflicts = [...$conflicts, ...$plan->conflicts];
        }

        if ([] !== $conflicts) {
            throw new UnsafeRewrite($conflicts);
        }

        usort($edits, static fn (Edit $a, Edit $b): int => $b->start <=> $a->start);

        $plan = new RewritePlan($code, $edits);

        if ([] !== $edits) {
            $this->verify($file->resolvedSymbols(), $plan->apply());
        }

        return $plan;
    }

    /**
     * Whatever the planner decided, every name must still mean the same symbol.
     *
     * @param list<string> $expected
     *
     * @throws RewriteVerificationFailed
     */
    private function verify(array $expected, string $rewritten): void
    {
        try {
            $actual = $this->parser->parse($rewritten)->resolvedSymbols();
        } catch (UnparsableSource $exception) {
            throw new RewriteVerificationFailed('The rewritten code does not parse: ' . $exception->getMessage(), 0, $exception);
        }

        foreach ($expected as $index => $symbol) {
            if ($symbol !== ($actual[$index] ?? null)) {
                throw new RewriteVerificationFailed(\sprintf('The rewrite would change a reference from "%s" to "%s".', $symbol, $actual[$index] ?? 'nothing'));
            }
        }

        if (\count($actual) !== \count($expected)) {
            throw new RewriteVerificationFailed(\sprintf('The rewrite would change the number of references from %d to %d.', \count($expected), \count($actual)));
        }
    }
}
