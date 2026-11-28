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
	global $config, $database_sessions, $database_hostname, $database_port, $database_default;

	$this->previous_config      = $config;
	$config['cacti_db_version'] = '1.3.0';
	$this->conn                 = new FakeMySQLPDO();
	$this->conn->exec('CREATE TABLE graph_tree (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL DEFAULT 0)');
	$this->conn->exec('CREATE TABLE user_auth_realm (realm_id INTEGER, user_id INTEGER)');
	$this->conn->exec('CREATE TABLE user_auth_group (id INTEGER PRIMARY KEY, enabled TEXT)');
	$this->conn->exec("INSERT INTO user_auth_group VALUES (3, 'on')");
	$this->conn->exec('CREATE TABLE user_auth_group_realm (group_id INTEGER, realm_id INTEGER)');
	$this->conn->exec('CREATE TABLE user_auth_group_members (group_id INTEGER, user_id INTEGER)');
	$this->conn->exec('INSERT INTO graph_tree (id, user_id) VALUES (10, 5), (11, 6)');

	$this->previous = $database_sessions["$database_hostname:$database_port:$database_default"] ?? null;

	$database_sessions["$database_hostname:$database_port:$database_default"] = $this->conn;
});

afterEach(function () {
	global $config, $database_sessions, $database_hostname, $database_port, $database_default;

	$config = $this->previous_config;

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

test('tree changes check ownership before dispatch and storage', function () {
	$src = file_get_contents(dirname(__DIR__, 4) . '/tree.php');

	expect(strpos($src, 'tree_require_edit_permission($tree_id)'))->toBeLessThan(strpos($src, "switch (grv('action'))"));

	foreach (['set_host_sort_type', 'set_branch_sort_type'] as $handler) {
		$start = strpos($src, 'function ' . $handler . '()');
		$body  = substr($src, $start, strpos($src, "\nfunction ", $start + 1) - $start);
		expect(strpos($body, 'tree_require_item_edit_permission'))->toBeLessThan(strpos($body, 'UPDATE graph_tree_items'));
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
