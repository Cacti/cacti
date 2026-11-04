<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

if ($argc !== 2) {
	exit(64);
}

define('CACTI_LOCALE', 'en-US');

/**
 * Return an empty setting for the isolated permission probe.
 *
 * @param string $name Setting name.
 *
 * @return string Empty setting value.
 */
function read_config_option($name) {
	return '';
}

/**
 * Write probe diagnostics to standard error.
 *
 * @param string $message Diagnostic message.
 *
 * @return void No value is returned.
 */
function cacti_log($message) {
	fwrite(STDERR, $message . PHP_EOL);
}

require dirname(__DIR__, 3) . '/lib/rrd.php';

exit(rrdtool_apply_file_permissions($argv[1], 'BOOST') ? 0 : 65);
