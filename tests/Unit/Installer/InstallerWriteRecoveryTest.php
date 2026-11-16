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

namespace InstallerWriteRecoveryTest;

$root = dirname(__DIR__, 3);

require_once $root . '/tests/Helpers/SourceSlice.php';

foreach (['install_write_poller_config', 'prime_default_settings'] as $name) {
	cacti_fixture_source_function($root . '/install/functions.php', $name, __NAMESPACE__);
}

function fwrite($stream, string $data, ?int $length = null) : int|false {
	if ($GLOBALS['installer_write_fault'] === 'write') {
		return false;
	}

	if ($GLOBALS['installer_write_fault'] === 'zero') {
		return 0;
	}

	return \fwrite($stream, $data, $GLOBALS['installer_write_fault'] === 'short' ? min(3, strlen($data)) : strlen($data));
}

function fflush($stream) : bool {
	return $GLOBALS['installer_write_fault'] !== 'flush' && \fflush($stream);
}

function rename(string $from, string $to) : bool {
	return $GLOBALS['installer_write_fault'] !== 'rename' && \rename($from, $to);
}

function db_fetch_cell_prepared($sql, $params = [], $col_name = '', $log = true, $db_conn = false) {
	return $GLOBALS['installer_saved_defaults'][$params[0]] ?? '';
}

function db_execute_prepared($sql, $params = [], $log = true, $db_conn = false) : bool {
	$GLOBALS['installer_default_attempts']++;

	if ($GLOBALS['installer_default_attempts'] === $GLOBALS['installer_default_fail_at']) {
		return false;
	}

	for ($i = 0; $i < count($params); $i += 2) {
		if (!array_key_exists($params[$i], $GLOBALS['installer_saved_defaults'])) {
			$GLOBALS['installer_saved_defaults'][$params[$i]] = $params[$i + 1];
		}
	}

	return true;
}

beforeEach(function () {
	$GLOBALS['installer_write_fault']      = '';
	$GLOBALS['installer_default_fail_at']  = 0;
	$GLOBALS['installer_default_attempts'] = 0;
	$GLOBALS['installer_saved_defaults']   = [];
	$_SESSION                              = [];
});

test('poller configuration replacement preserves the original on failed I/O', function (string $fault) {
	$directory = sys_get_temp_dir() . '/cacti-config-test-' . bin2hex(random_bytes(8));
	mkdir($directory, 0700);
	$file     = $directory . '/config.php';
	$original = "<?php\n// Keep \$poller_id configuration.\n\$poller_id = 1;\n\$other = 'unchanged';\n";
	file_put_contents($file, $original);
	chmod($file, 0640);

	try {
		$GLOBALS['installer_write_fault'] = $fault;
		$success                          = install_write_poller_config($file, 7);
		clearstatcache(true, $file);

		if (in_array($fault, ['write', 'zero', 'flush', 'rename'], true)) {
			expect($success)->toBeFalse()->and(file_get_contents($file))->toBe($original);
		} else {
			expect($success)->toBeTrue()
				->and(file_get_contents($file))->toBe(str_replace('$poller_id = 1;', '$poller_id = 7;', $original));
		}

		expect(fileperms($file) & 0777)->toBe(0640)
			->and(glob($directory . '/.cacti-config-*'))->toBe([]);
	} finally {
		unlink($file);
		rmdir($directory);
	}
})->with(['', 'short', 'write', 'zero', 'flush', 'rename']);

test('default settings retry a failed write and preserve existing values', function () {
	$GLOBALS['installer_saved_defaults']['existing'] = 'custom';
	$settings                                        = ['existing' => ['default' => 'default']];

	for ($i = 0; $i < 251; $i++) {
		$settings['setting_' . $i] = ['default' => (string) $i];
	}

	$GLOBALS['settings']                  = ['general' => $settings];
	$GLOBALS['installer_default_fail_at'] = 2;

	expect(prime_default_settings())->toBeFalse()
		->and(isset($_SESSION['settings_primed']))->toBeFalse();

	$GLOBALS['installer_default_fail_at'] = 0;
	expect(prime_default_settings())->toBeTrue()
		->and($_SESSION['settings_primed'])->toBeTrue()
		->and($GLOBALS['installer_saved_defaults'])->toHaveCount(252)
		->and($GLOBALS['installer_saved_defaults']['existing'])->toBe('custom')
		->and($GLOBALS['installer_saved_defaults']['setting_250'])->toBe('250');
});

test('poller config follows symlinks and refuses ambiguous assignments', function () {
	$directory = sys_get_temp_dir() . '/cacti-config-link-' . bin2hex(random_bytes(8));
	mkdir($directory, 0700);
	$file = $directory . '/actual.php';
	$link = $directory . '/config.php';
	file_put_contents($file, "<?php\n\$poller_id = 1;\n");
	symlink($file, $link);

	try {
		expect(install_write_poller_config($link, 9))->toBeTrue()
			->and(is_link($link))->toBeTrue()
			->and(file_get_contents($file))->toBe("<?php\n\$poller_id = 9;\n");
		$duplicate = "<?php\n\$poller_id = 1;\n\$poller_id = 2;\n";
		file_put_contents($file, $duplicate);
		expect(install_write_poller_config($link, 9))->toBeFalse()
			->and(file_get_contents($file))->toBe($duplicate);
		file_put_contents($file, '<?php // no assignment');
		expect(install_write_poller_config($link, 9))->toBeFalse()
			->and(file_get_contents($file))->toBe('<?php // no assignment');
	} finally {
		unlink($link);
		unlink($file);
		rmdir($directory);
	}
});
