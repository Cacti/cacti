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
 * utilities_get_mysql_recommendations() picks its tuning advice from the
 * engine, the server version and innodb_version. These tests run the real
 * function against the server variables each supported engine reports, and
 * read back the rows and the status it hands the installer.
 */

require_once dirname(__DIR__, 3) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 4) . '/include/global.php';
require_once dirname(__DIR__, 4) . '/lib/utility.php';
require_once dirname(__DIR__, 3) . '/Helpers/FakeMySQLPDO.php';

/**
 * Answers SHOW GLOBAL VARIABLES from a seeded table, the one MySQL statement
 * get_mysql_info() issues that sqlite cannot run.
 */
class MysqlRecommendationsPDO extends FakeMySQLPDO {
	public function prepare(string $query, array $options = []) : PDOStatement|false {
		if (preg_match('/^\s*SHOW\s+GLOBAL\s+VARIABLES\s*$/i', $query)) {
			$query = 'SELECT Variable_name, Value FROM global_variables ORDER BY Variable_name';
		}

		return parent::prepare(preg_replace('/\bSQL_NO_CACHE\b/', '', $query), $options);
	}
}

/**
 * Runs the recommendations against one server's variables.
 *
 * @param array<string, string> $variables The SHOW GLOBAL VARIABLES rows
 *
 * @return array{0: int, 1: string} The status and the rendered rows
 */
function mysql_recommendations_for(array $variables) : array {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$conn = new MysqlRecommendationsPDO();
	$conn->exec('CREATE TABLE global_variables (Variable_name TEXT PRIMARY KEY, Value TEXT)');
	$insert = $conn->prepare('INSERT INTO global_variables (Variable_name, Value) VALUES (?, ?)');

	foreach ($variables as $name => $value) {
		$insert->execute([$name, $value]);
	}

	$session = "$database_hostname:$database_port:$database_default";
	$prior   = $database_sessions[$session] ?? null;

	$database_sessions[$session] = $conn;

	try {
		ob_start();
		$status = utilities_get_mysql_recommendations();
		$html   = ob_get_clean();
	} finally {
		if ($prior === null) {
			unset($database_sessions[$session]);
		} else {
			$database_sessions[$session] = $prior;
		}
	}

	return [$status, $html];
}

/**
 * Returns the rendered row for one variable, or an empty string.
 */
function mysql_recommendation_row(string $html, string $name) : string {
	return preg_match('#<td>' . preg_quote($name, '#') . '</td>.*?</tr>#s', $html, $match) ? $match[0] : '';
}

test('a MySQL 5.5 server gets the pre-5.6 InnoDB advice and loses removed variables', function () {
	[$status, $html] = mysql_recommendations_for([
		'version'                        => '5.5.62',
		'innodb_version'                 => '5.5.62',
		'collation_server'               => 'latin1_swedish_ci',
		'character_set_server'           => 'utf8mb4',
		'character_set_client'           => 'utf8mb4',
		'innodb_flush_log_at_trx_commit' => '2',
		'innodb_file_io_threads'         => '16',
	]);

	$collation = mysql_recommendation_row($html, 'collation_server');
	$flush     = mysql_recommendation_row($html, 'innodb_flush_log_at_trx_commit');

	expect($status)->toBe(DB_STATUS_WARNING)
		->and($html)->toContain('MySQL Tuning')
		->and($collation)->toContain('utf8mb4_unicode_ci collation type')
		->and($collation)->toContain('textWarning')
		->and(mysql_recommendation_row($html, 'character_set_server'))->toContain("class='right '")
		->and(mysql_recommendation_row($html, 'character_set_client'))->toContain('utf8mb4 character set')
		->and($flush)->toContain('flush all transactions every second rather than at commit.  This allows MySQL to perform')
		->and($flush)->not->toContain('textError')
		->and(mysql_recommendation_row($html, 'innodb_file_io_threads'))->toBe('');
});

test('a failed error class recommendation outranks a warning', function () {
	[$status, $html] = mysql_recommendations_for([
		'version'               => '5.5.62',
		'innodb_version'        => '5.5.62',
		'collation_server'      => 'latin1_swedish_ci',
		'innodb_file_per_table' => 'OFF',
	]);

	expect($status)->toBe(DB_STATUS_ERROR)
		->and(mysql_recommendation_row($html, 'innodb_file_per_table'))->toContain('textError');
});

