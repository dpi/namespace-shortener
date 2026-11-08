<?php

/*
 * This file is part of the dpi/namespace-shortener-core project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Core\Config;

use Dpi\NamespaceShortener\Core\Exception\InvalidImportRoot;
use Safe\Exceptions\FilesystemException;

use function Safe\file_get_contents;
use function Safe\json_decode;
use function Safe\preg_match;
use function Safe\realpath;

/**
 * Merges import roots from a tool's configuration and from JSON files. See docs/guide/configuration.md.
 *
 * @phpstan-type Entry array{definition: string, root: ImportRoot, source: string}
 */
final class ImportRootConfig
{
    private const array FILE_KEYS = ['roots', 'include'];

    /**
     * @param array<mixed> $roots import roots, which override any from the files
     * @param array<mixed> $files paths to JSON files, relative to the working directory
     *
     * @throws InvalidImportRoot
     */
    public static function load(array $roots, array $files): ImportRootSet
    {
        $entries = self::merge($roots, $files, '', 'the configuration', []);

        return ImportRootSet::fromStrings(array_column($entries, 'definition'));
    }

    /**
     * @param array<mixed> $roots
     * @param array<mixed> $files
     * @param string $directory what relative paths in $files start from: empty for the working directory
     * @param string $source where these come from, for messages
     * @param list<string> $stack the files being loaded, to catch one including itself
     *
     * @return array<string, Entry> by the root's key
     *
     * @throws InvalidImportRoot
     */
    private static function merge(array $roots, array $files, string $directory, string $source, array $stack): array
    {
        $entries = [];
        $disagreements = [];

        foreach ($files as $file) {
            if (!\is_string($file)) {
                throw new InvalidImportRoot(\sprintf('A file included by %s must be a path, %s given.', $source, get_debug_type($file)));
            }

            foreach (self::loadFile(self::resolve($file, $directory), $stack) as $key => $entry) {
                if (isset($entries[$key]) && !$entries[$key]['root']->isSameAs($entry['root'])) {
                    $disagreements[$key] = \sprintf(
                        'Import root "%s" from %s disagrees with "%s" from %s. List the one you want in %s, which overrides both.',
                        $entries[$key]['definition'],
                        $entries[$key]['source'],
                        $entry['definition'],
                        $entry['source'],
                        $source,
                    );
                }

                $entries[$key] ??= $entry;
            }
        }

        $own = [];

        foreach ($roots as $definition) {
            if (!\is_string($definition)) {
                throw new InvalidImportRoot(\sprintf('An import root must be a string, %s given in %s.', get_debug_type($definition), $source));
            }

            try {
                $root = ImportRoot::fromString($definition);
            } catch (InvalidImportRoot $exception) {
                throw new InvalidImportRoot(\sprintf('In %s: %s', $source, $exception->getMessage()), 0, $exception);
            }

            if (isset($own[$root->key()])) {
                throw new InvalidImportRoot(\sprintf('Import root "%s" is configured more than once in %s.', $definition, $source));
            }

            $own[$root->key()] = true;
            $entries[$root->key()] = ['definition' => $definition, 'root' => $root, 'source' => $source];
        }

        foreach ($disagreements as $key => $message) {
            if (!isset($own[$key])) {
                throw new InvalidImportRoot($message);
            }
        }

        return $entries;
    }

    /**
     * @param list<string> $stack
     *
     * @return array<string, Entry>
     *
     * @throws InvalidImportRoot
     */
    private static function loadFile(string $path, array $stack): array
    {
        $unreadable = \sprintf('The import root file "%s" cannot be read. Relative paths start from the working directory, or in a file, from that file.', $path);

        try {
            $identity = realpath($path);
            $contents = is_file($identity) ? @file_get_contents($identity) : null;
        } catch (FilesystemException $exception) {
            throw new InvalidImportRoot($unreadable, 0, $exception);
        }

        if (null === $contents) {
            throw new InvalidImportRoot($unreadable);
        }

        if (\in_array($identity, $stack, true)) {
            throw new InvalidImportRoot(\sprintf('The import root file "%s" includes itself: %s.', $path, implode(' > ', [...$stack, $identity])));
        }

        $source = \sprintf('"%s"', $path);

        try {
            $data = json_decode($contents);
        } catch (\JsonException $exception) {
            throw new InvalidImportRoot(\sprintf('%s is not valid JSON: %s.', $source, $exception->getMessage()), 0, $exception);
        }

        if (!$data instanceof \stdClass) {
            throw new InvalidImportRoot(\sprintf('%s must contain a JSON object.', $source));
        }

        $values = get_object_vars($data);
        $unknown = array_diff(array_keys($values), self::FILE_KEYS);

        if ([] !== $unknown) {
            throw new InvalidImportRoot(\sprintf('%s has unknown keys: "%s". Expected "%s".', $source, implode('", "', $unknown), implode('", "', self::FILE_KEYS)));
        }

        return self::merge(
            self::listIn($values, 'roots', $source),
            self::listIn($values, 'include', $source),
            \dirname($path),
            $source,
            [...$stack, $identity],
        );
    }

    /**
     * @param array<mixed> $values
     *
     * @return list<mixed>
     *
     * @throws InvalidImportRoot
     */
    private static function listIn(array $values, string $key, string $source): array
    {
        $list = $values[$key] ?? [];

        if (!\is_array($list) || !array_is_list($list)) {
            throw new InvalidImportRoot(\sprintf('"%s" in %s must be a list.', $key, $source));
        }

        return $list;
    }

    private static function resolve(string $path, string $directory): string
    {
        $isAbsolute = 1 === preg_match('~^(?:[/\\\\]|[a-zA-Z]:[/\\\\])~', $path);

        return '' === $directory || $isAbsolute ? $path : $directory . '/' . $path;
    }
}
