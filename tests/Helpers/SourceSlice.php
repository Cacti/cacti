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
 * Load code built from checked-in source through a temporary file instead of
 * eval(), for a fragment that is not a whole function, or for stubs that must
 * share the fixture namespace. Only checked-in source may be passed.
 */
function cacti_fixture_require_code(string $code) : void {
	$temporary = tempnam(sys_get_temp_dir(), 'cacti-source-slice-');
	file_put_contents($temporary, '<?php ' . $code);

	try {
		require $temporary;
	} finally {
		unlink($temporary);
	}
}
