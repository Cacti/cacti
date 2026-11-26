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
 * Probe for DocumentDirectionTest. Run through cacti_test_isolated_probe();
 * prints a JSON verdict on stdout.
 *
 * With argv[1] '0' it loads include/global_languages.php through its
 * disabled-i18n fallback, which defines CACTI_LOCALE as en-US. With argv[1]
 * '1' the session language in argv[2] and the theme in argv[3] select the
 * locale and the theme the shells render under; see i18n_probe_bootstrap.php.
 * It needs its own process because the fakes below carry names lib/ declares.
 */

require_once dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require_once __DIR__ . '/i18n_probe_bootstrap.php';

$directions = [];

foreach (['ar-SA', 'he-IL', 'en-US', 'de-DE', 'xx-XX', '', '<script>'] as $locale) {
	$directions[$locale] = get_locale_direction($locale);
}

$rtl_locales = [];

foreach (get_list_of_locales() as $locale => $data) {
	if (get_locale_direction($locale) === 'rtl') {
		$rtl_locales[] = $locale;
	}
}

// Document direction pairs a locale with the theme that renders it.
$document_directions = [];

foreach (['he-IL', 'ar-SA', 'en-US'] as $locale) {
	foreach (['rtl-fixture', 'modern', 'midwinter', '../themes/rtl-fixture', 'Rtl-Fixture', ''] as $theme) {
		$document_directions[$locale . '|' . $theme] = get_document_direction($locale, $theme);
	}
}

// Without a theme argument get_selected_theme() is not loaded and the theme resolves to none.
$document_directions['he-IL|selected'] = get_document_direction('he-IL');

$rtl_themes = [];

foreach (glob(CACTI_PATH_INCLUDE . '/themes/*', GLOB_ONLYDIR) as $directory) {
	if (theme_supports_rtl(basename($directory))) {
		$rtl_themes[] = basename($directory);
	}
}

sort($rtl_themes);

if ($i18n_document_attributes === null) {
	throw new InvalidArgumentException('Unknown document direction scenario.');
}

if ($directions['ar-SA'] !== 'rtl' || $directions['he-IL'] !== 'rtl' || $directions['<script>'] !== 'ltr' || get_html_document_attributes() !== $i18n_document_attributes) {
	throw new RuntimeException('Locale direction or document attributes changed.');
}

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
function cacti_cookie_logout() {
	$GLOBALS['direction_fixture_logout'] = true;
}

define('AUTH_METHOD_BASIC', 2);
$GLOBALS['i18n_probe_options']['auth_method'] = 2;
require_once $root . '/lib/auth.php';
ob_start();
auth_display_custom_error_message('Fixture authentication error');
$auth_html       = ob_get_clean();
$is_request_ajax = false;
ob_start();
require $root . '/include/top_general_header.php';
$general_html = ob_get_clean();

foreach ([$auth_html, $general_html] as $html) {
	if (!str_contains($html, "<html$i18n_document_attributes>")) {
		throw new RuntimeException('A document shell omitted its locale attributes.');
	}
}

if (empty($GLOBALS['direction_fixture_logout']) || !str_contains($auth_html, 'Fixture authentication error')) {
	throw new RuntimeException('The authentication error did not render or clear its session.');
}

print json_encode([
	'directions'  => $directions,
	'active'      => get_locale_direction(),
	'attributes'  => get_html_document_attributes(),
	'rtl_locales' => $rtl_locales,
	'documents'   => $document_directions,
	'rtl_themes'  => $rtl_themes,
	'shells'      => ['auth_error' => $auth_html, 'general' => $general_html],
]);
