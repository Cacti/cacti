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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 2) . '/lib/cdef.php';
require_once dirname(__DIR__, 2) . '/lib/aggregate.php';

function cdef_integration_seed() : FakeMySQLPDO {
	$conn = new FakeMySQLPDO();

	$conn->exec('CREATE TABLE cdef (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
	$conn->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
	$conn->exec('CREATE TABLE version (cacti TEXT)');
	$conn->exec("INSERT INTO settings (name, value) VALUES ('log_destination', '0'), ('selective_debug', ''), ('selective_plugin_debug', '')");
	$conn->exec("INSERT INTO version (cacti) VALUES ('1.3.0')");
	$conn->exec('CREATE TABLE cdef_items (
		id INTEGER PRIMARY KEY,
		cdef_id INTEGER NOT NULL,
		sequence INTEGER NOT NULL,
		type TEXT NOT NULL,
		value TEXT NOT NULL
	)');
	$conn->exec('CREATE TABLE graph_templates_item (id INTEGER PRIMARY KEY, local_graph_id INTEGER, sequence INTEGER, cdef_id INTEGER)');
	$conn->exec('CREATE TABLE aggregate_graph_templates_item (aggregate_template_id INTEGER, graph_templates_item_id INTEGER, cdef_id INTEGER)');
	$conn->exec('CREATE TABLE aggregate_graphs_graph_item (aggregate_graph_id INTEGER, graph_templates_item_id INTEGER, cdef_id INTEGER)');
	$conn->exec("INSERT INTO cdef (id, name) VALUES (1, 'Base Definition'), (2, 'Nested Definition'), (5, 'Empty Definition')");
	$conn->exec("INSERT INTO cdef_items (id, cdef_id, sequence, type, value) VALUES
		(1, 1, 3, '2', '3'),
		(2, 1, 1, '4', 'CURRENT_DATA_SOURCE'),
		(3, 1, 2, '6', '8'),
		(4, 2, 1, '5', '1'),
		(5, 2, 2, '6', '2'),
		(6, 3, 1, '1', '7'),
		(7, 3, 2, '99', 'ignored'),
		(8, 8, 1, '5', '8'),
		(9, 9, 1, '5', '10'),
		(10, 10, 1, '5', '9'),
		(11, 11, 1, '5', '999'),
		(12, 3, 3, '1', '999'),
		(13, 3, 4, '2', '999'),
		(14, 4, 1, '5', '1'),
		(15, 4, 2, '5', '1')");

	return $conn;
}

function cdef_integration_wire(PDO $conn) : void {
	global $database_sessions, $database_hostname, $database_port, $database_default;

	$database_hostname                                                        = 'cdef_test_host';
	$database_port                                                            = '0';
	$database_default                                                         = 'cdef_test_db';
	$database_sessions["$database_hostname:$database_port:$database_default"] = $conn;
}

function cdef_integration_seed_mysql(PDO $conn) : void {
	$conn->exec('DROP TEMPORARY TABLE IF EXISTS cdef_items');
	$conn->exec('DROP TEMPORARY TABLE IF EXISTS cdef');
	$conn->exec('CREATE TEMPORARY TABLE cdef (id INTEGER PRIMARY KEY, name VARCHAR(255) NOT NULL)');
	$conn->exec('CREATE TEMPORARY TABLE settings (name VARCHAR(255) PRIMARY KEY, value TEXT)');
	$conn->exec("INSERT INTO settings (name, value) VALUES ('log_destination', '0'), ('selective_debug', ''), ('selective_plugin_debug', '')");
	$conn->exec('CREATE TEMPORARY TABLE cdef_items (
		id INTEGER PRIMARY KEY,
		cdef_id INTEGER NOT NULL,
		sequence INTEGER NOT NULL,
		type VARCHAR(8) NOT NULL,
		value VARCHAR(150) NOT NULL
	)');
	$conn->exec('CREATE TEMPORARY TABLE graph_templates_item (id INTEGER PRIMARY KEY, local_graph_id INTEGER, sequence INTEGER, cdef_id INTEGER)');
	$conn->exec('CREATE TEMPORARY TABLE aggregate_graph_templates_item (aggregate_template_id INTEGER, graph_templates_item_id INTEGER, cdef_id INTEGER)');
	$conn->exec('CREATE TEMPORARY TABLE aggregate_graphs_graph_item (aggregate_graph_id INTEGER, graph_templates_item_id INTEGER, cdef_id INTEGER)');
	$conn->exec("INSERT INTO cdef (id, name) VALUES (1, 'Base Definition'), (2, 'Nested Definition')");
	$conn->exec("INSERT INTO cdef_items (id, cdef_id, sequence, type, value) VALUES
		(1, 1, 3, '2', '3'),
		(2, 1, 1, '4', 'CURRENT_DATA_SOURCE'),
		(3, 1, 2, '6', '8'),
		(4, 2, 1, '5', '1'),
		(5, 2, 2, '6', '2'),
		(6, 6, 1, '5', '6')");
}

beforeEach(function () : void {
	global $database_sessions, $database_hostname, $database_port, $database_default, $cdef_functions, $cdef_operators;

	$this->cdef_database_globals = [$database_sessions, $database_hostname, $database_port, $database_default];
	$this->cdef_array_globals    = [$cdef_functions, $cdef_operators];
	$this->cdef_conn             = cdef_integration_seed();

	cdef_integration_wire($this->cdef_conn);
	$cdef_functions = [7 => 'Maximum'];
	$cdef_operators = [3 => '*'];
});

afterEach(function () : void {
	global $database_sessions, $database_hostname, $database_port, $database_default, $cdef_functions, $cdef_operators;

	[$database_sessions, $database_hostname, $database_port, $database_default] = $this->cdef_database_globals;
	[$cdef_functions, $cdef_operators]                                          = $this->cdef_array_globals;
});

test('stored CDEF item types resolve through production database helpers', function () : void {
	expect(get_cdef_item_name(1))->toBe('*')
		->and(get_cdef_item_name(2))->toBe('CURRENT_DATA_SOURCE')
		->and(get_cdef_item_name(3))->toBe('8')
		->and(get_cdef_item_name(4))->toBe('Base Definition')
		->and(get_cdef_item_name(5))->toBe('2')
		->and(get_cdef_item_name(6))->toBe('Maximum')
		->and(get_cdef_item_name(7))->toBeNull()
		->and(get_cdef_item_name(12))->toBeNull()
		->and(get_cdef_item_name(13))->toBeNull()
		->and(get_cdef_item_name(999))->toBeNull()
		->and(get_cdef(3))->toBeNull();
});

test('stored CDEFs preserve sequence and recursively expand nested definitions', function () : void {
	expect(get_cdef(1))->toBe('CURRENT_DATA_SOURCE,8,*')
		->and(get_cdef(2))->toBe('CURRENT_DATA_SOURCE,8,*,2')
		->and(get_cdef(5))->toBe('')
		->and(get_cdef(8))->toBeNull()
		->and(get_cdef(9))->toBeNull()
		->and(get_cdef(11))->toBeNull()
		->and(get_cdef(4))->toBe('CURRENT_DATA_SOURCE,8,*,CURRENT_DATA_SOURCE,8,*')
		->and(get_cdef(999))->toBeNull();
});

test('CDEF dependencies block deletion and aggregate totalling reports invalid definitions', function () : void {
	$this->cdef_conn->exec('INSERT INTO graph_templates_item (id, local_graph_id, sequence, cdef_id) VALUES (1, 42, 1, 1), (2, 42, 2, 5)');

	expect(cdef_is_in_use(1, [1]))->toBeTrue()
		->and(cdef_is_in_use(2, [2]))->toBeFalse()
		->and(aggregate_cdef_totalling(42, 2, 0))->toBeFalse();

	$this->cdef_conn->exec('DELETE FROM graph_templates_item WHERE id = 1');
	$this->cdef_conn->exec("INSERT INTO cdef_items (id, cdef_id, sequence, type, value) VALUES (20, 2, 3, '5', '1')");

	expect(cdef_is_in_use(1, [1]))->toBeTrue()
		->and(cdef_is_in_use(1, [1, 2, 4]))->toBeFalse();
});

test('CDEF resolution executes against MariaDB with production table shapes', function () : void {
	if (getenv('CACTI_CDEF_REAL_DB') !== '1') {
		$this->markTestSkipped('set CACTI_CDEF_REAL_DB=1 with CACTI_TEST_DB_* to run the MariaDB proof');
	}

	$host = getenv('CACTI_TEST_DB_HOST') ?: '127.0.0.1';
	$port = getenv('CACTI_TEST_DB_PORT') ?: '3306';
	$name = getenv('CACTI_TEST_DB_NAME') ?: 'cacti_test';
	$user = getenv('CACTI_TEST_DB_USER') ?: 'cacti';
	$pass = getenv('CACTI_TEST_DB_PASS') ?: 'cacti';
	$conn = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, [
		PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	]);

	cdef_integration_seed_mysql($conn);
	cdef_integration_wire($conn);

	expect(get_cdef_item_name(4))->toBe('Base Definition')
		->and(get_cdef(1))->toBe('CURRENT_DATA_SOURCE,8,*')
		->and(get_cdef(2))->toBe('CURRENT_DATA_SOURCE,8,*,2')
		->and(get_cdef(6))->toBeNull();
});
