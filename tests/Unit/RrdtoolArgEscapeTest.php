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
 * __rrd_execute() states its contract in its own docblock: a caller passing a
 * string quotes each variable argument with cacti_escapeshellarg() as it builds
 * the line, because the assembled line is handed to shell_exec().
 *
 * These callers interpolated the RRDfile path instead. The paths come from
 * data_template_data.data_source_path, which the web UI writes and an XML
 * template import can also set, so the value crossed into a shell it was never
 * quoted for. The affected core RRDtool integration files are checked here, so
 * a new unsafe interpolation in those files fails before reaching a shell.
 */

$rrdSource       = file_get_contents(dirname(__DIR__, 2) . '/lib/rrd.php');
$boostSource     = file_get_contents(dirname(__DIR__, 2) . '/lib/boost.php');
$dsstatsSource   = file_get_contents(dirname(__DIR__, 2) . '/lib/dsstats.php');
$functionsSource = file_get_contents(dirname(__DIR__, 2) . '/lib/functions.php');

foreach ([
	'lib/rrd.php'       => $rrdSource,
	'lib/boost.php'     => $boostSource,
	'lib/dsstats.php'   => $dsstatsSource,
	'lib/functions.php' => $functionsSource,
] as $path => $source) {
	if ($source === false) {
		throw new RuntimeException("Unable to read $path for RRDtool argument tests.");
	}
}

// --- the RRDtool binary itself ---

test('rrd_init launches the RRDtool binary without a shell', function () use ($rrdSource) {
	// An argv array never reaches a shell, so the path must NOT be escaped here;
	// quoting it would make the quotes part of the filename.
	expect($rrdSource)->toContain("proc_open([\$path, '-'], \$descriptors, \$pipes, null, null, ['bypass_shell' => true]);");
	expect($rrdSource)->not->toContain('popen(');
	expect($rrdSource)->not->toContain("\$rrdtool = cacti_escapeshellcmd((string) read_config_option('path_rrdtool'));");
	expect($rrdSource)->not->toContain("\$command = read_config_option('path_rrdtool') . ' - ';");
});

test('the proxy font path is quoted rather than hand wrapped', function () use ($rrdSource) {
	expect($rrdSource)->toContain("'setenv RRD_DEFAULT_FONT ' . cacti_escapeshellarg(");
	expect($rrdSource)->not->toContain('"setenv RRD_DEFAULT_FONT \'"');
});

// --- boost ---

test('boost quotes the RRDfile path on file_exists, last and create', function () use ($boostSource) {
	expect($boostSource)->toContain("rrdtool_execute('last ' . cacti_escapeshellarg(\$rrd_path)");
	expect($boostSource)->toContain("rrdtool_execute('create ' . cacti_escapeshellarg(\$data_source_path)");
	expect($boostSource)->not->toContain('rrdtool_execute("file_exists $rrd_path"');
	expect($boostSource)->not->toContain('rrdtool_execute("last $rrd_path"');
});

test('boost quotes the RRDfile path on update', function () use ($boostSource) {
	expect($boostSource)->toContain("rrdtool_execute('update ' . cacti_escapeshellarg(\$rrd_path)");
	expect($boostSource)->not->toContain('rrdtool_execute("update $rrd_path');
});

test('boost quotes the directory it asks RRDtool to create', function () use ($boostSource) {
	expect($boostSource)->toContain("'is_dir ' . cacti_escapeshellarg(dirname(\$data_source_path))");
	expect($boostSource)->toContain("'mkdir ' . cacti_escapeshellarg(dirname(\$data_source_path))");
});

// --- dsstats and functions ---

test('dsstats quotes the RRDfile path', function () use ($dsstatsSource) {
	expect($dsstatsSource)->toContain("rrdtool_execute('file_exists ' . cacti_escapeshellarg(\$rrdfile)");
	expect($dsstatsSource)->toContain("dsstats_rrdtool_execute('info ' . cacti_escapeshellarg(\$rrdfile)");
	expect($dsstatsSource)->not->toContain('"info $rrdfile"');
});

test('the data source info reader quotes the RRDfile path', function () use ($functionsSource) {
	expect($functionsSource)->toContain("rrdtool_execute('info ' . cacti_escapeshellarg(\$rrdfile)");
	expect($functionsSource)->not->toContain('rrdtool_execute("info $rrdfile"');
});

// --- dump, restore, tune and resize ---

test('the RRDfile check quotes dump and restore paths', function () use ($rrdSource) {
	expect($rrdSource)->toContain("'dump ' . cacti_escapeshellarg(\$file)");
	expect($rrdSource)->toContain("'restore -f ' . cacti_escapeshellarg(\$xml_file) . ' ' . cacti_escapeshellarg(\$new_file)");
	expect($rrdSource)->not->toContain('rrdtool_execute("dump $file"');
	expect($rrdSource)->not->toContain('rrdtool_execute("restore -f $xml_file $file"');
	expect($rrdSource)->not->toContain("\$xml_file = \$file . '.xml'");
});

test('tune and resize recommendations are quoted where they are built', function () use ($rrdSource) {
	expect($rrdSource)->toContain("cacti_escapeshellarg(\$info['filename']) . ' --data-source-type '");
	expect($rrdSource)->toContain("cacti_escapeshellarg(\$info['filename']) . ' --heartbeat '");
	expect($rrdSource)->toContain("cacti_escapeshellarg(\$info['filename']) . ' --minimum '");
	expect($rrdSource)->not->toContain("\$diff['tune'][]                        = \$info['filename'] . ' ' . '--data-source-type '");
});

// --- the sweep that catches a future caller ---

test('no RRDtool caller interpolates a bare path into the command line', function () use ($rrdSource, $boostSource, $dsstatsSource, $functionsSource) {
	$offenders = [];
	$pattern   = '/rrdtool_execute\(\s*"[^"\r\n]*\$(rrd_path|rrdfile|data_source_path|xml_file|file)\b/';

	expect(preg_match($pattern, 'rrdtool_execute("update --start -1h $rrdfile")'))->toBe(1);

	foreach ([
		'lib/rrd.php'       => $rrdSource,
		'lib/boost.php'     => $boostSource,
		'lib/dsstats.php'   => $dsstatsSource,
		'lib/functions.php' => $functionsSource
	] as $name => $source) {
		if (preg_match_all($pattern, $source, $m)) {
			foreach ($m[0] as $hit) {
				$offenders[] = $name . ': ' . $hit;
			}
		}
	}

	expect($offenders)->toBe([]);
});
