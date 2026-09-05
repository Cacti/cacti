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
 * Reports the PHP version utility_php_verify_recommends() shows an operator,
 * alongside the constant that is meant to be its only source. Runs in its own
 * process because it declares stand-ins for Cacti runtime functions.
 */

declare(strict_types = 1);

$root = dirname(__DIR__, 2);

require_once $root . '/include/global_constants.php';

function cacti_log(...$arguments) {
}

function cacti_strtoupper($string) {
	return strtoupper((string) $string);
}

function cacti_strtolower($string) {
	return strtolower((string) $string);
}

function __($string, ...$arguments) {
	return $string;
}

require_once $root . '/lib/utility.php';

$recommends = null;
utility_php_verify_recommends($recommends, 'probe');

$reported = null;

foreach ((array) $recommends as $entry) {
	if ($entry['name'] === 'version') {
		$reported = $entry['value'];

		break;
	}
}

print json_encode([
	'reported' => $reported,
	'constant' => CACTI_PHP_VERSION_MINIMUM,
], JSON_THROW_ON_ERROR);
