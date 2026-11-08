<?php

/*
 * This file is part of the dpi/namespace-shortener-phpcs project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\PhpCodeSniffer\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

use function Safe\file_get_contents;
use function Safe\file_put_contents;
use function Safe\mkdir;

/**
 * Nothing unresolvable may pass silently, and a change must only ever be made as a whole.
 */
final class SniffTest extends CliTestCase
{
    private const string FIXABLE = "<?php\n\nnamespace App;\n\nuse A\\B\\C\\D\\E;\n\nnew E();\n";

    private const string FIXED = "<?php\n\nnamespace App;\n\nuse A\\B\\C\\D;\n\nnew D\\E();\n";

    private const string CONFLICT = "<?php\n\nnamespace App;\n\nuse A\\B\\C\\D\\E;\n\nnew D();\nnew E();\n";

    private const array ROOTS = ['A\B\C\*'];

    public function testTheStandardIsInstalled(): void
    {
        $result = $this->runCommand([\PHP_BINARY, self::vendorPath('bin/phpcs'), '-i']);

        self::assertStringContainsString('DpiNamespaceShortener', $result['output']);
    }

    public function testEveryChangeIsAFixableFindingOnItsOwnLine(): void
    {
        $this->writeRuleset(self::ROOTS);
        $this->writeSource("<?php\n\nnamespace App;\n\nuse A\\B\\C\\D\\E;\nuse A\\B\\C\\D\\F;\n\n/** @var E \$e */\nnew E(new F());\n");

        $findings = $this->findings();

        self::assertSame(
            [
                [5, 'Replace "use A\B\C\D\E;" with "use A\B\C\D;".'],
                [6, 'Remove "use A\B\C\D\F;".'],
                [8, 'Replace "E" with "D\E".'],
                [9, 'Replace "E" with "D\E".'],
                [9, 'Replace "F" with "D\F".'],
            ],
            array_map(static fn (array $finding): array => [$finding['line'], $finding['message']], $findings),
        );

        foreach ($findings as $finding) {
            self::assertTrue($finding['fixable']);
            self::assertSame(self::SNIFF . '.NeedsRewrite', $finding['source']);
        }
    }

    public function testAnAddedImportIsDescribed(): void
    {
        $this->writeRuleset(self::ROOTS);
        $this->writeSource("<?php\n\nnamespace App;\n\nnew \\A\\B\\C\\D\\E();\n");

        self::assertSame(
            [[3, 'Add "use A\B\C\D;".'], [5, 'Replace "\A\B\C\D\E" with "D\E".']],
            array_map(static fn (array $finding): array => [$finding['line'], $finding['message']], $this->findings()),
        );
    }

    public function testPhpcsReportsFixableFilesAndChangesNothing(): void
    {
        $this->writeRuleset(self::ROOTS);
        $path = $this->writeSource(self::FIXABLE);

        self::assertSame(self::exitCode(phpcs3: 2, phpcs4: 1), $this->runTool('phpcs')['exitCode']);
        self::assertSame(self::FIXABLE, file_get_contents($path));
    }

    public function testPhpcbfAppliesTheFixAndEverythingThenPasses(): void
    {
        $this->writeRuleset(self::ROOTS);
        $path = $this->writeSource(self::FIXABLE);

        self::assertSame(self::exitCode(phpcs3: 1, phpcs4: 0), $this->runTool('phpcbf')['exitCode']);
        self::assertSame(self::FIXED, file_get_contents($path));
        self::assertSame(0, $this->runTool('phpcs')['exitCode']);
        self::assertSame(0, $this->runTool('phpcbf')['exitCode']);
    }

    public function testDiffReportShowsTheChangeWithoutMakingIt(): void
    {
        $this->writeRuleset(self::ROOTS);
        $path = $this->writeSource(self::FIXABLE);

        $result = $this->runTool('phpcs', ['--report=diff']);

        self::assertStringContainsString('+new D\E();', $result['output']);
        // The docs warn about this exit code, so a change should be noticed.
        self::assertSame(self::exitCode(phpcs3: 2, phpcs4: 0), $result['exitCode']);
        self::assertSame(self::FIXABLE, file_get_contents($path));
    }

    public function testConflictFailsPhpcsAndIsLeftAlone(): void
    {
        $this->writeRuleset(self::ROOTS);
        $path = $this->writeSource(self::CONFLICT);

        $result = $this->runTool('phpcs');

        self::assertSame(self::exitCode(phpcs3: 1, phpcs4: 2), $result['exitCode'], $result['output']);
        self::assertStringContainsString('would change it to mean something else', $result['output']);
        self::assertSame(self::CONFLICT, file_get_contents($path));
    }

