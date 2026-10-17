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
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

use Cacti\Installer\CommandRunner;
use Cacti\Installer\CompletionWarnings;
use Cacti\Installer\DependencyPreflight;
use Cacti\Installer\SchemaRequirements;

beforeEach(function () {
	foreach (['CommandRunner', 'CompletionWarnings', 'DependencyPreflight', 'SchemaRequirements', 'CollectorSynchronizer', 'RunReport', 'UpgradePlan', 'AuthenticationRequirements'] as $component) {
		require_once dirname(__DIR__, 3) . '/lib/Installer/' . $component . '.php';
	}
});

test('commands preserve literal arguments, output streams, empty output and failure status', function () {
	$runner = new CommandRunner();
	$result = $runner->run([PHP_BINARY, '-r', 'echo $argv[1], "\r\n"; fwrite(STDERR, "error\r\n"); exit(7);', 'literal; $(unsafe)']);
	expect($result['exitCode'])->toBe(7)
		->and($result['output'])->toContain('literal; $(unsafe)', 'error')
		->and($runner->run([PHP_BINARY, '-r', 'exit(0);']))->toBe(['exitCode' => 0, 'output' => []])
		->and($runner->run([])['exitCode'])->toBe(127)
		->and($runner->run(['/nonexistent-cacti-command'])['exitCode'])->toBeIn([126, 127]);
});

test('commands bound output and terminate silent children at the deadline', function () {
	$result = (new CommandRunner(timeout: 0.1))->run([PHP_BINARY, '-r', 'sleep(20);']);
	expect($result['exitCode'])->toBe(124)->and($result['output'])->toContain('Installer command exceeded its time limit.');
	$result = (new CommandRunner(outputLimit: 10))->run([PHP_BINARY, '-r', 'echo str_repeat("x", 10000), "tail";']);
	expect($result['output'])->toBe(['xxxxxxtail']);
});

test('command limits reject invalid configuration', function (float $timeout, int $limit) {
	expect(fn () => new CommandRunner($timeout, $limit))->toThrow(InvalidArgumentException::class);
})->with([[0.0, 1], [1.0, 0]]);

test('completion warnings round trip safely and preserve every incomplete collector', function () {
	$report = (new CompletionWarnings())->withCollectors(['failed' => [2, 2], 'skipped' => [3], 'timeout' => [4], 'deferred' => [5]]);
	expect($report->hasWarnings())->toBeTrue()
		->and(CompletionWarnings::decode($report->encode()))->toEqual($report)
		->and((new CompletionWarnings())->hasWarnings())->toBeFalse()
		->and(CompletionWarnings::decode('invalid')->hasWarnings())->toBeFalse()
		->and(CompletionWarnings::decode('{"collectors":{"failed":[-1,"secret",2],"skipped":"invalid"}}')->collectors)->toBe(['failed' => [2]]);
});

test('schema requirements accept extra columns and detect absent tables and columns', function () {
	$schema = SchemaRequirements::fromSql("CREATE TABLE `one` (\n  `id` int,\n  `name` text\n) ENGINE=InnoDB;\nCREATE TABLE `two` (\n  `id` int\n) ENGINE=InnoDB;");
	expect($schema->missing([['TABLE_NAME' => 'one', 'COLUMN_NAME' => 'id'], ['TABLE_NAME' => 'one', 'COLUMN_NAME' => 'extra']]))->toBe(['one.name', 'two.id'])
		->and($schema->missing([['TABLE_NAME' => 'one', 'COLUMN_NAME' => 'id'], ['TABLE_NAME' => 'one', 'COLUMN_NAME' => 'name'], ['TABLE_NAME' => 'two', 'COLUMN_NAME' => 'id']]))->toBe([]);
	expect(fn () => SchemaRequirements::fromSql("CREATE TABLE `truncated` (\n  `id` int"))->toThrow(InvalidArgumentException::class);
	expect(fn () => SchemaRequirements::fromSql('invalid'))->toThrow(InvalidArgumentException::class);
	expect(fn () => SchemaRequirements::fromSql("CREATE TABLE `empty` (\n) ENGINE=InnoDB;"))->toThrow(InvalidArgumentException::class);
});

