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

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

$root = dirname(__DIR__, 4);

test('CLI symlink guards, md5sum containment, pushout argv array, and float_rrdfile RRA checks enforce safety', function () {
	$res = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/BulkCliHardeningProbe.php');

	expect($res['chown_sym'])->toBeFalse()
		->and($res['chgrp_sym'])->toBeFalse()
		->and($res['md5_inside'])->toContain('ok.txt')
		->and($res['md5_inside'])->not->toContain('escape.txt')
		->and($res['md5_outside'])->toBeEmpty()
		->and(count($res['pushout_bg']))->toBe(1)
		->and($res['pushout_bg'][0][1])->toContain('--type=child')
		->and($res['pushout_bg'][0][1])->toContain('--host-template-id=5')
		->and($res['pushout_bg'][0][1])->toContain('--data-template-id=7')
		->and($res['float_outside'])->toBeFalse()
		->and($res['float_unix'])->toBeFalse()
		->and($res['float_win32'])->toBeFalse();
});

test('cli/md5sum.php, cli/input_whitelist.php, cli/batchgapfix.php, and cli/structure_rra_paths.php enforce containment and atomic writes', function () use ($root) {
	$md5Source     = file_get_contents($root . '/cli/md5sum.php');
	$wlSource      = file_get_contents($root . '/cli/input_whitelist.php');
	$batchSource   = file_get_contents($root . '/cli/batchgapfix.php');
	$structSource  = file_get_contents($root . '/cli/structure_rra_paths.php');

	expect($md5Source)->toContain("define_exit('EXIT_MD5ERR',  8,")
		->and($md5Source)->toContain("!hash_equals((string) \$hash_file, (string) \$hash_read)")
		->and($md5Source)->toContain("if (\$verify_failed) {\n\t\texit(EXIT_MD5ERR);")
		->and($wlSource)->toContain('if (!is_array($input_ws))')
		->and($wlSource)->toContain("tempnam(dirname(\$config['input_whitelist']), 'whitelist_')")
		->and($wlSource)->toContain("rename(\$tmp_file, \$config['input_whitelist'])")
		->and($batchSource)->toContain('ON dtd.local_data_id = dtr.local_data_id')
		->and($batchSource)->toContain("!data_source_path_within_rra(\$rrdfile['data_source_path'])")
		->and($batchSource)->toContain("cacti_escapeshellarg_cmd(\$rrdfile['data_source_path'])")
		->and($structSource)->toContain('!data_source_path_within_rra($new_rrd_path)');
});
