# Testing

The tests use PHPUnit, and run from the root. Each package's tests are in its `tests` directory. See [Packages](./packages).

```sh
composer test
vendor/bin/phpunit --testsuite core
vendor/bin/phpunit --testsuite php-cs-fixer
vendor/bin/phpunit --testsuite php-codesniffer
vendor/bin/phpunit --testsuite docs
```

## Fixtures

Most behaviours are a single fixture file. To cover a new one, add a file, with no PHP needed. Fixtures are at the root, because they describe the rule rather than one tool.

### `tests/Fixtures/fix`

```
--ROOTS--
A\B\C\*
--INPUT--
<?php

use A\B\C\D\E;

new E();
--EXPECTED--
<?php

use A\B\C\D;

new D\E();
```

Each fixture checks three things:

1. The input rewrites to the expected code.
2. Rewriting the expected code changes nothing.
3. Real PHP compiles the expected code.

The PHP_CodeSniffer tests run each fixture through `phpcbf` and `phpcs` too, to prove that going through its tokens gives the same result.

List one root per line. Write `{CRLF}` for a Windows line ending.

### `tests/Fixtures/conflict`

The same, but with an `--ERROR--` section of `kind|line|text from the message`:

```
--ERROR--
ImportCollision|9|already used by the import of "X\D"
```

A test fails if any `ConflictKind` has no fixture. Another fails if one has no section in the [errors guide](../guide/errors).

## CLI tests

Each adapter's `tests/Integration` runs its [tool](../tools/)'s real executable, since exit codes are what callers rely on. They're slower than unit tests. They cover:

- Exit codes for each kind of failure, in check and fix mode, in sequential and parallel mode
- Failed files being left alone, and the cache not hiding a failure
- Working alongside [other rules](../guide/other-rules)

## Run on PHP_CodeSniffer 3

The lock file has PHP_CodeSniffer 4, but version 3 is supported too, and tests expect the exit codes of whichever is installed. To run them on version 3, in a copy of this repository so the lock file stays as it is:

```sh
composer require --dev --with-all-dependencies squizlabs/php_codesniffer:^3.13
vendor/bin/phpunit --testsuite php-codesniffer
```

## Run on PHP 8.3

Your local PHP may be newer than the minimum, and that can hide real failures. PHP_CodeSniffer and PHP-Parser both emulate tokens that older PHP doesn't have, and can only clash there (see `packages/php-codesniffer/src/tokens.php`). To run the tests on the minimum with Docker:

```sh
docker run --rm -v "$PWD":/app -w /app php:8.3-cli php vendor/bin/phpunit --do-not-cache-result
```

## This site

```sh
cd docs
pnpm install
pnpm dev      # preview
pnpm build    # also fails on dead links
```

## Continuous integration

GitHub Actions runs these on every pull request, and on every push to `main`:

| Workflow | What it does |
| --- | --- |
| `.github/workflows/ci.yml` | Validates each `composer.json`, runs PHP-CS-Fixer and PHPStan, then runs the tests on PHP 8.3, 8.4, and 8.5, each with the highest and the lowest allowed dependencies. It also runs the PHP_CodeSniffer tests on [version 3](#run-on-php-codesniffer-3), on PHP 8.3, 8.4, and 8.5 |
| `.github/workflows/docs.yml` | Builds this site when it changes. On `main`, it also deploys the site to GitHub Pages |
