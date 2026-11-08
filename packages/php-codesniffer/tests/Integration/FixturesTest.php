<?php

/*
 * This file is part of the dpi/namespace-shortener-phpcs project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\PhpCodeSniffer\Tests\Integration;

use Dpi\NamespaceShortener\Tests\Support\Fixture;
use PHPUnit\Framework\Attributes\DataProvider;

use function Safe\file_get_contents;

/**
 * The fixtures describe the rule, not one tool, so going through PHP_CodeSniffer's tokens must give the same results.
 */
final class FixturesTest extends CliTestCase
{
    /**
     * @return iterable<string, array{Fixture}>
     */
    public static function fixFixtures(): iterable
    {
        return Fixture::all('fix');
    }

    /**
     * @return iterable<string, array{Fixture}>
     */
    public static function conflictFixtures(): iterable
    {
        return Fixture::all('conflict');
    }

    #[DataProvider('fixFixtures')]
    public function testPhpcbfProducesTheExpectedCodeAndPhpcsThenPasses(Fixture $fixture): void
    {
        $this->writeRuleset($fixture->roots);
        $path = $this->writeSource($fixture->input);

        $this->runTool('phpcbf');

        self::assertSame($fixture->outcome, file_get_contents($path));
        self::assertSame([], $this->findings());
    }

    #[DataProvider('conflictFixtures')]
    public function testConflictIsReportedAndPhpcbfLeavesTheFileAlone(Fixture $fixture): void
    {
        [$kind, $line, $message] = explode('|', trim($fixture->outcome), 3);

        $this->writeRuleset($fixture->roots);
        $path = $this->writeSource($fixture->input);

        $matching = array_filter(
            $this->findings(),
            static fn (array $finding): bool => self::SNIFF . '.' . $kind === $finding['source']
                && (int) $line === $finding['line']
                && !$finding['fixable']
                && str_contains($finding['message'], $message),
        );

        self::assertNotSame([], $matching);

        $this->runTool('phpcbf');

        self::assertSame($fixture->input, file_get_contents($path));
    }
}
