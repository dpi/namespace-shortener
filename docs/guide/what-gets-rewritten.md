# What gets rewritten

## Names in code

Every position where PHP accepts a class name is covered, including:

- `new`, static calls, class constants, `::class`, and `instanceof`
- `extends`, `implements`, and traits, along with `insteadof` and `as` adaptations
- Parameter, return, property, and constant types, including union, intersection, and DNF types
- `catch` and attributes

Names are found with a real parser, so a constant, method, property, named argument, or label that happens to share a name is never touched. Nor are strings and ordinary comments.

### Which names

| Name | Example | Rewritten? |
| --- | --- | --- |
| Through an import | `use A\B\C\D\E;` then `new E()` | Yes |
| Through a shorter import | `use A\B;` then `new B\C\D\E()` | Yes |
| Fully qualified | `new \A\B\C\D\E()` | Yes, and the import is added |
| Relying on the current namespace | `namespace A\B\C;` then `new D\E()` | No |
| Using `namespace\` | `new namespace\D\E()` | No |
| Qualified function or constant through an import | `E\helper()` | Yes, it has to follow the import |
| Fully qualified function or constant | `\A\B\C\D\helper()` | No |

## Names in docblocks

Docblocks are rewritten too, so PHPStan, Psalm, and IDEs keep resolving them:

```php
/**
 * @param D\E|null $e           types, including generics and shapes
 * @return array<string, D\E>
 * @throws D\E
 * @see D\E::method()           references: @see, @uses, @used-by, @covers, {@see}, {@link}
 * @D\Column(type="string")     annotations
 */
```

A docblock can't change how PHP runs, so a few things are left alone on purpose:

- Prose. A word that happens to match an import isn't a reference.
- Template and type alias names, such as `@template T`.
- Array shape keys, such as the `E` in `array{E: int}`.
- Types like `array`, `list`, and `mixed`.
- Tags that don't exactly match an import's case. `@column` isn't a reference to `use Foo\Column;`.

## Imports

- **Merging:** `use A\B\C\D\E;` and `use A\B\C\D\F;` become a single `use A\B\C\D;`.
- **Existing imports:** if the root is already imported under the right name, it's reused.
- **Aliases:** the configuration wins. `use A\B\C\D\E as Foo;` is rewritten like any other import, and with the root `G\H\I as L`, both `use G\H\I;` and `use G\H\I as Z;` become `use G\H\I as L;`.
- **Shorter imports:** `use A\B;` goes away if all its usages moved to a root, and stays if any remain.
- **Unused imports:** left alone. Removing them is the job of a dedicated rule.
- **`use function` and `use const`:** left alone.
- **Group and comma imports:** expanded to one statement per import, but only if one of their members changes. Comments inside them are lost when that happens.

### Placement

A new import takes the place of the first import it replaces. If it replaces nothing, it goes after the last import, or else after the `namespace` or `declare` statement. Sorting is left to [your import-sorting rule](./other-rules).

## Files with several namespaces

PHP keeps separate imports for each namespace in a file, so each is handled on its own.

## Known limits

- Class names inside strings, such as `'A\B\C\D\E'` or `class_alias()` arguments, aren't rewritten. They're always fully qualified, so they keep working.
- A docblock tag with no variable, such as `@param Foo`, is understood. One too malformed to read, such as `@var array<Foo`, is left as it is, even if its import has gone. It doesn't affect how the code runs, and PHPStan will report it.
