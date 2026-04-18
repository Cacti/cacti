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

/**
 * cacti_validate_theme - returns the requested theme name iff it names a
 * real directory under include/themes/ that contains an rrdtheme.php file.
 * Otherwise returns the configured default theme.
 *
 * Root-cause mitigation for LFI via the graph_theme request parameter.
 * basename() on the request value is not sufficient because an attacker
 * who can place files at predictable paths (plugin uploads, session files,
 * log rotation) can satisfy a basename + is_dir check with an
 * attacker-controlled directory. This helper builds the allowlist from the
 * filesystem once per request (cached statically) and rejects anything
 * that is not a genuine shipped theme.
 *
 * Applies to:
 *   GHSA-rm7p-qcqm-x5m6 (unauth LFI via graph_theme + rrdtool IPC)
 *   GHSA-cx5r-8q6h-r772 (pre-auth LFI via graph_theme)
 *
 * @param string $requested  The raw value from the request
 *
 * @return string  A validated theme name safe for path concatenation
 */
function cacti_validate_theme($requested) {
	global $config;
	static $valid_themes = null;

	$default = read_config_option('selected_theme');

	if (empty($default)) {
		$default = 'modern';
	}

	if ($valid_themes === null) {
		$valid_themes = array();
		$themes_dir   = $config['base_path'] . '/include/themes';

		if (is_dir($themes_dir)) {
			$entries = scandir($themes_dir);

			if ($entries !== false) {
				foreach ($entries as $entry) {
					if ($entry === '.' || $entry === '..') {
						continue;
					}

					$full = $themes_dir . '/' . $entry;

					if (is_dir($full) && is_file($full . '/rrdtheme.php')) {
						$valid_themes[$entry] = true;
					}
				}
			}
		}
	}

	$requested = basename((string) $requested);

	return isset($valid_themes[$requested]) ? $requested : $default;
}
