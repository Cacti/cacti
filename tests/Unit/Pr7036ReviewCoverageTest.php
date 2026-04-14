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

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';

function pr7036_source(string $path): string {
	return file_get_contents(dirname(__DIR__, 2) . '/' . $path);
}

test('PR7036 comments: data_input keeps shell hardening but allows pipe workflows', function () {
	$source = pr7036_source('data_input.php');
	$helper = pr7036_source('lib/security_validation.php');

	expect($source)->toContain("cacti_input_string_is_safe(\$save['input_string'])");
	expect($helper)->toContain("preg_replace('/<[a-zA-Z_]+>/', '', \$input_string)");
	expect($helper)->toContain("preg_match('/[;&`$\\n\\r]/', \$input_string_bare)");
	expect($source)->not->toContain('[;&|`$');

	$allowed = 'grep foo /tmp/x | wc -l';
	$blocked = "grep foo /tmp/x;\nrm -rf /";

	expect((bool) preg_match('/[;&`$\n\r]/', $allowed))->toBeFalse();
	expect((bool) preg_match('/[;&`$\n\r]/', $blocked))->toBeTrue();
});

test('PR7036 comments: api_automation sort regex supports backticks and parenthesis', function () {
	$source = pr7036_source('lib/api_automation.php');
	$helper = pr7036_source('lib/security_validation.php');

	expect($source)->toContain("cacti_validate_sort_column(\$v, 'description')");
	expect($helper)->toContain('/^[a-zA-Z_`][a-zA-Z0-9_`()., *]*$/');
});

test('PR7036 comments: html_filter sort_column uses sanitize_search_string to avoid UI breakage', function () {
	$source = pr7036_source('lib/html_filter.php');

	expect($source)->toContain("\$filters['sort_column']['options']    = ['options' => 'sanitize_search_string'];");
	expect($source)->toContain("cacti_validate_sort_direction(\$v, 'ASC')");
});

test('PR7036 comments: auth_login rotates session id on successful login', function () {
	$source = pr7036_source('auth_login.php');

	expect($source)->toContain('session_regenerate_id(true);');
	expect(strpos($source, 'session_regenerate_id(true);'))
		->toBeLessThan(strpos($source, '$_SESSION[SESS_USER_ID]'));
});

test('PR7036 comments: auth lockout counter uses atomic increment without +1 read-modify-write', function () {
	$source = pr7036_source('lib/auth.php');

	expect($source)->toContain('failed_attempts = failed_attempts + 1');
	expect($source)->not->toContain("failed_attempts']) + 1");
});

test('PR7036 comments: auth referer handling validates redirect url', function () {
	$source = pr7036_source('lib/auth.php');

	expect($source)->toContain("\$referer = validate_redirect_url(\$_SERVER['HTTP_REFERER']);");
});

test('PR7036 comments: import path handling validates with base containment helper', function () {
	$source = pr7036_source('lib/import.php');

	expect($source)->toContain('$validated = validate_relative_path_within($name, CACTI_PATH_BASE);');
	expect($source)->toContain('$filename = $validated;');
});

test('PR7036 comments: validate_path_within supports safe create paths and blocks separators', function () {
	$source = pr7036_source('lib/functions.php');

	expect($source)->toContain("str_contains(\$filename, '/') || str_contains(\$filename, '\\\\')");
	expect($source)->toContain('if ($resolved !== false)');
	expect($source)->toContain('return $combined;');
});

test('PR7036 comments: reports title output is escaped consistently', function () {
	$source = pr7036_source('lib/reports.php');

	expect(substr_count($source, 'htmle($title)'))->toBeGreaterThanOrEqual(7);
	expect($source)->not->toContain('<h3>$title</h3>');
});

test('PR7036 comments: rrd command escaping strips command substitution patterns', function () {
	$source = pr7036_source('lib/rrd.php');

	expect($source)->toContain("str_replace('`', '', \$command)");
	expect($source)->toContain("preg_replace('/\\$\\(/', '(', \$command)");
});

test('PR7036 comments: xml parsing hardening disables entity loader path', function () {
	$source = pr7036_source('lib/xml.php');

	expect(substr_count($source, 'libxml_disable_entity_loader(true)'))->toBeGreaterThanOrEqual(2);
});

test('PR7036 comments: remote agent does not honor request effective_user override', function () {
	$source = pr7036_source('remote_agent.php');

	expect($source)->not->toContain("gfrv('effective_user')");
	expect($source)->not->toContain("grv('effective_user')");
	expect($source)->toContain('$_SESSION[SESS_USER_ID] ?? 0');
});

test('PR7036 comments: remote agent logs FQDN mismatch migration hint', function () {
	$source = pr7036_source('remote_agent.php');

	expect($source)->toContain('matches poller');
	expect($source)->toContain('(id:$poller_id)');
	expect($source)->toContain('by short hostname but not FQDN');
});

test('PR7036 comments: centralized security validation helpers are loaded globally', function () {
	$source = pr7036_source('include/global.php');

	expect($source)->toContain("require_once(CACTI_PATH_LIBRARY . '/security_validation.php');");
});
