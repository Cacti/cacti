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

test('the allowed roots contain only scripts and plugins', function () {
	$roots = script_server_allowed_roots();

	expect($roots)->toContain(CACTI_PATH_SCRIPTS)
		->and($roots)->toContain(CACTI_PATH_PLUGINS)
		->and($roots)->not->toContain(CACTI_PATH_BASE)
		->and($roots)->not->toContain(CACTI_PATH_RESOURCE)
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
	expect(script_server_function_rejection($function, CACTI_PATH_SCRIPTS . '/ss_count_oids.php'))->toBe('is a PHP internal function');
})->with(['system', 'exec', 'passthru', 'shell_exec', 'file_put_contents', 'strlen']);

test('undefined and non-function names are refused', function () {
	$file = dirname(__DIR__, 3) . '/fixtures/script_server_functions.php';

	expect(script_server_function_rejection('ss_no_such_function', $file))->toBe('is not defined')
		->and(script_server_function_rejection('ReflectionClass::export', $file))->toBe('is not defined');
});

test('a function declared by a different file is refused', function () {
	$name = 'ss_outside_' . bin2hex(random_bytes(8));
	$file = sys_get_temp_dir() . '/' . $name . '.php';

	file_put_contents($file, "<?php\nfunction $name() { return 'x'; }\n");
	require $file;

	expect(script_server_function_rejection($name, CACTI_PATH_SCRIPTS . '/ss_count_oids.php'))->toBe('is not defined by the requested script file');

	unlink($file);
});

test('a function is allowed only for the file that defines it', function () {
	$file = dirname(__DIR__, 3) . '/fixtures/script_server_functions.php';

	expect(script_server_function_rejection('ss_fixture_echo', $file))->toBe('')
		->and(script_server_function_rejection('ss_fixture_echo', CACTI_PATH_SCRIPTS . '/ss_sql.php'))->toBe('is not defined by the requested script file')
		->and(script_server_function_rejection('get_cacti_version', $file))->toBe('is not defined by the requested script file');
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

test('responses must fit on one bounded non-empty line', function () {
	expect(script_server_dispatch('ss_fixture_multiline', []))->toBe('U')
		->and(script_server_dispatch('ss_fixture_long', []))->toBe('U')
		->and(script_server_normalize_response(''))->toBe('U')
		->and(script_server_normalize_response("value\tother"))->toBe('U')
		->and(script_server_normalize_response("value\0other"))->toBe('U')
		->and(script_server_normalize_response(str_repeat('x', SCRIPT_SERVER_MAX_RESPONSE_LENGTH)))->toBe(str_repeat('x', SCRIPT_SERVER_MAX_RESPONSE_LENGTH));

	$healthy = true;

	expect(script_server_normalize_response("first\nsecond", $healthy))->toBe('U')
		->and($healthy)->toBeFalse();

	$healthy = true;

	expect(script_server_normalize_response(str_repeat('x', SCRIPT_SERVER_MAX_RESPONSE_LENGTH + 1), $healthy))->toBe('U')
		->and($healthy)->toBeFalse();

	$healthy = false;

	expect(script_server_normalize_response('', $healthy))->toBe('U')
		->and($healthy)->toBeTrue();
});

test('a request splits into include file, function and parameters', function () {
	expect(script_server_split_request("/cacti/scripts/ss_x.php ss_x 'a b' 'c'"))->toBe(['/cacti/scripts/ss_x.php', 'ss_x', "'a b' 'c'"])
		->and(script_server_split_request("/cacti/scripts/ss_x.php 'ss_x' "))->toBe(['/cacti/scripts/ss_x.php', 'ss_x', ''])
		->and(script_server_split_request('/cacti/scripts/ss_x.php'))->toBe(['', '', '']);
});

test('only unambiguous PHP function tokens are accepted', function () {
	expect(script_server_function_name_is_valid('ss_example'))->toBeTrue()
		->and(script_server_function_name_is_valid('Vendor\\Package\\ss_example'))->toBeTrue()
		->and(script_server_function_name_is_valid('\\ss_example'))->toBeTrue()
		->and(script_server_function_name_is_valid('Class::method'))->toBeFalse()
		->and(script_server_function_name_is_valid('ss_example()'))->toBeFalse()
		->and(script_server_function_name_is_valid('ss example'))->toBeFalse()
		->and(script_server_function_name_is_valid(''))->toBeFalse();

	[, $repeated_quotes] = script_server_split_request("/cacti/scripts/ss_x.php ''ss_x''");
	[, $unclosed_quote]  = script_server_split_request("/cacti/scripts/ss_x.php 'ss_x");

	expect(script_server_function_name_is_valid($repeated_quotes))->toBeFalse()
		->and(script_server_function_name_is_valid($unclosed_quote))->toBeFalse();
});

test('a complete request is canonicalized and parsed before script loading', function () {
	$script  = CACTI_PATH_SCRIPTS . '/../scripts/ss_count_oids.php';
	$request = $script . " ss_count_oids '12' '1.3.6'";

	expect(script_server_validate_request($request, script_server_allowed_roots()))->toBe([
		realpath(CACTI_PATH_SCRIPTS . '/ss_count_oids.php'),
		'ss_count_oids',
		['12', '1.3.6']
	])
		->and(script_server_validate_request($script . ' Class::method', script_server_allowed_roots()))->toBeFalse()
		->and(script_server_validate_request($script . " ss_count_oids 'unterminated", script_server_allowed_roots()))->toBeFalse();
});

test('the include file must exist inside the Cacti roots', function () {
	$roots = script_server_allowed_roots();

	expect(script_server_resolve_include(CACTI_PATH_SCRIPTS . '/../scripts/ss_count_oids.php', $roots))->toBe(realpath(CACTI_PATH_SCRIPTS . '/ss_count_oids.php'))
		->and(script_server_resolve_include('/etc/hosts', $roots))->toBeFalse()
		->and(script_server_resolve_include(CACTI_PATH_SCRIPTS . '/ss_no_such_script.php', $roots))->toBeFalse();
});

test('a request is run only after the function passes the guard', function () {
	$file = dirname(__DIR__, 3) . '/fixtures/script_server_functions.php';

	expect(script_server_run('system', ['id'], $file))->toBe('U')
		->and(script_server_run('ss_fixture_echo', ['ok'], $file))->toBe('value:ok')
		->and(script_server_run('ss_fixture_echo', ['wrong-file'], CACTI_PATH_SCRIPTS . '/ss_sql.php'))->toBe('U')
		->and(script_server_run('ss_fixture_throws', [], $file))->toBe('U');
});
