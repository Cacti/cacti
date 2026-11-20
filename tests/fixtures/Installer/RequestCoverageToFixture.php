<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Turns the request coverage the installer scenarios publish into the per-file line map the
// changed-code gate reads from fixtures. Usage: RequestCoverageToFixture.php INPUT_DIR OUTPUT_FILE
[, $input, $output] = $argv + [null, null, null];

if ($input === null || $output === null || !is_dir($input)) {
	fwrite(STDERR, "usage: RequestCoverageToFixture.php INPUT_DIR OUTPUT_FILE\n");

	exit(2);
}

$local  = realpath(dirname(__DIR__, 3));
$merged = [];

foreach (glob($input . '/*.json') ?: [] as $file) {
	$report = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
	$root   = rtrim($report['root'], '/');

	foreach ($report['coverage'] as $path => $lines) {
		$target = $local . substr($path, strlen($root));

		foreach ($lines as $number => $hits) {
			$merged[$target]['lines'][$number] = max($hits, $merged[$target]['lines'][$number] ?? -2);
		}
	}
}

file_put_contents($output, json_encode($merged, JSON_THROW_ON_ERROR));
