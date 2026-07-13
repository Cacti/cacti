<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Cross-file contract test for the installer vendor refresh (#6456).
 *
 * refreshVendorDependencies() must stay non-fatal, run before the
 * database conversion, and only execute composer when the configured
 * binary exists and the dry-run reports missing packages alone.
 */

$root = dirname(__DIR__, 2);

test('install flow calls the vendor refresh before database conversion', function () use ($root) {
	$src = file_get_contents($root . '/lib/installer.php');
	expect($src)->not->toBeFalse('Failed to read lib/installer.php');

	$installPos = strpos($src, 'private function install() : void');
	expect($installPos)->not->toBeFalse('install() must exist');

	$refreshPos = strpos($src, '$this->refreshVendorDependencies();', $installPos);
	$convertPos = strpos($src, '$this->convertDatabase();', $installPos);

	expect($refreshPos)->not->toBeFalse('install() must call refreshVendorDependencies')
		->and($convertPos)->not->toBeFalse('install() must call convertDatabase')
		->and($refreshPos)->toBeLessThan($convertPos, 'vendor refresh must run before database conversion');
});

test('vendor refresh bails out without a configured composer binary', function () use ($root) {
	$src = file_get_contents($root . '/lib/installer.php');

	$fnPos = strpos($src, 'private function refreshVendorDependencies()');
	expect($fnPos)->not->toBeFalse('refreshVendorDependencies must exist');

	$fnEnd = strpos($src, "\n\tprivate function", $fnPos + 1);
	$body  = substr($src, $fnPos, ($fnEnd === false ? strlen($src) : $fnEnd) - $fnPos);

	expect(preg_match('/read_config_option\(\s*\'path_composer\'/', $body))->toBe(1, 'must read path_composer')
		->and(preg_match('/empty\(\$composer\)|file_exists\(\$composer\)/', $body))->toBe(1, 'must bail without a usable binary')
		->and(strpos($body, '--dry-run'))->not->toBeFalse('must dry-run before touching the tree')
		->and(strpos($body, 'is_resource_writable'))->not->toBeFalse('must check vendor writability before running composer')
		->and(strpos($body, 'addError'))->toBeFalse('refresh must never fail the install');
});

test('refresh gating requires pure installs', function () use ($root) {
	$src = file_get_contents($root . '/lib/installer.php');

	$pattern = '/Package operations: \(\\\\d\+\) installs\?, \(\\\\d\+\) updates\?, \(\\\\d\+\) removals\?/';
	expect(preg_match($pattern, $src))->toBe(1,
		'refresh must parse the dry-run operations summary')
		->and(preg_match('/\$ops\[1\] == 0 \|\| \$ops\[2\] > 0 \|\| \$ops\[3\] > 0/', $src))->toBe(1,
			'refresh must skip when there are no installs or any updates/removals');
});
