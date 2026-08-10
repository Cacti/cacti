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
 * Tests for RRDfile path escaping in the three CLI tools that shell out.
 *
 * Each one built its command by interpolation, and every path came from
 * data_template_data.data_source_path, which the web UI writes and an XML
 * template import can also set:
 *
 *   batchgapfix.php     --rrdfile='$path'   (single quotes only)
 *   float_rrdfiles.php  "$rrdtool_bin dump $rrd_path"
 *   update_heartbeat.php sprintf('rrdtool tune %s ', $f['rrd'])
 *
 * A path holding a quote or a shell operator therefore ran as the account
 * invoking the tool, which is not the account that wrote the path.
 */

$batchgapfixSource     = file_get_contents(dirname(__DIR__, 2) . '/cli/batchgapfix.php');
$floatRrdfilesSource   = file_get_contents(dirname(__DIR__, 2) . '/cli/float_rrdfiles.php');
$updateHeartbeatSource = file_get_contents(dirname(__DIR__, 2) . '/cli/update_heartbeat.php');

// --- batchgapfix ---

test('batchgapfix escapes the RRDfile path handed to removespikes', function () use ($batchgapfixSource) {
	expect($batchgapfixSource)->toContain('cacti_escapeshellarg($rrdfile[\'data_source_path\'])');
	expect($batchgapfixSource)->not->toContain('--rrdfile=\'%s\'');
});

test('batchgapfix escapes the window and method arguments', function () use ($batchgapfixSource) {
	expect($batchgapfixSource)->toContain('cacti_escapeshellarg($start_date)');
	expect($batchgapfixSource)->toContain('cacti_escapeshellarg($end_date)');
	expect($batchgapfixSource)->toContain('cacti_escapeshellarg($method)');
	expect($batchgapfixSource)->toContain('cacti_escapeshellarg($avgnan)');
});

test('batchgapfix spawns its children with an argument array', function () use ($batchgapfixSource) {
	expect($batchgapfixSource)->toContain('exec_background($php_bin, $args)');
	expect($batchgapfixSource)->not->toContain('exec_background($php_bin, $command)');
});

// --- float_rrdfiles ---

test('float_rrdfiles escapes the RRDfile path on dump', function () use ($floatRrdfilesSource) {
	expect($floatRrdfilesSource)->toContain("(string) read_config_option('path_rrdtool')");
	expect($floatRrdfilesSource)->toContain('cacti_escapeshellcmd($rrdtool_bin) . \' dump \' . cacti_escapeshellarg($rrd_path)');
	expect($floatRrdfilesSource)->not->toContain('"$rrdtool_bin dump $rrd_path"');
});

test('batchgapfix normalizes an unset PHP binary path', function () use ($batchgapfixSource) {
	expect($batchgapfixSource)->toContain("(string) read_config_option('path_php_binary')");
});

test('float_rrdfiles escapes both paths on restore', function () use ($floatRrdfilesSource) {
	expect($floatRrdfilesSource)->toContain('cacti_escapeshellarg($tmp_file)');
	expect($floatRrdfilesSource)->not->toContain('"$rrdtool_bin restore -f $tmp_file $rrd_path"');
});

// --- update_heartbeat ---

test('update_heartbeat escapes the RRDfile path', function () use ($updateHeartbeatSource) {
	expect($updateHeartbeatSource)->toContain('cacti_escapeshellarg($f[\'rrd\'])');
	expect($updateHeartbeatSource)->not->toContain('sprintf(\'rrdtool tune %s \'');
});

test('update_heartbeat escapes each data source name', function () use ($updateHeartbeatSource) {
	expect($updateHeartbeatSource)->toContain('cacti_escapeshellarg($ds . \':\' . $new_heartbeat)');
	expect($updateHeartbeatSource)->not->toContain('--heartbeat $ds:$new_heartbeat');
});

test('update_heartbeat takes RRDtool from the configured path', function () use ($updateHeartbeatSource) {
	expect($updateHeartbeatSource)->toContain('$rrdtool_bin = read_config_option(\'path_rrdtool\')');
	expect($updateHeartbeatSource)->toContain('cacti_escapeshellcmd($rrdtool_bin)');
});
