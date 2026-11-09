<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// The shared locale bootstrap reads the i18n mode from argv[1]. Every shell
// renders with i18n disabled, so the active locale is the en-US fallback.
$argv = [$argv[0], '0'];
require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 2) . '/Unit/fixtures/i18n_probe_bootstrap.php';

function document_shell_assert(bool $condition, string $message) : void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

// Right-to-left catalogs mirror the page; anything unknown stays left-to-right.
foreach (['ar-SA' => 'rtl', 'he-IL' => 'rtl', 'en-US' => 'ltr', 'de-DE' => 'ltr', 'xx-XX' => 'ltr', '' => 'ltr', '<script>' => 'ltr'] as $locale => $direction) {
	document_shell_assert(get_locale_direction($locale) === $direction, "Direction for '$locale' was not $direction");
}
document_shell_assert(get_locale_direction() === 'ltr', 'The active en-US locale did not default to ltr');

$attributes = " lang='en-US' dir='ltr'";
$html_tag   = "<html$attributes>";
document_shell_assert(get_html_document_attributes() === $attributes, 'Document attributes changed');

define('AUTH_METHOD_BASIC', 2);
define('CACTI_PATH_URL', '/cacti/');

function api_plugin_hook_function($name, $value = null) {
	return $value;
}
function draw_navigation_text($type = '') {
	return 'Document direction fixture';
}
function html_common_header($title) {
	print '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
}
function html_show_tabs_left() {
}
function html_graph_tabs_right() {
}
function draw_login_status($guest = false) {
}
function draw_menu($menu = []) {
}
function grow_dhtml_trees() {
}
function get_current_page() {
	return 'index.php';
}
function gnrv($name) {
	return '';
}
function isrv($name) {
	return false;
}
function cacti_cookie_logout() {
	$GLOBALS['document_shell_logout'] = true;
}

$shells = [];

// Console, graph and general page headers.
$is_request_ajax = false;

foreach (['top_header', 'top_graph_header', 'top_general_header'] as $header) {
	ob_start();
	require $root . "/include/$header.php";
	$shells[$header] = ob_get_clean();
}

// Login failure page.
$GLOBALS['i18n_probe_options']['auth_method'] = AUTH_METHOD_BASIC;
require_once $root . '/lib/auth.php';
ob_start();
auth_display_custom_error_message('Fixture authentication error');
$shells['auth_error'] = ob_get_clean();
document_shell_assert(!empty($GLOBALS['document_shell_logout']) && str_contains($shells['auth_error'], 'Fixture authentication error'), 'The login failure page did not render or clear its session');

// Login, password change and reset pages share html_auth_header().
cacti_fixture_source_function($root . '/lib/html.php', 'html_auth_header', '');
ob_start();
html_auth_header('login', 'Login to Cacti', 'User Login', 'Enter your credentials');
$shells['auth_header'] = ob_get_clean();

// Permission denied page from the authorization gate.
$title_header = 'Permission Denied';
ob_start();
require cacti_fixture_source_region($root . '/include/auth.php', '/^(\t\tprint "<!DOCTYPE html>\\\\n";\n.*?^\t\tprint "<\/head>\\\\n";\n)/ms', '');
$shells['permission_denied'] = ob_get_clean();
document_shell_assert($shells['permission_denied'] === "<!DOCTYPE html>\n$html_tag\n<head>\n<title>Permission Denied</title></head>\n", 'The permission denied shell changed');

// Installer and upgrade wizard.
ob_start();
require cacti_fixture_source_region($root . '/install/install.php', '/^(\?>\n<!DOCTYPE html>\n<html.*\n<head>\n)/m', '');
$shells['install'] = ob_get_clean();
document_shell_assert($shells['install'] === "<!DOCTYPE html>\n$html_tag\n<head>\n", 'The installer shell changed');

foreach ($shells as $name => $html) {
	document_shell_assert(substr_count($html, '<html') === 1 && str_contains($html, $html_tag), "The $name shell omitted its locale attributes");
}

print json_encode(['passed' => array_keys($shells)], JSON_THROW_ON_ERROR) . "\n";
