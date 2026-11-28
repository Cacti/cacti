<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\RraContainmentSkip;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

$root                              = dirname(__DIR__, 4);
$GLOBALS['cacti_fixture_contract'] = [
	'name'      => 'rra-containment-skip', 'complete' => false, 'assertions' => 0,
	'producers' => ['tests/fixtures/Core/Rrd/RraContainmentSkip.php', 'tests/Helpers/SourceSlice.php', 'tests/Helpers/FixtureCoverage.php', 'tests/Helpers/RequestCoverage.php'],
];
require $root . '/tests/Helpers/FixtureCoverage.php';

require $root . '/include/vendor/autoload.php';
require $root . '/tests/Helpers/SourceSlice.php';

function db_fetch_cell(string $sql) : int {
	$GLOBALS['lock_queries'][] = $sql;

	return 1;
}

function db_fetch_cell_prepared(string $sql, array $params) : int {
	$GLOBALS['lock_queries'][] = $params[0];

	return 1;
}

$directory = sys_get_temp_dir() . '/cacti-rra-' . bin2hex(random_bytes(8));
mkdir($directory . '/rra', 0700, true);
define('CACTI_PATH_RRA', $directory . '/rra');

foreach (['cacti_normalize_windows_path', 'cacti_path_is_within', 'data_source_path_within_rra'] as $function) {
	cacti_fixture_source_function($root . '/lib/functions.php', $function, __NAMESPACE__);
}

$external = $directory . '/manual.rrd';
$missing  = $directory . '/offline.rrd';
$managed  = CACTI_PATH_RRA . '/managed.rrd';
$invalid  = CACTI_PATH_RRA . '/notes.txt';
file_put_contents($external, 'external sample');
file_put_contents($managed, 'managed sample');
file_put_contents($invalid, 'notes');
$GLOBALS['lock_queries'] = [];

try {
	$rows = [];

	foreach ([$external, $missing, $managed, $invalid] as $index => $path) {
		$rows[] = [
			'local_data_id'    => $index + 1,
			'data_source_path' => $path,
			'rrd_path'         => $path,
			'new_rrd_path'     => $index < 2 ? CACTI_PATH_RRA . '/device/new.rrd' : $path,
		];
	}

	ob_start();

	try {
		$result = cacti_fixture_source_block($root . '/cli/structure_rra_paths.php', __NAMESPACE__,
			'/^(foreach \(\$data_sources as \$info\) \{.*?^\})/ms',
			['data_sources' => $rows, 'done_count' => 0, 'warn_count' => 0, 'skip_count' => 0, 'started' => false]);
	} finally {
		$output = (string) ob_get_clean();
	}

	if ($result['skip_count'] !== 3 || $result['warn_count'] !== 1 || $result['done_count'] !== 0) {
		throw new \RuntimeException('Expected both external RRDs and the correctly placed managed RRD to be skipped');
	}

	if ($output !== "WARNING: RRD Path '$invalid' is not an RRD file inside the RRA directory, Skipping" . PHP_EOL) {
		throw new \RuntimeException('Only the managed non-RRD file should generate a warning');
	}

	if (file_get_contents($external) !== 'external sample' || file_exists($missing) || is_dir(CACTI_PATH_RRA . '/device')) {
		throw new \RuntimeException('Manual files and their proposed destinations must stay untouched');
	}

	if ($GLOBALS['lock_queries'] !== ["SELECT GET_LOCK('boost.single_ds.3', 1)", "SELECT RELEASE_LOCK('boost.single_ds.3')",
		"SELECT GET_LOCK('boost.single_ds.4', 1)", 'boost.single_ds.4']) {
		throw new \RuntimeException('Only managed files should acquire and release a migration lock');
	}
} finally {
	unlink($invalid);
	unlink($managed);
	unlink($external);
	rmdir(CACTI_PATH_RRA);
	rmdir($directory);
}

$GLOBALS['cacti_fixture_contract']['assertions'] = 4;
$GLOBALS['cacti_fixture_contract']['complete']   = true;

print "PASS: RRA-containment guard skips external RRDs quietly and warns for managed non-RRD files\n";
