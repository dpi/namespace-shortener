<?php

/*
 * This file is part of the dpi/namespace-shortener-core project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Core\Tests\Unit;

use Dpi\NamespaceShortener\Core\Config\ImportRootConfig;
use Dpi\NamespaceShortener\Core\Config\ImportRootSet;
use Dpi\NamespaceShortener\Core\Exception\InvalidImportRoot;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Safe\file_put_contents;
use function Safe\json_encode;
use function Safe\mkdir;
use function Safe\rmdir;
use function Safe\unlink;

final class ImportRootConfigTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/namespace-shortener-' . bin2hex(random_bytes(6));
        mkdir($this->directory . '/lib', recursive: true);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->directory);
    }

    private function write(string $name, string $json): string
    {
        $path = $this->directory . '/' . $name;
        file_put_contents($path, $json);

        return $path;
    }

    /**
     * @param list<string> $roots
     * @param array<string, mixed> $extra
     */
    private function writeRoots(string $name, array $roots, array $extra = []): string
    {
        return $this->write($name, json_encode(['roots' => $roots, ...$extra]));
    }

    private static function localNameFor(ImportRootSet $set, string $name): ?string
    {
        return $set->match(explode('\\', $name))?->local;
    }

    public function testRootsAloneWork(): void
    {
        self::assertSame('C', self::localNameFor(ImportRootConfig::load(['A\B\*'], []), 'A\B\C\D'));
    }

    public function testAFileAloneWorks(): void
    {
        $file = $this->writeRoots('a.json', ['A\B\*']);

        self::assertSame('C', self::localNameFor(ImportRootConfig::load([], [$file]), 'A\B\C\D'));
    }

    public function testRootsAndFilesAreCombined(): void
    {
        $file = $this->writeRoots('a.json', ['A\B']);
        $set = ImportRootConfig::load(['X\Y'], [$file]);

        self::assertSame('B', self::localNameFor($set, 'A\B\C'));
        self::assertSame('Y', self::localNameFor($set, 'X\Y\Z'));
    }

    public function testAnIncludeIsRelativeToTheFileThatIncludesIt(): void
    {
        $this->writeRoots('lib/library.json', ['A\B']);
        $this->writeRoots('lib/entry.json', [], ['include' => ['library.json']]);
        $file = $this->writeRoots('own.json', ['X\Y'], ['include' => ['lib/entry.json']]);
        $set = ImportRootConfig::load([], [$file]);

        self::assertSame('B', self::localNameFor($set, 'A\B\C'));
        self::assertSame('Y', self::localNameFor($set, 'X\Y\Z'));
    }

    public function testAnAbsoluteIncludeIsUsedAsItIs(): void
    {
        $library = $this->writeRoots('lib/library.json', ['A\B']);
        $file = $this->writeRoots('own.json', [], ['include' => [$library]]);

        self::assertSame('B', self::localNameFor(ImportRootConfig::load([], [$file]), 'A\B\C'));
    }

    public function testAFilesRootsOverrideWhatItIncludes(): void
    {
        $library = $this->writeRoots('library.json', ['A\B']);
        $file = $this->writeRoots('own.json', ['a\b as Q'], ['include' => [$library]]);

        self::assertSame('Q', self::localNameFor(ImportRootConfig::load([], [$file]), 'A\B\C'));
    }

    public function testTheToolsRootsOverrideFiles(): void
    {
        $file = $this->writeRoots('a.json', ['A\B']);

        self::assertSame('Q', self::localNameFor(ImportRootConfig::load(['A\B as Q'], [$file]), 'A\B\C'));
    }

    public function testTheSameRootFromTwoFilesIsOneRoot(): void
    {
        $one = $this->writeRoots('one.json', ['A\B as Q']);
        $two = $this->writeRoots('two.json', ['\A\B  as  Q']);

        self::assertSame('Q', self::localNameFor(ImportRootConfig::load([], [$one, $two]), 'A\B\C'));
    }

    public function testAFileReachedTwiceIsFine(): void
    {
        $shared = $this->writeRoots('shared.json', ['A\B']);
        $one = $this->writeRoots('one.json', [], ['include' => [$shared]]);
        $two = $this->writeRoots('two.json', [], ['include' => [$shared]]);

        self::assertSame('B', self::localNameFor(ImportRootConfig::load([], [$one, $two]), 'A\B\C'));
    }

    public function testFilesThatDisagreeAreAnError(): void
    {
        $one = $this->writeRoots('one.json', ['A\B as Q']);
        $two = $this->writeRoots('two.json', ['A\B as R']);

        $this->expectException(InvalidImportRoot::class);
        $this->expectExceptionMessage('disagrees');

        ImportRootConfig::load([], [$one, $two]);
    }

    public function testADifferentCaseIsADisagreementBecauseImportsUseTheCaseConfigured(): void
    {
        $one = $this->writeRoots('one.json', ['A\B']);
        $two = $this->writeRoots('two.json', ['a\b']);

        $this->expectException(InvalidImportRoot::class);
        $this->expectExceptionMessage('disagrees');

        ImportRootConfig::load([], [$one, $two]);
    }

    public function testADisagreementIsSettledByListingTheRoot(): void
    {
        $one = $this->writeRoots('one.json', ['A\B as Q']);
        $two = $this->writeRoots('two.json', ['A\B as R']);

        self::assertSame('S', self::localNameFor(ImportRootConfig::load(['A\B as S'], [$one, $two]), 'A\B\C'));

        $own = $this->writeRoots('own.json', ['A\B as T'], ['include' => [$one, $two]]);

        self::assertSame('T', self::localNameFor(ImportRootConfig::load([], [$own]), 'A\B\C'));
    }

    public function testFilesWithNoRootsAtAllAreAnError(): void
    {
        $file = $this->write('a.json', '{}');

        $this->expectException(InvalidImportRoot::class);
        $this->expectExceptionMessage('At least one');

        ImportRootConfig::load([], [$file]);
    }

    public function testAFileWithNoRootsIsFineIfThereAreOthers(): void
    {
        $file = $this->write('a.json', '{}');

        self::assertSame('B', self::localNameFor(ImportRootConfig::load(['A\B'], [$file]), 'A\B\C'));
    }

    public function testAFileIncludingItselfIsAnError(): void
    {
        $one = $this->directory . '/one.json';
        $this->writeRoots('two.json', [], ['include' => ['one.json']]);
        $this->writeRoots('one.json', [], ['include' => ['two.json']]);

        $this->expectException(InvalidImportRoot::class);
        $this->expectExceptionMessage('includes itself');

        ImportRootConfig::load([], [$one]);
    }

    public function testAMissingFileIsAnError(): void
    {
        $this->expectException(InvalidImportRoot::class);
        $this->expectExceptionMessage('cannot be read');

        ImportRootConfig::load([], [$this->directory . '/nope.json']);
    }

    public function testADirectoryIsAnError(): void
    {
        $this->expectException(InvalidImportRoot::class);
        $this->expectExceptionMessage('cannot be read');

        ImportRootConfig::load([], [$this->directory]);
    }

    public function testAPathThatIsNotAStringIsAnError(): void
    {
        $this->expectException(InvalidImportRoot::class);
        $this->expectExceptionMessage('must be a path');

        ImportRootConfig::load([], [1]);
    }

    public function testTheSameRootTwiceInOneListIsStillAnError(): void
    {
        $this->expectException(InvalidImportRoot::class);
        $this->expectExceptionMessage('more than once');

        ImportRootConfig::load(['A\B', 'A\B as Q'], []);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidFiles(): iterable
    {
        yield 'not JSON' => ['{nope', 'not valid JSON'];
        yield 'a list' => ['["A\\\\B"]', 'must contain a JSON object'];
        yield 'a string' => ['"A\\\\B"', 'must contain a JSON object'];
        yield 'unknown key' => ['{"root": []}', 'unknown keys: "root"'];
        yield 'roots not a list' => ['{"roots": "A\\\\B"}', '"roots" in'];
        yield 'roots an object' => ['{"roots": {"a": "A\\\\B"}}', '"roots" in'];
        yield 'include not a list' => ['{"include": "a.json"}', '"include" in'];
        yield 'root not a string' => ['{"roots": [1]}', 'must be a string'];
        yield 'malformed root' => ['{"roots": ["A\\\\*\\\\B"]}', 'malformed'];
        yield 'root twice' => ['{"roots": ["A\\\\B", "A\\\\B as Q"]}', 'more than once'];
    }

    #[DataProvider('invalidFiles')]
    public function testAnInvalidFileIsAnErrorNamingTheFile(string $json, string $expectedMessage): void
    {
        $file = $this->write('broken.json', $json);

        try {
            ImportRootConfig::load(['X\Y'], [$file]);
            self::fail('Expected an error.');
        } catch (InvalidImportRoot $exception) {
            self::assertStringContainsString($expectedMessage, $exception->getMessage());
            self::assertStringContainsString('broken.json', $exception->getMessage());
        }
    }
}
