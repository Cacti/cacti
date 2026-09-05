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
 | Cacti is designed, written and maintained by the Cacti Group.           |
 |                                                                         |
 | Please read the included docs/CONTRIBUTING.md file for more information.|
 +-------------------------------------------------------------------------+
 */

require_once __DIR__ . '/../../../../lib/maintenance_cli.php';

if (!function_exists('__')) {
	function __($text) {
		return $text;
	}
}

require_once __DIR__ . '/../../../../lib/html_utility.php';

$longopts = array(
	'host-id::',
	'graph-template-id::',
	'host-template-id::',
	'graph-regex::',
	'all',
	'preserve',
	'quiet',
	'list',
	'list-hosts',
	'list-host-templates',
	'list-graph-templates',
	'force',
	'version',
	'help',
);
$shortopts = 'VvHh';

test('remove_graphs accepts only declared options with the right value shape', function () use ($shortopts, $longopts) {
	foreach (array('--graph-template-id=5', '--host-id=0', '--all', '--force', '--list-hosts', '-V', '-h', '-Vv') as $parameter) {
		expect(cacti_remove_graphs_parameter_is_valid($parameter, $shortopts, $longopts))->toBeTrue($parameter);
	}

	foreach (array('--graph-typo=5', '--graph-type=weekly', '--all=1', '--host-id', '--host-id=', '--graph-regex', '--', '-', '-x', '-Xv', 'graph') as $parameter) {
		expect(cacti_remove_graphs_parameter_is_valid($parameter, $shortopts, $longopts))->toBeFalse($parameter);
	}
});

test('remove_graphs short option validation follows its declaration', function () use ($longopts) {
	expect(cacti_remove_graphs_parameter_is_valid('-qV', 'VvHhq', $longopts))->toBeTrue()
		->and(cacti_remove_graphs_parameter_is_valid('-qX', 'VvHhq', $longopts))->toBeFalse();
});

test('remove_graphs treats validator error strings as regex failures', function () {
	expect(cacti_remove_graphs_regex_error('edge.*'))->toBeFalse()
		->and(cacti_remove_graphs_regex_error(str_repeat('a', 51)))->not->toBeFalse()
		->and(cacti_remove_graphs_regex_error('edge;.*'))->not->toBeFalse();
});

test('remove_graphs quiet mode follows the parsed option key', function () {
	expect(cacti_remove_graphs_quiet_enabled(array()))->toBeFalse()
		->and(cacti_remove_graphs_quiet_enabled(array('quiet' => false)))->toBeTrue();
});

test('reapply names builds balanced prepared query fragments', function () {
	foreach (array(
		array('all', 'edge', 2),
		array('1,2,3', '', 3),
		array('0', 'edge', 2),
		array('42', 'edge', 3),
	) as $case) {
		$where = cacti_reapply_names_where($case[0], $case[1]);

		expect($where)->toBeArray()
			->and(substr_count($where[0], '?'))->toBe(count($where[1]))
			->and(count($where[1]))->toBe($case[2]);
	}
});

test('reapply names preserves SQL parameter ordering', function () {
	list($where, $params) = cacti_reapply_names_where('7,9', 'edge');

	expect($where)->toContain('title_cache LIKE ?')
		->and($where)->toContain('host_id IN (?,?)')
		->and($params)->toBe(array('%edge%', '%edge%', 7, 9));
});

test('reapply names retains supported zero, whitespace and leading-zero ids', function () {
	foreach (array(' 5', '5 ', '1, 5', '007') as $host_id) {
		$where = cacti_reapply_names_where($host_id, '');

		expect($where)->toBeArray($host_id)
			->and(substr_count($where[0], '?'))->toBe(count($where[1]));
	}

	expect(cacti_reapply_names_where('0,5', '')[1])->toBe(array(0, 5))
		->and(cacti_reapply_names_where('1,0,3', '')[1])->toBe(array(1, 0, 3))
		->and(cacti_reapply_names_where('0,0', '')[1])->toBe(array(0, 0));
});

test('reapply names rejects an invalid member instead of narrowing the host list', function () {
	expect(cacti_reapply_names_where('1,abc,3', ''))->toBeFalse()
		->and(cacti_reapply_names_where('1,,3', ''))->toBeFalse()
		->and(cacti_reapply_names_where('1 UNION SELECT 1', ''))->toBeFalse()
		->and(cacti_reapply_names_where('', ''))->toBeFalse();
});

test('normalized maintenance failures use the portable non-zero exit', function () {
	foreach (array('removespikes.php', 'splice_rrd.php') as $script) {
		$source = file_get_contents(__DIR__ . '/../../../../cli/' . $script);

		expect($source)->not->toMatch('/exit\(-[0-9]+\)/');
	}
});
