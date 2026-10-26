<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

use Cacti\Vdef\DatabaseVdefRepository;
use Cacti\Vdef\VdefRepository;
use Cacti\Vdef\VdefResolver;
use Symfony\Component\Process\Process;

require_once dirname(__DIR__, 4) . '/lib/vdef.php';
require_once dirname(__DIR__, 3) . '/Helpers/FakeMySQLPDO.php';

beforeEach(function () : void {
	global $database_sessions, $database_hostname, $database_port, $database_default, $vdef_functions;
	$this->vdef_globals = [$database_sessions, $database_hostname, $database_port, $database_default, $vdef_functions];
	$this->connection   = getenv('CACTI_VDEF_REAL_DB') === '1' ? new PDO(
		'mysql:host=' . (getenv('CACTI_TEST_DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('CACTI_TEST_DB_PORT') ?: '3306') . ';dbname=' . (getenv('CACTI_TEST_DB_NAME') ?: 'cacti_test'),
		getenv('CACTI_TEST_DB_USER') ?: 'cacti', getenv('CACTI_TEST_DB_PASS') ?: 'cacti',
		[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
	) : new FakeMySQLPDO();
	$this->connection->exec('CREATE TEMPORARY TABLE vdef_items (id INTEGER PRIMARY KEY, vdef_id INTEGER, sequence INTEGER, type INTEGER, value TEXT)');
	$this->connection->exec("INSERT INTO vdef_items VALUES
		(1,1,1,4,'CURRENT_DATA_SOURCE'),(2,1,2,6,'95'),(3,1,3,1,'8'),
		(4,2,1,5,'1'),(5,3,1,99,'ignored'),(6,4,1,6,''),(7,5,1,6,'0'),
		(8,6,1,99,'ignored'),(9,6,2,6,'2'),(10,7,1,1,'9999'),
		(11,8,1,5,'999'),(12,8,2,6,'2')");
	$database_hostname                                = 'vdef_fixture';
	$database_port                                    = '0';
	$database_default                                 = 'vdef_fixture';
	$database_sessions['vdef_fixture:0:vdef_fixture'] = $this->connection;
	$vdef_functions                                   = [1 => 'MAXIMUM', 8 => 'PERCENT'];
});

afterEach(function () : void {
	global $database_sessions, $database_hostname, $database_port, $database_default, $vdef_functions;
	[$database_sessions, $database_hostname, $database_port, $database_default, $vdef_functions] = $this->vdef_globals;
});

test('legacy VDEF item types and nested previews preserve ordering and display spacing', function () : void {
	expect(get_vdef_item_name(1))->toBe('CURRENT_DATA_SOURCE')
		->and(get_vdef_item_name(2))->toBe('95')
		->and(get_vdef_item_name(3))->toBe('PERCENT')
		->and(get_vdef_item_name(5))->toBe('')
		->and(get_vdef(1))->toBe('CURRENT_DATA_SOURCE,95,PERCENT')
		->and(get_vdef(vdef_id: 1, display: true))->toBe('CURRENT_DATA_SOURCE, 95, PERCENT')
		->and(get_vdef(2))->toBe('CURRENT_DATA_SOURCE,95,PERCENT')
		->and(get_vdef(2, true))->toBe('CURRENT_DATA_SOURCE, 95, PERCENT')
		->and(get_vdef(999))->toBe('')
		->and(get_vdef(3))->toBe('')
		->and(get_vdef(4))->toBe('')
		->and(get_vdef(5))->toBe('0')
		->and(get_vdef(6))->toBe(',2')
		->and(get_vdef(8))->toBe(',2');
});

test('a reused VDEF resolver reads item changes and live function dictionary changes', function () : void {
	$resolver = new VdefResolver(new DatabaseVdefRepository());
	expect($resolver->resolve(1))->toBe('CURRENT_DATA_SOURCE,95,PERCENT');
	$this->connection->exec("UPDATE vdef_items SET value = '97' WHERE id = 2");
	$GLOBALS['vdef_functions'][8] = 'PERCENTNAN';
	expect($resolver->resolve(1))->toBe('CURRENT_DATA_SOURCE,97,PERCENTNAN');
});

test('failed VDEF reads retain the empty result contract', function () : void {
	$repository = new class implements VdefRepository {
		public function items(int $vdef_id) : array|false {
			return false;
		}
		public function itemName(int $vdef_item_id) : string {
			throw new LogicException('A failed read must not resolve items');
		}
	};
	expect((new VdefResolver($repository))->resolve(1))->toBe('');
});

test('a missing VDEF item retains its warnings and empty result', function () : void {
	$warnings = [];
	set_error_handler(static function (int $level, string $message) use (&$warnings) : bool {
		$warnings[] = $message;

		return true;
	});

	try {
		expect(get_vdef_item_name(999))->toBe('');
	} finally {
		restore_error_handler();
	}
	expect($warnings)->toHaveCount(2);
});

test('an unknown VDEF function retains its legacy type error', function () : void {
	set_error_handler(static fn () : bool => true);

	try {
		expect(fn () => get_vdef_item_name(10))->toThrow(TypeError::class);
	} finally {
		restore_error_handler();
	}
});

test('VDEF form presets preserve their field contracts', function () : void {
	expect(array_keys(preset_vdef_form_list()))->toBe(['name', 'id', 'save_component_vdeef'])
		->and(preset_vdef_item_form_list())->toBe(['sequence' => 'sequence', 'type' => 'type', 'value' => 'value']);
});

test('legacy VDEF functions load before Composer is available', function () : void {
	$path    = var_export(dirname(__DIR__, 4) . '/lib/vdef.php', true);
	$process = new Process([PHP_BINARY, '-n', '-r', 'require ' . $path . ';
		new \Cacti\Vdef\VdefResolver(new \Cacti\Vdef\DatabaseVdefRepository());
		foreach (["get_vdef", "get_vdef_item_name", "preset_vdef_form_list", "preset_vdef_item_form_list"] as $function) {
			if (!function_exists($function)) { exit(1); }
		}
	']);
	$process->run();
	expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
});
