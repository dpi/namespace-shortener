# Getting started

This package is a coding standard rule. It requires PHP 8.3 or later, and runs inside a coding standard tool. See [supported tools](../tools/).

It trades many deep imports for a few imports of the namespaces you choose:

```php
// Before
use App\Domain\Billing\Invoice;
use App\Domain\Billing\Payment;

new Invoice(new Payment());

// After, with the import root App\Domain\*
use App\Domain\Billing;

new Billing\Invoice(new Billing\Payment());
```

Usages get a little longer, while the `use` section gets shorter and each usage shows where it comes from.

## Install

Install the package for your tool:

::: code-group

```sh [PHP-CS-Fixer]
composer require --dev dpi/namespace-shortener-core:^1.0@beta dpi/namespace-shortener-php-cs-fixer:^1.0@beta
```

```sh [PHP_CodeSniffer]
composer require --dev dpi/namespace-shortener-core:^1.0@beta dpi/namespace-shortener-phpcs:^1.0@beta
```

:::

The packages are in beta, so Composer only accepts them if you name both packages and allow beta versions of each.

For PHP_CodeSniffer, Composer asks you to allow a plugin, which registers the standard. Say yes.

## Enable the rule

Add the rule to your tool's configuration, and tell it which [import roots](./configuration) to use:

::: code-group

```php [PHP-CS-Fixer]
<?php
// .php-cs-fixer.dist.php

use Dpi\NamespaceShortener\PhpCsFixer\NamespaceImportRootsFixer;

return (new PhpCsFixer\Config())
    ->registerCustomFixers([new NamespaceImportRootsFixer()])
    ->setFinder(PhpCsFixer\Finder::create()->in('src'))
    ->setRules([
        'Dpi/namespace_import_roots' => [
            'roots' => [
                'App\Domain\*',
                'Vendor\Library as Lib',
            ],
        ],
    ]);
```

```xml [PHP_CodeSniffer]
<?xml version="1.0"?>
<!-- phpcs.xml.dist -->
<ruleset name="My project">
    <file>src</file>

    <rule ref="DpiNamespaceShortener.Namespaces.ImportRoots">
        <properties>
            <property name="roots" type="array">
                <element value="App\Domain\*"/>
                <element value="Vendor\Library as Lib"/>
            </property>
        </properties>
    </rule>
</ruleset>
```

:::

## Run it

Check mode reports what would change, so use it in CI. Fix mode applies the changes.

::: code-group

```sh [PHP-CS-Fixer]
# Check mode
vendor/bin/php-cs-fixer check --diff

# Fix mode
vendor/bin/php-cs-fixer fix
```

```sh [PHP_CodeSniffer]
# Check mode
vendor/bin/phpcs

# Fix mode
vendor/bin/phpcbf
```

:::

Some changes can't be made safely. When that happens [the rule reports it](./errors) and doesn't change the file. Your [tool's page](../tools/) explains how to see the reason.
