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

/*
 * State files kept between polls by scripts/ss_net_snmp_disk_io.php and
 * scripts/ss_net_snmp_disk_bytes.php live under the system temporary
 * directory. The directory must be private to the poller user, links are
 * refused, and the state file is replaced in one step.
 */

global $called_by_script_server;
$called_by_script_server = true;
require_once __DIR__ . '/../../../scripts/ss_net_snmp_disk_io.php';
require_once __DIR__ . '/../../../scripts/ss_net_snmp_disk_bytes.php';

function ss_state_test_base() {
	$base = sys_get_temp_dir() . '/cacti-state-test-' . getmypid() . '-' . bin2hex(random_bytes(4));

	mkdir($base, 0700);

	return $base;
}

function ss_state_test_remove($path) {
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
	$this->base = ss_state_test_base();

	/* refusals are logged; keep that log inside the test directory */
	$this->config = $GLOBALS['config'];
	$GLOBALS['config']['base_path'] = $this->base;
	$GLOBALS['config']['poller_id'] = 1;
	$GLOBALS['config']['config_options_array']['path_cactilog'] = $this->base . '/cacti.log';
});

afterEach(function () {
	$GLOBALS['config'] = $this->config;

	ss_state_test_remove($this->base);
});

test('the state directory and its parent are created private to the poller user', function () {
	$dir = $this->base . '/cacti/net-snmp-devio';

	expect(ss_net_snmp_state_dir($dir))->toBe($dir);
	expect(fileperms($this->base . '/cacti') & 0777)->toBe(0700);
	expect(fileperms($dir) & 0777)->toBe(0700);
});

test('a state directory left open by an earlier release is closed', function () {
	$dir = $this->base . '/cacti/net-snmp-devio';

	/* earlier releases created these with mkdir(0777); any group or other
	 * permission bit takes the same tightening path, so group access is
	 * enough to prove it without creating a world-writable directory */
	mkdir($dir, 0700, true);
	chmod($this->base . '/cacti', 0770);
	chmod($dir, 0750);

	expect(ss_net_snmp_state_dir($dir))->toBe($dir);
	clearstatcache();
	expect(fileperms($this->base . '/cacti') & 0777)->toBe(0700);
	expect(fileperms($dir) & 0777)->toBe(0700);
});

test('a state directory that is a symbolic link is refused', function () {
	mkdir($this->base . '/elsewhere', 0700);
	mkdir($this->base . '/cacti', 0700);
	symlink($this->base . '/elsewhere', $this->base . '/cacti/net-snmp-devio');

	expect(ss_net_snmp_state_dir($this->base . '/cacti/net-snmp-devio'))->toBeFalse();
});

test('a parent directory that is a symbolic link is refused', function () {
	mkdir($this->base . '/elsewhere', 0700);
	symlink($this->base . '/elsewhere', $this->base . '/cacti');

	expect(ss_net_snmp_state_dir($this->base . '/cacti/net-snmp-devio'))->toBeFalse();
	expect(file_exists($this->base . '/elsewhere/net-snmp-devio'))->toBeFalse();
});

test('state is written atomically and read back', function () {
	$dir = ss_net_snmp_state_dir($this->base . '/cacti/net-snmp-devio');

	expect(ss_net_snmp_state_write($dir, "$dir/5_io", json_encode(array('uptime' => '100', 'dr1' => '42'))))->toBeTrue();
	expect(ss_net_snmp_state_read("$dir/5_io"))->toBe(array('uptime' => '100', 'dr1' => '42'));
	expect(glob("$dir/state*"))->toBe(array());
});

test('a missing state file reports no previous state', function () {
	$dir = ss_net_snmp_state_dir($this->base . '/cacti/net-snmp-devio');

	expect(ss_net_snmp_state_read("$dir/5_io"))->toBeFalse();
});

test('a state file that is a symbolic link is not read', function () {
	$dir = ss_net_snmp_state_dir($this->base . '/cacti/net-snmp-devio');

	file_put_contents($this->base . '/other', json_encode(array('uptime' => '1')));
	symlink($this->base . '/other', "$dir/5_io");

	expect(ss_net_snmp_state_read("$dir/5_io"))->toBeFalse();
});

test('writing replaces a symbolic link instead of following it', function () {
	$dir = ss_net_snmp_state_dir($this->base . '/cacti/net-snmp-devio');

	file_put_contents($this->base . '/other', 'unchanged');
	symlink($this->base . '/other', "$dir/5_io");

	expect(ss_net_snmp_state_write($dir, "$dir/5_io", '{}'))->toBeTrue();
	expect(file_get_contents($this->base . '/other'))->toBe('unchanged');
	expect(is_link("$dir/5_io"))->toBeFalse();
});

test('neither script creates a world-writable directory', function () {
	foreach (array('ss_net_snmp_disk_io.php', 'ss_net_snmp_disk_bytes.php') as $script) {
		$source = file_get_contents(__DIR__ . '/../../../scripts/' . $script);

		expect($source)->not->toContain('0777');
		expect($source)->toContain('ss_net_snmp_state_dir($tmpdir)');
	}
});

test('a counter that is not an unsigned integer yields U instead of an error', function () {
	expect(ss_counter_step('U', '12abc', '100', '4294967296'))->toBe('U');
	expect(ss_counter_step('U', '105', '', '4294967296'))->toBe('U');
	expect(ss_counter_step('U', '-5', '100', '4294967296'))->toBe('U');
	expect(ss_counter_step('U', '105', '100', '4294967296'))->toBe('5');
});
