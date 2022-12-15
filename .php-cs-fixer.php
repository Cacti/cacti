<?php

$header = <<<'EOF'
+-------------------------------------------------------------------------+
| Copyright (C) 2004-2022 The Cacti Group                                 |
|                                                                         |
| This program is free software; you can redistribute it and/or           |
| modify it under the terms of the GNU General Public License             |
| as published by the Free Software Foundation; either version 2          |
| of the License, or (at your option) any later version.                  |
|                                                                         |
| This program is distributed in the hope that it will be useful,         |
| but WITHOUT ANY WARRANTY; without even the implied warranty of          |
| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
| GNU General Public License for more details.                            |
+-------------------------------------------------------------------------+
| Cacti: The Complete RRDtool-based Graphing Solution                     |
+-------------------------------------------------------------------------+
| This code is designed, written, and maintained by the Cacti Group. See  |
| about.php and/or the AUTHORS file for specific developer information.   |
+-------------------------------------------------------------------------+
| http://www.cacti.net/                                                   |
+-------------------------------------------------------------------------+
EOF;

$finder = PhpCsFixer\Finder::create()
    ->exclude('tests', 'include/vendor', 'include/fa', 'plugins', 'vendor')
    ->in(__DIR__)
    // ->append([
    // 	__DIR__.'/dev-tools/doc.php',
    // 	__DIR__.'/php-cs-fixer',
    // ])
;

$config = new PhpCsFixer\Config();
$config
    ->setRiskyAllowed(true)
    ->setIndent("\t")
    ->setRules([
        // '@PHP56Migration' => true,
        // '@PhpCsFixer' => true,
        // '@PhpCsFixer:risky' => true,
        'header_comment' => false,
        // 'header_comment' => [
        //	'header' => $header,
        //	'comment_type' => 'comment',
        //	'separate' => 'bottom'
        // ],
        'list_syntax' => [
            'syntax' => 'long'
        ],
        'array_syntax' => [
            'syntax' => 'long'
        ],
        'trim_array_spaces' => false,
        'no_whitespace_before_comma_in_array' => true,
        'no_multiline_whitespace_around_double_arrow' => false,
        'normalize_index_brace' => true,
        'no_mixed_echo_print' => [
            'use' => 'print'
        ],
        'no_spaces_after_function_name' => true,
        //'braces' => [
        //	'position_after_functions_and_oop_constructs' => 'same',
        //	'position_after_control_structures' => 'same',
        //	'allow_single_line_closure' => true,
        //],
        'single_blank_line_at_eof' => true,
        'no_whitespace_in_blank_line' => true,
        'no_trailing_whitespace' => true,
        'method_chaining_indentation' => true,
        'indentation_type' => true,
        'constant_case' => [
            'case' => 'lower',
        ],
        'lowercase_keywords' => true,
        'line_ending' => true,
        'magic_constant_casing' => true,
        'native_function_casing' => true,
        'elseif' => true,
        'include' => false,
        'no_alternative_syntax' => true,
        'no_superfluous_elseif' => true,
        'no_trailing_comma_in_singleline' => true,
        'no_unneeded_curly_braces' => true,
        'no_useless_else' => false,
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => null,
            'always_move_variable' => false
        ],
        'declare_equal_normalize' => [
            'space' => 'single'
        ],
        'dir_constant' => true,
        'single_space_after_construct' => [
            'constructs' => [
                'abstract', 'as', 'attribute', 'break', 'case',
                'catch', 'class', 'clone', 'const', 'const_import',
                'continue', 'do', 'echo', 'else', 'elseif', 'extends',
                'final', 'finally', 'for', 'foreach', 'function',
                'function_import', 'global', 'goto', 'if', 'implements',
                'instanceof', 'insteadof', 'interface', 'match',
                'named_argument', 'new', 'open_tag_with_echo', 'php_open',
                'print', 'private', 'protected', 'public', 'return',
                'static', 'throw', 'trait', 'try', 'use', 'use_lambda',
                'use_trait', 'var', 'while', 'yield', 'yield_from'
            ],
        ],
        'switch_case_semicolon_to_colon' => true,
        'switch_case_space' => true,
        'switch_continue_to_break' => true,
        'logical_operators' => true,
        'function_declaration' => [
            'closure_function_spacing' => 'one'
        ],
        'no_spaces_inside_parenthesis' => true,
        'binary_operator_spaces' => [
            'operators' => [
                '+=' => 'align_single_space',
                '===' => 'align_single_space_minimal',
                '=' => 'align',
                '|' => 'single_space',
                '=>' => 'align',
                '!=' => 'align',
            ],
        ],
        'not_operator_with_space' => false,
        'no_spaces_around_offset' => [
            'positions' => [
                'outside',
                'inside'
            ],
        ],
        'standardize_not_equals' => true,
        'ternary_operator_spaces' => false,
        'full_opening_tag' => true,
        'linebreak_after_opening_tag' => true,
        'align_multiline_comment' => [
            'comment_type' => 'phpdocs_like'
        ],
        'phpdoc_add_missing_param_annotation' => true,
        'no_extra_blank_lines' => [
            'tokens' => [
                'attribute', 'break', 'case', 'continue', 'curly_brace_block',
                'default', 'extra', 'parenthesis_brace_block', 'return',
                'square_brace_block', 'switch', 'throw', 'use',
            ],
        ],
        'no_empty_statement' => true,
        'multiline_whitespace_before_semicolons' => [
            'strategy' => 'no_multi_line',
        ],
        'no_singleline_whitespace_before_semicolons' => true,
        'semicolon_after_instruction' => false,
        //'space_after_semicolon' => [
        //	'remove_in_empty_for_expressions' => true
        //],
        'blank_line_before_statement' => [
            'statements' => [
                'break', 'continue', 'declare', 'default', 'do', 'exit',
                'for', 'foreach', 'goto', 'if', 'return', 'switch',
                'throw', 'try', 'while', 'yield', 'yield_from'
            ],
        ],
        'explicit_string_variable' => false,
        'single_quote' => true,
        'string_line_ending' => true,
        'strict_param' => true,
        'class_attributes_separation' => [
            'elements' => [
                'const' => 'only_if_meta',
                'method' => 'one',
                'property' => 'one',
                'trait_import' => 'only_if_meta',
                'case' => 'only_if_meta',
            ],
        ],
    ])
    ->setFinder($finder);

return $config;
