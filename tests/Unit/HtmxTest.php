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

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';
require_once dirname(__DIR__, 2) . '/lib/htmx.php';

/*
 * read_config_option() is already declared by include/global.php. In CLI mode
 * it reads from $config[OPTIONS_CLI] before touching the database, so seeding
 * that array lets us drive the htmx helpers without a live connection.
 */
beforeEach(function () {
	global $config;

	unset(
		$config[OPTIONS_CLI]['htmx_enabled'],
		$_SERVER['HTTP_HX_REQUEST']
	);
});

test('htmx_version reads the version file', function () {
	expect(htmx_version())->toBe('2.0.6');
});

test('htmx_version returns empty string when the version file is missing', function () {
	$path   = CACTI_PATH_BASE . '/include/js/htmx.js.version';
	$backup = $path . '.bak.' . getmypid();

	$moved = rename($path, $backup);
	expect($moved)->toBeTrue();

	// The preceding test already called file_exists()/file_get_contents() on
	// $path, so PHP's stat cache can still report it as present right after
	// the rename above. Clear it or htmx_version() reads stale cached stat
	// data instead of the now-missing file, making this assertion flaky.
	clearstatcache(true, $path);

	try {
		expect(htmx_version())->toBe('');
	} finally {
		// Restore only when we actually moved the file, and fail loudly if the
		// restore does not land so a dirty repo never leaks into later tests.
		if ($moved && file_exists($backup)) {
			expect(rename($backup, $path))->toBeTrue();

			// Same stat-cache hazard in reverse: clear it so the next test's
			// file_exists()/file_get_contents() sees the restored file.
			clearstatcache(true, $path);
		}
	}
});

test('cacti_htmx_enabled is false when the cached value is explicitly empty', function () {
	global $config;

	// An absent row falls back to the 'on' default from global_settings.php,
	// so seed an explicitly empty cached value to hit the disabled path.
	$config[OPTIONS_CLI]['htmx_enabled'] = '';

	expect(cacti_htmx_enabled())->toBeFalse();
});

test('cacti_htmx_enabled is false when the setting is explicitly off', function () {
	global $config;

	$config[OPTIONS_CLI]['htmx_enabled'] = 'off';

	expect(cacti_htmx_enabled())->toBeFalse();
});

test('cacti_htmx_enabled is true when the setting is on', function () {
	global $config;

	$config[OPTIONS_CLI]['htmx_enabled'] = 'on';

	expect(cacti_htmx_enabled())->toBeTrue();
});

test('htmx_is_fragment_request is true when HX-Request header is set to true', function () {
	$_SERVER['HTTP_HX_REQUEST'] = 'true';

	expect(htmx_is_fragment_request())->toBeTrue();
});

test('htmx_is_fragment_request is false when the header is absent', function () {
	expect(htmx_is_fragment_request())->toBeFalse();
});

test('htmx_is_fragment_request is false when the header is present but not "true"', function () {
	$_SERVER['HTTP_HX_REQUEST'] = 'false';

	expect(htmx_is_fragment_request())->toBeFalse();
});

test('htmx_script_tag returns empty string when disabled', function () {
	global $config;

	$config[OPTIONS_CLI]['htmx_enabled'] = 'off';

	expect(htmx_script_tag())->toBe('');
});

test('htmx_script_tag returns empty string when the vendored file is missing', function () {
	global $config;

	$config[OPTIONS_CLI]['htmx_enabled'] = 'on';

	$path   = CACTI_PATH_BASE . '/include/js/htmx.js';
	$backup = $path . '.bak.' . getmypid();

	$moved = rename($path, $backup);
	expect($moved)->toBeTrue();

	// Every later 'enabled' test re-reads $path via file_exists()/md5_file();
	// clear the stat cache so this rename is visible immediately rather than
	// leaving stale "still present" data behind.
	clearstatcache(true, $path);

	try {
		expect(htmx_script_tag())->toBe('');
	} finally {
		if ($moved && file_exists($backup)) {
			expect(rename($backup, $path))->toBeTrue();
			clearstatcache(true, $path);
		}
	}
});

