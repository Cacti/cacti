<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Hand-off contract for the htmx loader. These tests pin the externally
 * observable behaviour that downstream callers (themes, plugins, page
 * renderers) rely on. Breaking any assertion here is a breaking change for
 * consumers, not an internal refactor.
 */

if (!file_exists(dirname(__DIR__, 2) . '/lib/htmx.php')) {
	test('htmx loader hand-off: feature not present on this branch', function () {})
		->skip('lib/htmx.php absent — feature PR #7066 not merged into develop yet');

	return;
}

require_once dirname(__DIR__, 1) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';
require_once dirname(__DIR__, 2) . '/lib/htmx.php';

beforeEach(function () {
	global $config;

	unset(
		$config[OPTIONS_CLI]['htmx_enabled'],
		$_SERVER['HTTP_HX_REQUEST']
	);
});

test('htmx_script_tag prefixes the src with CACTI_PATH_URL', function () {
	global $config;

	$config[OPTIONS_CLI]['htmx_enabled'] = 'on';

	$tag = htmx_script_tag();
	$md5 = md5_file(CACTI_PATH_BASE . '/include/js/htmx.js');

	expect($tag)->toContain("src='" . CACTI_PATH_URL . 'include/js/htmx.js?' . $md5 . "'");
});

test('htmx.js.version content matches version pinned by the integrity attribute', function () {
	global $config;

	$config[OPTIONS_CLI]['htmx_enabled'] = 'on';

	/*
	 * The vendored version file and the SRI hash baked into htmx_script_tag()
	 * must move together. If this assertion fails the SRI hash in lib/htmx.php
	 * is stale relative to the vendored binary and browsers will refuse to
	 * execute the script.
	 */
	expect(htmx_version())->toBe('2.0.6');

	expect(htmx_script_tag())->toContain(
		"integrity='sha384-ksKjJrwjL5VxqAkAZAVOPXvMkwAykMaNYegdixAESVr+KqLkKE8XBDoZuwyWVUDv'"
	);
});

test('htmx_script_tag cache-busts the src with the md5 of the vendored file', function () {
	global $config;

	$config[OPTIONS_CLI]['htmx_enabled'] = 'on';

	$md5 = md5_file(CACTI_PATH_BASE . '/include/js/htmx.js');

	// No 'v=' parameter name: matches the path + '?' + hash shape used by
	// get_md5_include_js()/get_theme_paths() in lib/functions.php.
	expect(htmx_script_tag())->toContain('/htmx.js?' . $md5);
});

test('htmx_script_tag renders a script tag when htmx_enabled is on', function () {
	global $config;

	$config[OPTIONS_CLI]['htmx_enabled'] = 'on';

	$tag = htmx_script_tag();

	expect($tag)->toContain('<script')
		->and($tag)->toMatch('/<script[^>]*nonce=/')
		->and($tag)->toContain('include/js/htmx.js');
});

test('console headers avoid inline logo click handlers', function () {
	$top_header = file_get_contents(CACTI_PATH_BASE . '/include/top_header.php');
	$general_header = file_get_contents(CACTI_PATH_BASE . '/include/top_general_header.php');

	expect($top_header)->not->toContain('onclick=')
		->and($general_header)->not->toContain('onclick=')
		->and($top_header)->toContain('data-load-url=')
		->and($general_header)->toContain('data-load-url=');
});

test('htmx_script_tag renders nothing when htmx_enabled is absent', function () {
	global $config;

	$config[OPTIONS_CLI]['htmx_enabled'] = '';

	$tag = htmx_script_tag();

	expect($tag)->toBe('')
		->and($tag)->not->toContain('<script')
		->and($tag)->not->toContain('htmx.js');
});

test('htmx_script_tag renders nothing when htmx_enabled is off', function () {
	global $config;

	$config[OPTIONS_CLI]['htmx_enabled'] = 'off';

	$tag = htmx_script_tag();

	expect($tag)->toBe('')
		->and($tag)->not->toContain('<script')
		->and($tag)->not->toContain('htmx.js');
});

test('include/global.php requires lib/htmx.php before any caller can invoke htmx_script_tag', function () {
	/*
	 * Regression guard for the integration bug: lib/html.php calls
	 * htmx_script_tag() during page render, so include/global.php must require
	 * lib/htmx.php no later than the point where lib/html.php's render path
	 * becomes reachable. The cheapest invariant that catches the original
	 * breakage is "htmx.php is required, and it appears before the dispatch
	 * section that runs page code". We assert by reading global.php directly
	 * rather than by exercising the full bootstrap.
	 */
	$global = file_get_contents(CACTI_PATH_BASE . '/include/global.php');

	expect($global)->toContain("require_once(CACTI_PATH_LIBRARY . '/html.php');")
		->and($global)->toContain("require_once(CACTI_PATH_LIBRARY . '/htmx.php');");

	$html_pos = strpos($global, "require_once(CACTI_PATH_LIBRARY . '/html.php');");
	$htmx_pos = strpos($global, "require_once(CACTI_PATH_LIBRARY . '/htmx.php');");

	expect($html_pos)->toBeInt()
		->and($htmx_pos)->toBeInt();

	/*
	 * htmx.php is a dependency of html.php: html.php's render path calls
	 * htmx_script_tag(), while htmx.php itself pulls nothing from html.php. The
	 * dependency loads first, so htmx.php must be required before html.php.
	 */
	expect($htmx_pos)->toBeLessThan($html_pos);

	$dispatch_pos = strpos($global, '$filename = get_current_page();');

	expect($dispatch_pos)->toBeInt()
		->and($htmx_pos)->toBeLessThan($dispatch_pos);
});
