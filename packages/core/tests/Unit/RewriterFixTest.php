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
use Dpi\NamespaceShortener\Core\Rewrite\Rewriter;
use Dpi\NamespaceShortener\Tests\Support\Fixture;
use Dpi\NamespaceShortener\Tests\Support\PhpLint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Each fixture in Fixtures/fix is one behaviour.
 */
final class RewriterFixTest extends TestCase
{
    #[DataProvider('fixtures')]
    public function testRewriteProducesExpectedCode(Fixture $fixture): void
    {
        self::assertSame($fixture->outcome, self::rewriter($fixture)->rewrite($fixture->input));
    }

    #[DataProvider('fixtures')]
    public function testFixingFixedCodeChangesNothing(Fixture $fixture): void
    {
        self::assertSame($fixture->outcome, self::rewriter($fixture)->rewrite($fixture->outcome));
    }

    #[DataProvider('fixtures')]
    public function testFixedCodeCompiles(Fixture $fixture): void
    {
        $result = PhpLint::lint($fixture->outcome);

        self::assertSame(0, $result['exitCode'], $result['output']);
    }

    /**
     * @return iterable<string, array{Fixture}>
     */
    public static function fixtures(): iterable
    {
        return Fixture::all('fix');
    }

    private static function rewriter(Fixture $fixture): Rewriter
    {
        return new Rewriter(ImportRootSet::fromStrings($fixture->roots));
    }
}
