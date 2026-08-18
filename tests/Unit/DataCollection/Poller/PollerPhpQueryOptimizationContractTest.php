<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Contract tests for poller.php's query-optimization fixes:
 *
 * #7529 -- three unscoped global aggregate scans (total_snmp_ports and the
 * mutually-exclusive active_profiles pair) ran on every poller, including
 * remote pollers, redundantly recomputing the same central-DB-wide answer.
 * They must now be gated behind poller_id == 1, with remote pollers reading
 * back the config option the central poller already stored.
 *
 * #7530 -- the process-leveling $sql_where used a non-sargable predicate
 * (`rrd_next_step - $poller_interval <= 0`), preventing use of the
 * poller_id_rrd_next_step index. The fix rewrites it as the algebraically
 * equivalent, sargable `rrd_next_step <= $poller_interval`.
 */

$source = file_get_contents(CACTI_PATH_BASE . '/poller.php');

test('the total_snmp_ports scan only runs on the central poller', function () use ($source) {
	$pos = strpos($source, "COUNT(DISTINCT snmp_port)");
	expect($pos)->not->toBeFalse();

	// walk backwards to the nearest enclosing "if ($poller_id == 1) {"
	$before = substr($source, 0, $pos);
	$ifPos  = strrpos($before, 'if ($poller_id == 1) {');

	expect($ifPos)->not->toBeFalse('total_snmp_ports scan must be inside an if ($poller_id == 1) block');

	// and there must be no closing brace of that if-block between it and the scan
	$between = substr($source, $ifPos, $pos - $ifPos);
	expect(substr_count($between, "\n}"))->toBe(0);
});

test('the active_profiles determination only runs on the central poller, with a remote fallback to read_config_option', function () use ($source) {
	$pos = strpos($source, 'COUNT(DISTINCT data_source_profile_id)');
	expect($pos)->not->toBeFalse();

	$before = substr($source, 0, $pos);
	$ifPos  = strrpos($before, 'if ($poller_id == 1) {');
	expect($ifPos)->not->toBeFalse('active_profiles determination must be inside an if ($poller_id == 1) block');

	$block = substr($source, $ifPos, strpos($source, "set_config_option('active_profiles'", $pos) - $ifPos + 40);
	expect($block)->toContain("db_fetch_cell('SELECT COUNT(DISTINCT rrd_next_step) FROM poller_item')");

	// the else branch (remote pollers) must read the value back, not recompute it
	$elsePos = strpos($source, "} else {\n\t// remote pollers read the value the central poller already computed and stored\n\t\$active_profiles = read_config_option('active_profiles');");
	expect($elsePos)->not->toBeFalse('remote pollers must fall back to read_config_option(\'active_profiles\') instead of recomputing it');
});

test('the process-leveling predicate is sargable (no arithmetic on the indexed column)', function () use ($source) {
	expect($source)->toContain("WHERE rrd_next_step <= \$poller_interval AND h.disabled = '' AND pi.poller_id = \$poller_id")
		->and($source)->not->toContain('rrd_next_step - $poller_interval <= 0');
});
