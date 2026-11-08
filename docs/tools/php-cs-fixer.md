# PHP-CS-Fixer

Everything specific to running the rule in [PHP-CS-Fixer](https://cs.symfony.com/).

## Setup

```sh
composer require --dev dpi/namespace-shortener-php-cs-fixer
```

Register the fixer, and enable the rule with your [import roots](../guide/configuration):

```php
<?php
// .php-cs-fixer.dist.php

use Dpi\NamespaceShortener\PhpCsFixer\NamespaceImportRootsFixer;

return (new PhpCsFixer\Config())
    ->registerCustomFixers([new NamespaceImportRootsFixer()])
    ->setFinder(PhpCsFixer\Finder::create()->in('src'))
    ->setRules([
        'Dpi/namespace_import_roots' => [
            'roots' => ['App\Domain\*'],
        ],
    ]);
```

The rule isn't marked as risky, so `setRiskyAllowed()` isn't needed.

Import roots can also come from [JSON files](../guide/configuration#json-files), with the `root_files` option. Relative paths start from the working directory, or use `__DIR__` to avoid depending on it.

## Running

| Mode | Command |
| --- | --- |
| Check | `vendor/bin/php-cs-fixer check --diff` |
| Fix | `vendor/bin/php-cs-fixer fix` |

## Exit codes

| Situation | `check` | `fix` |
| --- | --- | --- |
| Nothing to change | `0` | `0` |
| Files need changing | `8` | `0`, and they're changed |
| [A conflict](../guide/errors#conflicts) in a file | `64` | `64` |
| Rule enabled with `true`, so with no roots | `64` | `64` |
| [Invalid configuration](../guide/configuration#invalid-configuration) | `32` | `32` |
| A file has a PHP syntax error | `4` | `0` |

::: warning Syntax errors under `fix`
PHP-CS-Fixer's linter drops a file with a syntax error before any rule sees it, and `fix` still exits `0`. This rule can't change that, so **use `check` in CI**.
:::

## Seeing why a file failed

PHP-CS-Fixer only prints the reason for a failure at `-vv` or above. Without it, you see the file's path and the exit code.

```sh
vendor/bin/php-cs-fixer check -vv
```

## Failed files and other rules

When this rule fails on a file, PHP-CS-Fixer writes nothing to that file, so changes from other rules in the same run are lost too. Other files aren't affected.

The cache only records a file after it succeeds, so a failure can't be hidden on the next run.

## Caching and JSON files

PHP-CS-Fixer uses a cache by default. It's invalidated when the rule's options change, but not when the contents of a [JSON file](../guide/configuration#json-files) change, so a file it has recorded as clean stays that way until the file itself changes.

::: warning Delete the cache when a JSON file changes
Delete `.php-cs-fixer.cache`, or the file set with `setCacheFile()`, whenever a JSON file changes, including through updating a library. `--using-cache=no` gives a correct result for that run, but leaves the old cache for the next one.
:::

## Other rules

This rule runs at priority `10`, ahead of the rules that tidy imports.

Each of these is tested alongside it by running PHP-CS-Fixer for real. The tests check that the rewrite survives, and that a second run finds nothing left to do.

- `ordered_imports`, which also sorts the new imports
- `no_unused_imports`
- `single_import_per_statement`
- `fully_qualified_strict_types`, with and without `import_symbols`
- `global_namespace_import`
- The `@Symfony` and `@PER-CS` rule sets
