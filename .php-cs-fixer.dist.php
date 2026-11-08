<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        'packages',
        'tests',
    ])
    ->exclude([
        // input and expected output for the fixer under test: their style is part of the test
        'Fixtures',
    ]);

$headerComment = static fn (string $project): array => [
    'header' => <<<HEADER
        This file is part of the {$project} project.

        For the full copyright and license information, please view the LICENSE
        file that was distributed with this source code.
        HEADER,
    'comment_type' => 'comment',
    'location' => 'after_open',
    'separate' => 'both',
];

// the name in the composer.json nearest to a path, so each package's files name that package
$composerName = static function (string $path): string {
    $directory = dirname($path);
    while (!is_file($directory . '/composer.json')) {
        if (dirname($directory) === $directory) {
            throw new RuntimeException('No composer.json found above ' . $path);
        }
        $directory = dirname($directory);
    }

    return json_decode(file_get_contents($directory . '/composer.json'), true, flags: JSON_THROW_ON_ERROR)['name'];
};

// header_comment takes one fixed header, so it is configured per file here. This API is experimental in PHP-CS-Fixer
$headerPolicy = new class($headerComment, $composerName) implements PhpCsFixer\Config\RuleCustomisationPolicyInterface {
    public function __construct(
        private readonly Closure $headerComment,
        private readonly Closure $composerName,
    ) {
    }

    public function getPolicyVersionForCache(): string
    {
        $files = [__FILE__, __DIR__ . '/composer.json', ...glob(__DIR__ . '/packages/*/composer.json')];

        return hash('xxh128', implode('', array_map(hash_file(...), array_fill(0, count($files), 'xxh128'), $files)));
    }

    public function getRuleCustomisers(): array
    {
        return [
            'header_comment' => function (SplFileInfo $file): PhpCsFixer\Fixer\FixerInterface {
                $fixer = new PhpCsFixer\Fixer\Comment\HeaderCommentFixer();
                $fixer->configure(($this->headerComment)(($this->composerName)($file->getRealPath())));

                return $fixer;
            },
        ];
    }
};

return (new PhpCsFixer\Config('namespace-shortener'))
    ->setRiskyAllowed(true)
    ->setFinder($finder)
    ->setRuleCustomisationPolicy($headerPolicy)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'header_comment' => $headerComment($composerName(__FILE__)),
        'native_function_invocation' => false,
        'native_constant_invocation' => true,
        // strictness and types
        'strict_comparison' => true,
        'void_return' => true,
        'phpdoc_to_param_type' => true,
        'phpdoc_to_return_type' => true,
        'phpdoc_to_property_type' => true,
        'date_time_immutable' => true,
        'no_redundant_readonly_property' => true,
        // modern syntax
        'ternary_to_null_coalescing' => true,
        'assign_null_coalescing_to_coalesce_equal' => true,
        'list_syntax' => true,
        'octal_notation' => true,
        'use_arrow_functions' => true,
        'heredoc_to_nowdoc' => true,
        'explicit_string_variable' => true,
        'regular_callable_call' => true,
        'attribute_empty_parentheses' => true,
        // simplification
        'no_superfluous_elseif' => true,
        'simplified_if_return' => true,
        'simplified_null_return' => true,
        'combine_consecutive_issets' => true,
        'combine_consecutive_unsets' => true,
        'yield_from_array_to_yields' => true,
        'return_to_yield_from' => true,
        // PHPUnit
        'php_unit_attributes' => true,
        'php_unit_dedicate_assert' => true,
        'php_unit_expectation' => true,
        'php_unit_data_provider_static' => true,
        'php_unit_data_provider_return_type' => true,
        'php_unit_test_case_static_method_calls' => ['call_type' => 'self'],
        // phpdoc
        'phpdoc_no_duplicate_types' => true,
        'phpdoc_no_empty_return' => true,
        'phpdoc_param_order' => true,
        'phpdoc_tag_casing' => true,
        'phpdoc_order_by_value' => true,
        'concat_space' => ['spacing' => 'one'],
        'global_namespace_import' => false,
        'declare_strict_types' => true,
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_line_span' => ['method' => 'multi'],
        'strict_param' => true,
        'ordered_imports' => true,
        'increment_style' => false,
        // the default but `clone`, which PHP 8.5's clone with writes as a call: clone($this, ['prop' => $value])
        'single_space_around_construct' => [
            'constructs_followed_by_a_single_space' => [
                'abstract', 'as', 'attribute', 'break', 'case', 'catch', 'class', 'comment', 'const', 'const_import',
                'continue', 'do', 'echo', 'else', 'elseif', 'enum', 'extends', 'final', 'finally', 'for', 'foreach',
                'function', 'function_import', 'global', 'goto', 'if', 'implements', 'include', 'include_once',
                'instanceof', 'insteadof', 'interface', 'match', 'named_argument', 'namespace', 'new',
                'open_tag_with_echo', 'php_doc', 'php_open', 'print', 'private', 'private_set', 'protected',
                'protected_set', 'public', 'public_set', 'readonly', 'require', 'require_once', 'return', 'static',
                'switch', 'throw', 'trait', 'try', 'type_colon', 'use', 'use_lambda', 'use_trait', 'var', 'while',
                'yield', 'yield_from',
            ],
        ],
        'multiline_promoted_properties' => [
            'minimum_number_of_parameters' => 1,
        ],
        'trailing_comma_in_multiline' => [
            'elements' => ['arguments', 'array_destructuring', 'arrays', 'match', 'parameters'],
        ],
    ]);