    /**
     * Forked workers report through PHP_CodeSniffer's own mechanism, so this must survive it.
     */
    #[RequiresPhpExtension('pcntl')]
    public function testConflictAndFixableFilesAreReportedInParallel(): void
    {
        $this->writeRuleset(self::ROOTS);
        $this->writeSource(self::CONFLICT, 'Bad.php');
        $good = $this->writeSource(self::FIXABLE, 'Good.php');

        $result = $this->runTool('phpcs', ['--parallel=2']);

        self::assertSame(self::exitCode(phpcs3: 2, phpcs4: 3), $result['exitCode'], $result['output']);
        self::assertStringContainsString('would change it to mean something else', $result['output']);

        $this->runTool('phpcbf', ['--parallel=2']);

        self::assertSame(self::FIXED, file_get_contents($good));
    }

    /**
     * The docs warn that phpcbf's exit code is not enough under PHP_CodeSniffer 3, so a change should be noticed.
     */
    public function testConflictUnderPhpcbf(): void
    {
        $this->writeRuleset(self::ROOTS);
        $path = $this->writeSource(self::CONFLICT);

        self::assertSame(self::exitCode(phpcs3: 0, phpcs4: 2), $this->runTool('phpcbf')['exitCode']);
        self::assertSame(self::CONFLICT, file_get_contents($path));
    }

    public function testOneBadFileDoesNotStopGoodFilesBeingFixed(): void
    {
        $this->writeRuleset(self::ROOTS);
        $good = $this->writeSource(self::FIXABLE, 'Good.php');
        $bad = $this->writeSource(self::CONFLICT, 'Bad.php');

        self::assertSame(self::exitCode(phpcs3: 1, phpcs4: 2), $this->runTool('phpcbf')['exitCode']);
        self::assertSame(self::FIXED, file_get_contents($good));
        self::assertSame(self::CONFLICT, file_get_contents($bad));
    }

    public function testConflictIsNotHiddenByTheCache(): void
    {
        $this->writeRuleset(self::ROOTS);
        $this->writeSource(self::CONFLICT);

        $command = [\PHP_BINARY, self::vendorPath('bin/phpcs'), '--standard=phpcs.xml', '--cache=' . $this->directory . '/phpcs.cache', 'src'];

        for ($run = 0; $run < 2; ++$run) {
            self::assertSame(self::exitCode(phpcs3: 1, phpcs4: 2), $this->runCommand($command)['exitCode']);
        }
    }

    /**
     * Fixing the rest would leave the suppressed line pointing at an import that has gone.
     */
    public function testSuppressingOneFindingStopsTheWholeFileBeingFixed(): void
    {
        $source = "<?php\n\nnamespace App;\n\nuse A\\B\\C\\D\\E;\n\nnew E(); // phpcs:ignore\nnew E();\n";

        $this->writeRuleset(self::ROOTS);
        $path = $this->writeSource($source);

        self::assertSame([5, 8], array_map(static fn (array $finding): int => $finding['line'], $this->findings()));

        $result = $this->runTool('phpcbf');

        self::assertSame(self::exitCode(phpcs3: 2, phpcs4: 5), $result['exitCode'], $result['output']);
        self::assertSame($source, file_get_contents($path));
    }

    public function testIgnoringTheFileSkipsIt(): void
    {
        $source = "<?php\n// phpcs:ignoreFile\n\nnamespace App;\n\nuse A\\B\\C\\D\\E;\n\nnew E();\n";

        $this->writeRuleset(self::ROOTS);
        $path = $this->writeSource($source);

        self::assertSame(0, $this->runTool('phpcs')['exitCode']);
        self::assertSame(0, $this->runTool('phpcbf')['exitCode']);
        self::assertSame($source, file_get_contents($path));
    }

    public function testASyntaxErrorIsAnErrorOnItsLine(): void
    {
        $this->writeRuleset(self::ROOTS);
        $path = $this->writeSource("<?php\n\nclass {\n");

        $findings = array_filter($this->findings(), static fn (array $finding): bool => self::SNIFF . '.UnparsableSource' === $finding['source']);

        self::assertCount(1, $findings);
        self::assertSame(3, array_values($findings)[0]['line']);
        self::assertFalse(array_values($findings)[0]['fixable']);

        $this->runTool('phpcbf');

        self::assertSame("<?php\n\nclass {\n", file_get_contents($path));
    }

    /**
     * An import only applies to code after it, so the planner's result is wrong here and only verification sees it.
     */
    public function testAVerificationFailureIsAnError(): void
    {
        $source = "<?php\nnamespace App;\nnew \\A\\B\\C\\D\\E();\nuse X\\Y;\nnew Y();\n";

        $this->writeRuleset(self::ROOTS);
        $path = $this->writeSource($source);

        self::assertSame([self::SNIFF . '.RewriteVerificationFailed'], array_map(static fn (array $finding): string => $finding['source'], $this->findings()));

        $this->runTool('phpcbf');

        self::assertSame($source, file_get_contents($path));
    }

