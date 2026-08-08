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

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';

class DbUpdateTableRecordingPDO extends FakeMySQLPDO {
	public array $alterStatements = [];

	public function prepare(string $query, array $options = []): PDOStatement|false {
		if (str_starts_with(ltrim($query), 'ALTER TABLE')) {
			$this->alterStatements[] = $query;

			return parent::prepare('SELECT 1', $options);
		}

		if (str_contains($query, 'information_schema.TABLES')) {
			return parent::prepare("SELECT 'InnoDB' AS ENGINE, '' AS TABLE_COMMENT", $options);
		}

		return parent::prepare($query, $options);
	}
}

test('db_update_table batches schema changes and preserves timestamp expressions', function () {
	$connection = new DbUpdateTableRecordingPDO();
	$connection->exec('CREATE TABLE test_table (id INTEGER NOT NULL, changed TEXT, obsolete TEXT)');

	$result = db_update_table('test_table', [
		'collate' => 'utf8mb4_unicode_ci',
		'columns' => [
			['name' => 'id', 'type' => 'INTEGER', 'NULL' => false],
			['name' => 'changed', 'type' => 'datetime', 'NULL' => false, 'default' => 'CURRENT_TIMESTAMP(6)'],
			['name' => 'created_at', 'type' => 'timestamp', 'NULL' => false, 'default' => 'CURRENT_TIMESTAMP'],
		],
	], true, false, $connection);

	expect($result)->toBeTrue()
		->and($connection->alterStatements)->toHaveCount(1)
		->and($connection->alterStatements[0])->toContain('COLLATE = utf8mb4_unicode_ci')
		->and($connection->alterStatements[0])->not->toContain('DEFAULT COLLATE')
		->and($connection->alterStatements[0])->toContain('CHANGE `changed` `changed` datetime NOT NULL default CURRENT_TIMESTAMP(6)')
		->and($connection->alterStatements[0])->toContain('ADD `created_at` timestamp NOT NULL default CURRENT_TIMESTAMP')
		->and($connection->alterStatements[0])->toContain('DROP COLUMN `obsolete`');
});
