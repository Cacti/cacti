<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

namespace InstallerColorImportBatchTest;

$GLOBALS['installer_color_writes'] = [];

function cacti_count(mixed $value) : int {
	return is_array($value) ? count($value) : 0;
}

function cacti_sizeof(mixed $value) : int {
	return is_array($value) ? count($value) : 0;
}

function db_execute_prepared(string $sql, array $params = []) : bool {
	$GLOBALS['installer_color_writes'][] = [$sql, $params];

	return true;
}

$source = file_get_contents(dirname(__DIR__, 2) . '/install/functions.php');

if ($source === false) {
	throw new \RuntimeException('Unable to read install/functions.php for color import tests.');
}

if (preg_match('/function import_colors\(.*?^}\R/ms', $source, $matches) !== 1) {
	throw new \RuntimeException('Unable to extract import_colors() for color import tests.');
}

$colors   = dirname(__DIR__, 2) . '/install/colors.csv';
$function = str_replace("__DIR__ . '/colors.csv'", var_export($colors, true), $matches[0]);
$function = str_replace('function import_colors(', 'function import_colors_under_test(', $function);
eval('namespace InstallerColorImportBatchTest;' . $function);

test('color import uses bounded upsert batches without existence reads', function () {
	expect(import_colors_under_test())->toBeTrue()
		->and($GLOBALS['installer_color_writes'])->toHaveCount(2);

	$total_params = 0;

	foreach ($GLOBALS['installer_color_writes'] as [$sql, $params]) {
		expect($sql)
			->toContain('INSERT INTO colors')
			->toContain('ON DUPLICATE KEY UPDATE')
			->not->toContain('SELECT ')
			->and(count($params) % 3)->toBe(0);

		$total_params += count($params);
	}

	expect($total_params)->toBeGreaterThan(1000);
});
