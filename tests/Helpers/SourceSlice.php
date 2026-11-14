<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/**
 * Load an unchanged production function into a fixture namespace, avoiding a
 * web entry point's auth/dispatch side effects and process-global fake APIs.
 * Retain original line numbers so Xdebug results can be mapped to the source.
 * Only checked-in source may be passed; never request data or remote content.
 */
function cacti_fixture_source_function(string $file, string $function, string $namespace) : void {
	require_once __DIR__ . '/PhpSource.php';
	$source = file_get_contents($file);

	if ($source === false || realpath($file) === false) {
		throw new RuntimeException('Cannot read source file ' . $file);
	}
	$definition = \Cacti\Tests\Helpers\PhpSource::functionDefinition($source, $function);

	if ($namespace !== '' && !preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/D', $namespace)) {
		throw new InvalidArgumentException('Invalid fixture namespace');
	}
	$temporary = tempnam(sys_get_temp_dir(), 'cacti-source-slice-');

	if ($temporary === false) {
		throw new RuntimeException('Cannot create source fixture');
	}
	$code = '<?php ' . ($namespace === '' ? '' : 'namespace ' . $namespace . ';') . str_repeat("\n", $definition['line'] - 1) . $definition['code'];

	if (file_put_contents($temporary, $code) !== strlen($code)) {
		unlink($temporary);

		throw new RuntimeException('Cannot write complete source fixture');
	}
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);

	try {
		require $temporary;
	} finally {
		unlink($temporary);
	}
}
