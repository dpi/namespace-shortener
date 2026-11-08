# Terminology

These terms are used throughout the code and docs. Where one exists, they follow the [PHP manual](https://www.php.net/manual/en/language.namespaces.rules.php).

| Term | Meaning | Example |
| --- | --- | --- |
| **Import** | A `use` statement at the top level of a file, or one name within it | `use A\B;` |
| **Local name** | The name an import gives you: its alias, or else its last segment | `B` |
| **Segment** | One part of a name, between separators | `A` |
| **Import root** | A namespace configured to be imported | `G\H\I` |
| **Exact root** | A root imported under its last segment | `G\H\I` |
| **Aliased root** | A root imported under an alias | `G\H\I as L` |
| **Wildcard root** | A root completed by the one segment after its prefix | `A\B\C\*` |
| **Root file** | A JSON file of import roots, which can include others | `namespace-shortener.json` |
| **Unqualified name** | A name with no separator | `E` |
| **Qualified name** | A name with a separator, not at the start | `D\E` |
| **Fully qualified name** | A name starting with a separator | `\A\B\C\D\E` |
| **Relative name** | A name starting with the `namespace` keyword | `namespace\D\E` |
| **Scope** | A namespace block, or a whole file without one. Imports belong to a scope | |
| **Usage** | A name that resolves through an import | `new E()` |
| **Target** | An import that a scope needs after rewriting | |
| **Conflict** | A reason a rewrite can't safely be made | |
| **Tool** | A coding standard tool the rule runs in | PHP-CS-Fixer, PHP_CodeSniffer |
| **Adapter** | The package connecting the rule to one tool | `dpi/namespace-shortener-phpcs` |
| **Core** | The package holding the rule itself, which adapters share | `dpi/namespace-shortener-core` |
| **Check mode** | A run that reports what would change, and writes nothing | `phpcs` |
| **Fix mode** | A run that applies the changes | `phpcbf` |
