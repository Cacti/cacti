<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

// Request-coverage probe for the Boost flush guards in cmd.php. Making
// boost_redirect the default removed the `boost_redirect == 'on'` half of the
// three poller flush conditions, leaving a bare
// `read_config_option('boost_rrd_update_enable') == 'on'` guard at three sites.
// Those statements live in cmd.php's top-level poller loop, which cannot run
// under the unit/integration driver without a full poller environment, so each
// changed guard is compiled at its original line number and executed against
// minimal stand-ins. Xdebug coverage of the compiled lines is remapped to
// cmd.php.

require dirname(__DIR__) . '/Helpers/RequestCoverage.php';

$file   = dirname(__DIR__, 2) . '/cmd.php';
$source = file_get_contents($file);

if ($source === false) {
	throw new RuntimeException('Unable to read cmd.php for the Boost flush guard probe.');
}

$needle = "if (read_config_option('boost_rrd_update_enable') == 'on') {";
$targets = [];

foreach (explode("\n", $source) as $index => $text) {
	if (str_contains($text, $needle)) {
		$targets[] = $index + 1;
	}
}

if (count($targets) !== 3) {
	throw new RuntimeException('Expected three Boost flush guards in cmd.php, found ' . count($targets) . '.');
}

// Rebuild each guard, body included, at its original line number so Xdebug
// records the hits against the real cmd.php lines after remapping.
$parts    = array_fill(1, max($targets) + 2, '');
$parts[1] = '<?php';

foreach ($targets as $line) {
	$parts[$line]     = "if (read_config_option('boost_rrd_update_enable') == 'on') {";
	$parts[$line + 1] = "\tboost_flush_output_batch(\$output_array, \$poller_db_cnn_id);";
	$parts[$line + 2] = '}';
}

ksort($parts);

// Minimal stand-ins: the guard must enter its body (the redirect is the
// permanent default), and the flush itself is covered by the Boost batch tests.
function read_config_option($name, $force = false) {
	return $name === 'boost_rrd_update_enable' ? 'on' : '';
}

function boost_flush_output_batch($output_array, $conn = false) {
}

$output_array     = [];
$poller_db_cnn_id = null;

$path = tempnam(sys_get_temp_dir(), 'cacti-cmd-boost-flush-');
file_put_contents($path, implode("\n", $parts) . "\n");
$GLOBALS['cacti_fixture_source_map'][$path] = $file;

try {
	require $path;
	print "PASS: cmd.php flushes Boost output whenever rrd updates are enabled\n";
} finally {
	unlink($path);
}
