<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

use Cacti\Sites\DatabaseSiteRepository;
use Cacti\Sites\SiteRepository;
use Cacti\Sites\SiteService;

require_once dirname(__DIR__, 4) . '/lib/sites.php';
require_once dirname(__DIR__, 3) . '/Helpers/FakeMySQLPDO.php';

class SiteTestRepository implements SiteRepository {
	public array $rows    = [1 => ['id' => 1, 'name' => 'Main', 'disabled' => 'on', 'notes' => 'Keep notes']];
	public array $results = [];
	public function __construct(public readonly ArrayObject $events) {
	}
	public function find(mixed $id) : array|bool {
		$this->events[] = ['find', $id];

		return $this->rows[$id] ?? false;
	}
	public function save(array $values) : mixed {
		$this->events[] = ['save', $values];

		return $this->results ? array_shift($this->results) : 10;
	}
	public function setDisabled(mixed $id, bool $disabled) : void {
		$this->events[] = ['disabled', $id, $disabled];
	}
	public function delete(array $ids) : void {
		$this->events[] = ['delete', $ids];
	}
}

beforeEach(function () : void {
	$this->events     = new ArrayObject();
	$this->repository = new SiteTestRepository($this->events);
	$events           = $this->events;
	$this->service    = new SiteService($this->repository, static function () use ($events) : void {
		$events[] = ['changed'];
	}, static function (int $message) use ($events) : void {
		$events[] = ['message', $message];
	});
	$this->savedGlobals = [];

	foreach (['database_sessions', 'database_hostname', 'database_port', 'database_default', 'config', '_SESSION', 'no_http_headers'] as $key) {
		$this->savedGlobals[$key] = [array_key_exists($key, $GLOBALS), $GLOBALS[$key] ?? null];
	}
});

afterEach(function () : void {
	foreach ($this->savedGlobals as $key => [$exists, $value]) {
		if ($exists) {
			$GLOBALS[$key] = $value;
		} else {
			unset($GLOBALS[$key]);
		}
	}
});

test('site creation, update, and failure preserve notification and timestamp ordering', function () : void {
	$new = ['id' => 0, 'name' => 'New'];
	expect($this->service->save($new))->toBe(10)
		->and($this->events->getArrayCopy())->toBe([['save', $new], ['message', 1], ['changed']]);
	$this->events->exchangeArray([]);
	$existing = ['id' => 1, 'name' => 'Renamed'];
	expect($this->service->save($existing))->toBe(10)
		->and($this->events->getArrayCopy())->toBe([['save', $existing], ['message', 1]]);
	$this->events->exchangeArray([]);
	$this->repository->results = [false];
	expect($this->service->save($new))->toBeFalse()
		->and($this->events->getArrayCopy())->toBe([['save', $new], ['message', 2]]);
});

test('site toggles preserve IDs and touch timestamps once per nonempty operation', function () : void {
	$this->service->enable([]);
	$this->service->disable([]);
	expect($this->events->getArrayCopy())->toBe([]);
	$this->service->disable([1, '2']);
	$this->service->enable([1]);
	expect($this->events->getArrayCopy())->toBe([
		['disabled', 1, true], ['disabled', '2', true], ['changed'], ['disabled', 1, false], ['changed']
	]);
});

test('site duplication copies all fields and preserves missing-template and failure notifications', function () : void {
	$events  = $this->events;
	$missing = static function () use ($events) : void { $events[] = ['missing']; };
	$this->service->duplicate(1, '<site> Copy', $missing);
	expect($events->getArrayCopy())->toBe([
		['find', 1], ['save', ['id' => 0, 'name' => 'Main Copy', 'disabled' => 'on', 'notes' => 'Keep notes']],
		['message', 1], ['changed']
	]);
	$events->exchangeArray([]);
	$this->repository->results = [false];
	$this->service->duplicate([999, 1], 'Fixed name', $missing);
	expect($events->getArrayCopy())->toBe([
		['find', 999], ['missing'], ['find', 1],
		['save', ['id' => 0, 'name' => 'Fixed name', 'disabled' => 'on', 'notes' => 'Keep notes']],
		['message', 2], ['changed']
	]);
	$events->exchangeArray([]);
	$this->service->duplicate([], 'Nothing', $missing);
	expect($events->getArrayCopy())->toBe([['changed']]);
});

test('site deletion preserves empty selections and change notification order', function () : void {
	$this->service->delete([]);
	expect($this->events->getArrayCopy())->toBe([]);
	$this->service->delete([1, 2]);
	expect($this->events->getArrayCopy())->toBe([['delete', [1, 2]], ['changed']]);
});

test('production site storage and factory preserve settings and active-device associations', function () : void {
	$conn = new FakeMySQLPDO();
	$conn->exec("CREATE TABLE sites (id INTEGER PRIMARY KEY, name TEXT, disabled TEXT DEFAULT '', notes TEXT)");
	$conn->exec("CREATE TABLE host (id INTEGER PRIMARY KEY, site_id INTEGER, deleted TEXT DEFAULT '')");
	$conn->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
	$conn->exec("INSERT INTO sites VALUES (1,'Main','','Notes'),(2,'Other','','Other notes')");
	$conn->exec("INSERT INTO host VALUES (11,1,''),(12,1,'on'),(13,2,'')");
	$GLOBALS['database_hostname']                                  = 'sites_fixture';
	$GLOBALS['database_port']                                      = '0';
	$GLOBALS['database_default']                                   = 'sites_fixture';
	$GLOBALS['database_sessions']['sites_fixture:0:sites_fixture'] = $conn;
	$GLOBALS['no_http_headers']                                    = true;
	$repo                                                          = new DatabaseSiteRepository();
	expect($repo->find(1)['name'])->toBe('Main')->and($repo->find(999))->toBe([]);
	$repo->setDisabled(1, true);
	expect($repo->find(1)['disabled'])->toBe('on');
	$repo->setDisabled(1, false);
	expect($repo->find(1)['disabled'])->toBe('');
	expect(site_service()->save(['id' => 1, 'name' => 'Renamed', 'disabled' => '']))->not->toBeFalse();
	expect($repo->find(1)['name'])->toBe('Renamed');
	site_service()->disable([1]);
	expect($conn->query('SELECT COUNT(*) FROM settings WHERE name IN (\'time_last_change_site\',\'time_last_change_site_device\')')->fetchColumn())->toBe(2);
	$repo->delete([1]);
	expect($repo->find(1))->toBe([])
		->and((int) $conn->query('SELECT site_id FROM host WHERE id=11')->fetchColumn())->toBe(0)
		->and((int) $conn->query('SELECT site_id FROM host WHERE id=12')->fetchColumn())->toBe(1)
		->and((int) $conn->query('SELECT site_id FROM host WHERE id=13')->fetchColumn())->toBe(2);
});