test('schema requirements track mixed-case and hyphenated columns but not index lines', function () {
	$schema = SchemaRequirements::fromSql("CREATE TABLE `mixed` (\n  `id` int,\n  snmp_sysName varchar(300) NOT NULL,\n  `sysDescr` varchar(255),\n  `max-access` int,\n  PRIMARY KEY (`id`),\n  KEY `name` (`snmp_sysName`)\n) ENGINE=InnoDB;");
	expect($schema->tables['mixed'])->toBe(['id', 'snmp_sysName', 'sysDescr', 'max-access'])
		->and($schema->missing([['TABLE_NAME' => 'mixed', 'COLUMN_NAME' => 'id'], ['TABLE_NAME' => 'mixed', 'COLUMN_NAME' => 'snmp_sysName'], ['TABLE_NAME' => 'mixed', 'COLUMN_NAME' => 'max-access']]))->toBe(['mixed.sysDescr']);
});

test('shipped schema reports a missing mixed-case column', function () {
	$schema = SchemaRequirements::fromSql(file_get_contents(dirname(__DIR__, 3) . '/cacti.sql'));
	expect($schema->tables['host'])->toContain('snmp_sysDescr')
		->and($schema->tables['host'])->not->toContain('PRIMARY')
		->and($schema->tables['host'])->not->toContain('KEY');
});

test('shipped schema parser accounts for every shipped table', function () {
	$sql = file_get_contents(dirname(__DIR__, 3) . '/cacti.sql');
	preg_match_all('/^CREATE TABLE /m', $sql, $tables);
	expect(SchemaRequirements::fromSql($sql)->tables)->toHaveCount(count($tables[0]));
});

beforeEach(function () {
	$this->preflightRoot = sys_get_temp_dir() . '/cacti-preflight-' . bin2hex(random_bytes(8));
	$files               = ['composer.json'   => json_encode(['config' => ['platform' => ['php' => '8.3.0']], 'require' => ['ext-json' => '*', 'php' => '^8.3']]),
		'composer.lock'                          => '{"packages":[{"name":"test/package","version":"1.0"}]}',
		'include/vendor/composer/installed.json' => '{"packages":[{"name":"test/package","version":"1.0"}]}',
		'include/vendor/autoload.php'            => '<?php return true;', 'include/vendor/composer/platform_check.php' => '<?php return true;'];

	foreach (['jquery', 'jquery-ui', 'htmx', 'purify', 'select2', 'jquery.tablesorter', 'd3', 'billboard', 'pace', 'big', 'lzjs', 'screenfull', 'jstree', 'jquery.tablesorter.widgets', 'jquery.tablesorter.pager'] as $asset) {
		$files['include/js/' . $asset . '.js'] = '// asset';
	}

	foreach (['jquery.validate.js', 'jquery.validate.min.js', 'additional-methods.js', 'additional-methods.min.js'] as $asset) {
		$files['include/js/jquery.validate/' . $asset] = '// asset';
	}
	$files['cacti.sql']          = 'CREATE TABLE fixture (id int);';
	$files['install/install.js'] = '// installer';
	$files['include/layout.js']  = '// layout';

	foreach (['install/install.css', 'include/css/jquery.contextMenu.css', 'include/css/billboard.css',
		'include/fa/css/all.css', 'include/tabler/dist/tabler-icons.min.css',
		'include/vendor/lipis/flag-icons/css/flag-icons.css', 'include/themes/print.css'] as $asset) {
		$files[$asset] = 'body {}';
	}

	foreach ($files as $name => $contents) {
		$path = $this->preflightRoot . '/' . $name;

		if (!is_dir(dirname($path))) {
			mkdir(dirname($path), 0700, true);
		}
		file_put_contents($path, $contents);
	}
});

afterEach(function () {
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->preflightRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

	foreach ($files as $file) {
		$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
	}
	rmdir($this->preflightRoot);
});

test('preflight accepts a complete offline installation', function () {
	expect((new DependencyPreflight($this->preflightRoot))->errors())->toBe([]);
});

