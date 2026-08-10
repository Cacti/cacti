<?php
declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Regression test for GHSA-pjmv-fxjm-29cx.
 *
 * The plugin repository URL was an unvalidated free text setting, and archive
 * entries were written under plugins/<name>/ with no check that the entry name
 * stayed inside that directory.  Together they let an administrator point
 * plugin distribution at attacker infrastructure and have it write a PHP file
 * anywhere the web server can reach.
 *
 * @group regression
 */

$pluginsPath = dirname(__DIR__, 2) . '/lib/plugins.php';

test('the repository URL is validated before it builds a request', function () use ($pluginsPath) {
	$source = file_get_contents($pluginsPath);

	expect($source)->not->toMatch("/\\\$repo = trim\(read_config_option\('github_repository'\)/");
	expect($source)->toMatch("/\\\$repo = plugin_validate_repository_url\(read_config_option\('github_repository'\)\)/");
});

test('archive entries are contained before they are written', function () use ($pluginsPath) {
	$source = file_get_contents($pluginsPath);

	expect($source)->toMatch('/validate_relative_path_within\(\$rfile, \$restore_path\) === false/');

	$guard = strpos($source, 'validate_relative_path_within($rfile, $restore_path)');
	$write = strpos($source, 'file_put_contents($restore_path');

	expect($guard)->toBeLessThan($write);
});

test('plugins.php loads the containment module', function () use ($pluginsPath) {
	expect(file_get_contents($pluginsPath))
		->toMatch("/require_once\(__DIR__ \. '\/path_containment\.php'\)/");
	expect(file_exists(dirname(__DIR__, 2) . '/lib/path_containment.php'))->toBeTrue();
});
