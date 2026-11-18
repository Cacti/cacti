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

require_once dirname(__DIR__, 3) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 4) . '/include/global.php';
require_once dirname(__DIR__, 4) . '/lib/auth.php';

/*
 * Trees are modified by their owner or by a Users/Groups administrator
 * (realm 1). cacti_authorize_resource() carries that rule, and tree.php applies
 * it to the editor, the save handler, lock/unlock and the bulk actions, which
 * previously let any user holding the Trees realm change any tree.
 */

beforeEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$this->conn = new FakeMySQLPDO();
	$this->conn->exec('CREATE TABLE graph_tree (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL DEFAULT 0)');
	$this->conn->exec('CREATE TABLE user_auth_realm (realm_id INTEGER, user_id INTEGER)');
	$this->conn->exec('CREATE TABLE user_auth_group_realm (group_id INTEGER, realm_id INTEGER)');
	$this->conn->exec('CREATE TABLE user_auth_group_members (group_id INTEGER, user_id INTEGER)');
	$this->conn->exec('INSERT INTO graph_tree (id, user_id) VALUES (10, 5), (11, 6)');

	$this->previous = $database_sessions["$database_hostname:$database_port:$database_default"] ?? null;

	$database_sessions["$database_hostname:$database_port:$database_default"] = $this->conn;
});

afterEach(function () {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	if ($this->previous === null) {
		unset($database_sessions["$database_hostname:$database_port:$database_default"]);
	} else {
		$database_sessions["$database_hostname:$database_port:$database_default"] = $this->previous;
	}
});

test('an owner may modify their own tree and no one else may', function () {
	expect(cacti_authorize_resource(5, 10, 'graph_tree'))->toBeTrue()
		->and(cacti_authorize_resource(5, 11, 'graph_tree'))->toBeFalse()
		->and(cacti_authorize_resource(7, 10, 'graph_tree'))->toBeFalse();
});

test('a Users/Groups administrator may modify any tree, directly or through a group', function () {
	$this->conn->exec('INSERT INTO user_auth_realm (realm_id, user_id) VALUES (1, 8)');
	$this->conn->exec('INSERT INTO user_auth_group_realm (group_id, realm_id) VALUES (3, 1)');
	$this->conn->exec('INSERT INTO user_auth_group_members (group_id, user_id) VALUES (3, 9)');

	expect(cacti_authorize_resource(8, 10, 'graph_tree'))->toBeTrue()
		->and(cacti_authorize_resource(9, 11, 'graph_tree'))->toBeTrue();
});

test('the Trees realm alone does not grant ownership', function () {
	$this->conn->exec('INSERT INTO user_auth_realm (realm_id, user_id) VALUES (4, 12)');

	expect(cacti_authorize_resource(12, 10, 'graph_tree'))->toBeFalse();
});

test('missing trees, invalid ids and unknown resource types fail closed', function () {
	expect(cacti_authorize_resource(5, 999, 'graph_tree'))->toBeFalse()
		->and(cacti_authorize_resource(0, 10, 'graph_tree'))->toBeFalse()
		->and(cacti_authorize_resource(5, 0, 'graph_tree'))->toBeFalse()
		->and(cacti_authorize_resource(5, 10, 'unknown'))->toBeFalse();
});

test('tree.php gates the editor, save handler and bulk actions on ownership', function () {
	$src = file_get_contents(dirname(__DIR__, 4) . '/tree.php');

	$edit = substr($src, strpos($src, "case 'edit':"), 600);

	expect($edit)->toContain("cacti_authorize_resource((int) \$_SESSION[SESS_USER_ID], (int) grv('id'), 'graph_tree')")
		->and(strpos($edit, 'cacti_authorize_resource'))->toBeLessThan(strpos($edit, 'top_header()'));

	$save = substr($src, strpos($src, 'function form_save()'), 1200);

	expect($save)->toContain("cacti_authorize_resource((int) \$_SESSION[SESS_USER_ID], (int) grv('id'), 'graph_tree')");

	$actions = substr($src, strpos($src, 'function form_actions()'), 1800);

	expect($actions)->toContain('csrf_require_post();')
		->and($actions)->toContain("'graph_tree'")
		->and(strpos($actions, "'graph_tree'"))->toBeLessThan(strpos($actions, "gnrv('drp_action') == '1'"));

	foreach (['lock', 'unlock'] as $action) {
		$block = substr($src, strpos($src, "case '" . $action . "':"), 500);

		expect($block)->toContain("'graph_tree'");
	}
});

test('tree.php checks ownership before reordering trees or changing branch sorting', function () {
	$src = file_get_contents(dirname(__DIR__, 4) . '/tree.php');

	foreach (['tree_up', 'tree_down'] as $action) {
		$block = substr($src, strpos($src, "case '" . $action . "':"), 400);

		expect(strpos($block, "'graph_tree'"))->toBeLessThan(strpos($block, $action . '();'), $action);
	}

	$dnd = substr($src, strpos($src, 'function tree_dnd()'), 900);

	expect(strpos($dnd, "cacti_authorize_resource((int) \$_SESSION[SESS_USER_ID], (int) \$id, 'graph_tree')"))->not->toBeFalse()
		->and(strpos($dnd, "'graph_tree'"))->toBeLessThan(strpos($dnd, 'UPDATE graph_tree'));

	foreach (['set_host_sort_type', 'set_branch_sort_type'] as $function) {
		$start = strpos($src, 'function ' . $function . '()');
		$body  = substr($src, $start, strpos($src, "\nfunction ", $start + 1) - $start);

		expect($body)->toContain('SELECT graph_tree_id')
			->and(strpos($body, "'graph_tree'"))->toBeLessThan(strpos($body, 'UPDATE graph_tree_items'), $function);
	}
});

test('reports_remove_history compares the session user id and the history row\'s own report', function () {
	$src  = file_get_contents(dirname(__DIR__, 4) . '/lib/reports.php');
	$body = substr($src, strpos($src, 'function reports_remove_history('), 1600);

	expect($body)->toContain('FROM reports_log')
		->and($body)->toContain("\$history['source_id']")
		->and($body)->toContain('(int) $_SESSION[SESS_USER_ID]')
		->and($body)->not->toMatch('/user_id\'\]\s*==\s*SESS_USER_ID/')
		->and($body)->not->toContain('$report_id == 0');
});

test('oauth2.php requires a login and the Settings realm', function () {
	$root   = dirname(__DIR__, 4);
	$oauth2 = file_get_contents($root . '/oauth2.php');
	$arrays = file_get_contents($root . '/include/global_arrays.php');

	expect($oauth2)->toContain("require('./include/auth.php')")
		->and($oauth2)->not->toContain("require('./include/global.php')")
		->and($arrays)->toMatch("/'oauth2\.php'\s+=> 15/")
		->and($arrays)->not->toMatch("/'oauth2\.php'\s+=> -1/");
});
