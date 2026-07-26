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
 * Minimal fixture served by `php -S` for the HtmxLoaderTest harness. We
 * deliberately avoid include/global.php here: the full bootstrap reaches for
 * a database that the test harness does not provide. Instead we replicate
 * the two runtime pieces htmx.php actually depends on:
 *
 *   - CACTI_PATH_BASE  (used to locate the vendored asset)
 *   - read_config_option() (used to read the htmx_enabled setting)
 *
 * The fixture reads FIXTURE_HTMX_ENABLED and FIXTURE_EMIT_FRAGMENT_FLAG from
 * the environment so the harness can drive the two branches.
 */

define('CACTI_PATH_BASE', realpath(__DIR__ . '/../../..'));

if (!function_exists('read_config_option')) {
	function read_config_option(string $name, bool $force = false): mixed {
		if ($name === 'htmx_enabled') {
			return getenv('FIXTURE_HTMX_ENABLED') !== false
				? getenv('FIXTURE_HTMX_ENABLED')
				: 'on';
		}

		return null;
	}
}

require_once CACTI_PATH_BASE . '/lib/htmx.php';

header('Content-Type: text/html; charset=UTF-8');

if (getenv('FIXTURE_EMIT_FRAGMENT_FLAG') === '1') {
	echo htmx_is_fragment_request() ? 'FRAGMENT=true' : 'FRAGMENT=false';

	return;
}

echo "<!doctype html><html><head>\n";
echo htmx_script_tag();
echo "</head><body>ok</body></html>\n";
