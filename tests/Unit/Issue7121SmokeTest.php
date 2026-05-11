<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Smoke tests for issue #7121. Lightweight checks that the touched
 * code path is callable and produces sane output for both the
 * happy-path (legitimate templates the regex must accept) and the
 * negative-path (GHSA-c4qp bypass payloads the regex must still
 * reject). Runs without Cacti's bootstrap by extracting the function
 * body from lib/functions.php.
 */

$functionsSource = file_get_contents(__DIR__ . '/../../lib/functions.php');

if (!function_exists('_smoke_input_string_is_safe')) {
	preg_match('/^function cacti_input_string_is_safe\([^)]*\)\s*\{.*?^\}/sm', $functionsSource, $m);
	$src = preg_replace('/^function cacti_input_string_is_safe\(/m', 'function _smoke_input_string_is_safe(', $m[0]);
	eval($src); // nosemgrep: php.lang.security.eval-use.eval-use
}

test('cacti_input_string_is_safe loads and returns bool', function () {
	$result = _smoke_input_string_is_safe('<host>');
	expect($result)->toBeTrue();
	expect(_smoke_input_string_is_safe('cmd ;'))->toBeFalse();
});

test('representative happy-path templates round-trip through the validator', function () {
	$happy = [
		'<path_cacti>/scripts/x.php "<reason>"',
		"<path_cacti>/scripts/x.php '<reason>'",
		'<path_cacti>/scripts/x.php <arg1> <host_id2>',
		'<path_php_binary> -q <path_cacti>/scripts/x.php',
	];
	foreach ($happy as $tpl) {
		expect(_smoke_input_string_is_safe($tpl))->toBeTrue("happy-path: $tpl");
	}
});

test('representative attack payloads are rejected', function () {
	$bad = [
		'cmd ; rm -rf /',
		'cmd && bad',
		'cmd `id`',
		'cmd $(id)',
		'cmd > /tmp/out',
	];
	foreach ($bad as $payload) {
		expect(_smoke_input_string_is_safe($payload))->toBeFalse("attack: $payload");
	}
});
