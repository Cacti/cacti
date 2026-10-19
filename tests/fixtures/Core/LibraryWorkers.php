<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/** Model request-local state without carrying extension aliases between requests. */
function library_worker(string $scenario): void {
	global $assertions;
	$temporary = sys_get_temp_dir() . '/cacti-worker-' . bin2hex(random_bytes(8));
	library_expect(mkdir($temporary, 0700), true, 'Worker owns its coverage directory');

	try {
		$process = new Symfony\Component\Process\Process([PHP_BINARY, '-d', 'memory_limit=1G', __DIR__ . '/LibraryWorker.php', $scenario], CACTI_PATH_BASE,
			['CACTI_PR_RAW_COVERAGE' => $temporary, 'XDEBUG_MODE' => 'coverage'], null, 120);
		$process->run();
		library_expect($process->getExitCode(), 0, 'Worker completed: ' . $process->getErrorOutput() . $process->getOutput());
		$result = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);
		library_expect($result['scenario'], $scenario, 'Worker executed the requested scenario');
		library_expect($result['assertions'] > 0, true, 'Worker executed behavioral assertions');
		$reports = glob($temporary . '/library-worker-*.json');
		library_expect(count($reports), 1, 'Worker supplies exactly one completed coverage report');
		$report = json_decode(file_get_contents($reports[0]), true, 512, JSON_THROW_ON_ERROR);
		library_expect($report['contract']['complete'], true, 'Worker coverage reports completion');
		library_expect($report['contract']['assertions'], $result['assertions'], 'Worker evidence matches its executed assertions');

		foreach ($report['contract']['producers'] as $producer) {
			library_expect($report['contract']['hashes'][$producer], hash_file('sha256', CACTI_PATH_BASE . '/' . $producer), 'Worker producer matches the tested source');
		}
		library_import_coverage($reports);
		$assertions += $result['assertions'];
	} finally {
		foreach (glob($temporary . '/*.json') as $path) {
			unlink($path);
		}
		rmdir($temporary);
	}
}
