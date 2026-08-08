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

namespace InstallerDefaultSettingsBatchTest;

$GLOBALS['installer_default_writes'] = [];

function cacti_sizeof(mixed $value) : int {
	return is_array($value) ? count($value) : 0;
}

function db_execute_prepared(string $sql, array $params = []) : bool {
	$GLOBALS['installer_default_writes'][] = [$sql, $params];

	return true;
}

$source = file_get_contents(dirname(__DIR__, 2) . '/install/functions.php');

if ($source === false) {
	throw new \RuntimeException('Unable to read install/functions.php for default-setting tests.');
}

if (preg_match('/function prime_default_settings\(.*?^}\R/ms', $source, $matches) !== 1) {
	throw new \RuntimeException('Unable to extract prime_default_settings() for default-setting tests.');
}

$function = str_replace('function prime_default_settings(', 'function prime_default_settings_under_test(', $matches[0]);
eval('namespace InstallerDefaultSettingsBatchTest;' . $function);

beforeEach(function () {
	$GLOBALS['installer_default_writes'] = [];
	$GLOBALS['settings']                 = [];
	$_SESSION                            = [];
});

test('default settings are inserted in one batch without existence queries', function () {
	$GLOBALS['settings'] = [
		'general' => [
			'alpha' => ['default' => 'one'],
			'group' => ['items' => [
				'beta'  => ['default' => 'two'],
				'gamma' => ['description' => 'no default'],
			]],
		],
	];

	prime_default_settings_under_test();

	expect($GLOBALS['installer_default_writes'])->toHaveCount(1)
		->and($GLOBALS['installer_default_writes'][0][0])->toContain('INSERT IGNORE INTO settings')
		->toContain('(?, ?), (?, ?)')
		->and($GLOBALS['installer_default_writes'][0][1])->toBe(['alpha', 'one', 'beta', 'two'])
		->and($_SESSION['settings_primed'])->toBeTrue();
});

test('large setting collections are chunked and an existing prime marker skips work', function () {
	$items = [];

	for ($index = 0; $index < 251; $index++) {
		$items['setting_' . $index] = ['default' => (string) $index];
	}

	$GLOBALS['settings'] = ['large' => $items];
	prime_default_settings_under_test();

	expect($GLOBALS['installer_default_writes'])->toHaveCount(2)
		->and($GLOBALS['installer_default_writes'][0][1])->toHaveCount(500)
		->and($GLOBALS['installer_default_writes'][1][1])->toHaveCount(2);

	prime_default_settings_under_test();
	expect($GLOBALS['installer_default_writes'])->toHaveCount(2);
});