test('htmx_script_tag carries src, integrity, and crossorigin when enabled', function () {
	global $config;

	$config[OPTIONS_CLI]['htmx_enabled'] = 'on';

	$tag = htmx_script_tag();

	expect($tag)->toContain('src=')
		->and($tag)->toContain('include/js/htmx.js')
		->and($tag)->toMatch('/<script[^>]*nonce=/')
		->and($tag)->toMatch('/integrity=.sha384-[A-Za-z0-9+\/=]+./')
		->and($tag)->toContain("crossorigin='anonymous'");
});

test('get_md5_include_js adds the request nonce attribute', function () {
	$tag = get_md5_include_js('include/layout.js');

	expect($tag)->toMatch('/<script[^>]*nonce=\'[A-Za-z0-9+\/=]+\'/');
});

test('htmx_script_tag integrity matches the sha384 of the vendored file', function () {
	global $config;

	$config[OPTIONS_CLI]['htmx_enabled'] = 'on';

	$expected = 'sha384-' . base64_encode((string) hash_file('sha384', CACTI_PATH_BASE . '/include/js/htmx.js', true));

	expect(htmx_script_tag())->toContain("integrity='" . $expected . "'");
});

test('htmx_script_tag cache-busts with md5 of the vendored file', function () {
	global $config;

	$config[OPTIONS_CLI]['htmx_enabled'] = 'on';

	$md5 = md5_file(CACTI_PATH_BASE . '/include/js/htmx.js');

	// No 'v=' parameter name: matches the path + '?' + hash shape used by
	// get_md5_include_js()/get_theme_paths() in lib/functions.php.
	expect(htmx_script_tag())->toContain('/htmx.js?' . $md5);
});

test('htmx_script_tag emits a root-absolute src prefixed with CACTI_PATH_URL', function () {
	global $config;

	$config[OPTIONS_CLI]['htmx_enabled'] = 'on';

	$tag = htmx_script_tag();

	// extract the src attribute and assert it is not document-relative
	expect($tag)->toMatch('/src=\'' . preg_quote(CACTI_PATH_URL, '/') . 'include\/js\/htmx\.js/');

	// a document-relative src would start the path with the bare filename
	expect($tag)->not->toContain("src='include/js/htmx.js");
});

test('htmx_script_tag emits the htmx-config meta disabling eval and script tags', function () {
	global $config;

	$config[OPTIONS_CLI]['htmx_enabled'] = 'on';

	$tag = htmx_script_tag();

	/*
	 * The content attribute is htmlspecialchars-encoded, so the JSON quotes
	 * render as &quot;. Browsers decode the entity before htmx reads the
	 * attribute, so the effective config is {"allowEval":false,...}. Decode
	 * here and assert on the JSON htmx actually receives.
	 */
	expect($tag)->toContain("name='htmx-config'");

	preg_match("/content='([^']*)'/", $tag, $m);
	$content = html_entity_decode($m[1] ?? '', ENT_QUOTES, 'UTF-8');
	$decoded = json_decode($content, true);

	expect($decoded)->toBe(['allowEval' => false, 'allowScriptTags' => false]);
});

test('htmx_script_tag wires the csrf-magic token onto htmx requests', function () {
	global $config;

	$config[OPTIONS_CLI]['htmx_enabled'] = 'on';

	$tag = htmx_script_tag();

	expect($tag)->toContain("addEventListener('htmx:configRequest'")
		->and($tag)->toContain('csrfMagicToken')
		->and($tag)->toContain("parameters['__csrf_magic']");
});

test('htmx_script_tag orders the config meta before the htmx script element', function () {
	global $config;

	$config[OPTIONS_CLI]['htmx_enabled'] = 'on';

	$tag = htmx_script_tag();

	$meta_pos   = strpos($tag, "name='htmx-config'");
	$script_pos = strpos($tag, 'htmx.js');

	expect($meta_pos)->not->toBeFalse()
		->and($script_pos)->not->toBeFalse()
		->and($meta_pos)->toBeLessThan($script_pos);
});
