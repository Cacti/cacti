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
	'producers' => ['tests/fixtures/Core/Rrd/RraContainmentSkip.php', 'tests/Helpers/RequestCoverage.php'],
];
require $root . '/tests/Helpers/RequestCoverage.php';

/*
 * Collaborators the migration loop's RRA-containment guard calls.  Confining
 * them to this fixture namespace lets the unchanged guard statement run outside
 * cli/structure_rra_paths.php's database- and poller-driven main loop.  The
 * stub reports the path as outside the RRA tree so the guard takes its skip
 * branch, which is the behaviour under test.
 */
function data_source_path_within_rra(string $path) : bool {
	return false;
}

function db_fetch_cell_prepared(string $sql, array $params = []) {
	return '1';
}

// Slice the unchanged guard statement out of the CLI script, preserving line
// numbers so Xdebug results map back, and wrap it in a one-iteration loop so
// its `continue` is valid in isolation.
$file   = $root . '/cli/structure_rra_paths.php';
$source = (string) file_get_contents($file);

if (!preg_match('/^\tif \(file_exists\(\$old_rrd_path\).*?^\t\}/ms', $source, $match, PREG_OFFSET_CAPTURE)) {
	throw new \RuntimeException('Cannot locate the RRA-containment guard');
}
$line      = substr_count(substr($source, 0, $match[0][1]), "\n") + 1;
$temporary = tempnam(sys_get_temp_dir(), 'cacti-rra-guard-');
file_put_contents(
	$temporary,
	'<?php namespace ' . __NAMESPACE__ . ';' . str_repeat("\n", $line - 2)
		. "foreach (\$__rows as \$_) {\n" . $match[0][0] . "\n}\n"
);
$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);

$old_rrd_path  = tempnam(sys_get_temp_dir(), 'cacti-not-an-rrd-') . '.txt';
file_put_contents($old_rrd_path, 'not an rrd');
$local_data_id = 42;
$warn_count    = 0;
$__rows        = [0];

ob_start();

try {
	require $temporary;
} finally {
	unlink($temporary);
	$printed = (string) ob_get_clean();
	@unlink($old_rrd_path);
}

if ($warn_count !== 1) {
	throw new \RuntimeException('The guard must increment the warning count when skipping a non-RRA path');
}

if (strpos($printed, 'not an RRD file inside the RRA directory') === false) {
	throw new \RuntimeException('The guard must warn about the skipped path');
}

$GLOBALS['cacti_fixture_contract']['assertions'] = 2;
$GLOBALS['cacti_fixture_contract']['complete']   = true;

print "PASS: RRA-containment guard skips a stored path outside the RRA directory\n";
