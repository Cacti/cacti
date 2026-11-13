<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/**
 * Load an unchanged production function into a fixture namespace, avoiding a
 * web entry point's auth/dispatch side effects and process-global fake APIs.
 * Retain original line numbers so Xdebug results can be mapped to the source.
 * Only checked-in source may be passed; never request data or remote content.
 */
function cacti_fixture_source_function(string $file, string $function, string $namespace) : void {
	$source = file_get_contents($file);

	if (!preg_match('/^function ' . preg_quote($function, '/') . '\([^\n]*.*?^\}/ms', $source, $match, PREG_OFFSET_CAPTURE)) {
		throw new RuntimeException('Cannot locate source function ' . $function);
	}
	$line      = substr_count(substr($source, 0, $match[0][1]), "\n") + 1;
	$temporary = tempnam(sys_get_temp_dir(), 'cacti-source-slice-');
	file_put_contents($temporary, '<?php ' . ($namespace !== '' ? 'namespace ' . $namespace . ';' : '') . str_repeat("\n", $line - 1) . $match[0][0]);
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);

	try {
		require $temporary;
	} finally {
		unlink($temporary);
	}
}

/**
 * Find the lines of one top-level block in an unchanged production file.
 *
 * Script entry points such as cmd.php run most of their work outside any
 * function, so cacti_fixture_source_function() cannot reach it.  The block is
 * located by its content rather than by line number so the fixture follows
 * the code when unrelated lines move.
 *
 * @param string $file  checked-in source file
 * @param string $start pattern that matches exactly one line, the block's first
 * @param string $end   pattern for the block's last line, searched from $start on
 *
 * @return array{0: int, 1: int} the first and last line numbers, counted from 1
 */
function cacti_fixture_source_range(string $file, string $start, string $end) : array {
	$lines = file($file, FILE_IGNORE_NEW_LINES);
	$first = array_keys(preg_grep($start, $lines));

	if (count($first) !== 1) {
		throw new RuntimeException('Expected one line matching ' . $start . ' in ' . $file . ', found ' . count($first));
	}

	for ($index = $first[0]; $index < count($lines); $index++) {
		if (preg_match($end, $lines[$index])) {
			return [$first[0] + 1, $index + 1];
		}
	}

	throw new RuntimeException('Cannot find the end of the block at ' . $start . ' in ' . $file);
}

/**
 * Compile verbatim line ranges of an unchanged production file into a fixture
 * namespace, every line kept at its original number so Xdebug results can be
 * mapped back to the source.  Lines outside the ranges are left blank.
 *
 * Top-level script code shares variables with whoever includes it, so the
 * caller requires the returned file in the scope those lines expect and then
 * unlinks it.  Only checked-in source may be passed; never request data or
 * remote content.
 *
 * @param string                      $file      checked-in source file
 * @param list<array{0: int, 1: int}> $ranges    ascending, non-overlapping line ranges, none on line 1
 * @param string                      $namespace fixture namespace the lines are compiled into
 *
 * @return string path of the temporary file to require
 */
function cacti_fixture_source_lines(string $file, array $ranges, string $namespace) : string {
	$lines  = file($file, FILE_IGNORE_NEW_LINES);
	$output = array_fill(0, count($lines), '');
	$last   = 1;

	foreach ($ranges as [$first, $final]) {
		if ($first <= $last || $final < $first || $final > count($lines)) {
			throw new RuntimeException('Line ranges must ascend, must not overlap and must not touch line 1');
		}

		for ($line = $first; $line <= $final; $line++) {
			$output[$line - 1] = $lines[$line - 1];
		}

		$last = $final;
	}

	$output[0] = '<?php ' . ($namespace !== '' ? 'namespace ' . $namespace . ';' : '');
	$temporary = tempnam(sys_get_temp_dir(), 'cacti-source-lines-');

	file_put_contents($temporary, implode("\n", array_slice($output, 0, $last)) . "\n");
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);

	return $temporary;
}
