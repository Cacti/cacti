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
 * A data input method's input_string is a command template that the poller
 * hands to a shell, so shell metacharacters outside <placeholder> markers must
 * be refused both when the method is saved and when a template package is
 * imported.
 */

test('plain command templates and quoted placeholders are accepted', function (string $template) {
	expect(cacti_input_string_is_safe($template))->toBeTrue();
})->with([
	'empty'              => [''],
	'placeholders'       => ['<path_cacti>/scripts/ss_example.php ss_example <hostname> <snmp_community>'],
	'quoted placeholder' => ['<path_cacti>/scripts/example.php "<reason>"'],
	'single quoted'      => ["<path_php_binary> <path_cacti>/scripts/x.php '<arg1>'"],
	'digit suffix'       => ['<path_cacti>/scripts/x.php <arg1> <host_id2>'],
	'tab separator'      => ["<path_cacti>/scripts/x.php\t<arg1>"],
]);

test('shell metacharacters outside a placeholder are refused', function (mixed $template) {
	expect(cacti_input_string_is_safe($template))->toBeFalse();
})->with([
	'semicolon'      => ['<path_cacti>/scripts/x.php; id'],
	'pipe'           => ['<path_cacti>/scripts/x.php | wc -l'],
	'ampersand'      => ['<path_cacti>/scripts/x.php && id'],
	'backtick'       => ['<path_cacti>/scripts/x.php `id`'],
	'substitution'   => ['<path_cacti>/scripts/x.php $(id)'],
	'redirect'       => ['<path_cacti>/scripts/x.php > /tmp/out'],
	'input redirect' => ['<path_cacti>/scripts/x.php < /tmp/in'],
	'subshell'       => ['<path_cacti>/scripts/x.php (id)'],
	'braces'         => ['<path_cacti>/scripts/x.php {a,b}'],
	'bare quote'     => ["<path_cacti>/scripts/x.php 'a b'"],
	'newline'        => ["<path_cacti>/scripts/x.php\nid"],
	'return'         => ["<path_cacti>/scripts/x.php\rid"],
	'nul'            => ["<path_cacti>/scripts/x.php\0id"],
	'escape'         => ["<path_cacti>/scripts/x.php \x1b[2J"],
	'vertical tab'   => ["<path_cacti>/scripts/x.php\x0bid"],
	'delete'         => ["<path_cacti>/scripts/x.php \x7f"],
	'backslash'      => ['<path_cacti>/scripts/x.php \\n'],
	'not a string'   => [false],
]);

test('the data input save and the template import both call the guard before storing', function () {
	$root   = dirname(__DIR__, 4);
	$save   = file_get_contents($root . '/data_input.php');
	$import = file_get_contents($root . '/lib/import.php');

	$body = substr($save, strpos($save, 'function form_save()'), 2500);

	expect(strpos($body, "cacti_input_string_is_safe(\$save['input_string'])"))->toBeLessThan(strpos($body, "sql_save(\$save, 'data_input')"));

	$start = strpos($import, 'function xml_to_data_input_method(');
	$body  = substr($import, $start, 6000);

	expect(strpos($body, '!cacti_input_string_is_safe($save[$field_name])'))->not->toBeFalse()
		->and(strpos($body, '!cacti_input_string_is_safe($save[$field_name])'))->toBeLessThan(strpos($body, "sql_save(\$save, 'data_input')"));
});
