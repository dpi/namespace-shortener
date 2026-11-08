<?php

/*
 * This file is part of the dpi/namespace-shortener-phpcs project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\PhpCodeSniffer\Tests\Integration;

use Composer\InstalledVersions;
use Dpi\NamespaceShortener\Tests\Support\CliTestCase as BaseCliTestCase;

use function Safe\file_put_contents;
use function Safe\json_decode;

abstract class CliTestCase extends BaseCliTestCase
{
    protected const string SNIFF = 'DpiNamespaceShortener.Namespaces.ImportRoots';

    /**
     * Refers to the standard by name, as a project would, so this also relies on the Composer plugin having registered it.
     *
     * @param list<string> $roots
     * @param string $extraRules further rules, as XML
     */
    protected function writeRuleset(array $roots, string $extraRules = ''): void
    {
        $elements = implode('', array_map(static fn (string $root): string => \sprintf('<element value="%s"/>', htmlspecialchars($root)), $roots));
        $sniff = self::SNIFF;

        $this->writeRawRuleset(<<<XML
            <rule ref="{$sniff}">
                <properties>
                    <property name="roots" type="array">{$elements}</property>
                </properties>
            </rule>
            {$extraRules}
            XML);
    }

    protected function writeRawRuleset(string $rules): void
    {
        file_put_contents($this->directory . '/phpcs.xml', <<<XML
            <?xml version="1.0"?>
            <ruleset name="Test">
            {$rules}
            </ruleset>
            XML);
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{exitCode: int, output: string}
     */
    protected function runTool(string $tool, array $arguments = []): array
    {
        return $this->runCommand([\PHP_BINARY, self::vendorPath('bin/' . $tool), '--standard=phpcs.xml', '--no-colors', '--no-cache', '--report-width=1000', '-q', ...$arguments, 'src']);
    }

    /**
     * @return list<array{message: string, source: string, fixable: bool, line: int}>
     */
    protected function findings(): array
    {
        $result = $this->runTool('phpcs', ['--report=json']);
        $report = json_decode($result['output'], true);
        \assert(\is_array($report) && \is_array($report['files']));

        $findings = [];

        foreach ($report['files'] as $file) {
            \assert(\is_array($file) && \is_array($file['messages']));

            /** @var array{message: string, source: string, fixable: bool, line: int} $message */
            foreach ($file['messages'] as $message) {
                $findings[] = $message;
            }
        }

        return $findings;
    }

    /**
     * Exit codes changed between the major versions, and the two are both supported.
     */
    protected static function exitCode(int $phpcs3, int $phpcs4): int
    {
        return str_starts_with((string) InstalledVersions::getVersion('squizlabs/php_codesniffer'), '3.') ? $phpcs3 : $phpcs4;
    }
}
