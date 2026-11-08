# Packages

This repository is a monorepo. The code is split into packages, and everything else, including this site, is shared at the root.

| Directory | Package | Namespace | Purpose |
| --- | --- | --- | --- |
| `packages/core` | `dpi/namespace-shortener-core` | `Dpi\NamespaceShortener\Core` | The rule itself, working on plain strings. It knows nothing about any tool |
| `packages/php-cs-fixer` | `dpi/namespace-shortener-php-cs-fixer` | `Dpi\NamespaceShortener\PhpCsFixer` | The adapter for PHP-CS-Fixer |
| `packages/php-codesniffer` | `dpi/namespace-shortener-phpcs` | `Dpi\NamespaceShortener\PhpCodeSniffer` | The adapter for PHP_CodeSniffer, including its standard |

Each adapter depends on the core package and its own tool. Neither depends on the other, so installing one never brings in the other's tool.

## At the root

| Path | Purpose |
| --- | --- |
| `docs` | This site |
| `tests/Fixtures` | Fixtures shared by the core tests and the adapters |
| `tests/Support` | Helpers shared by every package's tests |
| `tests/DocumentationTest.php` | Checks that the docs and the code agree |
| `composer.json` | The development environment, including the autoloading for every package's tests |
| `.php-cs-fixer.dist.php`, `phpstan.neon`, `phpunit.xml.dist` | Tool configuration, covering every package |

## Working on it

Run `composer install` at the root, and run everything from there. There's one `vendor` directory, and `vendor/dpi` holds symlinks to `packages`, so a change to a package takes effect immediately. Packages don't have a `vendor` directory or a lock file of their own.

## Adding a tool

1. Create `packages/<tool>`, with a `composer.json` requiring the core package and the tool, and with the `dev` keyword, so that `composer require` recommends `--dev`. Give it the `LICENSE` and `.gitattributes` the others have.
2. Add it to the path repository's `versions` and to `require-dev` in the root `composer.json`, and to the `autoload-dev` section for its tests.
3. Add its `src` and `tests` to `phpstan.neon`, and add a test suite to `phpunit.xml.dist`.
4. Translate the [conflicts](../guide/errors#conflicts) and other exceptions from `Rewriter` into the tool's failures. Use `Rewriter::rewrite()` if the tool takes a whole file, or `Rewriter::plan()` if it reports each change.
5. Add a [tool page](../tools/), a tab in each guide page that has them, and a row in the table above.

## Versions

The packages share one version number, and depend on each other by it. The path repository in the root `composer.json` sets the version that the packages have locally, so raise it there along with their constraints.

Composer needs each package's `composer.json` at the root of its own repository, so publishing means splitting each directory in `packages` out to a read-only repository, and registering those.
