<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/**
 * Open a slice namespace on one line. Production code runs in the global
 * namespace, so import the global classes a slice names unqualified; without
 * the import, catch (Throwable) and new RuntimeException() would resolve to
 * missing namespaced classes and change the control flow under test.
 */
function cacti_fixture_namespace_header(string $namespace, string $code) : string {
	if ($namespace === '') {
		return '';
	}
	$imports = [];

	preg_match_all('/\b(?:new|instanceof)\s+([A-Z]\w*)\b|\bcatch\s*\(([^)$]+)|\b([A-Z]\w*)::/', $code, $matches, PREG_SET_ORDER);

	foreach ($matches as $match) {
		foreach (preg_split('/\s*\|\s*/', trim(($match[1] ?? '') . ($match[2] ?? '') . ($match[3] ?? ''))) as $name) {
			if (preg_match('/^[A-Z]\w*$/', $name) && (class_exists($name) || interface_exists($name)) && !class_exists($namespace . '\\' . $name, false)) {
				$imports[$name] = 'use ' . $name . ';';
			}
		}
	}

	return 'namespace ' . $namespace . '; ' . implode(' ', $imports);
}

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
	file_put_contents($temporary, '<?php ' . cacti_fixture_namespace_header($namespace, $match[0][0]) . str_repeat("\n", $line - 1) . $match[0][0]);
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
	$namespaced  = cacti_fixture_namespace_header($namespace, $method);
	// Fixture-only members live in an unmapped parent so their unexecuted
	// lines cannot be credited to, or counted against, the production file.
	$base        = tempnam(sys_get_temp_dir(), 'cacti-source-base-');
	file_put_contents($base, '<?php ' . $namespaced . "\n#[\\AllowDynamicProperties]\nclass " . $class . "Base { public \$old_cacti_version; public \$runReport; public \$mode; public \$stepCurrent; public \$buttonNext;\n" . $extra . "\n}\n");
	$header      = '<?php ' . $namespaced . "\nclass " . $class . ' extends ' . $class . "Base {\n";
	$headerLines = substr_count($header, "\n");
	file_put_contents($temporary, $header . str_repeat("\n", max(0, $line - 1 - $headerLines)) . $method . "\n}\n");
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);

	try {
		require $base;
		require $temporary;
	} finally {
		unlink($base);
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

	$fragment  = implode('', array_slice($lines, $from - 1, $to - $from + 1));
	// The prefix runs from an unmapped file so its skipped stub lines are not
	// attributed to the production file. Both files share this function scope.
	$setup     = tempnam(sys_get_temp_dir(), 'cacti-source-setup-');
	$temporary = tempnam(sys_get_temp_dir(), 'cacti-source-map-');
	file_put_contents($setup, "<?php\n" . $prefix . "\n");
	file_put_contents($temporary, "<?php\n" . str_repeat("\n", max(0, $from - 2)) . $fragment);
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);

	try {
		require $setup;
		require $temporary;
	} finally {
		unlink($setup);
		unlink($temporary);
	}
}
