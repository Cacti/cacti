<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Guards cacti_snmp_session_from_host()'s mapping from a $host row to the
 * positional cacti_snmp_session() arguments. Source-scan (no DB): a drift in
 * the field order or a dropped default would silently change SNMPv3 sessions.
 */

function _snmp_source() {
	$src = file_get_contents(dirname(__DIR__, 4) . '/lib/snmp.php');
	expect($src)->not->toBeFalse('Failed to read lib/snmp.php');

	return $src;
}

function _from_host_body() {
	$src   = _snmp_source();
	$start = strpos($src, 'function cacti_snmp_session_from_host(');
	expect($start)->not->toBeFalse('cacti_snmp_session_from_host() must exist');

	$end = strpos($src, "\nfunction ", $start + 1);

	return substr($src, $start, ($end === false ? strlen($src) : $end) - $start);
}

test('maps the host columns in cacti_snmp_session parameter order', function () {
	$body = _from_host_body();

	$order = array(
		'hostname', 'snmp_community', 'snmp_version', 'snmp_username', 'snmp_password',
		'snmp_auth_protocol', 'snmp_priv_passphrase', 'snmp_priv_protocol',
		'snmp_context', 'snmp_engine_id', 'snmp_port', 'snmp_timeout',
		'ping_retries', 'max_oids',
	);

	$pos = -1;
	foreach ($order as $key) {
		$at = strpos($body, "\$host['$key']");
		expect($at)->not->toBeFalse("mapping must reference \$host['$key']");
		expect($at)->toBeGreaterThan($pos, "\$host['$key'] must appear after the previous column");
		$pos = $at;
	}
});

test('falls back to the cacti_snmp_session defaults for optional columns', function () {
	$body = _from_host_body();

	// The default numbers must match cacti_snmp_session()'s own defaults.
	expect($body)->toContain("?? 161");   // snmp_port
	expect($body)->toContain("?? 500");   // snmp_timeout
	expect($body)->toContain("?? 0");     // ping_retries
	expect($body)->toContain("?? 10");    // max_oids
});

test('delegates to cacti_snmp_session rather than reimplementing it', function () {
	$body = _from_host_body();
	expect($body)->toContain('return cacti_snmp_session(');
});
