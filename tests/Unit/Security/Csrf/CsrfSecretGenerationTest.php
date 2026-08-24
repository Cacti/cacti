<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$root = dirname(__DIR__, 4);
$GLOBALS['config'] = array(
	'include_path' => $root . '/include',
	'is_web' => false,
);
$config = &$GLOBALS['config'];

require_once $root . '/include/csrf.php';

test('CSRF secrets use the platform cryptographic random source', function () {
	$first = cacti_csrf_generate_secret();
	$second = cacti_csrf_generate_secret();

	expect($first)->toMatch('/^[a-f0-9]{64}$/')
		->and($second)->toMatch('/^[a-f0-9]{64}$/')
		->and($second)->not->toBe($first);
});

test('persistent CSRF secret files are PHP-safe and reused', function () {
	$directory = sys_get_temp_dir() . '/cacti_csrf_' . bin2hex(random_bytes(8));
	mkdir($directory, 0700);
	$path = $directory . '/csrf-secret.php';

	try {
		$first = cacti_csrf_read_or_create_secret(array($path));
		$second = cacti_csrf_read_or_create_secret(array($path));

		expect($first)->toBe($second)
			->and(file_get_contents($path))->toBe($first)
			->and($first)->toMatch('/^<\?php\R\/\* Cacti CSRF secret: [a-f0-9]{64} \*\/\R$/');
	} finally {
		@unlink($path);
		@rmdir($directory);
	}
});

test('legacy CSRF randomness is not used by Cacti integration paths', function () use ($root) {
	$integration = file_get_contents($root . '/include/csrf.php');
	$installer = file_get_contents($root . '/install/functions.php');
	$refresh = file_get_contents($root . '/cli/refresh_csrf.php');

	expect($integration)->not->toContain('mt_rand(')
		->and($installer)->toContain('cacti_csrf_secret_file_contents()')
		->and($refresh)->toContain('cacti_csrf_secret_file_contents()')
		->and($refresh)->not->toContain('csrf_generate_secret()');
});
