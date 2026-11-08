<?php

/*
 * This file is part of the dpi/namespace-shortener project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Tests;

use Dpi\NamespaceShortener\Core\Rewrite\ConflictKind;
use PHPUnit\Framework\TestCase;

use function Safe\file_get_contents;
use function Safe\glob;
use function Safe\json_decode;
use function Safe\preg_match_all;

final class DocumentationTest extends TestCase
{
    public function testEveryConflictKindIsExplainedInTheErrorsGuide(): void
    {
        $guide = file_get_contents(__DIR__ . '/../docs/guide/errors.md');

        foreach (ConflictKind::cases() as $kind) {
            self::assertStringContainsString("\n### {$kind->name}\n", $guide);
        }
    }

    public function testOnlyRealConflictKindsAreExplainedInTheErrorsGuide(): void
    {
        $guide = file_get_contents(__DIR__ . '/../docs/guide/errors.md');
        preg_match_all('/^### (.+)$/m', $guide, $matches);

        self::assertEqualsCanonicalizing(
            array_map(static fn (ConflictKind $kind): string => $kind->name, ConflictKind::cases()),
            $matches[1],
        );
    }

    /**
     * These are the codes people use in rulesets, so they must all be findable.
     */
    public function testEveryConflictKindIsListedOnThePhpCodeSnifferPage(): void
    {
        $page = file_get_contents(__DIR__ . '/../docs/tools/php-codesniffer.md');

        foreach (ConflictKind::cases() as $kind) {
            self::assertStringContainsString("`DpiNamespaceShortener.Namespaces.ImportRoots.{$kind->name}`", $page);
        }
    }

    public function testEveryPackageIsListedOnThePackagesPage(): void
    {
        $page = file_get_contents(__DIR__ . '/../docs/contributing/packages.md');
        $manifests = glob(__DIR__ . '/../packages/*/composer.json');
        self::assertNotSame([], $manifests);

        foreach ($manifests as $manifest) {
            \assert(\is_string($manifest));

            $package = json_decode(file_get_contents($manifest), true);
            \assert(\is_array($package) && \is_string($package['name']));

            self::assertStringContainsString($package['name'], $page);
            self::assertStringContainsString('packages/' . basename(\dirname($manifest)), $page);
        }
    }
}
