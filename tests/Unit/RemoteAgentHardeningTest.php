<?php

/**
 * Source-scan tests for remote_agent.php input validation hardening.
 *
 * Covers two post-auth bugs:
 * 1. get_graph_data() read effective_user via raw grv() bypassing the integer
 *    filter registered by gfrv(), allowing non-integer values to reach
 *    rrdtool_function_graph() as the $user argument.
 *
 * 2. get_snmp_data()/get_snmp_data_walk() used gnrv('oid') which returns the
 *    raw, unfiltered request value. grv() applies any registered sanitizer
 *    before returning, so callers get a consistently sanitized value.
 */

$src = file_get_contents(__DIR__ . '/../../remote_agent.php');

test('get_graph_data uses (int) gfrv for effective_user, not raw grv', function () use ($src) {
	expect($src)->toContain('(int) gfrv(\'effective_user\')')
		->and($src)->not->toContain('$user = grv(\'effective_user\')');
});

test('get_snmp_data uses grv not gnrv for oid', function () use ($src) {
	// gnrv returns raw unfiltered value; grv applies any registered sanitizer
	$fn = substr($src, strpos($src, 'function get_snmp_data() :'));
	$fn = substr($fn, 0, strpos($fn, 'function get_snmp_data_walk()'));
	expect($fn)->toContain("grv('oid')")
		->and($fn)->not->toContain("gnrv('oid')");
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
	$val_pos = strpos($fn, 'remote_agent_validate_oid');
	$snmp_pos = strpos($fn, 'cacti_snmp_session');
	expect($val_pos)->not->toBeFalse()
		->and($snmp_pos)->not->toBeFalse()
		->and($val_pos)->toBeLessThan($snmp_pos);
});
