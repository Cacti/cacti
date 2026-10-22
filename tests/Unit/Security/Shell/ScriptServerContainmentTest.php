<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The Script Server only dispatches functions defined by script files inside
 * the Cacti roots, and one failing script must not end the shared process.
 */

require_once dirname(__DIR__, 4) . '/lib/script_server.php';
require_once dirname(__DIR__, 3) . '/fixtures/script_server_functions.php';

test('the allowed roots are the configured Cacti directories', function () {
	$roots = script_server_allowed_roots();

	expect($roots)->toContain(CACTI_PATH_BASE)
		->and($roots)->toContain(CACTI_PATH_SCRIPTS)
		->and($roots)->toContain(CACTI_PATH_RESOURCE)
		->and($roots)->toContain(CACTI_PATH_PLUGINS)
		->and($roots)->toBe(array_values(array_unique($roots)));
});

test('a script below the Cacti tree is allowed and paths outside it are not', function () {
	$roots = script_server_allowed_roots();

	expect(script_server_path_is_allowed(realpath(CACTI_PATH_SCRIPTS . '/ss_count_oids.php'), $roots))->toBeTrue()
		->and(script_server_path_is_allowed(realpath('/etc/hosts'), $roots))->toBeFalse()
		->and(script_server_path_is_allowed(false, $roots))->toBeFalse()
		->and(script_server_path_is_allowed('', $roots))->toBeFalse();
});

test('a sibling directory sharing the root prefix is outside the root', function () {
	$base    = sys_get_temp_dir() . '/ss_root_' . getmypid();
	$sibling = $base . '_evil';

	@mkdir($base);
	@mkdir($sibling);
	touch($sibling . '/x.php');

	expect(script_server_path_is_allowed(realpath($sibling . '/x.php'), [$base]))->toBeFalse();

	// the same path is decided again under a root set that does contain it
	expect(script_server_path_is_allowed(realpath($sibling . '/x.php'), [$sibling]))->toBeTrue();

	unlink($sibling . '/x.php');
	rmdir($sibling);
	rmdir($base);
});

test('PHP internal functions are never dispatched', function (string $function) {
	expect(script_server_function_rejection($function, script_server_allowed_roots()))->toBe('is a PHP internal function');
})->with(['system', 'exec', 'passthru', 'shell_exec', 'file_put_contents', 'strlen']);

test('undefined and non-function names are refused', function () {
	$roots = script_server_allowed_roots();

	expect(script_server_function_rejection('ss_no_such_function', $roots))->toBe('is not defined')
		->and(script_server_function_rejection('ReflectionClass::export', $roots))->toBe('is not defined');
});

test('a function declared outside the Cacti roots is refused', function () {
	$name = 'ss_outside_' . bin2hex(random_bytes(8));
	$file = sys_get_temp_dir() . '/' . $name . '.php';

	file_put_contents($file, "<?php\nfunction $name() { return 'x'; }\n");
	require $file;
	unlink($file);

	expect(script_server_function_rejection($name, script_server_allowed_roots()))->toBe('is defined outside the allowed script roots');
});

test('a script function inside the Cacti tree is allowed', function () {
	expect(script_server_function_rejection('ss_fixture_echo', script_server_allowed_roots()))->toBe('');
});

test('dispatch returns the trimmed result of a script function', function () {
	expect(script_server_dispatch('ss_fixture_echo', ['abc']))->toBe('value:abc')
		->and(script_server_dispatch('ss_fixture_echo', []))->toBe('value:none');
});

test('an exception thrown by a script becomes U', function () {
	expect(script_server_dispatch('ss_fixture_throws', []))->toBe('U');
});

test('a type error from a bad argument becomes U', function () {
	expect(script_server_dispatch('ss_fixture_typed', ['not-a-number']))->toBe('U');
});

test('a non-scalar result becomes U', function () {
	expect(script_server_dispatch('ss_fixture_array', []))->toBe('U');
});

test('a request splits into include file, function and parameters', function () {
	expect(script_server_split_request("/cacti/scripts/ss_x.php ss_x 'a b' 'c'"))->toBe(['/cacti/scripts/ss_x.php', 'ss_x', "'a b' 'c'"])
		->and(script_server_split_request("/cacti/scripts/ss_x.php 'ss_x' "))->toBe(['/cacti/scripts/ss_x.php', 'ss_x', ''])
		->and(script_server_split_request('/cacti/scripts/ss_x.php'))->toBe(['', '', '']);
});

test('the include file must exist inside the Cacti roots', function () {
	$roots = script_server_allowed_roots();

	expect(script_server_resolve_include(CACTI_PATH_SCRIPTS . '/../scripts/ss_count_oids.php', $roots))->toBe(realpath(CACTI_PATH_SCRIPTS . '/ss_count_oids.php'))
		->and(script_server_resolve_include('/etc/hosts', $roots))->toBeFalse()
		->and(script_server_resolve_include(CACTI_PATH_SCRIPTS . '/ss_no_such_script.php', $roots))->toBeFalse();
});

test('a request is run only after the function passes the guard', function () {
	$roots = script_server_allowed_roots();

	expect(script_server_run('system', ['id'], $roots, CACTI_PATH_SCRIPTS . '/ss_sql.php'))->toBe('U')
		->and(script_server_run('ss_fixture_echo', ['ok'], $roots, CACTI_PATH_SCRIPTS . '/ss_sql.php'))->toBe('value:ok')
		->and(script_server_run('ss_fixture_throws', [], $roots, CACTI_PATH_SCRIPTS . '/ss_sql.php'))->toBe('U');
});

test('script_server.php checks the include path and function before dispatching', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/script_server.php');

	$path    = strpos($source, 'script_server_resolve_include($include_file');
	$require = strpos($source, 'require_once($include_file)');
	$run     = strpos($source, 'script_server_run($function');

	expect($path)->toBeInt()
		->and($require)->toBeGreaterThan($path)
		->and($run)->toBeGreaterThan($require)
		->and($source)->not->toContain('call_user_func');
});
