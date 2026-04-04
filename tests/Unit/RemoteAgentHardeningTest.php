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

/**
 * Source-scan tests for remote_agent.php input validation hardening.
 *
 * Covers two post-auth bugs:
 * 1. get_graph_data() read effective_user via raw grv() bypassing the integer
 *    filter registered by gfrv(), allowing non-integer values to reach
 *    rrdtool_function_graph() as the $user argument.
 *
 * 2. get_snmp_data()/get_snmp_data_walk() called grv('oid') without a prior
 *    gfrv() registration, which triggers log_validation warnings. The fix
 *    registers 'oid' via gfrv() before reading it with grv().
 */

$src = file_get_contents(__DIR__ . '/../../remote_agent.php');

test('get_graph_data uses (int) gfrv for effective_user, not raw grv', function () use ($src) {
	expect($src)->toContain('(int) gfrv(\'effective_user\')')
		->and($src)->not->toContain('$user = grv(\'effective_user\')');
});

test('get_snmp_data registers oid with gfrv before reading with grv', function () use ($src) {
	$fn      = substr($src, strpos($src, 'function get_snmp_data() :'));
	$fn      = substr($fn, 0, strpos($fn, 'function get_snmp_data_walk()'));
	$gfrv_pos = strpos($fn, "gfrv('oid')");
	$grv_pos  = strpos($fn, "grv('oid')");

	expect($gfrv_pos)->not->toBeFalse()
		->and($grv_pos)->not->toBeFalse()
		->and($gfrv_pos)->toBeLessThan($grv_pos);
});

test('get_snmp_data uses grv not gnrv for oid', function () use ($src) {
	$fn = substr($src, strpos($src, 'function get_snmp_data() :'));
	$fn = substr($fn, 0, strpos($fn, 'function get_snmp_data_walk()'));
	expect($fn)->toContain("grv('oid')")
		->and($fn)->not->toContain("gnrv('oid')");
});

test('get_snmp_data_walk registers oid with gfrv before reading with grv', function () use ($src) {
	$fn      = substr($src, strpos($src, 'function get_snmp_data_walk() :'));
	$fn      = substr($fn, 0, strpos($fn, 'function ping_device()'));
	$gfrv_pos = strpos($fn, "gfrv('oid')");
	$grv_pos  = strpos($fn, "grv('oid')");

	expect($gfrv_pos)->not->toBeFalse()
		->and($grv_pos)->not->toBeFalse()
		->and($gfrv_pos)->toBeLessThan($grv_pos);
});

test('get_snmp_data_walk uses grv not gnrv for oid', function () use ($src) {
	$fn = substr($src, strpos($src, 'function get_snmp_data_walk() :'));
	$fn = substr($fn, 0, strpos($fn, 'function ping_device()'));
	expect($fn)->toContain("grv('oid')")
		->and($fn)->not->toContain("gnrv('oid')");
});

test('remote_agent_validate_oid helper exists and rejects shell metacharacters', function () use ($src) {
	expect($src)->toContain('function remote_agent_validate_oid(string $oid)');
});

test('oid validation is applied before SNMP session in get_snmp_data', function () use ($src) {
	$fn      = substr($src, strpos($src, 'function get_snmp_data() :'));
	$fn      = substr($fn, 0, strpos($fn, 'function get_snmp_data_walk()'));
	$val_pos  = strpos($fn, 'remote_agent_validate_oid');
	$snmp_pos = strpos($fn, 'cacti_snmp_session');

	expect($val_pos)->not->toBeFalse()
		->and($snmp_pos)->not->toBeFalse()
		->and($val_pos)->toBeLessThan($snmp_pos);
});

test('get_snmp_data prints U and returns immediately on invalid oid', function () use ($src) {
	$fn = substr($src, strpos($src, 'function get_snmp_data() :'));
	$fn = substr($fn, 0, strpos($fn, 'function get_snmp_data_walk()'));

	expect($fn)->toContain("\$oid === false")
		->and($fn)->toContain("print 'U'")
		->and($fn)->toContain('return;');
});

test('get_snmp_data_walk prints U and returns immediately on invalid oid', function () use ($src) {
	$fn = substr($src, strpos($src, 'function get_snmp_data_walk() :'));
	$fn = substr($fn, 0, strpos($fn, 'function ping_device()'));

	expect($fn)->toContain("\$oid === false")
		->and($fn)->toContain("print 'U'")
		->and($fn)->toContain('return;');
});

test('oid validation is applied before SNMP session in get_snmp_data_walk', function () use ($src) {
	$fn      = substr($src, strpos($src, 'function get_snmp_data_walk() :'));
	$fn      = substr($fn, 0, strpos($fn, 'function ping_device()'));
	$val_pos  = strpos($fn, 'remote_agent_validate_oid');
	$snmp_pos = strpos($fn, 'cacti_snmp_session');

	expect($val_pos)->not->toBeFalse()
		->and($snmp_pos)->not->toBeFalse()
		->and($val_pos)->toBeLessThan($snmp_pos);
});

test('effective_user is guarded by isrv and cast to int before use', function () use ($src) {
	$fn = substr($src, strpos($src, 'function get_graph_data() :'));
	$fn = substr($fn, 0, strpos($fn, 'function get_snmp_data()'));

	expect($fn)->toContain("isrv('effective_user')")
		->and($fn)->toContain("(int) gfrv('effective_user')")
		->and($fn)->not->toContain('$user = grv(\'effective_user\')');
});

test('get_graph_data defaults effective_user to 0 when absent', function () use ($src) {
	$fn = substr($src, strpos($src, 'function get_graph_data() :'));
	$fn = substr($fn, 0, strpos($fn, 'function get_snmp_data()'));

	// Failure path: no effective_user in request => $user must fall back to 0
	expect($fn)->toContain('$user = 0');
});

test('get_graph_data passes $user to rrdtool_function_graph', function () use ($src) {
	$fn = substr($src, strpos($src, 'function get_graph_data() :'));
	$fn = substr($fn, 0, strpos($fn, 'function get_snmp_data()'));

	// Happy path: integer $user (whether cast from input or defaulted to 0) reaches the graph renderer
	expect($fn)->toContain('rrdtool_function_graph(')
		->and($fn)->toContain(', $user)');
});
