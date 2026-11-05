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
 * Run the live Script Server contracts under the request-coverage collector.
 * The production entrypoint runs in child processes, so ordinary PHPUnit
 * coverage cannot observe those instructions.
 */

$root = dirname(__DIR__, 2);
$null = fopen(PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r');

if ($null === false) {
	throw new RuntimeException('Unable to open the null input stream');
}

$process = proc_open([
	PHP_BINARY,
	'-d',
	'auto_prepend_file=' . $root . '/tests/Helpers/RequestCoverage.php',
	$root . '/include/vendor/bin/pest',
	'--configuration=' . $root . '/tests/phpunit.xml',
	'--compact',
	$root . '/tests/Unit/DataCollection/Poller/ExecWithTimeoutTest.php',
	$root . '/tests/Unit/DataCollection/Poller/ScriptServerLogHelpersTest.php',
	$root . '/tests/Unit/DataCollection/Poller/ScriptServerProcessTest.php',
	$root . '/tests/Unit/DataCollection/Poller/ScriptServerRequestTest.php',
	$root . '/tests/Unit/Scripts/NetSnmpDiskStateFileTest.php',
	$root . '/tests/Unit/Security/Shell/ScriptServerContainmentTest.php'
], [
	0 => $null,
	1 => STDOUT,
	2 => STDERR
], $pipes, $root);

if (!is_resource($process)) {
	fclose($null);

	throw new RuntimeException('Unable to start the Script Server coverage contracts');
}

$status = proc_close($process);
fclose($null);

if ($status !== 0) {
	throw new RuntimeException('Script Server coverage contracts failed');
}
