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

namespace CactiBulkCliHardeningTest;

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';

$root = dirname(__DIR__, 4);

function cacti_path_is_within($path, $base) {
	return \cacti_path_is_within($path, $base);
}

if (!function_exists(__NAMESPACE__ . '\\sp_recursive_chown')) {
	\cacti_fixture_source_function($root . '/cli/structure_rra_paths.php', 'sp_recursive_chown', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/cli/structure_rra_paths.php', 'sp_recursive_chgrp', __NAMESPACE__);
}

if (!function_exists(__NAMESPACE__ . '\\dirToArray')) {
	\cacti_fixture_source_function($root . '/cli/md5sum.php', 'dirToArray', __NAMESPACE__);
}

test('sp_recursive_chown and sp_recursive_chgrp refuse symlinks', function () {
	$tmpDir = sys_get_temp_dir() . '/cacti-rra-sym-' . bin2hex(random_bytes(6));
	mkdir($tmpDir, 0700, true);
	$target  = $tmpDir . '/real.txt';
	$symlink = $tmpDir . '/link.txt';
	file_put_contents($target, 'data');
	symlink($target, $symlink);

	try {
		expect(sp_recursive_chown($symlink, getmyuid()))->toBeFalse()
			->and(sp_recursive_chgrp($symlink, getmygid()))->toBeFalse();
	} finally {
		@unlink($symlink);
		@unlink($target);
		@rmdir($tmpDir);
	}
});

test('cli/md5sum.php dirToArray blocks sibling prefix directories and skips symlinks', function () {
	$tmpParent  = sys_get_temp_dir() . '/cacti-md5-' . bin2hex(random_bytes(6));
	$baseDir    = $tmpParent . '/cacti';
	$siblingDir = $tmpParent . '/cacti_outside';
	mkdir($baseDir, 0700, true);
	mkdir($siblingDir, 0700, true);
	file_put_contents($baseDir . '/ok.txt', 'hello');
	file_put_contents($siblingDir . '/secret.txt', 'secret');
	symlink($siblingDir . '/secret.txt', $baseDir . '/escape.txt');

	$GLOBALS['debug'] = false;
	$GLOBALS['quiet'] = true;

	try {
		$inside  = dirToArray('', realpath($baseDir), '/^\/(\.|\.\.)$/');
		$outside = dirToArray('../cacti_outside', realpath($baseDir), '/^\/(\.|\.\.)$/');

		expect($inside)->toHaveKey('ok.txt')
			->and($inside)->not->toHaveKey('escape.txt')
			->and($outside)->toBeEmpty();
	} finally {
		@unlink($baseDir . '/escape.txt');
		@unlink($baseDir . '/ok.txt');
		@unlink($siblingDir . '/secret.txt');
		@rmdir($baseDir);
		@rmdir($siblingDir);
		@rmdir($tmpParent);
	}
});

test('cli/md5sum.php defines EXIT_MD5ERR and uses hash_equals for strict digest comparison', function () use ($root) {
	$source = file_get_contents($root . '/cli/md5sum.php');

	expect($source)->toContain("define_exit('EXIT_MD5ERR',  8,")
		->and($source)->toContain("!hash_equals((string) \$hash_file, (string) \$hash_read)")
		->and($source)->toContain("if (\$verify_failed) {\n\t\texit(EXIT_MD5ERR);")
		->and(hash_equals('0e123456789012345678901234567890', '0e987654321098765432109876543210'))->toBeFalse();
});

test('cli/input_whitelist.php preserves scoping in single-ID mode and writes atomically', function () use ($root) {
	$source = file_get_contents($root . '/cli/input_whitelist.php');

	expect($source)->toContain('if (!is_array($input_ws))')
		->and($source)->toContain("} elseif (isset(\$input_ws[\$value['hash']])) {\n\t\t\t\t\$input[\$value['hash']] = \$input_ws[\$value['hash']];\n\t\t\t}")
		->and($source)->toContain("tempnam(dirname(\$config['input_whitelist']), 'whitelist_')")
		->and($source)->toContain("rename(\$tmp_file, \$config['input_whitelist'])");
});

test('cli/batchgapfix.php, cli/float_rrdfiles.php, cli/rebuild_poller_cache.php, and lib/spikekill.php enforce RRA containment and safe escaping', function () use ($root) {
	$batchSource   = file_get_contents($root . '/cli/batchgapfix.php');
	$floatSource   = file_get_contents($root . '/cli/float_rrdfiles.php');
	$rebuildSource = file_get_contents($root . '/cli/rebuild_poller_cache.php');
	$spikeSource   = file_get_contents($root . '/lib/spikekill.php');
	$structSource  = file_get_contents($root . '/cli/structure_rra_paths.php');

	expect($batchSource)->toContain('ON dtd.local_data_id = dtr.local_data_id')
		->and($batchSource)->toContain("!data_source_path_within_rra(\$rrdfile['data_source_path'])")
		->and($batchSource)->toContain("cacti_escapeshellarg_cmd(\$rrdfile['data_source_path'])")
		->and($floatSource)->toContain('!data_source_path_within_rra($rrd_path)')
		->and($floatSource)->toContain('cacti_escapeshellarg_cmd($rrd_path)')
		->and($rebuildSource)->toContain('exec_background($php_binary, $args);')
		->and($spikeSource)->toContain("!data_source_path_within_rra(\$this->rrdfile)")
		->and($spikeSource)->toContain("\$this->rrdfile[0] === '-'")
		->and($structSource)->toContain('!data_source_path_within_rra($new_rrd_path)');
});
