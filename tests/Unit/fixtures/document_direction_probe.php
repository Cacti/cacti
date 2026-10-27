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
 * Loads include/global_languages.php through its disabled-i18n fallback, which
 * defines CACTI_LOCALE as en-US, then reports the direction helpers. It needs
 * its own process because the fakes below carry names lib/ declares.
 */

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

print json_encode([
	'directions'  => $directions,
	'active'      => get_locale_direction(),
	'attributes'  => get_html_document_attributes(),
	'rtl_locales' => $rtl_locales,
]);
