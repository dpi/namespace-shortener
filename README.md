Notice: Most of this package was built with 🤖 assistance, though direction, ideas, experience was driven by [@dpi](https://www.github.com/dpi). Without assistance, this project may have taken much longer or never would have materialised.

# Namespace Shortener

A coding standard rule that imports the namespaces you choose, and rewrites every usage to go through them. Requires PHP 8.3 or later, and is available for [PHP-CS-Fixer](https://cs.symfony.com/) and [PHP_CodeSniffer](https://github.com/PHPCSStandards/PHP_CodeSniffer).

```php
// Before
use App\Domain\Billing\Invoice;

new Invoice();

// After, with the import root App\Domain\*
use App\Domain\Billing;

new Billing\Invoice();
```

If a fix would break your code or change what it means, the rule reports it, and doesn't change the file.

## Install

Install the package for your tool:

| Tool | Package | Install |
| --- | --- | --- |
| PHP-CS-Fixer | [![Packagist Version](https://img.shields.io/packagist/v/dpi/namespace-shortener-php-cs-fixer)](https://packagist.org/packages/dpi/namespace-shortener-php-cs-fixer) | `composer require --dev dpi/namespace-shortener-php-cs-fixer` |
| PHP_CodeSniffer | [![Packagist Version](https://img.shields.io/packagist/v/dpi/namespace-shortener-phpcs)](https://packagist.org/packages/dpi/namespace-shortener-phpcs) | `composer require --dev dpi/namespace-shortener-phpcs` |

Then see [Getting started](docs/guide/getting-started.md).

## Packages

This repository is a monorepo:

| Directory | Package | Version | Purpose |
| --- | --- | --- | --- |
| [`packages/core`](packages/core) | `dpi/namespace-shortener-core` | [![Packagist Version](https://img.shields.io/packagist/v/dpi/namespace-shortener-core)](https://packagist.org/packages/dpi/namespace-shortener-core) | The rule itself, independent of any tool |
| [`packages/php-cs-fixer`](packages/php-cs-fixer) | `dpi/namespace-shortener-php-cs-fixer` | [![Packagist Version](https://img.shields.io/packagist/v/dpi/namespace-shortener-php-cs-fixer)](https://packagist.org/packages/dpi/namespace-shortener-php-cs-fixer) | The adapter for PHP-CS-Fixer |
| [`packages/php-codesniffer`](packages/php-codesniffer) | `dpi/namespace-shortener-phpcs` | [![Packagist Version](https://img.shields.io/packagist/v/dpi/namespace-shortener-phpcs)](https://packagist.org/packages/dpi/namespace-shortener-phpcs) | The adapter for PHP_CodeSniffer |

See [Packages](docs/contributing/packages.md) for how it fits together.

## Documentation

The documentation is in [`docs`](docs/index.md). To read it as a site:

```sh
cd docs
pnpm install
pnpm dev
```

## License

[BSD-3-Clause](LICENSE)

Copyright (c) 2026, dpi
