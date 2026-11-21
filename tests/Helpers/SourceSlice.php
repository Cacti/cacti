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
	file_put_contents($temporary, '<?php ' . ($namespace !== '' ? 'namespace ' . $namespace . ';' : '') . str_repeat("\n", $line - 1) . $definition['source']);
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);

	try {
		require $temporary;
	} finally {
		unlink($temporary);
	}
}

/** Run a checked-in controller block with its original line numbers and fixture inputs. */
function cacti_fixture_source_block(string $file, string $namespace, string $pattern, array $locals = []) : array {
	$text = file_get_contents($file);

	if (preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE) !== 1) {
		throw new RuntimeException('Cannot locate controller block in ' . $file);
	}
	[$code, $offset]                                 = $matches[1];
	$temporary                                       = tempnam(sys_get_temp_dir(), 'cacti-startup-');
	$GLOBALS['cacti_fixture_source_map'][$temporary] = $file;
	file_put_contents($temporary, '<?php namespace ' . $namespace . ';' . str_repeat("\n", substr_count(substr($text, 0, $offset), "\n")) . $code);
	register_shutdown_function(static function () use ($temporary) : void {
		if (is_file($temporary)) {
			unlink($temporary);
		}
	});
	extract($locals, EXTR_SKIP);
	require $temporary;

	return get_defined_vars();
}
