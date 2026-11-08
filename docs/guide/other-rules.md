# Working with other rules

This rule doesn't sort imports or remove unused ones, and leaves both to rules dedicated to them. Those rules need to see its result, and how that's arranged depends on your tool.

## Rules that work against this one

Some rules enforce the opposite style, and can't be combined with this one. Avoid any that:

- Require one import per class, or forbid partly qualified names like `D\E`.
- Add imports automatically for every class. IDE settings that do this can have the same effect.

## Compatibility by tool

Your tool's page lists the rules tested with this one:

- [PHP-CS-Fixer](../tools/php-cs-fixer#other-rules)
- [PHP_CodeSniffer](../tools/php-codesniffer#other-sniffs)