    public function testTabsAndWindowsLineEndingsSurviveBecausePhpcsExpandsTabsInternally(): void
    {
        $this->writeRuleset(self::ROOTS);
        $path = $this->writeSource("<?php\r\n\r\nnamespace App;\r\n\r\nuse A\\B\\C\\D\\E;\r\n\r\nfunction run() {\r\n\t/** @var E \$e\t*/\r\n\treturn\tnew E();\r\n}\r\n");

        // A tab width makes tokens' content differ from the file, which would shift any offset taken from it.
        $this->runTool('phpcbf', ['--tab-width=4']);

        self::assertSame(
            "<?php\r\n\r\nnamespace App;\r\n\r\nuse A\\B\\C\\D;\r\n\r\nfunction run() {\r\n\t/** @var D\\E \$e\t*/\r\n\treturn\tnew D\\E();\r\n}\r\n",
            file_get_contents($path),
        );
    }

    public function testAFileStartingWithHtmlOrEchoTagIsProcessedOnce(): void
    {
        $this->writeRuleset(self::ROOTS);
        $path = $this->writeSource("<p>Hi</p>\n<?php\nuse A\\B\\C\\D\\E;\n?>\n<?= new E() ?>\n<?php new E();\n");

        self::assertCount(3, $this->findings());

        $this->runTool('phpcbf');

        self::assertSame("<p>Hi</p>\n<?php\nuse A\\B\\C\\D;\n?>\n<?= new D\\E() ?>\n<?php new D\\E();\n", file_get_contents($path));
    }

