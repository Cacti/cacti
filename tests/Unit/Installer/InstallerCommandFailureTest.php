<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

require_once CACTI_PATH_INSTALL . '/functions.php';
require_once CACTI_PATH_LIBRARY . '/installer.php';

test('installer commands preserve failed exit status and both output streams', function () {
	$installer = (new ReflectionClass(Installer::class))->newInstanceWithoutConstructor();
	$result    = (new ReflectionMethod(Installer::class, 'runCommand'))->invoke($installer, [
		PHP_BINARY,
		'-r',
		'fwrite(STDOUT, $argv[1] . PHP_EOL); fwrite(STDERR, "download failed\n"); exit(7);',
		'literal argument; $(echo unexpected)',
	]);

	expect($result['exitCode'])->toBe(7)
		->and($result['output'])->toContain('literal argument; $(echo unexpected)', 'download failed');
});

test('installer rejects unsupported request methods before loading application state', function () {
	$installer = (new ReflectionClass(Installer::class))->newInstanceWithoutConstructor();
	$result    = (new ReflectionMethod(Installer::class, 'runCommand'))->invoke($installer, [
		PHP_BINARY,
		'-r',
		'$_SERVER["REQUEST_METHOD"] = "GET"; register_shutdown_function(function () { echo " status=" . http_response_code(); }); require $argv[1];',
		CACTI_PATH_INSTALL . '/step_json.php',
	]);

	expect($result['exitCode'])->toBe(0)
		->and(implode(PHP_EOL, $result['output']))->toContain('Method not allowed', 'status=405');
});
