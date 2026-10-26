<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

use Cacti\Gprint\DatabaseGprintPresetRepository;
use Cacti\Gprint\GprintPresetRepository;
use Cacti\Gprint\GprintPresetService;

require_once dirname(__DIR__, 4) . '/lib/gprint.php';
require_once dirname(__DIR__, 3) . '/Helpers/FakeMySQLPDO.php';

class GprintTestRepository implements GprintPresetRepository {
	public mixed $result   = 12;
	public array|bool $row = ['id' => 1, 'name' => 'Existing', 'gprint_text' => '%8.2lf %s'];
	public function __construct(public readonly ArrayObject $events) {
	}
	public function find(mixed $id): array|bool {
		$this->events[] = ['find', $id];

		return $this->row;
	}
	public function save(array $values): mixed {
		$this->events[] = ['save', $values];

		return $this->result;
	}
	public function delete(array $ids): void {
		$this->events[] = ['delete', $ids];
	}
}

beforeEach(function (): void {
	$this->events     = new ArrayObject();
	$this->repository = new GprintTestRepository($this->events);
	$events           = $this->events;
	$this->service    = new GprintPresetService($this->repository, static function (int $id) use ($events): void {
		$events[] = ['message', $id];
	});
	$this->savedGlobals = [];

	foreach (['database_sessions', 'database_hostname', 'database_port', 'database_default', 'config', '_SESSION', 'no_http_headers'] as $key) {
		$this->savedGlobals[$key] = [array_key_exists($key, $GLOBALS), $GLOBALS[$key] ?? null];
	}
});

afterEach(function (): void {
	foreach ($this->savedGlobals as $key => [$exists, $value]) {
		if ($exists) {
			$GLOBALS[$key] = $value;
		} else {
			unset($GLOBALS[$key]);
		}
	}
});

test('GPRINT saves preserve hashes, format strings, return values, and notification ordering', function (): void {
	$values = ['id' => 0, 'hash' => bin2hex(random_bytes(16)), 'name' => 'New', 'gprint_text' => '%8.2lf %s\\n'];
	expect($this->service->save($values))->toBe(12)
		->and($this->events->getArrayCopy())->toBe([['save', $values], ['message', 1]]);
	$this->events->exchangeArray([]);
	$values['id']             = 12;
	$this->repository->result = '12';
	expect($this->service->save($values))->toBe('12')
		->and($this->events->getArrayCopy())->toBe([['save', $values], ['message', 1]]);

	foreach ([false, 0] as $failure) {
		$this->events->exchangeArray([]);
		$this->repository->result = $failure;
		expect($this->service->save($values))->toBe($failure)
			->and($this->events->getArrayCopy())->toBe([['save', $values], ['message', 2]]);
	}
});

test('GPRINT lookups retain missing rows and deletion preserves the selected IDs', function (): void {
	expect($this->service->find('1'))->toBe($this->repository->row);
	$this->repository->row = false;
	expect($this->service->find(999))->toBeFalse();
	$this->repository->row = [];
	expect($this->service->find(998))->toBe([]);
	$this->service->delete([]);
	$this->service->delete([1, '2']);
	expect($this->events->getArrayCopy())->toBe([['find', '1'], ['find', 999], ['find', 998], ['delete', [1, '2']]]);
});

test('GPRINT database storage and the legacy factory round-trip values and preserve unselected presets', function (): void {
	$conn = new FakeMySQLPDO();
	$conn->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
	$conn->exec('CREATE TABLE graph_templates_gprint (id INTEGER PRIMARY KEY, hash TEXT, name TEXT, gprint_text TEXT, graphs INTEGER DEFAULT 0, templates INTEGER DEFAULT 0)');
	$hash = bin2hex(random_bytes(16));
	$conn->prepare('INSERT INTO graph_templates_gprint VALUES (1, ?, ?, ?, 3, 2), (2, ?, ?, ?, 0, 0), (3, ?, ?, ?, 0, 0)')
		->execute([$hash, 'Existing', '%8.2lf %s', bin2hex(random_bytes(16)), 'Delete', '%8.0lf', bin2hex(random_bytes(16)), 'Keep', '%8.1lf']);
	$GLOBALS['database_hostname']                                    = 'gprint_fixture';
	$GLOBALS['database_port']                                        = '0';
	$GLOBALS['database_default']                                     = 'gprint_fixture';
	$GLOBALS['database_sessions']['gprint_fixture:0:gprint_fixture'] = $conn;
	$GLOBALS['no_http_headers']                                      = true;
	$repo                                                            = new DatabaseGprintPresetRepository();
	expect($repo->find(999))->toBe([])->and($repo->find(1)['hash'])->toBe($hash);
	expect(gprint_preset_service()->save(['id' => 1, 'hash' => $hash, 'name' => 'Renamed', 'gprint_text' => '%8.4lf %s\\n', 'graphs' => 3, 'templates' => 2]))->not->toBeFalse();
	$row = gprint_preset_service()->find(1);
	expect($row['name'])->toBe('Renamed')->and($row['gprint_text'])->toBe('%8.4lf %s\\n')
		->and($row['hash'])->toBe($hash)->and((int) $row['graphs'])->toBe(3)->and((int) $row['templates'])->toBe(2);
	gprint_preset_service()->delete([2]);
	expect($repo->find(2))->toBe([])->and($repo->find(3)['name'])->toBe('Keep');
});