test('a MySQL 8.0 server gets the IO thread and buffer pool instance advice', function () {
	[$status, $html] = mysql_recommendations_for([
		'version'                      => '8.0.40',
		'innodb_version'               => '8.0.40',
		'collation_server'             => 'utf8mb4_unicode_ci',
		'innodb_flush_log_at_timeout'  => '3',
		'innodb_read_io_threads'       => '64',
		'innodb_write_io_threads'      => '16',
		'innodb_buffer_pool_instances' => '64',
		'innodb_io_capacity'           => '5000',
		'innodb_io_capacity_max'       => '20000',
		'innodb_flush_neighbors'       => '0',
	]);

	// every tuned value passes, so only the release below 8.4 warns
	expect($status)->toBe(DB_STATUS_WARNING)
		->and(mysql_recommendation_row($html, 'version'))->toContain('textWarning')
		->and(mysql_recommendation_row($html, 'innodb_read_io_threads'))->not->toContain('textWarning')
		->and($html)->toContain('dev.mysql.com/doc/refman/8.0/en/server-system-variables.html')
		->and(mysql_recommendation_row($html, 'innodb_flush_log_at_timeout'))->toContain('As of MySQL 8.0.40, the you can control how often MySQL flushes')
		->and(mysql_recommendation_row($html, 'innodb_read_io_threads'))->toContain('Depending on your MariaDB/MySQL versions, this value can go as high as 64')
		->and(mysql_recommendation_row($html, 'innodb_write_io_threads'))->toContain('multiple write io threads')
		->and(mysql_recommendation_row($html, 'innodb_buffer_pool_instances'))->toContain('versions of MySQL upto and including MySQL 8.0')
		->and(mysql_recommendation_row($html, 'innodb_io_capacity'))->toContain('200 * the number of active drives')
		->and(mysql_recommendation_row($html, 'innodb_io_capacity_max'))->toContain('2000 * the number of active drives')
		// deprecated from MySQL 8.0.20, so the advice is withheld
		->and(mysql_recommendation_row($html, 'innodb_flush_neighbors'))->toBe('');
});

test('a MariaDB 10.4 server gets the pre-10.5 pool advice and atomic writes', function () {
	[$status, $html] = mysql_recommendations_for([
		'version'                      => '10.4.34-MariaDB',
		'innodb_version'               => '10.4.34',
		'collation_server'             => 'utf8mb4_unicode_ci',
		'innodb_buffer_pool_instances' => '1',
		'innodb_doublewrite'           => 'ON',
		'innodb_use_atomic_writes'     => 'ON',
	]);

	$doublewrite = mysql_recommendation_row($html, 'innodb_doublewrite');

	expect($status)->toBe(DB_STATUS_ERROR)
		->and($html)->toContain('MariaDB Tuning')
		->and($html)->toContain('https://mariadb.com/kb/en/server-system-variables/')
		->and(mysql_recommendation_row($html, 'innodb_buffer_pool_instances'))->toContain('versions of MariaDB less than 10.5')
		->and($doublewrite)->toContain('this setting should be off if atomic writes are enabled')
		->and($doublewrite)->toContain('textError')
		->and(mysql_recommendation_row($html, 'innodb_use_atomic_writes'))->toContain('atomic writes over the doublewrite buffer');
});

test('a MariaDB 10.6 server drops the obsolete pool advice', function () {
	[$status, $html] = mysql_recommendations_for([
		'version'                         => '10.6.21-MariaDB',
		'innodb_version'                  => '10.6.21',
		'collation_server'                => 'utf8mb4_unicode_ci',
		'innodb_flush_log_at_timeout'     => '1',
		'innodb_read_io_threads'          => '32',
		'innodb_write_io_threads'         => '16',
		'innodb_io_capacity'              => '5000',
		'innodb_io_capacity_max'          => '10000',
		'innodb_flush_neighbors'          => '0',
		'innodb_additional_mem_pool_size' => '8388608',
		'innodb_buffer_pool_instances'    => '8',
	]);

	expect($status)->toBe(DB_STATUS_ERROR)
		->and(mysql_recommendation_row($html, 'innodb_flush_log_at_timeout'))->toContain('As of MariaDB 10.6.21')
		->and(mysql_recommendation_row($html, 'innodb_read_io_threads'))->toContain('multiple read io threads')
		->and(mysql_recommendation_row($html, 'innodb_read_io_threads'))->not->toContain('can go as high as 64')
		->and(mysql_recommendation_row($html, 'innodb_write_io_threads'))->toContain('multiple write io threads')
		->and(mysql_recommendation_row($html, 'innodb_io_capacity'))->toContain('100000')
		->and(mysql_recommendation_row($html, 'innodb_io_capacity_max'))->toContain('200000')
		->and(mysql_recommendation_row($html, 'innodb_flush_neighbors'))->toContain('set this to 0')
		->and(mysql_recommendation_row($html, 'innodb_additional_mem_pool_size'))->toBe('')
		->and(mysql_recommendation_row($html, 'innodb_buffer_pool_instances'))->toBe('');
});

test('a MariaDB 11.8 server is told to turn snapshot isolation off', function () {
	[$status, $html] = mysql_recommendations_for([
		'version'                   => '11.8.2-MariaDB',
		'collation_server'          => 'utf8mb4_unicode_ci',
		'innodb_snapshot_isolation' => 'ON',
	]);

	$isolation = mysql_recommendation_row($html, 'innodb_snapshot_isolation');

	expect($status)->toBe(DB_STATUS_ERROR)
		->and($isolation)->toContain('Set innodb_snapshot_isolation=OFF in your [mysqld] configuration. See MDEV-39628.')
		->and($isolation)->toContain('textError');
});
