<?php

$header = <<<EOF
This file is part of the overtrue/phplint package

(c) overtrue

This source file is subject to the MIT license that is bundled
with this source code in the file LICENSE.
EOF;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'header_comment' => ['header' => $header],
        'blank_line_after_opening_tag' => true,
        'braces_position' => true,
        'compact_nullable_type_declaration' => true,
        'concat_space' => ['spacing' => 'one'],
        'declare_equal_normalize' => ['space' => 'none'],
        'declare_parentheses' => true,
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
        'new_with_parentheses' => true,
        'no_empty_statement' => true,
        'no_extra_blank_lines' => true,
        'no_leading_import_slash' => true,
        'no_leading_namespace_whitespace' => true,
        'no_unused_imports' => true,
        'no_whitespace_in_blank_line' => true,
        'return_type_declaration' => ['space_before' => 'none'],
        'single_space_around_construct' => true,
        'single_trait_insert_per_statement' => true,
        'type_declaration_spaces' => true,
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->exclude('vendor')
            ->notPath('fixtures/syntax_error.php')
            ->in([__DIR__.'/src/', __DIR__.'/examples', __DIR__.'/tests/'])
    )
;
