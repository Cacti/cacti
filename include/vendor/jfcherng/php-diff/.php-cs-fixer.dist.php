<?php

$config = (new PhpCsFixer\Config())
    ->setIndent("    ")
    ->setLineEnding("\n")
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setRiskyAllowed(true)
    ->setRules([
        '@PHP71Migration' => true,
        '@PHP71Migration:risky' => true,
        '@PHP73Migration' => true,
        '@PHP74Migration' => true,
        '@PHP74Migration:risky' => true,
        '@PHP80Migration' => false,
        '@PHP80Migration:risky' => true,
        '@PHP81Migration' => false,
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
        'compact_nullable_type_declaration' => true,
        'concat_space' => ['spacing' => 'one'],
        'echo_tag_syntax' => ['format' => 'short'],
        'fully_qualified_strict_types' => true,
        'heredoc_indentation' => false, // disabling this makes test cases more readable
        'linebreak_after_opening_tag' => true,
        'list_syntax' => ['syntax' => 'short'],
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
        'multiline_whitespace_before_semicolons' => ['strategy' => 'new_line_for_chained_calls'],
        'native_constant_invocation' => ['scope' => 'all'],
        'native_function_invocation' => ['scope' => 'all', 'include' => ['@compiler_optimized']],
        'native_type_declaration_casing' => true,
        'no_alternative_syntax' => true,
        'no_null_property_initialization' => true,
        'no_superfluous_elseif' => true,
        'no_trailing_whitespace_in_string' => false, // test cases have trailing spaces
        'no_unneeded_control_parentheses' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'not_operator_with_space' => false,
        'not_operator_with_successor_space' => false,
        'ordered_class_elements' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha', 'imports_order' => ['class', 'const', 'function']],
        'ordered_interfaces' => true,
        'php_unit_set_up_tear_down_visibility' => true,
        'php_unit_strict' => true,
        'php_unit_test_class_requires_covers' => true,
        'phpdoc_add_missing_param_annotation' => true,
        'phpdoc_order' => true,
        'phpdoc_order_by_value' => ['annotations' => ['covers']],
        'phpdoc_to_comment' => false,
        'phpdoc_types_order' => true,
        'pow_to_exponentiation' => true,
        'random_api_migration' => true,
        'return_assignment' => false,
        'simple_to_complex_string_variable' => true,
        'single_line_comment_style' => true,
        'single_trait_insert_per_statement' => true,
        'static_lambda' => false,
        'strict_comparison' => false,
        'strict_param' => false,
        'string_implicit_backslashes' => false,
        'string_line_ending' => true,
        'trailing_comma_in_multiline' => ['after_heredoc' => true, 'elements' => ['arrays', 'arguments']],
        'types_spaces' => ['space_multiple_catch' => 'single'],
        'yoda_style' => false,
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->notPath([
                '/branch-\\w+/', // git worktree
                'example/demo_web.php',
            ])
            ->exclude('libs')
            ->exclude('tests/data')
            ->exclude('tests/Fixtures')
            ->exclude('var')
            ->exclude('vendor')
            ->in(__DIR__)
    )
;

return $config;
