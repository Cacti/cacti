<?php
declare(strict_types = 1);
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

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once CACTI_PATH_LIBRARY . '/rrd.php';

test('graph text removes credential variables before substitution', function () {
	$received = null;
	$resolver = static function (string $value, array $graph, array $graphItem) use (&$received) : string {
		$received = [$value, $graph, $graphItem];

		return str_replace('|host_description|', "edge ' switch\nwest", $value);
	};

	$result = rrdtool_resolve_graph_text(
		'|host_description| |host_snmp_community| |host_snmp_password| |host_snmp_priv_passphrase|',
		['host_id' => 7],
		['id'      => 11],
		$resolver
	);

	expect($received)->toBe([
		'|host_description|   ',
		['host_id' => 7],
		['id'      => 11]
	]);
	expect($result)->toBe("edge ' switch\nwest   ");
});

test('every credential placeholder is removed case insensitively', function (string $placeholder) {
	$result = rrdtool_resolve_graph_text(
		$placeholder,
		[],
		[],
		static fn (string $value) : string => $value
	);

	expect($result)->toBe('');
})->with([
	'community'      => '|host_snmp_community|',
	'username'       => '|HOST_SNMP_USERNAME|',
	'password'       => '|host_snmp_password|',
	'privacy secret' => '|host_snmp_priv_passphrase|',
	'context'        => '|host_snmp_context|',
	'engine id'      => '|host_snmp_engine_id|'
]);

test('graph options resolve values before their argument escaping', function () {
	$source = file_get_contents(CACTI_PATH_LIBRARY . '/rrd.php');
	$start  = strpos($source, 'function rrd_function_process_graph_options(');
	$end    = strpos($source, 'function rrdtool_resolve_graph_text(', $start);
	$body   = substr($source, $start, $end - $start);

	$resolverPosition = strpos($body, '$value = rrdtool_resolve_graph_text(');
	$escapePosition   = strpos($body, 'cacti_escapeshellarg(htmle($value))');

	expect($resolverPosition)->not->toBeFalse()
		->and($escapePosition)->not->toBeFalse()
		->and($resolverPosition)->toBeLessThan($escapePosition)
		->and($body)->not->toContain('rrd_substitute_host_query_data($graph_opts');
});

test('RRDtool log context never includes command arguments', function () {
	$command = "graph '/tmp/device.rrd' --title 'private device' --secret 'do-not-log'";
	$context = rrdtool_command_log_context($command);

	expect($context)->toStartWith('command=graph bytes=')
		->and($context)->toContain('sha256=' . hash('sha256', $command))
		->and($context)->not->toContain('/tmp/device.rrd')
		->and($context)->not->toContain('private device')
		->and($context)->not->toContain('do-not-log');
});

test('RRDtool log context handles malformed and empty commands safely', function () {
	foreach (["\0secret", "\r\nsecret", '', '   '] as $command) {
		$context = rrdtool_command_log_context($command);

		expect($context)->toStartWith('command=unknown bytes=')
			->and($context)->toContain('sha256=' . hash('sha256', $command));
	}
});
