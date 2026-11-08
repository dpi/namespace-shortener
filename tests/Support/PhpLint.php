<?php

/*
 * This file is part of the dpi/namespace-shortener project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Tests\Support;

use function Safe\fclose;
use function Safe\fwrite;
use function Safe\proc_close;
use function Safe\stream_get_contents;

/**
 * Asks the real PHP compiler about code. PHP is the authority on what compiles, not this project's assumptions.
 */
final class PhpLint
{
    /**
     * @return array{exitCode: int, output: string}
     */
    public static function lint(string $code): array
    {
        // @phpstan-ignore theCodingMachineSafe.function (Safe's proc_open only takes a string, and an array avoids the shell)
        $process = proc_open(
            [\PHP_BINARY, '-d', 'display_errors=stderr', '-l'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        \assert(\is_resource($process));

        fwrite($pipes[0], $code);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['exitCode' => proc_close($process), 'output' => $output];
    }
}
