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
 * CACTI_SERVER_OS is a constant, so the win32 branch of cacti_escapeshellarg_cmd()
 * cannot be exercised in the same process as the unix-default test suite. This
 * probe runs in its own process with CACTI_SERVER_OS actually set to 'win32'
 * so the metachar/percent stripping really executes, instead of being inferred
 * from source text.
 *
 * Each test case is printed as "label\tresult" on its own stdout line.
 */

$root = dirname(__DIR__, 2);

define('CACTI_SERVER_OS', 'win32');
define('SNMP_ESCAPE_CHARACTER', '"');
define('CACTI_ESCAPE_CHARACTER', '"');

require_once $root . '/include/vendor/autoload.php';
require_once $root . '/lib/functions.php';

$cases = [
	'metachars'        => cacti_escapeshellarg_cmd('a"b&c|d^e<f>g(h)i'),
	'percent-default'  => cacti_escapeshellarg_cmd('host%PATH%name'),
	'percent-stripped' => cacti_escapeshellarg_cmd('host%PATH%name', true, true),
	'credential-kept'  => cacti_escapeshellarg_cmd('p%ss'),
];

foreach ($cases as $label => $result) {
	echo $label . "\t" . $result . "\n";
}
