<?php

/*
 * This file is part of the dpi/namespace-shortener-core project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Dpi\NamespaceShortener\Core\Analysis;

/**
 * PHP's name resolution rules.
 *
 * @see https://www.php.net/manual/en/language.namespaces.rules.php
 */
final class NameResolution
{
    /**
     * @param list<string> $namespace
     * @param array<string, ImportItem> $imports normal imports, by lowercase local name
     */
    public static function resolve(int $start, string $text, NameContext $context, array $namespace, array $imports): NameOccurrence
    {
        if (str_starts_with($text, '\\')) {
            return new NameOccurrence($start, $text, NameForm::FullyQualified, $context, self::split(substr($text, 1)), null);
        }

        if (0 === strncasecmp($text, 'namespace\\', 10)) {
            return new NameOccurrence($start, $text, NameForm::Relative, $context, [...$namespace, ...self::split(substr($text, 10))], null);
        }

        $segments = self::split($text);
        $form = 1 === \count($segments) ? NameForm::Unqualified : NameForm::Qualified;

        // An unqualified function or constant is never looked up in the class import table.
        $import = NameForm::Unqualified === $form && NameContext::FunctionOrConstant === $context
            ? null
            : ($imports[strtolower($segments[0])] ?? null);

        if (null === $import) {
            return new NameOccurrence($start, $text, $form, $context, [...$namespace, ...$segments], null);
        }

        return new NameOccurrence($start, $text, $form, $context, [...$import->segments, ...\array_slice($segments, 1)], $import->id);
    }

    /**
     * @return non-empty-list<string>
     */
    private static function split(string $name): array
    {
        /* @var non-empty-list<string> */
        return explode('\\', $name);
    }
}
