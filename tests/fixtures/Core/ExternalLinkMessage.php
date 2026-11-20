<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 3);
require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';
require $root . '/include/global_constants.php';
require $root . '/lib/html.php';

foreach (['raise_message', 'raise_message_html', 'raise_message_has_markup', 'get_message_title'] as $function) {
	cacti_fixture_source_function($root . '/lib/functions.php', $function, '');
}

function __(string $format, mixed ...$args) : string {
	return vsprintf($format, $args);
}

function cacti_log(string $message, bool $print, string $category, mixed $level = '') : void {
	$GLOBALS['logged'][] = [$category, $message];
}

function cacti_sizeof(mixed $value) : int {
	return is_countable($value) ? count($value) : 0;
}

function gfrv(string $name) : int {
	return (int) $_REQUEST[$name];
}

function db_fetch_row_prepared(string $query, array $params) : array {
	if ($params !== [8]) {
		throw new RuntimeException('Wrong link ID');
	}

	return ['id' => 8, 'title' => 'North & South', 'style' => 'TAB', 'refresh' => 0, 'contentfile' => $GLOBALS['url']];
}

function is_realm_allowed(int $realm) : bool {
	return $realm === 10008;
}

function general_header() : void {
}

function bottom_footer() : void {
}

$no_http_headers = true;
$_REQUEST        = ['id' => 8];
$_SESSION        = [];
$logged          = [];
$original        = getcwd();
$work            = sys_get_temp_dir() . '/cacti-link-message-' . bin2hex(random_bytes(8));
mkdir($work . '/include', 0700, true);
file_put_contents($work . '/include/global.php', "<?php\n");

try {
	chdir($work);
	$url = 'https://example.invalid:badport/';
	ob_start();
	require $root . '/link.php';
	$output  = ob_get_clean();
	$plain   = "External Link ID '8' with Title 'North & South' attempted to inject an invalid URL and was blocked!";
	$warning = $_SESSION[SESS_MESSAGES]['invalid_url'] ?? [];

	if ($logged !== [['SECURITY', $plain]] || ($warning['message'] ?? null) !== html_escape($plain) || ($warning['level'] ?? null) !== MESSAGE_LEVEL_ERROR || str_contains($output, '<iframe')) {
		throw new RuntimeException('The log and UI did not receive the expected external link diagnostic');
	}

	$url = 'https://example.invalid/graphs?id=1&view=2';
	ob_start();
	require $root . '/link.php';
	$output = ob_get_clean();

	if (!str_contains($output, 'src="https://example.invalid/graphs?id=1&amp;view=2"') || count($logged) !== 1) {
		throw new RuntimeException('A valid external link did not render cleanly');
	}
} finally {
	chdir($original);
	unlink($work . '/include/global.php');
	rmdir($work . '/include');
	rmdir($work);
}

print "External link message passed\n";
