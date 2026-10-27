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
 * Probe for I18nFallbackTranslateTest. Run through cacti_test_isolated_probe();
 * prints a JSON verdict on stdout.
 *
 * Loads include/global_languages.php with i18n support switched off, which is
 * the path that returns early to the fallback locale, and reports whether the
 * translation API survived the trip. It needs its own process because the
 * fakes below carry names lib/ declares.
 *
 * argv[1] is the value read_config_option() reports for i18n_language_support.
 */

require_once __DIR__ . '/i18n_probe_bootstrap.php';

print json_encode([
	'i18n_support'     => $i18n_support,
	'translate_exists' => function_exists('__'),
	'esc_exists'       => function_exists('__esc'),
	'gettext_exists'   => function_exists('__gettext'),
	'translated'       => function_exists('__') ? __('Version %s', '1.3.0') : null,
]);
