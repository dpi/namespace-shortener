<?php

/*
 * This file is part of the dpi/namespace-shortener project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Tests\Support;

use PHPUnit\Framework\TestCase;

use function Safe\fclose;
use function Safe\file_put_contents;
use function Safe\mkdir;
use function Safe\proc_close;
use function Safe\rmdir;
use function Safe\stream_get_contents;
use function Safe\unlink;

/**
 * Runs a tool's real executable in a temporary directory, since exit codes are what callers depend on.
 */
abstract class CliTestCase extends TestCase
{
    protected string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/namespace-shortener-' . bin2hex(random_bytes(6));
        mkdir($this->directory . '/src', recursive: true);
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

    protected function writeSource(string $code, string $name = 'Source.php'): string
    {
        $path = $this->directory . '/src/' . $name;
        file_put_contents($path, $code);

        return $path;
    }

    protected static function vendorPath(string $path): string
    {
        return \dirname(__DIR__, 2) . '/vendor/' . $path;
    }

    /**
     * @param list<string> $command
     *
     * @return array{exitCode: int, output: string}
     */
    protected function runCommand(array $command): array
    {
        // @phpstan-ignore theCodingMachineSafe.function (Safe's proc_open only takes a string, and an array avoids the shell)
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->directory,
        );
        \assert(\is_resource($process));

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['exitCode' => proc_close($process), 'output' => $output];
    }
}
