<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

global $called_by_script_server;
$called_by_script_server = true;

require_once dirname(__DIR__, 3) . '/scripts/ss_net_snmp_disk_io.php';
require_once dirname(__DIR__, 3) . '/scripts/ss_net_snmp_disk_bytes.php';

function ss_state_test_base() : string {
	$base = sys_get_temp_dir() . '/cacti-state-test-' . getmypid() . '-' . bin2hex(random_bytes(4));

	mkdir($base, 0700);

	return $base;
}

function ss_state_test_remove(string $path) : void {
	if (is_link($path) || is_file($path)) {
		unlink($path);
	} elseif (is_dir($path)) {
		foreach (scandir($path) as $entry) {
			if ($entry != '.' && $entry != '..') {
				ss_state_test_remove("$path/$entry");
			}
		}

		rmdir($path);
	}
}

beforeEach(function () {
	global $config;

	$this->base   = ss_state_test_base();
	$this->config = $config;

	$config[OPTIONS_CLI]['path_cactilog']   = $this->base . '/cacti.log';
	$config[OPTIONS_CLI]['log_destination'] = 1;
});

afterEach(function () {
	global $config;

	$config = $this->config;
	ss_state_test_remove($this->base);
});

test('state directories are created private to the poller user', function () {
	$dir = $this->base . '/cacti/net-snmp-devio';

	expect(CactiSnmpStateStore::directory($dir))->toBe($dir)
		->and(fileperms($this->base . '/cacti') & 0777)->toBe(0700)
		->and(fileperms($dir) & 0777)->toBe(0700);
});

test('permissions left by an earlier release are tightened', function () {
	$dir = $this->base . '/cacti/net-snmp-devio';

	mkdir($dir, 0700, true);
	chmod($this->base . '/cacti', 0770);
	chmod($dir, 0750);

	expect(CactiSnmpStateStore::directory($dir))->toBe($dir);
	clearstatcache();
	expect(fileperms($this->base . '/cacti') & 0777)->toBe(0700)
		->and(fileperms($dir) & 0777)->toBe(0700);
});

test('symbolic-link state directories are refused', function () {
	mkdir($this->base . '/elsewhere', 0700);
	mkdir($this->base . '/cacti', 0700);
	symlink($this->base . '/elsewhere', $this->base . '/cacti/net-snmp-devio');

	expect(CactiSnmpStateStore::directory($this->base . '/cacti/net-snmp-devio'))->toBeFalse();
})->skip(DIRECTORY_SEPARATOR === '\\', 'symbolic links require Unix semantics');

test('state is replaced atomically and read back', function () {
	$dir  = CactiSnmpStateStore::directory($this->base . '/cacti/net-snmp-devio');
	$file = "$dir/5_io";
	$data = json_encode(['uptime' => '100', 'dr1' => '42']);

	expect($dir)->toBeString()
		->and($data)->toBeString()
		->and(CactiSnmpStateStore::write($dir, $file, $data))->toBeTrue()
		->and(CactiSnmpStateStore::read($file))->toBe(['uptime' => '100', 'dr1' => '42'])
		->and(glob("$dir/state*"))->toBe([]);
});

test('invalid state and destinations outside the private directory are refused', function () {
	$dir  = CactiSnmpStateStore::directory($this->base . '/cacti/net-snmp-devio');
	$file = "$dir/5_io";

	file_put_contents($file, '{invalid');

	expect(CactiSnmpStateStore::read($file))->toBeFalse()
		->and(CactiSnmpStateStore::write($dir, $this->base . '/outside', '{}'))->toBeFalse();
});

test('state reads and writes are bounded', function () {
	$dir  = CactiSnmpStateStore::directory($this->base . '/cacti/net-snmp-devio');
	$file = "$dir/5_io";
	$data = str_repeat('x', 1048577);

	file_put_contents($file, $data);

	expect(CactiSnmpStateStore::read($file))->toBeFalse()
		->and(CactiSnmpStateStore::write($dir, "$dir/6_io", $data))->toBeFalse();
});

test('symbolic-link state files are not read or followed on replacement', function () {
	$dir = CactiSnmpStateStore::directory($this->base . '/cacti/net-snmp-devio');

	file_put_contents($this->base . '/other', 'unchanged');
	symlink($this->base . '/other', "$dir/5_io");

	expect(CactiSnmpStateStore::read("$dir/5_io"))->toBeFalse()
		->and(CactiSnmpStateStore::write($dir, "$dir/5_io", '{}'))->toBeTrue()
		->and(file_get_contents($this->base . '/other'))->toBe('unchanged')
		->and(is_link("$dir/5_io"))->toBeFalse();
})->skip(DIRECTORY_SEPARATOR === '\\', 'symbolic links require Unix semantics');

test('real-time state identifiers are bounded and path safe', function () {
	expect(CactiSnmpStateStore::realtimeId(42))->toBe('42')
		->and(CactiSnmpStateStore::realtimeId(str_repeat('a', 64)))->toBe(str_repeat('a', 64))
		->and(CactiSnmpStateStore::realtimeId('preview-A'))->toBeFalse()
		->and(CactiSnmpStateStore::realtimeId('../preview'))->toBeFalse()
		->and(CactiSnmpStateStore::realtimeId('preview/one'))->toBeFalse()
		->and(CactiSnmpStateStore::realtimeId(str_repeat('a', 65)))->toBeFalse()
		->and(CactiSnmpStateStore::realtimeId(['preview']))->toBeFalse();
});

test('standalone disk scripts initialize their helpers before dispatch', function (string $script, string $expected) {
	$process = proc_open([
		PHP_BINARY,
		'-d',
		'auto_prepend_file=' . dirname(__DIR__, 2) . '/fixtures/script_server_bootstrap.php',
		dirname(__DIR__, 3) . '/scripts/' . $script,
		'-1'
	], [
		0 => ['pipe', 'r'],
		1 => ['pipe', 'w'],
		2 => ['pipe', 'w']
	], $pipes);

	if (!is_resource($process)) {
		throw new RuntimeException('Unable to start standalone script fixture');
	}

	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$status = proc_close($process);

	expect($status)->toBe(0)
		->and($stdout)->toBe($expected)
		->and($stderr)->not->toContain('Fatal error');
})->with([
	'io'    => ['ss_net_snmp_disk_io.php', 'reads:0 writes:0'],
	'bytes' => ['ss_net_snmp_disk_bytes.php', 'bytesread:0 byteswritten:0'],
]);
