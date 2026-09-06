<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

test('the unified CLI entrypoint loads the CLI-only guard before autoloading', function () : void {
	$source = file_get_contents(dirname(__DIR__, 4) . '/bin/cacti');

	expect($source)->not->toBeFalse()
		->and($source)->toContain("require_once \$root . '/include/cli_only.php';");

	if ($source === false) {
		return;
	}

	expect(strpos($source, 'include/cli_only.php'))->toBeLessThan(strpos($source, 'require $autoload'));
});

test('the CLI-only guard rejects non-CLI SAPIs before application bootstrap', function () : void {
	$source = file_get_contents(dirname(__DIR__, 4) . '/include/cli_only.php');

	expect($source)->not->toBeFalse()
		->and($source)->toContain("PHP_SAPI !== 'cli'")
		->and($source)->toContain('http_response_code(403)')
		->and($source)->toContain('exit(1)');
});

test('the bin directory denies web access under supported Apache versions', function () : void {
	$source = file_get_contents(dirname(__DIR__, 4) . '/bin/.htaccess');

	expect($source)->not->toBeFalse()
		->and($source)->toContain('Require all denied')
		->and($source)->toContain('Order Allow,Deny')
		->and($source)->toContain('Deny from all');
});

test('every executable Console fixture loads the CLI-only guard', function () : void {
	$fixtures = glob(dirname(__DIR__, 3) . '/fixtures/Console/cli/*.php');

	expect($fixtures)->not->toBeFalse()->not->toBeEmpty();

	foreach ($fixtures ?: [] as $fixture) {
		expect(file_get_contents($fixture))->toContain("require_once dirname(__DIR__, 4) . '/include/cli_only.php';");
	}
});

test('the tests directory denies browsing and direct Apache access', function () : void {
	$root     = dirname(__DIR__, 4);
	$deny     = file_get_contents($root . '/tests/.htaccess');
	$redirect = file_get_contents($root . '/tests/index.php');

	expect($deny)->not->toBeFalse()
		->and($deny)->toContain('Require all denied')
		->and($deny)->toContain('Deny from all')
		->and($redirect)->not->toBeFalse()
		->and($redirect)->toContain("header('Location:../index.php')");
});

test('TTY handoff requires stderr to remain attached to the terminal', function () : void {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/Console/Command/LegacyScriptCommand.php');

	expect($source)->not->toBeFalse()
		->and($source)->toContain("stream_isatty(STDERR)");
});
