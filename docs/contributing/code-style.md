# Code style

The style for this project's own code.

## Enforced by tools

`composer ci` runs all of these, and must pass.

| Tool | Configuration | Command |
| --- | --- | --- |
| PHP-CS-Fixer | `.php-cs-fixer.dist.php` | `composer cs`, or `composer cs:fix` to apply |
| PHPStan at max level, with strict rules | `phpstan.neon` | `composer stan` |
| PHPUnit | `phpunit.xml.dist` | `composer test` |

These cover every package, and run from the root. The PHP-CS-Fixer rules are the same as those of the author's other projects: `@Symfony` and `@Symfony:risky`, plus stricter rules for types, modern syntax, PHPUnit, and phpDoc. Only the finder, the config name, and the license header differ. Don't change a rule to suit a piece of code.

## PHP version

The minimum is PHP 8.3, so no syntax or functions from later versions. That rules out `private(set)`, property hooks, and `new Foo()->bar()`.

This applies to every package. Your local PHP may be newer and won't catch this: PHPStan's `phpVersion` is set to 8.3 to catch it, and the tests should be run on 8.3 too. See [Testing](./testing#run-on-php-8-3).

## Writing code

- Use `declare(strict_types=1)` in every file. The fixer adds it.
- Start every file with the header naming its Composer package and pointing to the `LICENSE` file. The fixer adds it, taking the name from the nearest `composer.json`.
- Make classes `final`, and `readonly` where they hold no changing state.
- Keep each tool's API in its [adapter package](./packages). The core package works on plain strings, so any tool can use it, and must not depend on any tool.
- Use the words in [Terminology](../reference/terminology) for class, method, and variable names. Say "import root", not "prefix" or "rule".
- Throw an exception that implements `NamespaceShortenerException` for any failure. Never skip a problem by returning early.
- Import PHP functions that can fail from [Safe](https://github.com/thecodingmachine/safe), such as `use function Safe\file_get_contents;`, so that they throw instead of returning `false`. They still raise PHP's warnings, so use `@` where a failure is expected. PHPStan reports any you miss.
- Report every conflict in a file together, rather than stopping at the first.

The PHP_CodeSniffer adapter has to work with both major versions. Its sniff leaves out parameter types that version 3's interface doesn't have, such as on `$stackPtr`, since adding one is a fatal error there.

## Where documentation goes

| What | Where |
| --- | --- |
| How to use it, and how it behaves | This site |
| Types PHP can't express, such as `list<string>` | phpDoc |
| What a class is for, when the name doesn't say | One or two lines of phpDoc |
| Why a piece of code does something surprising | A comment beside it |

Write guide pages without assuming a particular tool. Anything specific to one goes on its [tool page](../tools/), or in a tab where a reader needs to see real configuration.

If behaviour changes, update this site in the same change.

## Tests

- Every behaviour has a test, and every conflict has a fixture. See [Testing](./testing).
- Prefer asking real PHP and each tool's real executable to assuming what they do.
