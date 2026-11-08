# Errors

A rule that applies fixes automatically has to be careful about the ones it can't apply safely. So a fix either happens correctly, or the run fails: this rule never skips a problem silently, and never writes a change that alters what code means.

## When a file fails

- The run fails with a bad exit code in check mode, and in fix mode too in most tools. So use check mode in CI.
- This rule doesn't change the file. Whether other rules can depends on your tool.
- Other files are still processed.
- The file isn't remembered as clean, so it fails again next time.
- The reason covers every conflict in the file, with line numbers.

Exit codes, and how to see the reason, depend on your tool:

- [PHP-CS-Fixer](../tools/php-cs-fixer#exit-codes)
- [PHP_CodeSniffer](../tools/php-codesniffer#exit-codes)

[Invalid configuration](./configuration#invalid-configuration) fails the run too.

## Conflicts

### ReservedName

PHP doesn't allow the name for an import.

```php
// A\B\*
use A\B\String\E;   // use A\B\String; is a fatal error
```

**To fix it:** add an aliased root, such as `A\B\String as Str`.

The list covers every name reserved in any supported PHP version. `array` and `callable` only became fatal in PHP 8.5, but they're refused everywhere so fixed code keeps working when you upgrade.

### KeywordAsBareName

The name works as part of a longer name, but not on its own.

```php
// A\B\*
new \A\B\List();    // new List(); is a parse error
```

`use A\B\List;` with `List\Item` is fine, so this only fails when the name would stand alone.

**To fix it:** add an aliased root.

### ImportCollision

Another import already has the name. This includes an import nothing uses.

```php
// A\B\C\*
use X\D;
use A\B\C\D\E;      // use A\B\C\D; is a fatal error: "D" is taken
```

**To fix it:** alias the root, or rename or remove the other import.

### DeclaredClass

The file declares a class, interface, trait, or enum with the name.

```php
// A\B\C\*
use A\B\C\D\E;      // use A\B\C\D; is a fatal error

class D extends E {}
```

**To fix it:** alias the root, or rename the class.

### ImplicitNamespaceName

The most dangerous case, because PHP wouldn't complain. A name relying on the current namespace would silently start to mean something else.

```php
// A\B\C\*
namespace App;

use A\B\C\D\E;

new D();            // means App\D today, and would become A\B\C\D
new E();
```

Qualified names (`D\Y`), type hints, and docblocks are all checked.

**To fix it:** alias the root, or write the existing name in full.

### CompetingRoots

Two roots need the same name in one namespace.

```php
// A\B\* and X\Y\*
use A\B\D\E;        // wants "D" for A\B\D
use X\Y\D\F;        // wants "D" for X\Y\D
```

**To fix it:** replace one with an aliased root.

## Verification failures

As a last line of defence, every rewrite is parsed again, and every name must still resolve to the same symbol. If not, the file fails with `RewriteVerificationFailed`, rather than being written.

You'd normally only see this when imports come after the code using them, which PHP allows, but where an import only applies to the code after it:

```php
new \A\B\C\D\E();
use X\Y;            // the new import would land here, after its usage
```

**To fix it:** move the imports to the top of the file.
