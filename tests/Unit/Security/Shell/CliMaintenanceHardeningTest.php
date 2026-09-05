<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$base                  = dirname(__DIR__, 4);
$auditDatabaseSource   = file_get_contents($base . '/cli/audit_database.php');
$batchgapfixSource     = file_get_contents($base . '/cli/batchgapfix.php');
$floatRrdfilesSource   = file_get_contents($base . '/cli/float_rrdfiles.php');
$updateHeartbeatSource = file_get_contents($base . '/cli/update_heartbeat.php');

test('database sourced RRD paths are escaped before shell execution', function () use ($batchgapfixSource, $floatRrdfilesSource, $updateHeartbeatSource) {
	expect($batchgapfixSource)->toContain('cacti_escapeshellarg($rrdfile[\'data_source_path\'])')
		->and($batchgapfixSource)->toContain('exec_background($php_bin, $args)')
		->and($floatRrdfilesSource)->toContain("cacti_escapeshellarg(\$rrdtool_bin) . ' dump ' . cacti_escapeshellarg(\$rrd_path)")
		->and($floatRrdfilesSource)->toContain("cacti_escapeshellarg(\$tmp_file) . ' ' . cacti_escapeshellarg(\$rrd_path)")
		->and($updateHeartbeatSource)->toContain("cacti_escapeshellarg(\$f['rrd'])")
		->and($updateHeartbeatSource)->toContain("cacti_escapeshellarg(\$ds . ':' . \$new_heartbeat)");
});

test('CLI subprocesses pass background arguments as arrays', function () use ($batchgapfixSource, $floatRrdfilesSource) {
	expect($batchgapfixSource)->toContain('exec_background($php_bin, $args)')
		->and($floatRrdfilesSource)->toContain('exec_background($php_binary, $args)');
});

test('float rrdfiles uses a private temporary file and reachable cleanup', function () use ($floatRrdfilesSource) {
	expect($floatRrdfilesSource)->toContain("tempnam(\$tmp_dir, 'cacti_float_')")
		->and($floatRrdfilesSource)->not->toContain("\$tmp_dir . '/' . \$local_data_id . '.xml'")
		->and(substr_count($floatRrdfilesSource, 'unlink($tmp_file);'))->toBeGreaterThanOrEqual(3)
		->and($floatRrdfilesSource)->toContain('$lf = false;')
		->and($floatRrdfilesSource)->toContain('$seebug = is_resource($lf);')
		->and($floatRrdfilesSource)->not->toContain("cacti_float_rrdfiles.log");
});

test('mysql option file values are quoted and escaped', function () use ($auditDatabaseSource) {
	preg_match('/function audit_database_option_value\(.*?^}\R/ms', $auditDatabaseSource, $matches);

	expect($matches)->toHaveKey(0);

	eval($matches[0]);

	expect(audit_database_option_value('abc#def;ghi'))->toBe('"abc#def;ghi"')
		->and(audit_database_option_value("line\nnext"))->toBe('"line\\nnext"')
		->and(audit_database_option_value('a"b\\c'))->toBe('"a\\"b\\\\c"')
		->and(audit_database_option_value("bad\0value"))->toBeFalse();
});
