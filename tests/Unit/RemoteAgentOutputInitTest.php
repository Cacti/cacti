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

/*
 * remote_agent.php's get_snmp_data() and get_snmp_data_walk() assigned
 * $output only inside the if (!empty($host_id)) branch, then printed
 * $output / fed it to cacti_sizeof() unconditionally. An empty host_id
 * left $output undefined; a missing host row left it stale or unset
 * and the agent leaked PHP notice text instead of the standard 'U'
 * marker. Both functions must initialise $output before the host
 * lookup and must short-circuit with 'U' when the host row is missing.
 */

$source = file_get_contents(__DIR__ . '/../../remote_agent.php');

function _ra_function_body(string $source, string $needle): string {
	$start = strpos($source, $needle);
	expect($start)->not->toBeFalse();

	/* Search for the next top-level function definition that follows
	 * this one. Two-newline + "function " is the canonical separator. */
	$end = strpos($source, "\nfunction ", $start + strlen($needle));
	return substr($source, $start, $end !== false ? $end - $start : 4000);
}

test('get_snmp_data initialises $output before the host lookup', function () use ($source) {
	$body = _ra_function_body($source, 'function get_snmp_data() {');

	$initPos   = strpos($body, "\$output = ''");
	$hostCheck = strpos($body, 'if (!empty($host_id))');

	expect($initPos)->not->toBeFalse('$output must be initialised to an empty string');
	expect($hostCheck)->not->toBeFalse();
	expect($initPos < $hostCheck)->toBeTrue('initialisation must precede the host_id guard');
});

test('get_snmp_data bails with U when the host row is missing', function () use ($source) {
	$body = _ra_function_body($source, 'function get_snmp_data() {');

	expect($body)->toContain('!cacti_sizeof($host)');
	expect($body)->toContain("print 'U'");
});

test('get_snmp_data_walk initialises $output as an array before the host lookup', function () use ($source) {
	$body = _ra_function_body($source, 'function get_snmp_data_walk() {');

	$initPos   = strpos($body, '$output = array()');
	$hostCheck = strpos($body, 'if (!empty($host_id))');

	expect($initPos)->not->toBeFalse('$output must be initialised as an array');
	expect($hostCheck)->not->toBeFalse();
	expect($initPos < $hostCheck)->toBeTrue('array init must precede the host_id guard');
});

test('get_snmp_data_walk preserves the cacti_sizeof($output) / U fallback', function () use ($source) {
	$body = _ra_function_body($source, 'function get_snmp_data_walk() {');

	expect($body)->toContain('!cacti_sizeof($host)');
	expect($body)->toContain('cacti_sizeof($output)');
	expect($body)->toContain('json_encode($output)');
	expect($body)->toContain("print 'U'");
});
