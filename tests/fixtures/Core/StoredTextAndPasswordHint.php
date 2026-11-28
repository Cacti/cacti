<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiStoredTextAndPasswordHintFixture;

use RuntimeException;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

/*
 * Coverage probe for two outputs:
 *
 * - the Concurrent Processes and Max Threads rows of Technical Info, which
 *   print stored Data Collector names;
 * - the checkpass answer of user_admin.php, which the live password hint
 *   reads as 'ok' or as the failed rule.
 *
 * Each block is the checked-in code with its original line numbers. The
 * escaping helper is the checked-in one; the database, request and page
 * helpers are stubs.
 *
 *   php StoredTextAndPasswordHint.php
 */

$root = dirname(__DIR__, 3);

const COLLECTOR = 'Main <i class="markup">a</i>';

function check(bool $condition, string $message) : void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function __(string $text, mixed ...$args) : string {
	return $args === [] ? $text : vsprintf($text, $args);
}

function db_fetch_assoc(string $sql) : array {
	return [
		['name' => COLLECTOR, 'processes' => 2, 'threads' => 4],
		['name' => 'Remote', 'processes' => 1, 'threads' => 1],
	];
}

function get_nfilter_request_var(string $name) : mixed {
	return $GLOBALS['request'][$name] ?? '';
}

function secpass_check_pass(string $password) : string {
	return $password === 'weak' ? 'Password must be at least 8 characters!' : 'ok';
}

function form_alternate_row() : void {
}

function form_end_row() : void {
}

cacti_fixture_source_function($root . '/lib/html.php', 'html_escape', __NAMESPACE__);

/*
 * Run a checked-in block of a page with its original line numbers, so Xdebug
 * maps the result back to the page. Only checked-in source is passed.
 */
function run_block(string $file, string $pattern) : string {
	$text = file_get_contents($file);

	if (preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE) !== 1) {
		throw new RuntimeException('Cannot locate the block in ' . $file);
	}
	[$code, $offset] = $matches[1];
	$temporary       = tempnam(sys_get_temp_dir(), 'cacti-block-');

	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);
	file_put_contents($temporary, '<?php namespace ' . __NAMESPACE__ . ';' . str_repeat("\n", substr_count(substr($text, 0, $offset), "\n")) . $code);
	ob_start();

	try {
		require $temporary;
	} finally {
		$output = (string) ob_get_clean();
		unlink($temporary);
	}

	return $output;
}

// Technical Info prints each Data Collector name as text
$summary = run_block(
	$root . '/utilities.php',
	'/^\t\t(\$pollers = db_fetch_assoc\(\'SELECT name, processes, threads.*?implode\(\'<br>\', \$threads\) \. \'<\/td>\';\n\t\tform_end_row\(\);)/ms'
);
$name = htmlspecialchars(COLLECTOR, ENT_QUOTES | ENT_HTML5);

check(str_contains($summary, 'Name: ' . $name . ', Procs: 2<br>Name: Remote, Procs: 1'), 'Concurrent Processes does not print the Data Collector names as text');
check(str_contains($summary, 'Name: ' . $name . ', Threads: 4<br>Name: Remote, Threads: 1'), 'Max Threads does not print the Data Collector names as text');
check(!str_contains($summary, COLLECTOR), 'A Data Collector name reached Technical Info unescaped');

// checkpass answers 'ok' only for a password that passes every rule
$checkpass = '/case \'checkpass\':\n(.*?^\t+\}$)/ms';

$GLOBALS['request'] = ['password' => 'weak'];
check(run_block($root . '/user_admin.php', $checkpass) === 'Password must be at least 8 characters!', 'checkpass did not report the failed password rule');

$GLOBALS['request'] = ['password' => 'Str0ng!Passphrase'];
check(run_block($root . '/user_admin.php', $checkpass) === 'ok', 'checkpass did not accept a password that passes every rule');

print "PASS: Technical Info and checkpass print stored text and the failed password rule\n";
