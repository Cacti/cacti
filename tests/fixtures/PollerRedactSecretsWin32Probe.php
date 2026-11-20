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
 * CACTI_SERVER_OS is a constant, so the win32 forms of cacti_escapeshellarg()
 * and cacti_escapeshellarg_cmd() cannot be built in the same process as the
 * unix-default test suite.  This probe runs in its own process with
 * CACTI_SERVER_OS set to 'win32', builds a Script Server command line for
 * each credential and escaping form, and prints the command as
 * poller_redact_snmp_secrets() masks it.
 *
 * The credentials are read as JSON from stdin; the masked commands are
 * printed as JSON keyed "<credential label>/<form>".
 */

$root = dirname(__DIR__, 2);

define('CACTI_SERVER_OS', 'win32');
define('SNMP_ESCAPE_CHARACTER', '"');
define('CACTI_ESCAPE_CHARACTER', '"');

require_once $root . '/include/vendor/autoload.php';
require_once $root . '/lib/functions.php';
require_once $root . '/tests/Helpers/SourceSlice.php';

cacti_fixture_source_function($root . '/lib/poller.php', 'poller_redact_snmp_secrets', '');

$forms = [
	'win32'           => function (string $arg) : string {
		return cacti_escapeshellarg($arg);
	},
	'win32 cmd'       => function (string $arg) : string {
		return cacti_escapeshellarg_cmd($arg);
	},
	'win32 cmd strip' => function (string $arg) : string {
		return cacti_escapeshellarg_cmd($arg, true, true);
	},
];

$secrets = json_decode((string) stream_get_contents(STDIN), true);
$results = [];

foreach ($secrets as $label => $secret) {
	$item = ['snmp_community' => '', 'snmp_password' => $secret, 'snmp_priv_passphrase' => ''];

	foreach ($forms as $form => $escape) {
		$parts = ['/opt/cacti/scripts/ss_x.php', 'ss_x'];

		foreach (['host1', $secret, "3:161:500:1:10::user:$secret:SHA"] as $arg) {
			$parts[] = $escape($arg);
		}

		$results["$label/$form"] = poller_redact_snmp_secrets(implode(' ', $parts), $item);
	}
}

print json_encode($results);
