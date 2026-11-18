<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

use SebastianBergmann\CodeCoverage\Data\RawCodeCoverageData;
use SebastianBergmann\CodeCoverage\StaticAnalysis\ParsingFileAnalyser;

require dirname(__DIR__, 2) . '/include/vendor/autoload.php';

// Use the locked PHPUnit analyser so multiline expressions have the same
// meaning in native request reports and the PHPUnit Clover report.
if ($argc !== 4) {
	fwrite(STDERR, "Usage: php normalize_request_coverage.php INPUT_DIRECTORY OUTPUT_DIRECTORY CLOVER_REPORT\n");
	exit(2);
}

$root    = realpath(dirname(__DIR__, 2));
$input   = realpath($argv[1]);
$output  = $argv[2];
$written = [];
$created = false;

try {
	$clover = simplexml_load_file($argv[3], SimpleXMLElement::class, LIBXML_NONET);

	if ($clover === false) {
		throw new RuntimeException('Could not read the PHPUnit coverage scope.');
	}
	$scope = [];

	foreach ($clover->xpath('//file') as $file) {
		$source = realpath((string) $file['name']);

		if ($source !== false && str_starts_with($source, $root . '/')) {
			$scope[substr($source, strlen($root) + 1)] = true;
		}
	}

	if (!$scope) {
		throw new RuntimeException('The PHPUnit coverage scope contains no local files.');
	}
	$reports = $input === false ? [] : glob($input . '/*.json');

	if (!$reports) {
		throw new RuntimeException('Request coverage directory contains no reports.');
	}

	if (file_exists($output) || !mkdir($output, 0700)) {
		throw new RuntimeException('Normalization requires a new output directory.');
	}
	$created  = true;
	$analyser = new ParsingFileAnalyser(false, false);

	foreach ($reports as $path) {
		$bytes  = file_get_contents($path);
		$report = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);

		if (!is_string($report['root'] ?? null) || !is_array($report['coverage'] ?? null) || isset($report['normalization'])) {
			throw new RuntimeException('Expected an unprocessed native request report.');
		}
		$raw = RawCodeCoverageData::fromXdebugWithoutPathCoverage($report['coverage']);

		foreach (array_keys($report['coverage']) as $file) {
			$prefix = rtrim($report['root'], '/') . '/';

			if (!str_starts_with($file, $prefix)) {
				throw new RuntimeException('Coverage source is outside its recorded root.');
			}
			$relative = substr($file, strlen($prefix));

			// Generated configuration and unrelated fixture code are not part of
			// the changed-file denominator. Preserve their raw evidence verbatim.
			if (!isset($scope[$relative])) {
				continue;
			}
			$local    = realpath($root . '/' . $relative);

			if ($local === false || !str_starts_with($local, $root . '/') || !is_file($local)
				|| hash_file('sha256', $local) !== ($report['hashes'][$file] ?? null)) {
				throw new RuntimeException('Coverage source does not match ' . $relative);
			}
			$map = $analyser->executableLinesIn($local);
			$raw->keepLineCoverageDataOnlyForLines($file, array_keys($map));
			$raw->markExecutableLineByBranch($file, $map);
		}
		$report['coverage']      = $raw->lineCoverage();
		$report['normalization'] = [
			'input_sha256'         => hash('sha256', $bytes),
			'normalizer_sha256'    => hash_file('sha256', __FILE__),
			'composer_lock_sha256' => hash_file('sha256', $root . '/composer.lock'),
		];
		$destination = $output . '/' . basename($path);
		$written[]   = $destination;
		$encoded     = json_encode($report, JSON_THROW_ON_ERROR);

		if (file_put_contents($destination, $encoded, LOCK_EX) !== strlen($encoded)) {
			throw new RuntimeException('Could not write normalized coverage.');
		}
	}
	printf("Normalized %d native request reports using the locked PHPUnit analyser.\n", count($written));
} catch (Throwable $error) {
	foreach ($written as $path) {
		if (is_file($path)) {
			unlink($path);
		}
	}

	if ($created) {
		rmdir($output);
	}
	fwrite(STDERR, $error->getMessage() . "\n");
	exit(1);
}
