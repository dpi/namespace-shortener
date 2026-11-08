<?php

/*
 * This file is part of the dpi/namespace-shortener-core project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Core\Tests\Unit;

use Dpi\NamespaceShortener\Core\Config\ImportRootSet;
use Dpi\NamespaceShortener\Core\Exception\RewriteVerificationFailed;
use Dpi\NamespaceShortener\Core\Exception\UnparsableSource;
use Dpi\NamespaceShortener\Core\Exception\UnsafeRewrite;
use Dpi\NamespaceShortener\Core\Rewrite\ConflictKind;
use Dpi\NamespaceShortener\Core\Rewrite\Rewriter;
use Dpi\NamespaceShortener\Tests\Support\Fixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Each fixture in Fixtures/conflict is a rewrite which must fail, rather than break or silently change the code.
 */
final class RewriterConflictTest extends TestCase
{
    #[DataProvider('fixtures')]
    public function testConflictIsRaisedWithExplanation(Fixture $fixture): void
    {
        [$kind, $line, $message] = explode('|', trim($fixture->outcome), 3);

        try {
            (new Rewriter(ImportRootSet::fromStrings($fixture->roots)))->rewrite($fixture->input);
            self::fail('Expected a conflict.');
        } catch (UnsafeRewrite $exception) {
            $conflicts = array_filter(
                $exception->conflicts,
                static fn ($conflict): bool => $conflict->kind->name === $kind && (string) $conflict->line === $line,
            );

            self::assertNotSame([], $conflicts, 'Got: ' . $exception->getMessage());
            self::assertStringContainsString($message, $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{Fixture}>
     */
    public static function fixtures(): iterable
    {
        return Fixture::all('conflict');
    }

    public function testEveryConflictKindHasAFixture(): void
    {
        $covered = [];

        foreach (Fixture::all('conflict') as [$fixture]) {
            $covered[explode('|', trim($fixture->outcome), 2)[0]] = true;
        }

        foreach (ConflictKind::cases() as $kind) {
            self::assertArrayHasKey($kind->name, $covered);
        }
    }

    public function testEveryConflictInAFileIsReportedTogether(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App;
            use X\D;
            use A\B\C\D\E;
            use A\B\String\F;
            new E();
            new F();
            PHP;

        try {
            (new Rewriter(ImportRootSet::fromStrings(['A\B\C\*', 'A\B\*'])))->rewrite($code);
            self::fail('Expected a conflict.');
        } catch (UnsafeRewrite $exception) {
            $kinds = array_map(static fn ($conflict): ConflictKind => $conflict->kind, $exception->conflicts);

            self::assertContains(ConflictKind::ImportCollision, $kinds);
            self::assertContains(ConflictKind::ReservedName, $kinds);
        }
    }

    /**
     * An import only applies to code after it, so the planner's result is wrong here and only verification sees it.
     */
    public function testRewriteThatChangesAReferenceIsRefusedByVerification(): void
    {
        $code = "<?php\nnamespace App;\nnew \\A\\B\\C\\D\\E();\nuse X\\Y;\nnew Y();\n";

        $this->expectException(RewriteVerificationFailed::class);
        $this->expectExceptionMessage('would change a reference');

        (new Rewriter(ImportRootSet::fromStrings(['A\B\C\*'])))->rewrite($code);
    }

    public function testUnparsableSourceIsAnError(): void
    {
        $this->expectException(UnparsableSource::class);

        (new Rewriter(ImportRootSet::fromStrings(['A\*'])))->rewrite("<?php\nclass {\n");
    }
}
