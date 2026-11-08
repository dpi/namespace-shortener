<?php

/*
 * This file is part of the dpi/namespace-shortener-core project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Core\Tests\Unit;

use Dpi\NamespaceShortener\Core\Php\ReservedNames;
use Dpi\NamespaceShortener\Tests\Support\PhpLint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Holds the lists of names to what the real compiler does, so they can't drift from PHP.
 */
final class ReservedNamesTest extends TestCase
{
    #[DataProvider('reservedClassNames')]
    public function testCompilerRejectsReservedClassNameAsImport(string $name): void
    {
        if (\PHP_VERSION_ID < 80500 && \in_array($name, ['array', 'callable'], true)) {
            self::assertTrue(ReservedNames::isReservedClassName($name));

            return;
        }

        self::assertNotSame(0, PhpLint::lint(\sprintf("<?php\nnamespace App;\nuse Foo\\%s;\n", $name))['exitCode']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function reservedClassNames(): iterable
    {
        foreach (ReservedNames::CLASS_NAMES as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('keywords')]
    public function testCompilerRejectsKeywordAsImportAlias(string $name): void
    {
        self::assertNotSame(0, PhpLint::lint(\sprintf("<?php\nnamespace App;\nuse Foo as %s;\n", $name))['exitCode']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function keywords(): iterable
    {
        foreach (ReservedNames::KEYWORDS as $name) {
            // Only tokenized as a keyword from PHP 8.4, but reserved here so results don't vary by version.
            if ('__property__' !== $name) {
                yield $name => [$name];
            }
        }
    }

    public function testKeywordIsAllowedAsTheLastSegmentOfAnImport(): void
    {
        $result = PhpLint::lint("<?php\nnamespace App;\nuse Foo\\List;\necho List\\Bar::class;\n");

        self::assertSame(0, $result['exitCode'], $result['output']);
    }
}
