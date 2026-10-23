<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

require_once __DIR__ . '/PhpSource.php';

/**
 * Load an unchanged production function into a fixture namespace, avoiding a
 * web entry point's auth/dispatch side effects and process-global fake APIs.
 * Retain original line numbers so Xdebug results can be mapped to the source.
 * Only checked-in source may be passed; never request data or remote content.
 */
function cacti_fixture_source_function(string $file, string $function, string $namespace) : void {
	$source = file_get_contents($file);

	if ($source === false) {
		throw new RuntimeException('Cannot read source file ' . $file);
	}
	$definition = \Cacti\Tests\Helpers\PhpSource::functionDefinition($source, $function);
	$line       = $definition['line'];
	$temporary  = tempnam(sys_get_temp_dir(), 'cacti-source-slice-');
	file_put_contents($temporary, '<?php namespace ' . $namespace . ';' . str_repeat("\n", $line - 1) . $definition['source']);
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);

	try {
		require $temporary;
	} finally {
		unlink($temporary);
	}
}