test('preflight rejects incomplete or invalid metadata', function (string $file, ?string $contents) {
	$path = $this->preflightRoot . '/' . $file;
	$contents === null ? unlink($path) : file_put_contents($path, $contents);
	expect((new DependencyPreflight($this->preflightRoot))->errors()[0])->toContain('metadata is missing or invalid');
})->with([['composer.json', null], ['composer.lock', 'invalid'], ['composer.lock', '{}'], ['composer.json', '{}'], ['include/vendor/composer/installed.json', '{}'], ['include/vendor/composer/installed.json', 'null']]);

test('preflight reports runtime, extension, vendor and asset failures before loading code', function () {
	file_put_contents($this->preflightRoot . '/include/vendor/composer/installed.json', '{"packages":[]}');
	unlink($this->preflightRoot . '/include/js/jquery.js');
	file_put_contents($this->preflightRoot . '/include/js/htmx.js', '');
	unlink($this->preflightRoot . '/install/install.css');
	$errors = (new DependencyPreflight($this->preflightRoot, '8.2.0', []))->errors();
	expect(implode(' ', $errors))->toContain('supported PHP', 'extension json', 'test/package', 'jquery.js', 'htmx.js', 'install/install.css');
	expect((new DependencyPreflight($this->preflightRoot, '9.0.0', ['json']))->errors()[0])->toContain('supported PHP');
});

test('preflight captures platform and autoloader failures without exposing their output', function (string $file) {
	file_put_contents($this->preflightRoot . '/' . $file, '<?php echo "credential-secret"; throw new RuntimeException("credential-secret");');
	expect((new DependencyPreflight($this->preflightRoot))->errors())->toBe(['Composer platform or autoloader verification failed. Run composer check-platform-reqs --no-dev and cli/prepare_install.sh in this PHP environment.']);
})->with(['include/vendor/composer/platform_check.php', 'include/vendor/autoload.php']);

test('collector synchronization visits every collector once with explicit outcomes', function () {
	$visited    = [];
	$replicated = [];
	$pollers    = [];

	foreach ([[2, 1, 0], [3, 1, 0], [4, 9, 0], [5, 1, 100], [6, 1, null]] as [$id, $status, $gap]) {
		$pollers[] = compact('id', 'status', 'gap');
	}
	$result = (new \Cacti\Installer\CollectorSynchronizer([9]))->synchronize($pollers, 100, true,
		function (int $id) use (&$replicated) : bool {
			$replicated[] = $id;

			return $id === 2;
		},
		function (int $id) use (&$visited) : void { $visited[] = $id; });
	expect($visited)->toBe([2, 3, 4, 5, 6])->and($replicated)->toBe([2, 3])
		->and($result)->toBe(['success' => [2], 'failed' => [3], 'skipped' => [4], 'timeout' => [5, 6], 'deferred' => [], 'total' => 5]);
	$result = (new \Cacti\Installer\CollectorSynchronizer([]))->synchronize($pollers, 100, false,
		fn () => throw new RuntimeException('Must not replicate'), static function () : void {});
	expect($result['deferred'])->toBe([2, 3, 4, 5, 6]);
});

test('run reports preserve failed terminal state and reject arbitrary diagnostic data', function () {
	$report = new \Cacti\Installer\RunReport();
	$report->record('preflight', -1, true);
	$report->record('migration', 1.5, false);
	$report->finish(true, new CompletionWarnings());
	$data = json_decode($report->encode(), true);
	expect($data['state'])->toBe('failed')->and($data['operations'][0]['duration_seconds'])->toBe(0)
		->and($data['operations'][1]['duration_seconds'])->toBe(1.5)
		->and($data['run_id'])->toMatch('/^[a-f0-9]{32}$/');
	expect(fn () => $report->record('schema', 1, true))->toThrow(LogicException::class);
	expect(fn () => (new \Cacti\Installer\RunReport())->record('password=secret', 1, true))->toThrow(LogicException::class);
});

test('run report distinguishes success, warnings and failure', function (bool $success, bool $warnings, string $expected) {
	$report = new \Cacti\Installer\RunReport();
	$report->finish($success, new CompletionWarnings($warnings ? ['failed' => [2]] : []));
	expect(json_decode($report->encode(), true)['state'])->toBe($expected);
})->with([[true, false, 'succeeded'], [true, true, 'completed_with_warnings'], [false, false, 'failed']]);

