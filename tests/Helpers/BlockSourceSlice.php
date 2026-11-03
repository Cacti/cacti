<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/**
 * Load a bounded top-level production block into a fixture function.
 * The wrapper accepts an associative array, exposes it as local variables,
 * and returns every local after the block runs. Original line numbers are
 * retained so request coverage maps back to the production entry point.
 * Only checked-in source and fixture-owned variables may be passed.
 */
function cacti_fixture_source_block(string $file, string $startMarker, string $endMarker, string $function, string $namespace) : void {
	$source = file_get_contents($file);
	$start  = strpos($source, $startMarker);
	$end    = $start === false ? false : strpos($source, $endMarker, $start);

	if ($start === false || $end === false) {
		throw new RuntimeException('Cannot locate source block ' . $function);
	}

	$end      += strlen($endMarker);
	$line      = substr_count(substr($source, 0, $start), "\n") + 1;
	$block     = substr($source, $start, $end - $start);
	$temporary = tempnam(sys_get_temp_dir(), 'cacti-source-block-');
	$header    = '<?php ' . ($namespace === '' ? '' : 'namespace ' . $namespace . '; use \\RuntimeException; use \\Throwable;') .
		' function ' . $function . '($variables) { extract($variables, EXTR_SKIP);';
	file_put_contents($temporary, $header . str_repeat("\n", $line - 1) . $block . "\nreturn get_defined_vars(); }");
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);

	try {
		require $temporary;
	} finally {
		unlink($temporary);
	}
}
