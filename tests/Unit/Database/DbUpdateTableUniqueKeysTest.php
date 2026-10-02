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
 * develop reworked the schema API to express a unique index as a keys[] entry
 * with 'unique' => true. This regression coverage pins the restored backward
 * compatibility for the legacy unique_keys[] definition style: db_table_create()
 * must emit the UNIQUE index on a fresh install, and db_update_table() must add
 * it when missing and preserve it (never DROP it as "undeclared") on a refresh.
 */

require_once dirname(__DIR__, 2) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 2) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 3) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/lib/database.php';

class DbUniqueKeysRecordingPDO extends FakeMySQLPDO {
	public array $ddl = [];

	public function prepare(string $query, array $options = []): PDOStatement|false {
		$trim = ltrim($query);

		if (str_starts_with($trim, 'ALTER TABLE') || str_starts_with($trim, 'CREATE TABLE')) {
			$this->ddl[] = $query;

			return parent::prepare('SELECT 1', $options);
		}

		if (str_contains($query, 'information_schema.TABLES')) {
			return parent::prepare("SELECT 'InnoDB' AS ENGINE, '' AS TABLE_COMMENT", $options);
		}

		return parent::prepare($query, $options);
	}
}

function unique_keys_definition(): array {
	return [
		'columns' => [
			['name' => 'id', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'auto_increment' => true],
			['name' => 'user_id', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false],
			['name' => 'type', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false],
		],
		'primary'     => 'id',
		'unique_keys' => [
			['name' => 'user_id_type', 'columns' => ['user_id', 'type']],
		],
		'type' => 'InnoDB',
	];
}

test('db_table_create emits a UNIQUE index for a legacy unique_keys definition', function () {
	$connection = new DbUniqueKeysRecordingPDO();

	expect(db_table_create('plugin_webseer_contacts', unique_keys_definition(), false, $connection))->toBeTrue();

	$creates = array_values(array_filter($connection->ddl, static fn ($sql) => str_starts_with(ltrim($sql), 'CREATE TABLE')));

	expect($creates)->toHaveCount(1)
		->and($creates[0])->toContain('UNIQUE INDEX `user_id_type`')
		->and($creates[0])->toContain('(`user_id`,`type`)');
});

test('db_update_table adds a missing unique index from a legacy unique_keys definition', function () {
	$connection = new DbUniqueKeysRecordingPDO();
	$connection->exec('CREATE TABLE plugin_webseer_contacts (id INTEGER NOT NULL, user_id INTEGER NOT NULL, type INTEGER NOT NULL)');

	expect(db_update_table('plugin_webseer_contacts', unique_keys_definition(), false, false, $connection))->toBeTrue();

	$alters = implode("\n", array_filter($connection->ddl, static fn ($sql) => str_starts_with(ltrim($sql), 'ALTER TABLE')));

	expect($alters)->toContain('ADD UNIQUE INDEX `user_id_type`')
		->and($alters)->not->toContain('DROP INDEX `user_id_type`');
});

test('db_update_table preserves an existing unique index instead of dropping it', function () {
	$connection = new DbUniqueKeysRecordingPDO();
	$connection->exec('CREATE TABLE plugin_webseer_contacts (id INTEGER NOT NULL, user_id INTEGER NOT NULL, type INTEGER NOT NULL)');
	$connection->exec('CREATE UNIQUE INDEX user_id_type ON plugin_webseer_contacts (user_id, type)');

	expect(db_update_table('plugin_webseer_contacts', unique_keys_definition(), false, false, $connection))->toBeTrue();

	$alters = implode("\n", array_filter($connection->ddl, static fn ($sql) => str_starts_with(ltrim($sql), 'ALTER TABLE')));

	// the declared unique index matches the live schema, so it is neither
	// dropped as "undeclared" nor recreated
	expect($alters)->not->toContain('DROP INDEX `user_id_type`')
		->and($alters)->not->toContain('ADD UNIQUE INDEX `user_id_type`');
});
