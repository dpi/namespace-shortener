# PHP_CodeSniffer

Everything specific to running the rule in [PHP_CodeSniffer](https://github.com/PHPCSStandards/PHP_CodeSniffer), through its `phpcs` and `phpcbf` commands. Versions 3.13 and later, and 4, are supported.

## Setup

```sh
composer require --dev dpi/namespace-shortener-phpcs
```

Composer asks you to allow the `dealerdirect/phpcodesniffer-composer-installer` plugin, which is what registers the standard with PHP_CodeSniffer. Say yes, then check it's there:

```sh
vendor/bin/phpcs -i
```

The list should include `DpiNamespaceShortener`. PHP_CodeSniffer has to be installed through Composer too, as its PHAR can't load this standard's dependencies.

Add the sniff to your ruleset, with your [import roots](../guide/configuration):

```xml
<?xml version="1.0"?>
<!-- phpcs.xml.dist -->
<ruleset name="My project">
    <file>src</file>

    <rule ref="DpiNamespaceShortener.Namespaces.ImportRoots">
        <properties>
            <property name="roots" type="array">
                <element value="App\Domain\*"/>
            </property>
        </properties>
    </rule>
</ruleset>
```

Import roots can also come from [JSON files](../guide/configuration#json-files), with the `rootFiles` property:

```xml
<property name="rootFiles" type="array">
    <element value="namespace-shortener.json"/>
</property>
```

Relative paths start from the working directory, not the ruleset, so run PHP_CodeSniffer from the directory they're relative to. Otherwise, it fails, saying the file can't be read.

Both properties have to be in the ruleset. `phpcs:set` comments don't affect them, because the sniff reads them before any file.

## Running

| Mode | Command |
| --- | --- |
| Check | `vendor/bin/phpcs` |
| See the changes | `vendor/bin/phpcs --report=diff` |
| Fix | `vendor/bin/phpcbf` |

## What it reports

Each change is its own fixable error, on the line where it would happen:

```
 5 | ERROR | [x] Replace "use A\B\C\D\E;" with "use A\B\C\D;".
 6 | ERROR | [x] Remove "use A\B\C\D\F;".
 8 | ERROR | [x] Replace "E" with "D\E".
 9 | ERROR | [x] Replace "E" with "D\E".
```

## Error codes

Each code starts with the sniff's name. Only the first is fixable.

| Code | Meaning |
| --- | --- |
| `DpiNamespaceShortener.Namespaces.ImportRoots.NeedsRewrite` | A change this rule would make |
| `DpiNamespaceShortener.Namespaces.ImportRoots.ReservedName` | [ReservedName](../guide/errors#reservedname) |
| `DpiNamespaceShortener.Namespaces.ImportRoots.KeywordAsBareName` | [KeywordAsBareName](../guide/errors#keywordasbarename) |
| `DpiNamespaceShortener.Namespaces.ImportRoots.ImportCollision` | [ImportCollision](../guide/errors#importcollision) |
| `DpiNamespaceShortener.Namespaces.ImportRoots.DeclaredClass` | [DeclaredClass](../guide/errors#declaredclass) |
| `DpiNamespaceShortener.Namespaces.ImportRoots.ImplicitNamespaceName` | [ImplicitNamespaceName](../guide/errors#implicitnamespacename) |
| `DpiNamespaceShortener.Namespaces.ImportRoots.CompetingRoots` | [CompetingRoots](../guide/errors#competingroots) |
| `DpiNamespaceShortener.Namespaces.ImportRoots.RewriteVerificationFailed` | [A verification failure](../guide/errors#verification-failures) |
| `DpiNamespaceShortener.Namespaces.ImportRoots.UnparsableSource` | The file has a PHP syntax error |

## Exit codes

PHP_CodeSniffer changed its exit codes between versions 3 and 4.

| Situation | `phpcs` 3 | `phpcs` 4 | `phpcbf` 3 | `phpcbf` 4 |
| --- | --- | --- | --- | --- |
| Nothing to change | `0` | `0` | `0` | `0` |
| Files need changing | `2` | `1` | `1`, and they're changed | `0`, and they're changed |
| [A conflict](../guide/errors#conflicts) in a file | `1` | `2` | `0` | `2` |
| A file has a PHP syntax error | `1` | `2` | `0` | `2` |
| [A suppressed finding](#suppressing-findings) | `2` | `1` | `2` | `5` |
| [Invalid configuration](../guide/configuration#invalid-configuration), or no roots | `3` | `16` | `3` | `16` |

::: warning Use `phpcs` in CI
`phpcbf` from version 3 exits `0` when the only problems are ones it can't fix, so conflicts and syntax errors don't fail it. `phpcs --report=diff` from version 4 exits `0` even when there are changes to make. Use `phpcs` with any other report to fail a build.
:::

## Seeing why a file failed

`phpcs` always prints the reason, with its line number. `phpcbf` doesn't, so run `phpcs` to see it.

## Failed files and other sniffs

When this rule can't fix a file, it makes no change to it. Unlike PHP-CS-Fixer, other sniffs still fix that file in the same run.

The `--cache` option can't hide a failure, because PHP_CodeSniffer's cache keeps a file's errors and reports them again.

## Caching and JSON files

PHP_CodeSniffer's cache is off unless you use `--cache` or `<arg name="cache"/>`. It's invalidated when your ruleset changes, but not when the contents of a [JSON file](../guide/configuration#json-files) change, so a file keeps its old result until the file itself changes.

::: warning Delete the cache when a JSON file changes
If you use the cache, delete its file whenever a JSON file changes, including through updating a library. `--no-cache` gives a correct result for that run, but leaves the old cache for the next one.
:::

## Suppressing findings

`phpcs:ignore` and `phpcs:disable` hide a finding as usual, and `phpcs:ignoreFile` skips the file. But the changes to a file only work together: rewriting the rest would leave a suppressed line using an import that has gone. So if any finding in a file is suppressed:

- `phpcs` still reports the others as fixable.
- `phpcbf` changes nothing in that file for this rule, and reports `FAILED TO FIX`.

Excluding `NeedsRewrite` from the sniff in your ruleset has the same effect, for every file. To leave some code out, exclude whole files with `<exclude-pattern>`.

## Syntax errors

PHP_CodeSniffer runs sniffs on files with syntax errors, so this rule always reports them, as `UnparsableSource`, rather than leaving them to the tool.

## Other sniffs

PHP_CodeSniffer has no way to order sniffs. Instead, `phpcbf` keeps making passes over a file until nothing changes, so this rule and others settle on a result together.

Each of these is tested alongside it by running `phpcbf` for real. The tests check that the rewrite survives, that this rule then has nothing left to report, and that a second run changes nothing.

- The `PSR1`, `PSR2`, `PSR12`, `PEAR`, and `Squiz` standards
- `PSR12.Files.ImportStatement`
- `PSR2.Namespaces.UseDeclaration`

Third-party standards aren't tested. See [rules that work against this one](../guide/other-rules#rules-that-work-against-this-one).
