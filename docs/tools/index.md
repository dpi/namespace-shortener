# Supported tools

The rule's logic doesn't depend on any one tool. Each supported tool has a small adapter, in a package of its own, that connects it to the rule. Install the package for your tool, and the shared logic comes with it.

| Tool | Package | Rule name |
| --- | --- | --- |
| [PHP-CS-Fixer](./php-cs-fixer) | `dpi/namespace-shortener-php-cs-fixer` | `Dpi/namespace_import_roots` |
| [PHP_CodeSniffer](./php-codesniffer) | `dpi/namespace-shortener-phpcs` | `DpiNamespaceShortener.Namespaces.ImportRoots` |
