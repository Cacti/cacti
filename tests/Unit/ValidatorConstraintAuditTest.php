<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

// Load the audit tool as a library (functions only, no filesystem scan).
if (!defined('VALIDATOR_AUDIT_LIB_ONLY')) {
	define('VALIDATOR_AUDIT_LIB_ONLY', true);
}

require_once __DIR__ . '/../tools/validator_constraint_audit.php';

function validator_scan(string $php): array {
	return validator_scan_source("<?php\n" . $php);
}

// Classify the single validator call in a snippet.
function validator_category_of(string $php): string {
	$sites = validator_scan($php);
	expect($sites)->toHaveCount(1);

	return $sites[0]['category'];
}

test('CactiValidator::validateInput with a non-empty constraint array is constrained', function () {
	expect(validator_category_of(
		"CactiValidator::validateInput(gnrv('id'), 'id', [new Assert\Regex('/^[0-9]+\$/')], 3);"
	))->toBe('constrained');
});

test('CactiValidator::validateInput with an empty array literal is empty', function () {
	expect(validator_category_of(
		"CactiValidator::validateInput(gnrv('notes'), 'notes', [], 3);"
	))->toBe('empty');
});

test('CactiValidator::validateInput with array() empty literal is empty', function () {
	expect(validator_category_of(
		"CactiValidator::validateInput(gnrv('notes'), 'notes', array(), 3);"
	))->toBe('empty');
});

test('CactiValidator::validateInput preceded by a no-validation comment is documented-empty', function () {
	expect(validator_category_of(
		"// no-validation: free-text notes field, never reaches a sink\n" .
		"CactiValidator::validateInput(gnrv('notes'), 'notes', [], 3);"
	))->toBe('documented-empty');
});

test('a same-line trailing no-validation comment also documents the exemption', function () {
	expect(validator_category_of(
		"CactiValidator::validateInput(gnrv('notes'), 'notes', [], 3); // no-validation: free text, no sink reads it raw"
	))->toBe('documented-empty');
});

test('a no-validation comment on an unrelated earlier line does not exempt a later call', function () {
	expect(validator_category_of(
		"// no-validation: this refers to something else entirely\n" .
		"\$other = 1;\n" .
		"CactiValidator::validateInput(gnrv('notes'), 'notes', [], 3);"
	))->toBe('empty');
});

test('a trailing no-validation comment on the previous line does not exempt the call', function () {
	expect(validator_category_of(
		"\$other = 1; // no-validation: this is attached to \$other, not the call below\n" .
		"CactiValidator::validateInput(gnrv('notes'), 'notes', [], 3);"
	))->toBe('empty');
});

test('a plain non-Cacti validateInput() call is not matched at all', function () {
	expect(validator_scan("SomeOtherClass::validateInput(\$v, 'x', [], 3);"))->toBe([]);
});

test('a multi-constraint array literal is constrained, not split by its internal comma', function () {
	expect(validator_category_of(
		"CactiValidator::validateInput(gnrv('id'), 'id', [new Assert\NotBlank(), new Assert\Regex('/^[0-9]+\$/')], 3);"
	))->toBe('constrained');
});

test('a nested empty array ahead of a real constraint is still constrained', function () {
	// Regression case: split_call_args() must track [] nesting too, or the
	// comma after the inner [] gets mistaken for the outer array's boundary
	// and the real Assert\NotBlank() constraint that follows never gets seen.
	expect(validator_category_of(
		"CactiValidator::validateInput(gnrv('id'), 'id', [[], new Assert\NotBlank()], 3);"
	))->toBe('constrained');
});

test('a dynamic (variable) constraints argument is dynamic, not empty', function () {
	expect(validator_category_of(
		"CactiValidator::validateInput(gnrv('id'), 'id', \$constraints, 3);"
	))->toBe('dynamic');
});

test('form_input_validate with a non-empty regex is constrained', function () {
	expect(validator_category_of(
		"form_input_validate(get_nfilter_request_var('id'), 'id', '^[0-9]+\$', true, 3);"
	))->toBe('constrained');
});

test('form_input_validate with an empty single-quoted regex is empty', function () {
	expect(validator_category_of(
		"form_input_validate(get_nfilter_request_var('notes'), 'notes', '', true, 3);"
	))->toBe('empty');
});

test('form_input_validate with an empty double-quoted regex is empty', function () {
	expect(validator_category_of(
		'form_input_validate(get_nfilter_request_var(\'notes\'), \'notes\', "", true, 3);'
	))->toBe('empty');
});

test('form_input_validate preceded by a no-validation comment is documented-empty', function () {
	expect(validator_category_of(
		"// no-validation: address free-text field, saved via parameterized sql_save()\n" .
		"form_input_validate(get_nfilter_request_var('address'), 'address', '', true, 3);"
	))->toBe('documented-empty');
});

test('form_input_validate with a dynamic regex argument is dynamic', function () {
	expect(validator_category_of(
		"form_input_validate(gnrv('id'), 'id', \$regex, true, 3);"
	))->toBe('dynamic');
});

test('a blank line between the comment and the call breaks the exemption', function () {
	expect(validator_category_of(
		"// no-validation: stale comment, not actually attached to the call below\n" .
		"\n" .
		"CactiValidator::validateInput(gnrv('notes'), 'notes', [], 3);"
	))->toBe('empty');
});

test('multiple calls in one snippet are all found and classified independently', function () {
	$sites = validator_scan(
		"CactiValidator::validateInput(gnrv('a'), 'a', [], 3);\n" .
		"CactiValidator::validateInput(gnrv('b'), 'b', [new Assert\Regex('/x/')], 3);\n" .
		"form_input_validate(gnrv('c'), 'c', '', true, 3);\n"
	);

	expect($sites)->toHaveCount(3)
		->and($sites[0]['category'])->toBe('empty')
		->and($sites[1]['category'])->toBe('constrained')
		->and($sites[2]['category'])->toBe('empty');
});
