<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
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
 * The 1.3.0 upgrade moves users of the removed Carrot and Hollyberry themes to
 * Modern. The remap runs for real against an in-memory database so a wrong
 * column, name or parameter order fails here rather than during an upgrade.
 */

namespace Upgrade130RetiredThemesTest;

require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/Helpers/FakeMySQLPDO.php';
require_once CACTI_PATH_BASE . '/install/functions.php';
require_once CACTI_PATH_BASE . '/install/upgrades/1_3_0.php';

/**
 * Returns the connection key db_execute_prepared() looks up by default.
 *
 * @return string
 */
function upgrade_theme_connection_key() : string {
	global $database_default, $database_hostname, $database_port;

	return "$database_hostname:$database_port:$database_default";
}

beforeEach(function () {
	$this->conn = new \FakeMySQLPDO();
	$this->conn->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
	$this->conn->exec('CREATE TABLE settings_user (user_id INTEGER, name TEXT, value TEXT, PRIMARY KEY (user_id, name))');

	$this->previous = $GLOBALS['database_sessions'] ?? null;

	$GLOBALS['database_sessions'][upgrade_theme_connection_key()] = $this->conn;
});

afterEach(function () {
	if ($this->previous === null) {
		unset($GLOBALS['database_sessions']);
	} else {
		$GLOBALS['database_sessions'] = $this->previous;
	}
});

test('users of a removed theme move to modern and every other setting is untouched', function () {
	$this->conn->exec("INSERT INTO settings (name, value) VALUES ('selected_theme', 'hollyberry'), ('log_verbosity', 'carrot')");
	$this->conn->exec("INSERT INTO settings_user (user_id, name, value) VALUES
		(1, 'selected_theme', 'carrot'),
		(2, 'selected_theme', 'hollyberry'),
		(3, 'selected_theme', 'raspberry'),
		(4, 'selected_theme', 'midwinter'),
		(5, 'graph_theme', 'carrot')");

	ob_start();
	upgrade_retired_themes_1_3_0();
	ob_end_clean();

	$global = $this->conn->query("SELECT name, value FROM settings WHERE name IN ('selected_theme', 'log_verbosity') ORDER BY name")->fetchAll(\PDO::FETCH_KEY_PAIR);
	$users  = $this->conn->query('SELECT user_id, value FROM settings_user ORDER BY user_id')->fetchAll(\PDO::FETCH_KEY_PAIR);

	expect($global)->toBe(['log_verbosity' => 'carrot', 'selected_theme' => 'modern'])
		->and($users)->toBe([1 => 'modern', 2 => 'modern', 3 => 'raspberry', 4 => 'midwinter', 5 => 'carrot']);
});

test('the remap runs during the 1.3.0 upgrade after deprecated files are pruned', function () {
	$source = file_get_contents(CACTI_PATH_BASE . '/install/upgrades/1_3_0.php');

	$prune = strpos($source, "\tprune_deprecated_files();");
	$remap = strpos($source, "\tupgrade_retired_themes_1_3_0();");

	expect($prune)->not->toBeFalse()
		->and($remap)->not->toBeFalse()
		->and($remap)->toBeGreaterThan($prune);
});

test('the removed theme directories are pruned on upgrade', function () {
	$source = file_get_contents(CACTI_PATH_BASE . '/install/functions.php');

	expect($source)->toContain("\t\t'include/themes/carrot',\n")
		->toContain("\t\t'include/themes/hollyberry',\n");
});
