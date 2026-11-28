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
 * Coverage probe for three outputs:
 *
 * - the Concurrent Processes and Max Threads rows of Technical Info, which
 *   print stored Data Collector names;
 * - the checkpass answer of user_admin.php, which the live password hint
 *   reads as 'ok' or as the failed rule;
 * - the export error path of package.php, which prints the template XML.
 *
 * Each block is the checked-in code with its original line numbers. The
 * escaping helper is the checked-in one; the database, request and page
 * helpers are stubs.
 *
 *   php StoredTextAndPasswordHint.php
 */

$root = dirname(__DIR__, 3);

const COLLECTOR = 'Main <i class="markup">a</i>';
const TEMPLATE  = '<template><name>Graph <i class="markup">b</i></name></template>';

function check(bool $condition, string $message) : void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

function __(string $text, mixed ...$args) : string {
	return $args === [] ? $text : vsprintf($text, $args);
}

function db_fetch_assoc(string $sql) : array|false {
	return $GLOBALS['poller_rows'] ?? [
		['name' => COLLECTOR, 'processes' => 2, 'threads' => 4],
		['name' => 'Remote', 'processes' => 1, 'threads' => 1],
	];
}

function gnrv(string $name) : mixed {
	return $GLOBALS['request'][$name] ?? '';
}

function secpass_check_pass(string $password) : string {
	return $password === 'weak' ? 'Password must be at least 8 characters!' : 'ok';
}

function form_alternate_row() : void {
}

function form_end_row() : void {
}

function top_header() : void {
}

function bottom_footer() : void {
}

cacti_fixture_source_function($root . '/lib/html.php', 'html_escape', __NAMESPACE__);

function run_block(string $file, string $pattern, array $locals = []) : string {
	ob_start();

	try {
		cacti_fixture_source_block($file, __NAMESPACE__, $pattern, $locals);
	} finally {
		$output = (string) ob_get_clean();
	}

	return $output;
}

// Technical Info prints each Data Collector name as text
$summary_block = '/^\t(\$pollers = db_fetch_assoc\(\'SELECT name, processes, threads.*?implode\(\'<br>\', \$threads\) \. \'<\/td>\';\n\tform_end_row\(\);)/ms';
$summary       = run_block($root . '/support.php', $summary_block);
$name          = htmlspecialchars(COLLECTOR, ENT_QUOTES | ENT_HTML5);

check(str_contains($summary, 'Name: ' . $name . ', Procs: 2<br>Name: Remote, Procs: 1'), 'Concurrent Processes does not print the Data Collector names as text');
check(str_contains($summary, 'Name: ' . $name . ', Threads: 4<br>Name: Remote, Threads: 1'), 'Max Threads does not print the Data Collector names as text');
check(!str_contains($summary, COLLECTOR), 'A Data Collector name reached Technical Info unescaped');

$GLOBALS['poller_rows'] = false;
set_error_handler(static function (int $severity, string $message) : never {
	throw new RuntimeException($message);
});

try {
	$summary = run_block($root . '/support.php', $summary_block);
	check(substr_count($summary, '<td></td>') === 2, 'Failed poller queries should leave both summary values empty');
} finally {
	restore_error_handler();
}

// checkpass answers 'ok' only for a password that passes every rule
$checkpass = '/case \'checkpass\':\n(.*?^\t+\}$)/ms';

$GLOBALS['request'] = ['password' => 'weak'];
check(run_block($root . '/user_admin.php', $checkpass) === 'Password must be at least 8 characters!', 'checkpass did not report the failed password rule');

$GLOBALS['request'] = ['password' => 'Str0ng!Passphrase'];
check(run_block($root . '/user_admin.php', $checkpass) === 'ok', 'checkpass did not accept a password that passes every rule');

// The export error path prints the template XML as text
$export = run_block(
	$root . '/package.php',
	'/^\t\t(top_header\(\);\n\t\tprint __\(\'WARNING: Export Errors Encountered.*?bottom_footer\(\);)/ms',
	['xml_data' => TEMPLATE]
);

check(str_contains($export, htmlspecialchars(TEMPLATE, ENT_QUOTES | ENT_HTML5)), 'The export error path does not print the template XML as text');
check(!str_contains($export, TEMPLATE), 'The template XML reached the export error page unescaped');

print "PASS: Technical Info, checkpass and the export error path print stored text as text\n";
