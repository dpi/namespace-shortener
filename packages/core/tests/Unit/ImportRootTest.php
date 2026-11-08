<?php

/*
 * This file is part of the dpi/namespace-shortener-core project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Core\Tests\Unit;

use Dpi\NamespaceShortener\Core\Config\ImportRoot;
use Dpi\NamespaceShortener\Core\Config\ImportRootSet;
use Dpi\NamespaceShortener\Core\Exception\InvalidImportRoot;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImportRootTest extends TestCase
{
    public function testWildcardCapturesOneSegment(): void
    {
        $match = ImportRoot::fromString('A\B\C\*')->match(['A', 'B', 'C', 'D', 'E', 'F']);

        self::assertNotNull($match);
        self::assertSame(['A', 'B', 'C', 'D'], $match->root);
        self::assertSame('D', $match->local);
        self::assertSame(['E', 'F'], $match->rest);
    }

    public function testWildcardNeedsASegmentAfterThePrefix(): void
    {
        self::assertNull(ImportRoot::fromString('A\B\C\*')->match(['A', 'B', 'C']));
    }

    public function testExactRootLocalNameIsItsLastSegment(): void
    {
        $match = ImportRoot::fromString('G\H\I')->match(['G', 'H', 'I', 'J', 'K']);

        self::assertNotNull($match);
        self::assertSame('I', $match->local);
        self::assertFalse($match->explicitAlias);
        self::assertSame(['J', 'K'], $match->rest);
    }

    public function testAliasedRootLocalNameIsItsAlias(): void
    {
        $match = ImportRoot::fromString('G\H\I as L')->match(['G', 'H', 'I', 'J', 'K']);

        self::assertNotNull($match);
        self::assertSame('L', $match->local);
        self::assertTrue($match->explicitAlias);
    }

    public function testMatchingIsCaseInsensitiveAndKeepsSourceCaseForTheCapturedSegment(): void
    {
        $match = ImportRoot::fromString('A\B\*')->match(['a', 'b', 'cC', 'D']);

        self::assertNotNull($match);
        self::assertSame(['A', 'B', 'cC'], $match->root);
    }

    public function testLeadingSeparatorIsAccepted(): void
    {
        self::assertNotNull(ImportRoot::fromString('\A\B')->match(['A', 'B']));
    }

    public function testSiblingNamespaceDoesNotMatch(): void
    {
        self::assertNull(ImportRoot::fromString('A\B')->match(['A', 'BB', 'C']));
    }

    #[DataProvider('invalidRoots')]
    public function testInvalidRootsAreRejected(string $definition, string $expectedMessage): void
    {
        $this->expectException(InvalidImportRoot::class);
        $this->expectExceptionMessage($expectedMessage);

        ImportRoot::fromString($definition);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidRoots(): iterable
    {
        yield 'empty' => ['', 'malformed'];
        yield 'wildcard alone' => ['*', 'malformed'];
        yield 'wildcard in the middle' => ['A\*\B', 'malformed'];
        yield 'partial wildcard' => ['A\B*', 'malformed'];
        yield 'trailing separator' => ['A\B\\', 'malformed'];
        yield 'empty segment' => ['A\\\\B', 'malformed'];
        yield 'digit first' => ['A\1B', 'malformed'];
        yield 'dangling as' => ['A\B as', 'malformed'];
        yield 'aliased wildcard' => ['A\* as X', 'cannot have an alias'];
        yield 'reserved class name alias' => ['A\B as String', 'cannot use "String" as an alias'];
        yield 'keyword alias' => ['A\B as List', 'cannot use "List" as an alias'];
        yield 'reserved last segment' => ['A\String', 'Give it an alias'];
    }

    public function testReservedLastSegmentIsAcceptedWithAnAlias(): void
    {
        self::assertNotNull(ImportRoot::fromString('A\String as Str')->match(['A', 'String']));
    }

    public function testSetRejectsNoRoots(): void
    {
        $this->expectException(InvalidImportRoot::class);
        $this->expectExceptionMessage('At least one');

        ImportRootSet::fromStrings([]);
    }

    public function testSetRejectsNonStrings(): void
    {
        $this->expectException(InvalidImportRoot::class);
        $this->expectExceptionMessage('must be a string');

        ImportRootSet::fromStrings([1]);
    }

    public function testSetRejectsTheSameRootTwiceEvenWithADifferentAliasOrCase(): void
    {
        $this->expectException(InvalidImportRoot::class);
        $this->expectExceptionMessage('more than once');

        ImportRootSet::fromStrings(['G\H\I', 'g\h\i as L']);
    }

    public function testSetPrefersTheLongerRootWhateverTheOrder(): void
    {
        $match = ImportRootSet::fromStrings(['A\*', 'A\B\C\*'])->match(['A', 'B', 'C', 'D', 'E']);

        self::assertNotNull($match);
        self::assertSame('D', $match->local);
    }

    public function testSetPrefersAnExactRootOverAWildcardOfTheSameLength(): void
    {
        $match = ImportRootSet::fromStrings(['A\B\*', 'A\B as X'])->match(['A', 'B', 'C', 'D']);

        self::assertNotNull($match);
        self::assertSame('X', $match->local);
    }
}
