<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Filter;

require dirname(__DIR__, 2) . '/include/vendor/autoload.php';

$root       = dirname(__DIR__, 2);
$fixture    = $root . '/tests/fixtures/Core/RequestCoverageNormalization.php';
$temporary  = sys_get_temp_dir() . '/cacti-normalize-' . bin2hex(random_bytes(8));
$assertions = 0;

function normalization_expect(bool $condition, string $message): void {
	global $assertions;
	$assertions++;

	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function normalization_run(string $input, string $output): int {
	global $temporary;
	$process = proc_open([PHP_BINARY, __DIR__ . '/normalize_request_coverage.php', $input, $output, $temporary . '/coverage.xml'],
		[0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

	if (!is_resource($process)) {
		throw new RuntimeException('Could not start the normalizer.');
	}
	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$status = proc_close($process);

	if ($status !== 0) {
		print $stdout . $stderr;
	}

	return $status;
}

try {
	normalization_expect(mkdir($temporary, 0700), 'Test owns its temporary directory');
	normalization_expect(mkdir($temporary . '/raw', 0700), 'Test owns its input directory');
	file_put_contents($temporary . '/coverage.xml', '<coverage><project><file name="' . htmlspecialchars($fixture, ENT_QUOTES | ENT_XML1) . '"/></project></coverage>');
	$filter = new Filter();
	$filter->includeFile($fixture);
	$coverage = new CodeCoverage((new Selector())->forLineCoverage($filter), $filter);
	$coverage->start('admitted');
	require $fixture;
	$result = request_coverage_normalization(true);
	$raw    = xdebug_get_code_coverage();
	$coverage->stop();
	normalization_expect($result === 'first admitted last', 'The real multiline expression executed');
	$report = ['root' => $root, 'coverage' => [$fixture => $raw[$fixture]],
		'hashes'         => [$fixture => hash_file('sha256', $fixture)], 'contract' => null];
	$bytes = json_encode($report, JSON_THROW_ON_ERROR);
	file_put_contents($temporary . '/raw/request.json', $bytes);
	normalization_expect(normalization_run($temporary . '/raw', $temporary . '/normalized') === 0, 'Actual Xdebug report normalizes');
	$normalized = json_decode(file_get_contents($temporary . '/normalized/request.json'), true, 512, JSON_THROW_ON_ERROR);
	$actual     = array_keys(array_filter($normalized['coverage'][$fixture], static fn (int $hits): bool => $hits > 0));
	$expected   = array_keys(array_filter($coverage->getData()->lineCoverage()[$fixture], static fn (?array $tests): bool => !empty($tests)));
	sort($actual);
	sort($expected);
	normalization_expect($actual === $expected && count($actual) > 0, 'Normalized lines match PHPUnit processing of the same real execution');
	$source = file($fixture);

	foreach ($source as $index => $line) {
		if (str_contains($line, 'throw new RuntimeException')) {
			normalization_expect(!in_array($index + 1, $actual, true), 'An unexecuted denial remains uncovered');
		}

		if (str_contains($line, "'first',") || str_contains($line, "'last',")) {
			normalization_expect(in_array($index + 1, $actual, true), 'Executed multiline arguments receive coverage');
		}
	}
	normalization_expect($normalized['hashes'] === $report['hashes'], 'Production source hashes are preserved');
	normalization_expect($normalized['normalization']['input_sha256'] === hash('sha256', $bytes), 'Original raw evidence remains identifiable');
	normalization_expect(file_get_contents($temporary . '/raw/request.json') === $bytes, 'Raw evidence is unchanged');
	normalization_expect(normalization_run($temporary . '/raw', $temporary . '/normalized') !== 0, 'An existing output directory cannot be overwritten');
	$report['hashes'][$fixture] = str_repeat('0', 64);
	file_put_contents($temporary . '/raw/z-stale.json', json_encode($report, JSON_THROW_ON_ERROR));
	normalization_expect(normalization_run($temporary . '/raw', $temporary . '/invalid') !== 0, 'Stale source is rejected');
	normalization_expect(!file_exists($temporary . '/invalid'), 'Failure removes partial normalized evidence');
	unlink($temporary . '/raw/z-stale.json');
	$report['root'] = $root . '/outside';
	file_put_contents($temporary . '/raw/z-outside.json', json_encode($report, JSON_THROW_ON_ERROR));
	normalization_expect(normalization_run($temporary . '/raw', $temporary . '/outside') !== 0, 'Files outside the recorded root are rejected');
	mkdir($temporary . '/empty');
	normalization_expect(normalization_run($temporary . '/empty', $temporary . '/missing') !== 0, 'Empty discovery is a failure');
	printf("PASS: request coverage normalization (%d assertions)\n", $assertions);
} finally {
	if (is_dir($temporary)) {
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

		foreach ($files as $file) {
			$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}
		rmdir($temporary);
	}
}
