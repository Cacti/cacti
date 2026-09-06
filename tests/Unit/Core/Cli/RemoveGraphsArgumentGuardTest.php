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

$longopts = array(
	'host-id:',
	'graph-template-id:',
	'host-template-id:',
	'graph-regex:',
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

/**
 * Exercise regex validation in an isolated process so translation and error
 * handler doubles cannot shadow Cacti functions during Pest collection.
 *
 * @param string $regex           Expression to validate.
 * @param bool   $broken_contract Replace the validator with a false result.
 *
 * @return array{result: string|false, handler: mixed}
 */
function remove_graphs_regex_result($regex, $broken_contract = false) {
	$root        = dirname(__DIR__, 4);
	$translation = 'function __($message) { return $message; }'
		. 'function CactiErrorHandler() { return true; }';
	$validator   = $broken_contract
		? 'function validate_is_rlike_regex($regex) { return false; }'
		: 'require ' . var_export($root . '/lib/html_utility.php', true) . ';';
	$code        = $translation . $validator
		. 'require ' . var_export($root . '/lib/maintenance_cli.php', true) . ';'
		. '$result = cacti_remove_graphs_regex_error(' . var_export($regex, true) . ');'
		. '$handler = set_error_handler(function () {});'
		. 'echo json_encode(array("result" => $result, "handler" => $handler));';
	$pipes       = array();
	$process     = proc_open(array(PHP_BINARY, '-r', $code), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);

	expect($process)->not->toBeFalse();

	$output = stream_get_contents($pipes[1]);
	$error  = stream_get_contents($pipes[2]);

	fclose($pipes[1]);
	fclose($pipes[2]);

	expect(proc_close($process))->toBe(0, $error);

	return json_decode($output, true);
}

test('remove_graphs accepts only declared options with the right value shape', function () use ($shortopts, $longopts) {
	foreach (array('--graph-template-id=5', '--host-id=0', '--all', '--force', '--list-hosts', '-V', '-h', '-Vv') as $parameter) {
		expect(cacti_remove_graphs_parameter_is_valid($parameter, $shortopts, $longopts))->toBeTrue($parameter);
	}

	foreach (array('--graph-typo=5', '--graph-type=weekly', '--all=1', '--host-id', '--host-id=', '--graph-regex', '--', '-', '-x', '-Xv', '-Hfoo', 'graph') as $parameter) {
		expect(cacti_remove_graphs_parameter_is_valid($parameter, $shortopts, $longopts))->toBeFalse($parameter);
	}
});

test('remove_graphs short option validation follows its declaration', function () use ($longopts) {
	expect(cacti_remove_graphs_parameter_is_valid('-qV', 'VvHhq', $longopts))->toBeTrue()
		->and(cacti_remove_graphs_parameter_is_valid('-qX', 'VvHhq', $longopts))->toBeFalse();
});

test('remove_graphs uses the real regex length and semicolon guards', function () {
	$valid     = remove_graphs_regex_result('edge.*');
	$malformed = remove_graphs_regex_result('(');
	$too_long  = remove_graphs_regex_result(str_repeat('a', 51));
	$semicolon = remove_graphs_regex_result('edge;.*');
	$alteration = remove_graphs_regex_result('eth0|eth1');
	$repeat     = remove_graphs_regex_result('^Gi[0-9]{1,2}$');

	expect($valid['result'])->toBeFalse()
		->and($valid['handler'])->toBe('CactiErrorHandler')
		->and($malformed['result'])->toContain('Compilation failed')
		->and($too_long['result'])->toBe('Cacti regular expressions are limited to 50 characters only for security reasons.')
		->and($semicolon['result'])->toBe('Cacti regular expressions can not includes the semi-color character.')
		->and($alteration['result'])->toContain('do not support alternation')
		->and($repeat['result'])->toContain('do not support alternation');
});

test('remove_graphs fails closed when its regex validator breaks contract', function () {
	expect(remove_graphs_regex_result('edge.*', true)['result'])->toBe('Invalid regular expression.');
});

test('remove_graphs quiet mode follows the parsed option key', function () {
	expect(cacti_remove_graphs_quiet_enabled(array()))->toBeFalse()
		->and(cacti_remove_graphs_quiet_enabled(array('quiet' => false)))->toBeTrue();
});

test('reapply names builds balanced prepared query fragments', function () {
	foreach (array(
		array('all', 'edge', 2),
		array('1,2,3', '', 3),
		array('0', 'edge', 3),
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

test('reapply names rejects malformed values that compare loosely to zero', function () {
	foreach (array('0e5', '0.0', '-0', '+0', '0.') as $host_id) {
		expect(cacti_reapply_names_where($host_id, ''))->toBeFalse($host_id);
	}

	expect(cacti_reapply_names_where('0', ''))->toBe(array(' AND graph_local.host_id=?', array(0)));
	expect(cacti_reapply_names_where('all', ''))->toBe(array('', array()));
});

test('normalized maintenance failures use the portable non-zero exit', function () {
	foreach (array('removespikes.php', 'splice_rrd.php') as $script) {
		$source = file_get_contents(__DIR__ . '/../../../../cli/' . $script);

		expect($source)->not->toMatch('/exit\(-[0-9]+\)/');
	}
});

test('remove_graphs wires strict validation before getopt', function () {
	$source = file_get_contents(__DIR__ . '/../../../../cli/remove_graphs.php');

	expect($source)->not->toBeFalse()
		->and($source)->toContain('cacti_remove_graphs_parameter_is_valid($parameter, $shortopts, $longopts)')
		->and($source)->toContain('ERROR: Invalid Argument:')
		->and($source)->not->toContain("'graph-type::'");

	expect($source)->toContain('displayHosts($hosts, $quietMode)')
		->and($source)->toContain('displayHostTemplates($hostTemplates, $quietMode)')
		->and($source)->toContain('displayGraphTemplates($graphTemplates, $quietMode)');
});

test('every declared remove_graphs option has a switch branch', function () use ($shortopts, $longopts) {
	$source = file_get_contents(dirname(__DIR__, 4) . '/cli/remove_graphs.php');

	foreach ($longopts as $option) {
		expect($source)->toContain("case '" . rtrim($option, ':') . "':");
	}

	foreach (str_split(str_replace(':', '', $shortopts)) as $option) {
		expect($source)->toContain("case '$option':");
	}
});

test('all regex consumers honor the validator true-or-error contract', function () {
	$root = dirname(__DIR__, 4);

	$add_graphs = file_get_contents($root . '/cli/add_graphs.php');
	expect($add_graphs)->toContain('validate_is_rlike_regex($item)')
		->and($add_graphs)->toContain('if ($validation !== true)')
		->and($add_graphs)->toContain("' AND field_value ' . db_qstr_rlike(")
		->and($add_graphs)->not->toContain('field_value REGEXP "');

	foreach (array(
		'cli/apply_automation_rules.php',
		'aggregate_graphs.php',
		'lib/functions.php',
		'lib/clog_webapi.php',
	) as $file) {
		$source = file_get_contents($root . '/' . $file);
		$lines  = preg_grep('/validate_is_regex\s*\(/', explode("\n", $source));

		foreach ($lines as $line) {
			expect($line)->toContain('=== true');
		}
	}
});

test('graph-name reapply wires invalid selectors to distinct failures', function () {
	$source = file_get_contents(__DIR__ . '/../../../../cli/poller_graphs_reapply_names.php');

	expect($source)->not->toBeFalse()
		->and($source)->toContain('cacti_reapply_names_where($host_id, $filter)')
		->and($source)->toContain('You must specify either a host_id')
		->and($source)->toContain("Invalid host id '");
});