    public function testAFileWithNoPhpIsLeftAlone(): void
    {
        $this->writeRuleset(self::ROOTS);
        $path = $this->writeSource("<p>Hi</p>\n");

        self::assertSame([], array_filter($this->findings(), static fn (array $finding): bool => str_starts_with($finding['source'], self::SNIFF)));
        self::assertSame("<p>Hi</p>\n", file_get_contents($path));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidConfigurations(): iterable
    {
        $rule = static fn (string $property): string => '<rule ref="' . self::SNIFF . '"><properties>' . $property . '</properties></rule>';

        yield 'malformed root' => [$rule('<property name="roots" type="array"><element value="A\*\B"/></property>'), 'malformed'];
        yield 'aliased wildcard' => [$rule('<property name="roots" type="array"><element value="A\* as X"/></property>'), 'cannot have an alias'];
        yield 'reserved alias' => [$rule('<property name="roots" type="array"><element value="A\B as String"/></property>'), 'cannot use "String"'];
        yield 'duplicate roots' => [$rule('<property name="roots" type="array"><element value="A\B"/><element value="A\B as C"/></property>'), 'more than once'];
        yield 'empty roots' => [$rule('<property name="roots" type="array"></property>'), 'At least one'];
        yield 'not an array' => [$rule('<property name="roots" value="A\B"/>'), 'type="array"'];
        yield 'missing root file' => [$rule('<property name="rootFiles" type="array"><element value="nope.json"/></property>'), 'cannot be read'];
        yield 'rootFiles not an array' => [$rule('<property name="rootFiles" value="nope.json"/>'), 'type="array"'];
        yield 'unknown property' => [$rule('<property name="roots" type="array"><element value="A\B"/></property><property name="nope" value="1"/>'), 'nope'];
        yield 'no property' => ['<rule ref="' . self::SNIFF . '"/>', 'At least one'];
        yield 'whole standard with no property' => ['<rule ref="DpiNamespaceShortener"/>', 'At least one'];
    }

    #[DataProvider('invalidConfigurations')]
    public function testInvalidConfigurationFailsTheRunWithoutReadingAFile(string $rules, string $expectedMessage): void
    {
        $this->writeRawRuleset($rules);
        $path = $this->writeSource(self::FIXABLE);

        foreach (['phpcs', 'phpcbf'] as $tool) {
            $result = $this->runTool($tool);

            self::assertSame(self::exitCode(phpcs3: 3, phpcs4: 16), $result['exitCode'], $result['output']);
            self::assertStringContainsString($expectedMessage, $result['output']);
        }

        self::assertSame(self::FIXABLE, file_get_contents($path));
    }

    /**
     * Unlike PHP-CS-Fixer, where a failure discards every rule's changes to the file. The docs describe the difference.
     */
    public function testOtherSniffsStillFixAFileThisRuleCannot(): void
    {
        $this->writeRuleset(self::ROOTS, '<rule ref="Generic.Arrays.DisallowLongArraySyntax"/>');
        $path = $this->writeSource(self::CONFLICT . "\$a = array();\n");

        $this->runTool('phpcbf');

        self::assertSame(self::CONFLICT . "\$a = [];\n", file_get_contents($path));
    }

    /**
     * PHP_CodeSniffer emulates the tokens for these on older PHP, and PHP-Parser's emulation has to get along with it.
     */
    public function testSyntaxNewerThanThePhpInUseIsHandled(): void
    {
        $this->writeRuleset(self::ROOTS, '<rule ref="PSR12"/>');
        $path = $this->writeSource(<<<'PHP'
            <?php

            namespace App;

            use A\B\C\D\E;

            class Example
            {
                public private(set) ?E $e = null;
                public protected(set) int $count = 0;

                public function run(): string
                {
                    (void) new E();

                    return __PROPERTY__;
                }
            }

            PHP);

        $this->runTool('phpcbf');
        $code = file_get_contents($path);

        self::assertStringContainsString('use A\B\C\D;', $code);
        self::assertStringContainsString('public private(set) ?D\E $e', $code);
        self::assertStringContainsString('new D\E()', $code);

        foreach ($this->findings() as $finding) {
            self::assertStringStartsNotWith('Internal.', $finding['source'], $finding['message']);
            self::assertStringStartsNotWith(self::SNIFF, $finding['source'], $finding['message']);
        }
    }

    public function testRootsCanComeFromAFileAndBeOverridden(): void
    {
        mkdir($this->directory . '/lib');
        file_put_contents($this->directory . '/lib/library.json', '{"roots": ["A\\\\B\\\\C\\\\D", "G\\\\H\\\\I"]}');
        file_put_contents($this->directory . '/roots.json', '{"include": ["lib/library.json"], "roots": ["G\\\\H\\\\I as L"]}');
        $this->writeRawRuleset('<rule ref="' . self::SNIFF . '"><properties><property name="rootFiles" type="array"><element value="roots.json"/></property></properties></rule>');
        $path = $this->writeSource("<?php\n\nnamespace App;\n\nuse A\\B\\C\\D\\E;\nuse G\\H\\I\\J;\n\nnew E();\nnew J();\n");

        $this->runTool('phpcbf');

        self::assertSame("<?php\n\nnamespace App;\n\nuse A\\B\\C\\D;\nuse G\\H\\I as L;\n\nnew D\\E();\nnew L\\J();\n", file_get_contents($path));
    }

    public function testTheRootsPropertyOverridesAFile(): void
    {
        file_put_contents($this->directory . '/roots.json', '{"roots": ["A\\\\B\\\\C\\\\D"]}');
        $this->writeRawRuleset('<rule ref="' . self::SNIFF . '"><properties><property name="rootFiles" type="array"><element value="roots.json"/></property><property name="roots" type="array"><element value="A\B\C\D as Q"/></property></properties></rule>');
        $path = $this->writeSource(self::FIXABLE);

        $this->runTool('phpcbf');

        self::assertSame("<?php\n\nnamespace App;\n\nuse A\\B\\C\\D as Q;\n\nnew Q\\E();\n", file_get_contents($path));
    }

    /**
     * PHP_CodeSniffer's cache doesn't know about these files. The docs warn about this, so a change should be noticed.
     *
     * Both roots are exact on purpose: its cache also depends on which classes were loaded, and only some forms load ReservedNames.
     */
    public function testTheCacheDoesNotNoticeAChangedRootFile(): void
    {
        file_put_contents($this->directory . '/roots.json', '{"roots": ["X\\\\Y"]}');
        $this->writeRawRuleset('<rule ref="' . self::SNIFF . '"><properties><property name="rootFiles" type="array"><element value="roots.json"/></property></properties></rule>');
        $this->writeSource(self::FIXABLE);

        $cached = [\PHP_BINARY, self::vendorPath('bin/phpcs'), '--standard=phpcs.xml', '--cache=' . $this->directory . '/phpcs.cache', 'src'];
        self::assertSame(0, $this->runCommand($cached)['exitCode']);

        file_put_contents($this->directory . '/roots.json', '{"roots": ["A\\\\B\\\\C\\\\D"]}');

        self::assertSame(['stale' => 0, 'fresh' => self::exitCode(phpcs3: 2, phpcs4: 1)], [
            'stale' => $this->runCommand($cached)['exitCode'],
            'fresh' => $this->runTool('phpcs')['exitCode'],
        ]);
    }

    public function testAliasedAndExactRootsAreAccepted(): void
    {
        $this->writeRuleset(['G\H\I', 'X\Y\Z as L']);
        $path = $this->writeSource("<?php\n\nnamespace App;\n\nuse G\\H\\I\\J\\K;\nuse X\\Y\\Z\\M;\n\nnew K();\nnew M();\n");

        $this->runTool('phpcbf');

        self::assertSame("<?php\n\nnamespace App;\n\nuse G\\H\\I;\nuse X\\Y\\Z as L;\n\nnew I\\J\\K();\nnew L\\M();\n", file_get_contents($path));
    }
}
