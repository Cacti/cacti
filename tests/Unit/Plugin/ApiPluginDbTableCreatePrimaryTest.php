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
*/

/*
 * api_plugin_db_table_create() builds the PRIMARY KEY clause through
 * db_format_index_create(), so a plugin may declare 'primary' as the legacy
 * scalar column name or as an array of one or more columns. This pins the
 * generated CREATE TABLE DDL for each form: a regression to raw string
 * interpolation of an array primary would re-emit `PRIMARY KEY (` + "`Array`" +
 * `)` and break a fresh install, which the helper tests alone would not catch.
 */

require_once dirname(__DIR__, 2) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 2) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 3) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/lib/database.php';
require_once dirname(__DIR__, 3) . '/lib/plugins.php';

class PluginTableCreateRecordingPDO extends FakeMySQLPDO {
	public array $ddl = [];

	public function prepare(string $query, array $options = []): PDOStatement|false {
		$trim = ltrim($query);

		if (str_starts_with($trim, 'CREATE TABLE')) {
			$this->ddl[] = $query;

			return parent::prepare('SELECT 1', $options);
		}

		if (str_starts_with($trim, 'SHOW TABLES')) {
			// no tables exist yet, so api_plugin_db_table_create() takes the create path
			return parent::prepare('SELECT name FROM sqlite_master WHERE 1 = 0', $options);
		}

		if (str_starts_with($trim, 'REPLACE INTO')) {
			// plugin_db_changes ownership bookkeeping; accept its two bound params
			return parent::prepare('SELECT ?, ?', $options);
		}

		return parent::prepare($query, $options);
	}
}

function plugin_create_table_ddl(mixed $primary): string {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$connection = new PluginTableCreateRecordingPDO();
	$database_sessions["$database_hostname:$database_port:$database_default"] = $connection;

	$data = [
		'columns' => [
			['name' => 'id', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'auto_increment' => true],
			['name' => 'other', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false],
		],
		'primary' => $primary,
		'type'    => 'InnoDB',
	];

	api_plugin_db_table_create('unittest', 'plugin_unittest_primary', $data);

	unset($database_sessions["$database_hostname:$database_port:$database_default"]);

	$creates = array_values(array_filter($connection->ddl, static fn ($sql) => str_starts_with(ltrim($sql), 'CREATE TABLE')));

	expect($creates)->toHaveCount(1);

	return $creates[0];
}

test('api_plugin_db_table_create emits a backticked PRIMARY KEY for the legacy scalar form', function () {
	expect(plugin_create_table_ddl('id'))->toContain('PRIMARY KEY (`id`)');
});

test('api_plugin_db_table_create emits the same PRIMARY KEY for a single-column array', function () {
	expect(plugin_create_table_ddl(['id']))->toContain('PRIMARY KEY (`id`)')
		->and(plugin_create_table_ddl(['id']))->not->toContain('`Array`');
});

test('api_plugin_db_table_create emits a composite PRIMARY KEY for a multi-column array', function () {
	expect(plugin_create_table_ddl(['id', 'other']))->toContain('PRIMARY KEY (`id`,`other`)')
		->and(plugin_create_table_ddl(['id', 'other']))->not->toContain('`Array`');
});
