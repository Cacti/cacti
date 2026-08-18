<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Smoke tests for issue #7131. Cheap structural checks that verify the
 * touched files parse, the relevant functions exist with the expected
 * signatures, and the post-fix shape is in place. Runs without Cacti's
 * bootstrap.
 */

$repoRoot = __DIR__ . '/../..';

test('lib/template.php parses and contains change_data_template()', function () use ($repoRoot) {
	expect(file_exists("$repoRoot/lib/template.php"))->toBeTrue();
	$src = file_get_contents("$repoRoot/lib/template.php");
	expect($src)->toContain('function change_data_template(int $local_data_id, int $data_template_id');
});

test('lib/api_data_source.php parses and contains api_data_source_duplicate()', function () use ($repoRoot) {
	expect(file_exists("$repoRoot/lib/api_data_source.php"))->toBeTrue();
	$src = file_get_contents("$repoRoot/lib/api_data_source.php");
	expect($src)->toContain('function api_data_source_duplicate(int $_local_data_id, int $_data_template_id');
});

test('post-fix data_input_data write shape is consistent across both paths', function () use ($repoRoot) {
	$expected = '(data_input_field_id, data_template_data_id, data_template_id, local_data_id, host_id, t_value, value)';
	expect(file_get_contents("$repoRoot/lib/template.php"))->toContain($expected);
	expect(file_get_contents("$repoRoot/lib/api_data_source.php"))->toContain($expected);
});

test('change_data_template stamps function-scope identity for child rows', function () use ($repoRoot) {
	$src = file_get_contents("$repoRoot/lib/template.php");
	expect($src)->toContain('SELECT host_id');
	expect($src)->toContain('FROM data_local');
});

test('api_data_source_duplicate uses bound identity vars for the bind list', function () use ($repoRoot) {
	$src = file_get_contents("$repoRoot/lib/api_data_source.php");
	expect($src)->toContain('$bound_data_template_id');
	expect($src)->toContain('$bound_local_data_id');
	expect($src)->toContain('$bound_host_id');
});
