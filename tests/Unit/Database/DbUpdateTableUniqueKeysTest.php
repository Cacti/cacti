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

/*
 * Plugins declare key and primary columns as strings, either a single column
 * ('site_id') or the backtick-joined form ('port_number`,`mac_address'), not as
 * arrays. A refresh of an existing table must accept both without throwing and
 * must not rebuild an index whose live columns already match.
 */
class DbStringKeysPDO extends DbUniqueKeysRecordingPDO {
	/** @var array<int, array{0: string, 1: int, 2: string, 3: int}> */
	public array $indexRows = [];

	public function prepare(string $query, array $options = []): PDOStatement|false {
		if (preg_match('/^SHOW\s+INDEX\s+FROM\b/i', ltrim($query))) {
			$selects = [];

			foreach ($this->indexRows as [$key, $seq, $column, $non_unique]) {
				$selects[] = "SELECT '$key' AS Key_name, $seq AS Seq_in_index, '$column' AS Column_name, $non_unique AS Non_unique";
			}

			return parent::prepare($selects ? implode(' UNION ALL ', $selects) : 'SELECT 1 WHERE 0', $options);
		}

		return parent::prepare($query, $options);
	}
}

function string_keys_definition(): array {
	return [
		'columns' => [
			['name' => 'id', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false],
			['name' => 'site_id', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false],
			['name' => 'port_number', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false],
			['name' => 'mac_address', 'type' => 'varchar(20)', 'NULL' => false],
		],
		'primary' => 'id',
		'keys'    => [
			['name' => 'site_id', 'columns' => 'site_id'],
			['name' => 'port_mac', 'columns' => 'port_number`,`mac_address'],
		],
		'type' => 'InnoDB',
	];
}

function string_keys_alters(DbStringKeysPDO $connection): string {
	return implode("\n", array_filter($connection->ddl, static fn ($sql) => str_starts_with(ltrim($sql), 'ALTER TABLE')));
}

test('db_update_table accepts string-form key columns without rebuilding a matching index', function () {
	$connection = new DbStringKeysPDO();
	$connection->exec('CREATE TABLE plugin_string_keys (id INTEGER NOT NULL, site_id INTEGER NOT NULL, port_number INTEGER NOT NULL, mac_address TEXT NOT NULL)');
	$connection->indexRows = [
		['PRIMARY', 1, 'id', 0],
		['site_id', 1, 'site_id', 1],
		['port_mac', 1, 'port_number', 1],
		['port_mac', 2, 'mac_address', 1],
	];

	expect(db_update_table('plugin_string_keys', string_keys_definition(), false, false, $connection))->toBeTrue();

	$alters = string_keys_alters($connection);

	expect($alters)->not->toContain('DROP INDEX')
		->and($alters)->not->toContain('ADD INDEX')
		->and($alters)->not->toContain('PRIMARY KEY');
});

test('db_update_table rebuilds a string-form key whose live columns differ', function () {
	$connection = new DbStringKeysPDO();
	$connection->exec('CREATE TABLE plugin_string_keys (id INTEGER NOT NULL, site_id INTEGER NOT NULL, port_number INTEGER NOT NULL, mac_address TEXT NOT NULL)');
	$connection->indexRows = [
		['PRIMARY', 1, 'id', 0],
		['site_id', 1, 'site_id', 1],
		['port_mac', 1, 'port_number', 1],
	];

	expect(db_update_table('plugin_string_keys', string_keys_definition(), false, false, $connection))->toBeTrue();

	$alters = string_keys_alters($connection);

	expect($alters)->toContain('DROP INDEX `port_mac`')
		->and($alters)->toContain('ADD INDEX `port_mac` (`port_number`,`mac_address`)')
		->and($alters)->not->toContain('INDEX `site_id`');
});

test('db_update_table compares a backtick-joined string primary against the live key', function () {
	$definition            = string_keys_definition();
	$definition['primary'] = 'site_id`,`port_number';

	$connection = new DbStringKeysPDO();
	$connection->exec('CREATE TABLE plugin_string_keys (id INTEGER NOT NULL, site_id INTEGER NOT NULL, port_number INTEGER NOT NULL, mac_address TEXT NOT NULL)');
	$connection->indexRows = [
		['PRIMARY', 1, 'site_id', 0],
		['PRIMARY', 2, 'port_number', 0],
		['site_id', 1, 'site_id', 1],
		['port_mac', 1, 'port_number', 1],
		['port_mac', 2, 'mac_address', 1],
	];

	expect(db_update_table('plugin_string_keys', $definition, false, false, $connection))->toBeTrue()
		->and(string_keys_alters($connection))->not->toContain('PRIMARY KEY');
});

test('db_index_columns_to_array normalizes array, scalar and backtick-joined columns', function () {
	expect(db_index_columns_to_array(['`user_id`', 'type ']))->toBe(['user_id', 'type'])
		->and(db_index_columns_to_array('user_id'))->toBe(['user_id'])
		->and(db_index_columns_to_array('`user_id`'))->toBe(['user_id'])
		->and(db_index_columns_to_array('port_number`,`mac_address'))->toBe(['port_number', 'mac_address'])
		->and(db_index_columns_to_array('`a`, `b`'))->toBe(['a', 'b']);
});
