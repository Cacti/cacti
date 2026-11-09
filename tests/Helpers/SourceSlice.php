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
 * Load one production method into a disposable class at the original line
 * numbers so Xdebug maps hits back to the checked-in file.
 */
function cacti_fixture_source_method(string $file, string $method, string $namespace, string $class = 'FixtureHost', string $extra = '') : void {
	$source = file_get_contents($file);

	if (!preg_match('/^[ \t]*(?:public|private|protected)(?: static)? function ' . preg_quote($method, '/') . '\([^\n]*.*?^\t\}/ms', $source, $match, PREG_OFFSET_CAPTURE)) {
		throw new RuntimeException('Cannot locate source method ' . $method);
	}
	$line        = substr_count(substr($source, 0, $match[0][1]), "\n") + 1;
	$temporary   = tempnam(sys_get_temp_dir(), 'cacti-source-slice-');
	$method      = preg_replace('/\bprivate /', 'public ', $match[0][0], 1);
	$header      = '<?php ' . ($namespace !== '' ? 'namespace ' . $namespace . ';' : '') . "\n#[\\AllowDynamicProperties]\nclass " . $class . " { public \$old_cacti_version; public \$runReport; public \$mode; public \$stepCurrent; public \$buttonNext;\n" . $extra;
	$headerLines = substr_count($header, "\n");
	file_put_contents($temporary, $header . str_repeat("\n", max(0, $line - 1 - $headerLines)) . $method . "\n}\n");
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);

	try {
		require $temporary;
	} finally {
		unlink($temporary);
	}
}

/**
 * Require a production file at its original line numbers after replacing
 * individual lines. Mapped hits count against the checked-in path.
 *
 * @param array<int, string> $replacements 1-based line replacements
 */
function cacti_fixture_map_require(string $file, array $replacements = []) : mixed {
	$lines = file($file);

	if ($lines === false) {
		throw new RuntimeException('Cannot read ' . $file);
	}

	foreach ($replacements as $number => $text) {
		$lines[$number - 1] = str_ends_with($text, "\n") ? $text : $text . "\n";
	}
	$temporary = tempnam(sys_get_temp_dir(), 'cacti-source-map-');
	file_put_contents($temporary, implode('', $lines));
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);

	try {
		return require $temporary;
	} finally {
		unlink($temporary);
	}
}

/** Record Xdebug evidence for a file that has no executable added lines. */
function cacti_fixture_instrument_file(string $file) : void {
	$temporary = tempnam(sys_get_temp_dir(), 'cacti-source-map-');
	file_put_contents($temporary, "<?php\n");
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);

	try {
		require $temporary;
	} finally {
		unlink($temporary);
	}
}

/**
 * Execute a line range from a production file at those original numbers.
 */
function cacti_fixture_map_fragment(string $file, int $from, int $to, string $prefix, array $replacements = []) : void {
	$lines = file($file);

	if ($lines === false) {
		throw new RuntimeException('Cannot read ' . $file);
	}

	foreach ($replacements as $number => $text) {
		$lines[$number - 1] = str_ends_with($text, "\n") ? $text : $text . "\n";
	}

	$fragment    = implode('', array_slice($lines, $from - 1, $to - $from + 1));
	$header      = "<?php\n" . $prefix . (str_ends_with($prefix, "\n") ? '' : "\n");
	$headerLines = substr_count($header, "\n");
	$temporary   = tempnam(sys_get_temp_dir(), 'cacti-source-map-');
	file_put_contents($temporary, $header . str_repeat("\n", max(0, $from - 1 - $headerLines)) . $fragment);
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);

	try {
		require $temporary;
	} finally {
		unlink($temporary);
	}
}
