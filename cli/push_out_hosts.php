#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

ini_set('output_buffering', 'Off');

require(__DIR__ . '/../include/cli_check.php');

require_once(CACTI_PATH_LIBRARY . '/utility.php');

ini_set('max_execution_time', '0');
ini_set('memory_limit', '-1');

print 'WARNING: Deprecated script push_out_hosts.php. Please use rebuild_poller_cache.php.' . PHP_EOL;
cacti_log('WARNING: Deprecated script push_out_hosts.php. Please use rebuild_poller_cache.php.', true, 'PUSHOUT');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

/* optional parameters for host selection */
$debug            = false;
$host_id          = false;
$host_template_id = false;
$data_template_id = false;

/* optional for threading and verbose display */
$threads           = 5;

/* optional for force handing and resume */
$forcerun          = false;

$php_binary = read_config_option('path_php_binary');

$parameters = implode(' ', $parms);

passthru ($php_binary . ' ' . CACTI_PATH_CLI . '/rebuild_poller_cache.php ' .$parameters);
