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
 * GHSA-rjvj-r52f-8v5q. On Windows the SNMP binaries run through cmd.exe, which
 * ignores the backslash escape and toggles quote-state on every ". Wrapping a
 * community or credential in quotes therefore cannot neutralize a value like
 *   public" & whoami & "
 * because cmd.exe sees the injected & outside quotes and chains the command.
 * The fix strips cmd.exe metacharacters (" & | ^ < > ( )) from SNMP field
 * values and from the device target before they reach exec().
 */

global $config;
$config['cacti_server_os'] = 'win32';

if (!defined('SNMP_ESCAPE_CHARACTER')) {
	define('SNMP_ESCAPE_CHARACTER', '"');
}

if (!defined('CACTI_ESCAPE_CHARACTER')) {
	define('CACTI_ESCAPE_CHARACTER', '"');
}

/* snmp_format_target() delegates the final quoting to cacti_escapeshellarg(),
 * which lives in lib/functions.php and pulls in the wider bootstrap. Provide a
 * minimal win32 stand-in so the target formatter can be exercised in
 * isolation; it mirrors the win32 quote-wrapping branch of the real helper. */
if (!function_exists('cacti_escapeshellarg')) {
	function cacti_escapeshellarg($string, $quote = true) {
		global $config;

		if ($string == '') {
			return $string;
		}

		$string = str_replace(array("\n", "\r"), array('', ''), $string);

		if (substr_count($string, CACTI_ESCAPE_CHARACTER)) {
			$string = str_replace(CACTI_ESCAPE_CHARACTER, '\\' . CACTI_ESCAPE_CHARACTER, $string);
		}

		if ($quote) {
			return CACTI_ESCAPE_CHARACTER . $string . CACTI_ESCAPE_CHARACTER;
		}

		return $string;
	}
}

if (!function_exists('snmp_escape_string')) {
	require_once __DIR__ . '/../../../../lib/snmp.php';
}

test('snmp_escape_string fails closed for cmd.exe metacharacters on win32', function () {
	global $config;
	$config['cacti_server_os'] = 'win32';

	$result = snmp_escape_string('public" & calc & "');

	/* SNMP community/credential values never legitimately contain shell
	 * metacharacters, so a value carrying one is rejected outright. */
	expect($result)->toBe('');
});

test('snmp_escape_string leaves a normal community intact', function () {
	global $config;
	$config['cacti_server_os'] = 'win32';

	expect(snmp_escape_string('normalcommunity'))->toBe('"normalcommunity"');
});

test('snmp_format_target strips metacharacters from the device target', function () {
	$result = snmp_format_target('host" & calc & "', 161);

	/* no breakout: the injected & and the closing " must be gone */
	expect(strpos($result, '&'))->toBeFalse();

	/* the only quotes are the wrapper cacti_escapeshellarg adds */
	expect(substr_count($result, '"'))->toBe(2);
	expect($result)->toBe('"host  calc  ":161');
});
