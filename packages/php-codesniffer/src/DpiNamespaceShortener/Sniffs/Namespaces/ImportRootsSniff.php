<?php

/*
 * This file is part of the dpi/namespace-shortener-phpcs project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\PhpCodeSniffer\DpiNamespaceShortener\Sniffs\Namespaces;

use Dpi\NamespaceShortener\Core\Config\ImportRootConfig;
use Dpi\NamespaceShortener\Core\Exception\InvalidImportRoot;
use Dpi\NamespaceShortener\Core\Exception\RewriteVerificationFailed;
use Dpi\NamespaceShortener\Core\Exception\UnparsableSource;
use Dpi\NamespaceShortener\Core\Exception\UnsafeRewrite;
use Dpi\NamespaceShortener\Core\Rewrite\Edit;
use Dpi\NamespaceShortener\Core\Rewrite\RewritePlan;
use Dpi\NamespaceShortener\Core\Rewrite\Rewriter;
use Dpi\NamespaceShortener\PhpCodeSniffer\TokenEdits;
use PHP_CodeSniffer\Exceptions\RuntimeException;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

use function Safe\preg_replace;

/**
 * Adapts the rewriter to PHP_CodeSniffer. Conflicts become errors, and each change becomes a fixable error.
 */
final class ImportRootsSniff implements Sniff
{
    /**
     * The import roots, set with a property in the ruleset. Not typed, so a wrong type gets this sniff's error.
     */
    public mixed $roots = [];

    /**
     * Paths to JSON files of import roots, relative to the working directory. Typed for the same reason.
     */
    public mixed $rootFiles = [];

    private ?Rewriter $rewriter = null;

    /**
     * PHP_CodeSniffer has set the properties by now, and turns this exception into a failed run before any file is read.
     *
     * @return list<int>
     */
    public function register(): array
    {
        if (!\is_array($this->roots) || !\is_array($this->rootFiles)) {
            throw new RuntimeException('DpiNamespaceShortener: the "roots" and "rootFiles" properties must have type="array".');
        }

        try {
            $this->rewriter = new Rewriter(ImportRootConfig::load($this->roots, $this->rootFiles));
        } catch (InvalidImportRoot $exception) {
            throw new RuntimeException('DpiNamespaceShortener: ' . $exception->getMessage(), 0, $exception);
        }

        return [\T_OPEN_TAG, \T_OPEN_TAG_WITH_ECHO];
    }

    /**
     * Runs once, at the first opening tag, since the rewriter handles the whole file.
     */
    public function process(File $phpcsFile, $stackPtr): int
    {
        \assert(null !== $this->rewriter);

        try {
            $this->report($phpcsFile, $this->rewriter->plan($phpcsFile->fixer->getContents()));
        } catch (UnsafeRewrite $exception) {
            foreach ($exception->conflicts as $conflict) {
                $phpcsFile->addErrorOnLine($conflict->message, $conflict->line, $conflict->kind->name);
            }
        } catch (RewriteVerificationFailed $exception) {
            $phpcsFile->addErrorOnLine($exception->getMessage(), 1, 'RewriteVerificationFailed');
        } catch (UnparsableSource $exception) {
            $phpcsFile->addErrorOnLine($exception->getMessage(), $exception->sourceLine, 'UnparsableSource');
        }

        return $phpcsFile->numTokens;
    }

    private function report(File $phpcsFile, RewritePlan $plan): void
    {
        $tokens = new TokenEdits($phpcsFile->fixer, $phpcsFile->numTokens);
        $fixable = true;

        foreach ($plan->edits as $edit) {
            // False under phpcs, or under phpcbf if the finding is suppressed.
            $fixable = $phpcsFile->addFixableError($this->describe($plan->code, $edit), $tokens->tokenAt($edit->start), 'NeedsRewrite') && $fixable;
        }

        // A suppressed finding keeps its code, and the plan only works as a whole, so nothing else can change either.
        if ($fixable && [] !== $plan->edits) {
            $tokens->apply($plan);
        }
    }

    private function describe(string $code, Edit $edit): string
    {
        $old = self::singleLine(substr($code, $edit->start, $edit->length));
        $new = self::singleLine($edit->replacement);

        return match (true) {
            '' === $old => \sprintf('Add "%s".', $new),
            '' === $new => \sprintf('Remove "%s".', $old),
            default => \sprintf('Replace "%s" with "%s".', $old, $new),
        };
    }

    private static function singleLine(string $code): string
    {
        return trim(preg_replace('/\s*\R\s*/', ' ', $code));
    }
}
