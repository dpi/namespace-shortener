<?php

/*
 * This file is part of the dpi/namespace-shortener project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Tests\Support;

use function Safe\file_get_contents;
use function Safe\glob;
use function Safe\preg_match;

/**
 * A fixture file holds one behaviour, in sections introduced by lines like `--ROOTS--`.
 */
final readonly class Fixture
{
    /**
     * @param list<string> $roots
     */
    public function __construct(
        public array $roots,
        public string $input,
        public string $outcome,
    ) {
    }

    public static function load(string $path): self
    {
        $contents = file_get_contents($path);

        $pattern = '/^--ROOTS--\n(?<roots>.*?)\n--INPUT--\n(?<input>.*?)\n--(?:EXPECTED|ERROR)--\n(?<outcome>.*)$/sD';

        preg_match($pattern, $contents, $matches);

        if ([] === $matches) {
            throw new \RuntimeException(\sprintf('Malformed fixture: %s', $path));
        }

        return new self(
            explode("\n", $matches['roots']),
            str_replace('{CRLF}', "\r\n", $matches['input']),
            str_replace('{CRLF}', "\r\n", substr($matches['outcome'], 0, -1)),
        );
    }

    /**
     * @return iterable<string, array{self}>
     */
    public static function all(string $directory): iterable
    {
        $paths = glob(__DIR__ . '/../Fixtures/' . $directory . '/*.fixture');

        foreach ($paths as $path) {
            \assert(\is_string($path));

            yield basename($path, '.fixture') => [self::load($path)];
        }
    }
}
