# Configuring import roots

An **import root** is a namespace you want imported. You configure the rule by listing them.

## Setting the option

The rule has two options, and needs at least one of them. Import roots can be listed directly with `roots`, or come from [JSON files](#json-files).

| Option | PHP_CodeSniffer property | Type | Purpose |
| --- | --- | --- | --- |
| `roots` | `roots` | List of strings | Import roots, one per string |
| `root_files` | `rootFiles` | List of paths | [JSON files](#json-files) of import roots |

Where the option goes depends on your tool:

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
                'A\B\C\*',
                'G\H\I',
                'X\Y\Z as L',
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
                <element value="A\B\C\*"/>
                <element value="G\H\I"/>
                <element value="X\Y\Z as L"/>
            </property>
        </properties>
    </rule>
</ruleset>
```

:::

## Forms of import root

Each string takes one of three forms.

| Form | Example | Imports | A usage becomes |
| --- | --- | --- | --- |
| [Wildcard](#wildcard-root) | `A\B\C\*` | `use A\B\C\D;` | `D\E` |
| [Exact](#exact-root) | `G\H\I` | `use G\H\I;` | `I\J\K` |
| [Aliased](#aliased-root) | `G\H\I as L` | `use G\H\I as L;` | `L\J\K` |

### Wildcard root

`A\B\C\*` imports the namespace one level below `A\B\C`, whichever that is. The `*` stands for exactly one segment.

```php
use A\B\C\D\E;      // use A\B\C\D;     E -> D\E
use A\B\C\D\E\F;    // use A\B\C\D;     F -> D\E\F
use A\B\C\X\Y;      // use A\B\C\X;     Y -> X\Y
use A\B\C\D;        // unchanged: already at the root
use A\B\C;          // unchanged: above the root
```

A wildcard root can't have an alias, because it imports a different name for each match.

### Exact root

`G\H\I` imports that namespace under its last segment.

```php
use G\H\I\J\K;      // use G\H\I;       K -> I\J\K
```

### Aliased root

`G\H\I as L` imports that namespace under the alias.

```php
use G\H\I\J\K;      // use G\H\I as L;  K -> L\J\K
```

Use an alias when the last segment is a name PHP reserves, such as `Foo\String as Str`.

## When several roots match

The most specific root wins, whatever order they're listed in:

1. The root with the most literal segments.
2. On a tie, an exact or aliased root beats a wildcard root.

So with `A\*` and `A\B\C\*`, the name `A\B\C\D\E` matches `A\B\C\*`. With `A\B\*` and `A\B as Q`, `A\B\C\D` matches `A\B as Q`.

## Case

PHP's class names and namespaces are case-insensitive, so matching is too. The rewritten import uses the case from your configuration, and for the segment a wildcard captures, the case from the source.

## JSON files

Import roots can live in a JSON file, so they can be shared between projects, or shipped with a library for the projects that use it. A file has two optional keys:

| Key | Type | Purpose |
| --- | --- | --- |
| `roots` | List of strings | Import roots |
| `include` | List of paths | Other JSON files to merge in |

```json
{
    "include": ["vendor/acme/widgets/namespace-shortener.json"],
    "roots": [
        "App\\Domain\\*"
    ]
}
```

JSON needs each backslash in a namespace doubled.

Reference the file from your tool's configuration:

::: code-group

```php [PHP-CS-Fixer]
'Dpi/namespace_import_roots' => [
    'root_files' => ['namespace-shortener.json'],
],
```

```xml [PHP_CodeSniffer]
<rule ref="DpiNamespaceShortener.Namespaces.ImportRoots">
    <properties>
        <property name="rootFiles" type="array">
            <element value="namespace-shortener.json"/>
        </property>
    </properties>
</rule>
```

:::

Paths in your tool's configuration start from the working directory, so run the tool from the project's root. Paths in `include` start from the file they're written in. A file that can't be read fails the run, so a wrong path can't quietly disable a root.

### Shipping roots with a library

A library can include a JSON file of the roots it recommends:

```json
{
    "roots": ["Acme\\Widgets\\*"]
}
```

Projects using the library then merge it into their own, through `include` or `root_files`. They get any changes to it when they update the library.

### How files are merged

- **What you list wins.** A file's `roots` override any root for the same namespace from what it includes, and the tool's `roots` override any from `root_files`. So you can give a library's root an alias without editing its file. This only replaces a root with the same namespace and form: `A\B` doesn't replace `A\B\*`.
- **The same root twice is fine.** Files can overlap, and a file can be reached more than once.
- **Disagreements fail the run.** If two files give the same namespace different roots, such as `A\B` and `A\B as Q`, and nothing overrides them, the run fails rather than picking one. That includes a difference in case, because imports use the case you configure. To settle it, list the root you want in the place that includes both.

Roots from different files can still [compete for a name](./errors#competingroots), which is reported when it affects a file, as usual.

### Caches

Your tool's cache doesn't know about these files, so after one changes, including through updating a library, it can treat files as clean that no longer are. **Delete your tool's cache file when a JSON file changes**, including any cache kept between CI runs. See [PHP-CS-Fixer](../tools/php-cs-fixer#caching-and-json-files) and [PHP_CodeSniffer](../tools/php-codesniffer#caching-and-json-files).

## Invalid configuration

These fail the run before any file is changed:

- A malformed root, such as `A\*\B`, `A\B*`, or an empty string.
- A wildcard with an alias, such as `A\* as X`.
- An alias PHP reserves, such as `A\B as String`.
- An exact root whose last segment PHP reserves, with no alias, such as `A\String`.
- The same root listed twice in one list, even with a different case or alias.
- No import roots at all, a root that isn't a string, or an unknown option. In PHP_CodeSniffer, that includes a `roots` or `rootFiles` property without `type="array"`.
- A [JSON file](#json-files) that can't be read, isn't valid, has an unknown key, or includes itself.
- [Files that disagree](#how-files-are-merged) about a root.
