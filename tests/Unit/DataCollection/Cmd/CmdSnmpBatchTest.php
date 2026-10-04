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
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Source-level regression coverage for cmd.php's batched SNMP collection path.
 * cmd.php runs the poller on include, so (like the other Cmd tests) these
 * assertions scan the source to pin the behaviours reviewed on PR #8166:
 * per-session-identity grouping, preservation of every poller row, requested
 * (incl. symbolic) OID remapping, and the SNMPv1 per-OID retry fallback.
 */

$source = file_get_contents(__DIR__ . '/../../../../cmd.php');

test('cmd.php parses and defines the batched SNMP collection helpers', function () use ($source) {
	expect($source)->toContain('function cmd_snmp_session_key($host_id, $item)')
		->and($source)->toContain('function cmd_snmp_collect_batch(')
		->and($source)->toContain('function cmd_snmp_collect_group(');
});

test('the session identity key hashes a structured, complete field set', function () use ($source) {
	/* collision-safe structured encoding, not a delimiter-joined subset */
	expect($source)->toContain('sha1(serialize(array(')
		->and($source)->not->toContain("sha1(implode('|'");

	/* including the per-item-overridable hostname and timeout */
	expect($source)->toContain("'hostname'             => \$item['hostname']")
		->and($source)->toContain("'snmp_timeout'         => \$item['snmp_timeout']")
		->and($source)->toContain("'snmp_engine_id'       => \$item['snmp_engine_id']");
});

test('the batch is partitioned by session identity and keeps every poller row', function () use ($source) {
	/* every (local_data_id, rrd_name) row is appended, not keyed by data source */
	expect($source)->toContain('$snmp_batch[] = $item;')
		->and($source)->not->toContain('$snmp_batch[$ds] = $item;');

	/* partition the batch by the full session identity before collecting */
	expect($source)->toContain('$groups[cmd_snmp_session_key($item[\'host_id\'], $item)][] = $item;');
});

test('batched results are remapped by the requested OID as well as the numeric key', function () use ($source) {
	$groupStart = strpos($source, 'function cmd_snmp_collect_group(');
	expect($groupStart)->not->toBeFalse();

	/* numeric key the session returns ... */
	$numeric = strpos($source, "\$byoid[ltrim((string) \$roid, '.')] = \$value;", $groupStart);
	expect($numeric)->not->toBeFalse('results must be mapped by the returned numeric OID');

	/* ... plus a positional association back to the exact requested OID string */
	$positional = strpos($source, '$values = array_values($results);', $groupStart);
	expect($positional)->not->toBeFalse('results must also be associated positionally with the requested OID');
	expect(strpos($source, 'foreach ($requested as $i => $roid) {', $groupStart))->not->toBeFalse();
});

test('SNMPv1 retries unresolved OIDs individually', function () use ($source) {
	$groupStart = strpos($source, 'function cmd_snmp_collect_group(');
	expect($groupStart)->not->toBeFalse();

	$v1Guard = strpos($source, "if (\$first['snmp_version'] == 1) {", $groupStart);
	expect($v1Guard)->not->toBeFalse('a v1-specific per-OID retry branch must exist');

	$retry = strpos($source, 'cacti_snmp_session_get($session, $oid, true);', $v1Guard);
	expect($retry)->not->toBeFalse('the v1 branch must retry each unresolved OID individually');
});
