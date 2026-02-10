<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude('include/vendor')
    ->exclude('rra')
    ->exclude('cache')
    ->exclude('log')
    ->exclude('mib')
    ->exclude('resource')
    ->exclude('service')
    ->exclude('tests')
    ->exclude('locales')
    ->exclude('images')
    ->exclude('plugins')
    ->exclude('formats')
    ->exclude('contrib')
    ->exclude('docs')
    ->in(__DIR__)
    ->append(array(
        __DIR__.'/php-cs-fixer',
    )
);

$config = new PhpCsFixer\Config();
$config
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setRiskyAllowed(true)
    ->setIndent("\t")
    ->setLineEnding("\n")
    ->setRules(array(
    'header_comment' => false,
    //'header_comment' => ['header' => $header, 'comment_type' => 'comment', 'separate' => 'bottom'],
    'comment_to_phpdoc'                           => true,
    'phpdoc_align'                                => true,
    'list_syntax'                                 => ['syntax' => 'short'],
    'array_syntax'                                => ['syntax' => 'short'],
    'trim_array_spaces'                           => false,
    'no_whitespace_before_comma_in_array'         => true,
    'whitespace_after_comma_in_array'             => true,
    'no_multiline_whitespace_around_double_arrow' => true,
    'no_whitespace_in_blank_line'                 => true,
    'no_trailing_whitespace'                      => true,
    'normalize_index_brace'                       => true,
    'no_mixed_echo_print'                         => ['use' => 'print'],
    'no_spaces_after_function_name'               => true,
    'braces' => [
        'position_after_functions_and_oop_constructs' => 'same',
        'position_after_control_structures'           => 'same',
        'allow_single_line_closure'                   => true
    ],
    'braces_position' => [
        'anonymous_classes_opening_brace'   => 'same_line',
        'anonymous_functions_opening_brace' => 'same_line',
        'classes_opening_brace'             => 'same_line',
        'functions_opening_brace'           => 'same_line'
    ],
    'single_blank_line_at_eof'          => true,
    'method_chaining_indentation'       => true,
    'indentation_type'                  => true,
    'constant_case'                     => true,
    'lowercase_keywords'                => true,
    'line_ending'                       => true,
    'magic_constant_casing'             => true,
    'native_function_casing'            => true,
    'elseif'                            => true,
    'include'                           => false,
    'no_alternative_syntax'             => true,
    'no_superfluous_elseif'             => true,
    'no_trailing_comma_in_singleline'   => true,
    'no_unneeded_braces'                => true,
    'no_useless_else'                   => false,
    'yoda_style'                        => [
        'equal' => false,
        'identical' => false,
        'less_and_greater' => null,
        'always_move_variable' => false
    ],
    'declare_equal_normalize'           => ['space' => 'single'],
    'dir_constant'                      => true,
    'single_space_around_construct'     => [
        'constructs_followed_by_a_single_space' => [
            'abstract',
            'as',
            'attribute',
            'break',
            'case',
            'catch',
            'class',
            'clone',
            'const',
            'const_import',
            'continue',
            'do',
            'echo',
            'else',
            'elseif',
            'extends',
            'final',
            'finally',
            'for',
            'foreach',
            'function',
            'function_import',
            'global',
            'goto',
            'if',
            'implements',
            'instanceof',
            'insteadof',
            'interface',
            'match',
            'named_argument',
            'new',
            'open_tag_with_echo',
            'php_open',
            'print',
            'private',
            'protected',
            'public',
            'return',
            'static',
            'throw',
            'trait',
            'try',
            'use',
            'use_lambda',
            'use_trait',
            'var',
            'while',
            'yield',
            'yield_from'
        ]
    ],
    'concat_space'                      => ['spacing' => 'one'],
    'switch_case_semicolon_to_colon'    => true,
    'switch_case_space'                 => true,
    'switch_continue_to_break'          => true,
    'logical_operators'                 => true,
    'function_declaration'              => ['closure_function_spacing' => 'one'],
    'spaces_inside_parentheses'         => true,
    'binary_operator_spaces'            => [
        'operators' => [
            '+='  => 'align_single_space',
            '===' => 'align_single_space_minimal',
            '='   => 'align_single_space',
            '|'   => 'single_space',
            '=>'  => 'align',
            '!='  => 'align'
        ]
    ],
    'not_operator_with_space'           => false,
    'no_spaces_around_offset'           => ['positions' => ['outside', 'inside']],
    'standardize_not_equals'            => true,
    'ternary_operator_spaces'           => true,
    'full_opening_tag'                  => false,
    'linebreak_after_opening_tag'       => false,
    'phpdoc_add_missing_param_annotation' => true,
    'no_extra_blank_lines'              => [
        'tokens' => [
            'break',
            'case',
            'continue',
            'curly_brace_block',
            'default',
            'extra',
            'parenthesis_brace_block',
            'return',
            'square_brace_block',
            'switch',
            'throw',
            'use'
        ]
    ],
    'no_empty_statement'                => true,
    'multiline_whitespace_before_semicolons'      => true,
    'no_singleline_whitespace_before_semicolons'  => true,
    'semicolon_after_instruction'       => false,
    'space_after_semicolon'             => ['remove_in_empty_for_expressions' => true],
    'blank_line_before_statement'       => [
        'statements' => [
            'continue',
            'break',
            'declare',
            'do',
            'for',
            'foreach',
            'goto',
            'if',
            'return',
            'switch',
            'throw',
            'try',
            'while',
            'yield',
            'yield_from'
        ]
    ],
    'explicit_string_variable'          => false,
    'single_quote'                      => true,
    'string_line_ending'                => true,
    'strict_param'                      => true,
    'align_multiline_comment'           => ['comment_type' => 'phpdocs_like'],
    'single_line_comment_spacing'       => true,
    'single_line_comment_style'         => true,
    'multiline_comment_opening_closing' => true
    ))
    ->setFinder($finder);

return $config;
