<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\RealtimeShell;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Usage: RealtimeDocumentShell.php disabled|missing|unwritable|page [language theme]
// Without a language the shell renders with i18n disabled, so the active
// locale is the en-US fallback. With one the shared locale bootstrap puts the
// language in the session and selects the theme.
$mode = $argv[1] ?? 'page';
$argv = isset($argv[3]) ? [$argv[0], '1', $argv[2], $argv[3]] : [$argv[0], '0'];
require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 2) . '/Unit/fixtures/i18n_probe_bootstrap.php';

if ($i18n_document_attributes === null) {
	throw new \InvalidArgumentException('Unknown document direction scenario');
}

// A translated session changes the message, so only the en-US runs pin its text.
$html_tag = "<html$i18n_document_attributes>";
$english  = CACTI_LOCALE === 'en-US';
$expected = [
	'disabled'   => "$html_tag\n<body>\n\t<p><strong>" . ($english ? "Real-time has been disabled by your administrator.</strong></p>\n</body>\n</html>\n" : ''),
	'missing'    => "$html_tag\n<body>\n\t<p><strong>" . ($english ? 'The Image Cache Directory does not exist.' : ''),
	'unwritable' => "$html_tag\n<body>\n\t<p><strong>" . ($english ? 'The Image Cache Directory is not writable.' : ''),
	'page'       => "<!DOCTYPE html>\n$html_tag\n<head>\n",
];

if (!isset($expected[$mode])) {
	throw new \InvalidArgumentException('Unknown realtime shell mode ' . $mode);
}

// The realtime settings and cache directory state select which shell renders.
function read_config_option($name, $force = false) {
	if ($name === 'realtime_enabled') {
		return $GLOBALS['realtime_shell_mode'] === 'disabled' ? '' : 'on';
	}

	return $name === 'realtime_cache_path' ? '/fixture/realtime-cache' : '';
}
function is_dir($path) {
	return $GLOBALS['realtime_shell_mode'] !== 'missing';
}
function is_writable($path) {
	return $GLOBALS['realtime_shell_mode'] !== 'unwritable';
}
function get_selected_theme() {
	return 'modern';
}

$GLOBALS['realtime_shell_mode'] = $mode;
$region                         = \cacti_fixture_source_region(dirname(__DIR__, 3) . '/graph_realtime.php', "/^(if \\(read_config_option\\('realtime_enabled'\\) == ''\\) \\{\n.*?^<head>\n)/ms", __NAMESPACE__);

// The error shells exit, so the verdict is checked once output is complete.
ob_start();
register_shutdown_function(static function () use ($mode, $expected) : void {
	$html = ob_get_clean();

	if (!str_starts_with($html, $expected[$mode]) || substr_count($html, '<html') !== 1) {
		fwrite(STDERR, $html);

		throw new \RuntimeException('The realtime ' . $mode . ' shell omitted its locale attributes');
	}
	print json_encode(['passed' => $mode], JSON_THROW_ON_ERROR) . "\n";
});
require $region;
