Notice: Most of this package was built with 🤖 assistance, though direction, ideas, experience was driven by [@dpi](https://www.github.com/dpi). Without assistance, this project may have taken much longer or never would have materialised.

# Namespace Shortener

> [!NOTE]
> Read the full documentation at **[dpi.github.io/namespace-shortener](https://dpi.github.io/namespace-shortener/)**.

A coding standard rule that imports the namespaces you choose, and rewrites every usage to go through them. Requires PHP 8.3 or later, and is available for [PHP-CS-Fixer](https://cs.symfony.com/) and [PHP_CodeSniffer](https://github.com/PHPCSStandards/PHP_CodeSniffer).

```php
// Before
use App\Domain\Billing\Invoice;

new Invoice();

// After, with the import root App\Domain\*
use App\Domain\Billing;

new Billing\Invoice();
```

If a fix would break your code or change what it means, the rule [reports it](https://dpi.github.io/namespace-shortener/guide/errors), and doesn't change the file.

## Install

Install the package for your tool:

| Tool | Package | Install |
| --- | --- | --- |
| [PHP-CS-Fixer](https://dpi.github.io/namespace-shortener/tools/php-cs-fixer) | [![Packagist Version](https://img.shields.io/packagist/v/dpi/namespace-shortener-php-cs-fixer)](https://packagist.org/packages/dpi/namespace-shortener-php-cs-fixer) | `composer require --dev dpi/namespace-shortener-php-cs-fixer` |
| [PHP_CodeSniffer](https://dpi.github.io/namespace-shortener/tools/php-codesniffer) | [![Packagist Version](https://img.shields.io/packagist/v/dpi/namespace-shortener-phpcs)](https://packagist.org/packages/dpi/namespace-shortener-phpcs) | `composer require --dev dpi/namespace-shortener-phpcs` |

Then see [Getting started](https://dpi.github.io/namespace-shortener/guide/getting-started).

## Documentation

- [Getting started](https://dpi.github.io/namespace-shortener/guide/getting-started)
- [Configuring import roots](https://dpi.github.io/namespace-shortener/guide/configuration)
- [What gets rewritten](https://dpi.github.io/namespace-shortener/guide/what-gets-rewritten)
- [Errors](https://dpi.github.io/namespace-shortener/guide/errors)
- [Working with other rules](https://dpi.github.io/namespace-shortener/guide/other-rules)
- [Supported tools](https://dpi.github.io/namespace-shortener/tools/)
- [Terminology](https://dpi.github.io/namespace-shortener/reference/terminology)

## Packages

This repository is a monorepo:

| Directory | Package | Version | Purpose |
| --- | --- | --- | --- |
| `packages/core` | `dpi/namespace-shortener-core` | [![Packagist Version](https://img.shields.io/packagist/v/dpi/namespace-shortener-core)](https://packagist.org/packages/dpi/namespace-shortener-core) | The rule itself, independent of any tool |
| `packages/php-cs-fixer` | `dpi/namespace-shortener-php-cs-fixer` | [![Packagist Version](https://img.shields.io/packagist/v/dpi/namespace-shortener-php-cs-fixer)](https://packagist.org/packages/dpi/namespace-shortener-php-cs-fixer) | The adapter for PHP-CS-Fixer |
| `packages/php-codesniffer` | `dpi/namespace-shortener-phpcs` | [![Packagist Version](https://img.shields.io/packagist/v/dpi/namespace-shortener-phpcs)](https://packagist.org/packages/dpi/namespace-shortener-phpcs) | The adapter for PHP_CodeSniffer |

See [Packages](https://dpi.github.io/namespace-shortener/contributing/packages) for how it fits together.

## Contributing

See [Packages](https://dpi.github.io/namespace-shortener/contributing/packages), [Code style](https://dpi.github.io/namespace-shortener/contributing/code-style), and [Testing](https://dpi.github.io/namespace-shortener/contributing/testing).

## License

BSD-3-Clause

Copyright (c) 2026, dpi
