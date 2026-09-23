<?php

$config = (new PhpCsFixer\Config())
    ->setIndent("    ")
    ->setLineEnding("\n")
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setRiskyAllowed(true)
    ->setRules([
        '@PHP71Migration' => true,
        '@PHP73Migration' => false,
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => true,
        '@PSR12' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'align_multiline_comment' => true,
        'array_indentation' => true,
        'array_syntax' => ['syntax' => 'short'],
        'combine_consecutive_issets' => true,
        'combine_consecutive_unsets' => true,
        'combine_nested_dirname' => true,
        'comment_to_phpdoc' => true,
        'compact_nullable_typehint' => true,
        'concat_space' => ['spacing' => 'one'],
        'escape_implicit_backslashes' => false,
        'fully_qualified_strict_types' => true,
        'linebreak_after_opening_tag' => true,
        'list_syntax' => ['syntax' => 'short'],
        'method_argument_space' => ['ensure_fully_multiline' => true],
        'native_constant_invocation' => true,
        'native_function_invocation' => true,
        'native_function_type_declaration_casing' => true,
        'no_alternative_syntax' => true,
        'no_multiline_whitespace_before_semicolons' => true,
        'no_null_property_initialization' => true,
        'no_short_echo_tag' => true,
        'no_superfluous_elseif' => true,
        'no_unneeded_control_parentheses' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'not_operator_with_space' => false,
        'not_operator_with_successor_space' => false,
        'ordered_class_elements' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha', 'imports_order' => ['class', 'const', 'function']],
        'ordered_interfaces' => true,
        'php_unit_ordered_covers' => true,
        'php_unit_set_up_tear_down_visibility' => true,
        'php_unit_strict' => true,
        'php_unit_test_class_requires_covers' => true,
        'phpdoc_add_missing_param_annotation' => true,
        'phpdoc_order' => true,
        'phpdoc_to_comment' => false,
        'phpdoc_types_order' => true,
        'pow_to_exponentiation' => true,
        'random_api_migration' => true,
        'return_assignment' => false,
        'simple_to_complex_string_variable' => true,
        'single_line_comment_style' => true,
        'single_trait_insert_per_statement' => true,
        'strict_comparison' => false,
        'strict_param' => false,
        'string_line_ending' => true,
        'yoda_style' => false,
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->exclude('libs')
            ->exclude('tests/Fixtures')
            ->exclude('var')
            ->exclude('vendor')
            ->in(__DIR__)
    )
;

return $config;