test('upgrade plans include only migrations in the requested range and preserve selected effects', function () {
	$plan = new \Cacti\Installer\UpgradePlan('1.2.22', '1.3.0', ['1.2.21', '1.2.22', '1.2.23', '1.3.0', '1.4.0'], ['custom template'], ['host'], ['missing: disabled'], [2, 3]);
	$data = json_decode($plan->encode(), true);
	expect((new \Cacti\Installer\UpgradePlan('new_install', '1.3.0', ['1.2.22', '1.3.0']))->migrations)->toBe([]);
	expect($data['migrations'])->toBe(['1.2.23', '1.3.0'])->and($data['templates'])->toBe(['custom template'])
		->and($data['tables'])->toBe(['host'])->and($data['collectors'])->toBe([2, 3]);
});

test('authentication postconditions preserve valid mappings and detect broken configuration', function () {
	$checker   = new \Cacti\Installer\AuthenticationRequirements();
	$providers = [
		['id' => 7, 'type' => 1, 'enabled' => 'on', 'parameters' => '{"server":"ldap"}'],
		['id' => 8, 'type' => 1, 'enabled' => '', 'parameters' => null],
		['id' => 9, 'type' => 2, 'enabled' => 'on', 'parameters' => '{}'],
	];
	expect($checker->errors($providers, [0, 1007, 1008, 1009]))->toBe([]);
	$providers[0]['parameters'] = 'invalid';
	$providers[2]['type']       = 1;
	$errors                     = $checker->errors($providers, [1007, 1010]);
	expect(implode(' ', $errors))->toContain('Provider 7', 'LDAP provider 9', 'realm 1010');
});

test('preflight detects missing dependency entry points even when installed metadata is present', function () {
	expect((new DependencyPreflight($this->preflightRoot, requiredClasses: ['Cacti\\Installer\\MissingDependencyFixture']))->errors())->toBe(['Composer platform or autoloader verification failed. Run composer check-platform-reqs --no-dev and cli/prepare_install.sh in this PHP environment.']);
});

test('run reports record controlled migration versions and reject arbitrary version content', function () {
	$report = new \Cacti\Installer\RunReport();
	$report->record('migration', 1, true, '1.3.0');
	expect(json_decode($report->encode(), true)['operations'][0]['version'])->toBe('1.3.0');
	expect(fn () => $report->record('migration', 1, true, 'password=secret'))->toThrow(LogicException::class);
});

test('long installer reports fit the settings column and retain the failed migration', function () {
	$report = new \Cacti\Installer\RunReport();

	for ($i = 0; $i < 100; $i++) {
		$report->record('migration', 0.123456789, true, '1.2.' . $i);
	}
	$report->record('migration', 1.5, false, '1.3.0');
	$encoded = $report->encode();
	$data    = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);
	expect(strlen($encoded))->toBeLessThanOrEqual(4096)
		->and($data['operations_omitted'])->toBeGreaterThan(0)
		->and($data['state'])->toBe('failed')
		->and($data['operations'][array_key_last($data['operations'])]['version'])->toBe('1.3.0');
});

test('large collector warnings keep valid JSON and disclose omitted IDs', function () {
	$report = new \Cacti\Installer\RunReport();
	$report->finish(true, new CompletionWarnings(['failed' => range(1, 2000)]));
	$encoded = $report->encode();
	$data    = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);
	expect(strlen($encoded))->toBeLessThanOrEqual(4096)
		->and($data['warning_ids_omitted'])->toBeGreaterThan(0)
		->and(count($data['warnings']['collectors']['failed']) + $data['warning_ids_omitted'])->toBe(2000)
		->and($data['state'])->toBe('completed_with_warnings');
});

test('a single oversized diagnostic entry fails instead of returning unpersistable JSON', function () {
	$report = new \Cacti\Installer\RunReport();
	$report->record('migration', 1, false, '1.2.' . str_repeat('9', 4096));
	expect(fn () => $report->encode())->toThrow(LogicException::class, 'Installer report exceeds its storage limit');
});
