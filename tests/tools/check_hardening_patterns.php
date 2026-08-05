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

$options = getopt('', ['base:', 'head:']);
$base    = $options['base'] ?? '';
$head    = $options['head'] ?? 'HEAD';
$files   = cli_file_args($argv);

/* Explicit file arguments get a whole-file scan for local use.  CI runs
   derive the file list from the diff and must only flag lines a PR added
   or modified, so legacy patterns elsewhere in a touched file do not
   block unrelated work. */
$changed_lines = null;

if ($files === []) {
	$changed_lines = changed_php_lines($base, $head);
	$files         = array_keys($changed_lines);
}

$patterns = [
	'raw-superglobal-request' => [
		'pattern' => '/\$_(?:GET|POST|REQUEST)\s*\[/',
		'message' => 'Use Cacti request helpers or typed filter objects instead of raw request superglobals.',
		'allow'   => [
			// pre-boot code; the request helper layer is not loaded yet
			'include/global.php',
			'lib/PackageListFilter.php',
			// test fixture standing in for the request helper itself
			'tests/HandOff/fixtures/cacti_dispatch_probe.php',
		]
	],
//	'manual-request-query-url' => [
//		'pattern' => '/[?&][A-Za-z0-9_-]+=\s*[\'"]?\s*\.\s*g(?:r|fr|nr)v\s*\(/',
//		'message' => 'Use a URL builder or typed page-state URL method instead of concatenating request values into query strings.'
//	],
	'inline-js-php-string' => [
		'pattern' => '/(?:var|let|const)\s+[A-Za-z0-9_$]+\s*=\s*[\'"][^\'"]*<\?php\s+print\s+/',
		'message' => 'Use JSON encoding for PHP values injected into JavaScript.'
	],
	'raw-request-value-attribute' => [
		'pattern' => '/value\s*=\s*[\'"][^\'"]*<\?php\s+print\s+g(?:r|fr|nr)v\s*\(/',
		'message' => 'Use form rendering helpers for request-backed input values.'
	],
	'serialized-hidden-state' => [
		'pattern' => '/(?:type\s*=\s*[\'"]hidden[\'"].*serialize\s*\(|html_hidden_input\s*\([^;]*serialize\s*\()/',
		'message' => 'Do not round-trip serialized PHP state through hidden fields.'
	],
];

$violations = [];

foreach ($files as $file) {
	if (!is_file($file) || pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
		continue;
	}

	/* The guard protects product code.  Test fixtures legitimately assign
	   request superglobals and embed inline patterns to exercise the code
	   under test, so the tests tree is out of scope. */
	if (str_starts_with(preg_replace('#^\./#', '', $file), 'tests/')) {
		continue;
	}

	$lines = file($file, FILE_IGNORE_NEW_LINES);

	if ($lines === false) {
		continue;
	}

	foreach ($lines as $index => $line) {
		if ($changed_lines !== null && !isset($changed_lines[$file][$index + 1])) {
			continue;
		}

		foreach ($patterns as $name => $rule) {
			if (isset($rule['allow']) && in_array($file, $rule['allow'], true)) {
				continue;
			}

			if (preg_match($rule['pattern'], $line) === 1) {
				$violations[] = sprintf('%s:%d: %s: %s', $file, $index + 1, $name, $rule['message']);
			}
		}
	}
}

if ($violations !== []) {
	fwrite(STDERR, "Hardening pattern guard found unsafe changed-file patterns:\n");
	fwrite(STDERR, implode("\n", $violations) . "\n");

	exit(1);
}

exit(0);

function changed_php_lines(string $base, string $head) : array {
	if ($base === '') {
		$base = trim((string) shell_exec('git merge-base HEAD upstream/develop 2>/dev/null'));
	}

	if ($base === '') {
		$base = trim((string) shell_exec('git merge-base HEAD origin/develop 2>/dev/null'));
	}

	if ($base === '') {
		return [];
	}

	/* -U0 keeps hunk headers tight so only added/modified new-side lines
	   are recorded; --no-ext-diff and the explicit prefixes guard against
	   local diff drivers or diff.noprefix breaking the parse. */
	$command = sprintf(
		'git diff -U0 --no-ext-diff --src-prefix=a/ --dst-prefix=b/ --diff-filter=ACMR %s...%s -- %s 2>/dev/null',
		escapeshellarg($base),
		escapeshellarg($head),
		escapeshellarg('*.php')
	);

	$output = shell_exec($command);

	if (!is_string($output) || trim($output) === '') {
		return [];
	}

	$changed = [];
	$file    = '';

	foreach (preg_split('/\R/', $output) as $line) {
		if (preg_match('/^\+\+\+ b\/(.+)$/', $line, $matches) === 1) {
			$file = $matches[1];
		} elseif ($file !== '' && preg_match('/^@@ -\d+(?:,\d+)? \+(\d+)(?:,(\d+))? @@/', $line, $matches) === 1) {
			$start = (int) $matches[1];
			$count = isset($matches[2]) ? (int) $matches[2] : 1;

			for ($i = 0; $i < $count; $i++) {
				$changed[$file][$start + $i] = true;
			}
		}
	}

	return $changed;
}

function cli_file_args(array $argv) : array {
	$files = [];

	for ($i = 1; $i < count($argv); $i++) {
		$arg = $argv[$i];

		if ($arg === '--base' || $arg === '--head') {
			$i++;

			continue;
		}

		if (str_starts_with($arg, '--base=') || str_starts_with($arg, '--head=')) {
			continue;
		}

		if (str_starts_with($arg, '--')) {
			continue;
		}

		$files[] = $arg;
	}

	return $files;
}
