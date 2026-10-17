<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

define('IN_CACTI_INSTALL', 1);
require_once __DIR__ . '/../include/auth.php';

if (!is_realm_allowed(26)) {
	http_response_code(403);
	exit;
}

$report = read_config_option('maintenance_lastrun_installer_report', true);

if (!is_string($report) || $report === '') {
	http_response_code(404);
	exit;
}

header('Content-Type: application/json; charset=UTF-8');
header('Content-Disposition: attachment; filename="cacti-installer-report.json"');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
print $report;
