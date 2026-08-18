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
 * Every net-snmp exec path interpolates the SNMP port raw into the command line
 * (hostname:port). cacti_snmp_options_sanitize() previously only defaulted an
 * empty port to 161 without casting, so a non-numeric port would pass through
 * verbatim. Force it to an integer so it can never carry shell metacharacters.
 */

$src = file_get_contents(CACTI_PATH_LIBRARY . '/snmp.php');

test('cacti_snmp_options_sanitize forces the port to an integer', function () use ($src) {
	$start = strpos($src, 'function cacti_snmp_options_sanitize(');
	$body  = substr($src, $start, strpos($src, "\n}", $start) - $start);

	expect($body)->toContain('$port = (int) $port;');
	// non-numeric ports fall back to the default rather than passing through
	expect($body)->toContain('empty($port) || !is_numeric($port)');
});

test('the ext-based session target also casts the port', function () use ($src) {
	expect($src)->toContain("':' . (is_numeric(\$port) ? (int) \$port : 161)");
});

test('the cast neutralises a metacharacter-bearing port value', function () {
	// model of the sanitizer's port handling
	$normalize = static fn ($port) => (empty($port) || !is_numeric($port)) ? 161 : (int) $port;

	expect($normalize('161; rm -rf /'))->toBe(161);
	expect($normalize('161 -c public'))->toBe(161);
	expect($normalize(''))->toBe(161);
	expect($normalize('1611'))->toBe(1611);
	expect($normalize(162))->toBe(162);
});
