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

/**
 * Returns true when htmx support is enabled for this install. Reads the
 * htmx_enabled setting row. Default behavior is enabled on develop so the
 * pilot surfaces; flip the setting to 'off' to disable site-wide.
 *
 * Guards on function_exists('read_config_option') so callers during early
 * CLI bootstrap (before the settings table is reachable) get false rather
 * than a fatal call.
 *
 * @return bool
 */
function cacti_htmx_enabled(): bool {
	if (!function_exists('read_config_option')) {
		// @codeCoverageIgnoreStart
		// Reached only before include/global.php declares read_config_option
		// (early CLI bootstrap). Every test loads global.php, which declares it
		// process-wide, so this guard cannot be exercised under the test suite.
		return false;
		// @codeCoverageIgnoreEnd
	}

	$value = read_config_option('htmx_enabled');

	// When the settings row is absent read_config_option() falls back to the
	// default from include/global_settings.php ('on'), so '' only reaches us
	// when the cached value is explicitly empty (or no default is registered,
	// as in stripped-down test bootstraps). Treat that as disabled so
	// half-initialised installs are safe.
	// The setting is a drop_array with explicit 'on'/'off' values, so treat only
	// an exact 'on' as enabled. Anything else (an empty cached value, a stray
	// '0'/'1', or an explicit 'off') leaves htmx disabled.
	return $value === 'on';
}

/**
 * Returns true when the current request was issued by htmx. The HX-Request
 * header signals that htmx initiated the fetch, so the page can return a
 * fragment instead of a full HTML document.
 *
 * PHP normalises header names to HTTP_ + uppercase + underscores, so
 * HX-Request arrives as HTTP_HX_REQUEST.
 *
 * @return bool
 */
function htmx_is_fragment_request(): bool {
	return ($_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true';
}

/**
 * Returns the vendored htmx version string (reads include/js/htmx.js.version).
 * Empty string when the version file is missing.
 *
 * @return string
 */
function htmx_version(): string {
	$path = CACTI_PATH_BASE . '/include/js/htmx.js.version';

	if (!file_exists($path)) {
		return '';
	}

	return trim((string) file_get_contents($path));
}

/**
 * Subresource Integrity hash pinned to the exact vendored htmx 2.0.6 build
 * (include/js/htmx.js). This is a constant, not a value recomputed from the
 * served file at runtime: a self-referential hash gives no supply-chain
 * protection, since swapping htmx.js would simply re-hash to match it. With
 * a fixed constant the browser rejects any vendored file that no longer matches.
 *
 * On an htmx upgrade, replace the vendored file and recompute this value:
 *   php -r 'echo "sha384-".base64_encode(hash_file("sha384","include/js/htmx.js",true));'
 */
const HTMX_2_0_6_SRI = 'sha384-ksKjJrwjL5VxqAkAZAVOPXvMkwAykMaNYegdixAESVr+KqLkKE8XBDoZuwyWVUDv';

/**
 * Returns a <script> tag that loads the vendored htmx.js with an SRI
 * integrity attribute and crossorigin="anonymous". Returns empty string when
 * htmx is disabled or the vendored file is absent.
 *
 * hx-boost is intentionally left off the global document. Boost intercepts
 * every anchor and form, which conflicts with Cacti's existing jQuery-driven
 * navigation and AJAX submission model. Pages that want htmx behaviour opt in
 * per-element with explicit hx-* attributes.
 *
 * The integrity attribute uses the HTMX_2_0_6_SRI constant pinned to the
 * vendored build (see that constant for why the hash is not recomputed here).
 *
 * The src is root-absolute (prefixed with CACTI_PATH_URL) so the asset
 * resolves the same way on plugin pages served from subdirectories, matching
 * get_md5_include_js() in lib/functions.php. A document-relative src 404s
 * whenever the current page lives below the Cacti root. The cache-buster
 * query string matches that helper's shape too (path + '?' + hash, no
 * parameter name), and is recomputed on every call rather than cached in a
 * static: get_md5_include_js()/get_theme_paths() do the same, since a static
 * cache would leave long-lived PHP processes (FPM, script_server) serving a
 * stale hash after include/js/htmx.js changes.
 *
 * Two wiring pieces precede the script element:
 *   - an htmx-config meta that disables allowEval/allowScriptTags. htmx 2.0.6
 *     defaults both to true, which Cacti's CSP (no unsafe-eval) forbids. The
 *     meta is read by htmx at load, so it must appear before the script.
 *   - an htmx:configRequest listener that adds the csrf-magic token to
 *     body-based (POST/PUT/PATCH) htmx requests. Cacti validates the
 *     __csrf_magic field on POSTs; layout.js injects it into $.post payloads,
 *     but htmx has no such hook, so the first hx-post would otherwise fail
 *     CSRF validation. GET and DELETE are excluded because htmx 2.0.6 encodes
 *     their parameters into the URL (methodsThatUseUrlParams), which would
 *     leak the token into query strings and server logs.
 *
 * @return string
 */
function htmx_script_tag(): string {
	if (!cacti_htmx_enabled()) {
		return '';
	}

	$js_path = CACTI_PATH_BASE . '/include/js/htmx.js';

	if (!file_exists($js_path)) {
		return '';
	}

	// md5 is a cache-buster for the asset URL only, recomputed on every call
	// (not cached in a static) so an htmx.js upgrade is visible without a
	// process restart. Integrity is the pinned constant, never derived from
	// the served file (see HTMX_2_0_6_SRI).
	$md5 = md5_file($js_path);

	// CACTI_PATH_URL is defined by include/global_path.php during a normal
	// bootstrap. Fall back to '/' when the constant is absent (lightweight
	// fixtures that load lib/htmx.php without the full path bootstrap).
	$base = defined('CACTI_PATH_URL') ? CACTI_PATH_URL : '/';
	$url  = $base . 'include/js/htmx.js?' . $md5;

	$config_meta = "<meta name='htmx-config' content='"
		. htmlspecialchars('{"allowEval":false,"allowScriptTags":false}', ENT_QUOTES, 'UTF-8')
		. "'>\n";

	// htmx:configRequest fires before each request and bubbles to document;
	// listen on document so this works from the <head> where document.body is
	// still null. Mirror layout.js by adding csrfMagicToken under the
	// __csrf_magic field that csrf-magic validates. Only body-based verbs get
	// the token: htmx puts GET and DELETE parameters into the URL, which would
	// leak it (see the function doc).
	$csrf_wiring = "<script type='text/javascript'" . cacti_csp_nonce_attribute() . ">\n"
		. "document.addEventListener('htmx:configRequest', function(evt) {\n"
		. "\tvar verb = String(evt.detail.verb).toLowerCase();\n"
		. "\tif (typeof csrfMagicToken !== 'undefined' && (verb === 'post' || verb === 'put' || verb === 'patch')) {\n"
		. "\t\tevt.detail.parameters['__csrf_magic'] = csrfMagicToken;\n"
		. "\t}\n"
		. "});\n"
		. "</script>\n";

	$tag = "<script type='text/javascript' src='" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "'"
		. cacti_csp_nonce_attribute()
		. " integrity='" . HTMX_2_0_6_SRI . "'"
		. " crossorigin='anonymous'></script>\n";

	// Both wiring pieces precede the script element (see the function doc):
	// the config meta must exist before htmx reads it at load, and registering
	// the listener first means no configRequest can ever fire without it.
	return $config_meta . $csrf_wiring . $tag;
}
