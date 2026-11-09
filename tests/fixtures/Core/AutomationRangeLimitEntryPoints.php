<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiAutomationEntryPointsFixture;

use CactiSettings;
use RuntimeException;

// Checks the range limit where it meets the setting definition, the 1.3.0
// upgrade and the poller entry point. The upgrade statement and the poller
// block are top-level or long-function code, so the exact checked-in source is
// cut out at its original line numbers and run against recording doubles. The
// poller block ends in exit(1), so its failure path runs in a child process.
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';

$root = dirname(__DIR__, 3);

/**
 * Loads a checked-in statement block into this namespace at its source lines.
 *
 * @param string $file    The source file.
 * @param string $pattern A pattern matching exactly one block.
 *
 * @return \Closure A closure running the block with $network_id in scope.
 */
function source_block(string $file, string $pattern) : \Closure {
	$source = file_get_contents($file);

	if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE) !== 1) {
		throw new RuntimeException('Expected one source block in ' . basename($file));
	}
	[$block, $offset] = $matches[0][0];
	$line             = substr_count(substr($source, 0, $offset), "\n") + 1;
	$temporary        = tempnam(sys_get_temp_dir(), 'cacti-entry-block-');
	file_put_contents($temporary, '<?php namespace ' . __NAMESPACE__ . '; return static function (int $network_id) {' . str_repeat("\n", $line - 1) . $block . '};');
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);

	try {
		return require $temporary;
	} finally {
		unlink($temporary);
	}
}

$GLOBALS['cacti_entry_probe'] = ['prime' => true, 'calls' => []];

/**
 * Prime double returning the configured outcome.
 *
 * @param int $network_id The network.
 *
 * @return bool The configured prime result.
 */
function automation_primeIPAddressTable(int $network_id) : bool {
	$GLOBALS['cacti_entry_probe']['calls'][] = ['prime', $network_id];

	return $GLOBALS['cacti_entry_probe']['prime'];
}

/**
 * Database double recording statements.
 *
 * @param string $sql    The statement.
 * @param array  $params The bound parameters.
 *
 * @return bool Always true.
 */
function db_execute_prepared(string $sql, array $params = []) : bool {
	$GLOBALS['cacti_entry_probe']['calls'][] = ['execute', preg_replace('/\s+/', ' ', $sql), $params];

	return true;
}

/**
 * Task cleanup double.
 *
 * @param int $network_id The network.
 *
 * @return void
 */
function clearAllTasks(int $network_id) : void {
	$GLOBALS['cacti_entry_probe']['calls'][] = ['clear', $network_id];
}

/**
 * Schema double recording index requests.
 *
 * @param mixed ...$args The db_install_add_key() arguments.
 *
 * @return void
 */
function db_install_add_key(...$args) : void {
	$GLOBALS['cacti_entry_probe']['calls'][] = ['add_key', $args];
}

$pollerBlock = '/^\tif \(!automation_primeIPAddressTable\(\$network_id\)\) \{\n.*?^\t\}\n/ms';

if (($argv[1] ?? '') === '--poller-failure') {
	$GLOBALS['cacti_entry_probe']['prime'] = false;
	register_shutdown_function(static function () : void {
		print json_encode($GLOBALS['cacti_entry_probe']['calls'], JSON_THROW_ON_ERROR) . "\n";
	});
	source_block($root . '/poller_automation.php', $pollerBlock)(7);

	throw new RuntimeException('poller block returned after a failed prime');
}

$passed = 0;

// The setting definition and its constraints, loaded through the bootstrap.
require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once $root . '/include/global.php';
require_once $root . '/lib/CactiSettings.php';

$definition = $GLOBALS['settings']['general']['automation_max_network_ips'] ?? null;

if (!is_array($definition) || $definition['default'] !== '65536' || $definition['method'] !== 'textbox') {
	throw new RuntimeException('automation_max_network_ips setting is missing or changed');
}

foreach (['65536' => false, '1' => false, '16777216' => false, '0' => true, '16777217' => true, '-5' => true, '1e3' => true] as $value => $rejected) {
	$violations = CactiSettings::validate(['automation_max_network_ips' => (string) $value], ['general' => ['automation_max_network_ips' => $definition]]);

	if (isset($violations['automation_max_network_ips']) !== $rejected) {
		throw new RuntimeException('automation_max_network_ips accepted or refused ' . $value . ' unexpectedly');
	}
	$passed++;
}

// The 1.3.0 upgrade adds the BTREE worker claim index.
$GLOBALS['cacti_entry_probe']['calls'] = [];
source_block($root . '/install/upgrades/1_3_0.php', "/^\tdb_install_add_key\('automation_ips',[^\n]*\n/m")(0);

if ($GLOBALS['cacti_entry_probe']['calls'] !== [['add_key', ['automation_ips', 'INDEX', 'network_status_pid', ['network_id', 'status', 'pid'], 'BTREE']]]) {
	throw new RuntimeException('1.3.0 upgrade no longer adds the automation_ips claim index');
}
$passed++;

// A primed network continues to the scan without cleanup.
$GLOBALS['cacti_entry_probe']['calls'] = [];
source_block($root . '/poller_automation.php', $pollerBlock)(7);

if ($GLOBALS['cacti_entry_probe']['calls'] !== [['prime', 7]]) {
	throw new RuntimeException('poller cleaned up after a successful prime');
}
$passed++;

// A failed prime removes the partial rows, clears the tasks and exits 1.
$childCoverage = null;
$environment   = getenv();

if (getenv('CACTI_COVERAGE_FILE')) {
	$childCoverage                      = tempnam(sys_get_temp_dir(), 'cacti-entry-coverage-');
	$environment['CACTI_COVERAGE_FILE'] = $childCoverage;
}
$pipes   = [];
$process = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', __FILE__, '--poller-failure'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);

if (!is_resource($process)) {
	throw new RuntimeException('could not start the poller failure child');
}
$output = stream_get_contents($pipes[1]);
$errors = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$status = proc_close($process);

if ($status !== 1 || trim($errors) !== '') {
	throw new RuntimeException('poller failure child exited ' . $status . ': ' . trim($errors));
}

if (json_decode($output, true, 512, JSON_THROW_ON_ERROR) !== [['prime', 7], ['execute', 'DELETE FROM automation_ips WHERE network_id = ?', [7]], ['clear', 7]]) {
	throw new RuntimeException('poller failure path did not remove the partial network: ' . trim($output));
}
$passed++;

// Fold the child's line hits into this probe's evidence after it is written.
if ($childCoverage !== null) {
	register_shutdown_function(static function () use ($childCoverage) : void {
		$target = getenv('CACTI_COVERAGE_FILE');
		$data   = json_decode(file_get_contents($target), true, 512, JSON_THROW_ON_ERROR);

		foreach (json_decode(file_get_contents($childCoverage), true, 512, JSON_THROW_ON_ERROR) as $file => $child) {
			foreach ($child['lines'] as $line => $hits) {
				$data[$file]['lines'][$line] = max($hits, $data[$file]['lines'][$line] ?? -2);
			}
		}
		unlink($childCoverage);
		file_put_contents($target, json_encode($data, JSON_THROW_ON_ERROR));
	});
}

print json_encode(['passed' => $passed], JSON_THROW_ON_ERROR) . "\n";
