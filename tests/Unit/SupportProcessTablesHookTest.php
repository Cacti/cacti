<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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
*/

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';

/*
 * support_process_tables() (support.php, #7353) publishes the built-in process
 * tables through the 'support_process_tables' plugin hook, then discards any
 * definition a plugin returns whose label/table/select are not all strings.
 * That is a type check only -- it does not validate that 'select' is
 * syntactically valid SQL, so a malformed plugin SELECT can still break the
 * UNION in show_cacti_processes().
 *
 * The function is extracted from support.php and evaluated with its hook call
 * redirected to a test-controlled value, so the plugin return can be driven
 * without a registered plugin. This mirrors the source-extraction convention in
 * Issue7070PercentileContractTest; eval() runs only Cacti's own source with no
 * external input.
 */
function process_tables_define_probe(): void {
	if (function_exists('support_process_tables_probe')) {
		return;
	}

	$src = file_get_contents(dirname(__DIR__, 2) . '/support.php');

	if (preg_match('/function support_process_tables\(\) : array \{.*?^\}/sm', $src, $m) !== 1) {
		throw new RuntimeException('could not locate support_process_tables() in support.php');
	}

	$body = $m[0];

	// Redirect the hook call to a global the test controls. When the global is
	// unset the real hook runs (no registered plugins, so it returns $defaults).
	// A regex (rather than an exact-string match) tolerates minor reformatting
	// of the call, and the count check catches the rewrite silently no-op'ing.
	$body = preg_replace(
		'/\$definitions\s*=\s*api_plugin_hook_function\(\s*\'support_process_tables\'\s*,\s*\$defaults\s*\)\s*;/',
		"\$definitions = array_key_exists('__test_process_hook', \$GLOBALS) ? \$GLOBALS['__test_process_hook'] : api_plugin_hook_function('support_process_tables', \$defaults);",
		$body,
		-1,
		$hook_call_count
	);

	if ($hook_call_count !== 1) {
		throw new RuntimeException("expected exactly one support_process_tables() hook call to rewrite, found $hook_call_count");
	}

	$body = preg_replace('/^function support_process_tables\(\)/m', 'function support_process_tables_probe()', $body);

	eval($body);
}

process_tables_define_probe();

beforeEach(function () {
	unset($GLOBALS['__test_process_hook']);
});

test('the built-in process tables are all well formed', function () {
	$tables = support_process_tables_probe();

	expect($tables)->toBeArray()
		->and($tables)->toHaveKeys([
			'poller_time', 'processes', 'grid_processes', 'automation_processes',
			'plugin_hmib_processes', 'plugin_mikrotik_processes',
			'plugin_webseer_processes', 'plugin_servcheck_processes',
			'mac_track_processes',
		]);

	foreach ($tables as $definition) {
		expect($definition['label'])->toBeString()
			->and($definition['table'])->toBeString()
			->and($definition['select'])->toBeString();
	}
});

test('a non-array hook result falls back to the built-in tables', function () {
	$GLOBALS['__test_process_hook'] = 'a plugin returned a string';

	$tables = support_process_tables_probe();

	expect($tables)->toBeArray()
		->and($tables)->toHaveKey('poller_time');
});

test('malformed plugin definitions are discarded and valid ones kept', function () {
	$GLOBALS['__test_process_hook'] = [
		'good_plugin' => [
			'label'  => 'Good Plugin',
			'table'  => 'plugin_good_processes',
			'select' => 'SELECT 1',
		],
		'not_an_array'   => 'nope',
		'missing_select' => ['label' => 'x', 'table' => 'y'],
		'array_label'    => ['label' => ['x'], 'table' => 'y', 'select' => 'z'],
		'array_table'    => ['label' => 'x', 'table' => ['y'], 'select' => 'z'],
		'array_select'   => ['label' => 'x', 'table' => 'y', 'select' => ['z']],
	];

	$tables = support_process_tables_probe();

	expect(array_keys($tables))->toBe(['good_plugin'])
		->and($tables['good_plugin']['table'])->toBe('plugin_good_processes');
});

test('the process query is skipped when no tables are available', function () {
	// A regression guard: when every process table is absent the UNION source is
	// empty, and show_cacti_processes() must render an empty result instead of a
	// broken "FROM ()" query.
	$src = file_get_contents(dirname(__DIR__, 2) . '/support.php');

	expect($src)->toContain("if (\$sql_inner == '') {")
		->and($src)->toContain('$processes  = [];');
});
