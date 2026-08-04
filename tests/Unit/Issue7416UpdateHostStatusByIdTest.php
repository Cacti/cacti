<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * update_host_status() looks the host up by id ($host_id) but the closing
 * UPDATE previously matched WHERE hostname = ? using $host['hostname'].
 * Hostnames are not unique in Cacti (multiple devices commonly share one),
 * so the write could silently update every host sharing that hostname
 * instead of just the polled one. The fix scopes the UPDATE to the same
 * id the row was read with. Structural check: the WHERE clause and its
 * bound value must both key off id, not hostname.
 */

$source = file_get_contents(__DIR__ . '/../../lib/functions.php');

test('update_host_status() UPDATE is scoped by WHERE id = ?', function () use ($source) {
	$pos = strpos($source, "db_execute_prepared('UPDATE host SET");
	expect($pos)->not->toBeFalse();

	$fragment = substr($source, $pos, 700);

	expect($fragment)->toContain('WHERE id = ?');
	expect($fragment)->not->toContain('WHERE hostname = ?');
});

test('update_host_status() binds $host[\'id\'] as the WHERE parameter, not $host[\'hostname\']', function () use ($source) {
	$pos = strpos($source, "db_execute_prepared('UPDATE host SET");
	expect($pos)->not->toBeFalse();

	$fragment = substr($source, $pos, 900);

	expect($fragment)->toContain("\$host['id']\n\t\t)");
	expect($fragment)->not->toContain("\$host['hostname']");
});
