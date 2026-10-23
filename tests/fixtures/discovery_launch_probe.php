<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
*/

declare(strict_types = 1);

function read_config_option(string $name) : string {
	if ($name !== 'path_webroot') {
		throw new RuntimeException('Unexpected setting');
	}

	return '/tmp/cacti discovery';
}

require dirname(__DIR__, 2) . '/lib/api_automation.php';
$scenario = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
echo json_encode(automation_get_scan_arguments(2, 7, $scenario['thread'], $scenario['force'], $scenario['debug'], $scenario['dryrun']), JSON_THROW_ON_ERROR), PHP_EOL;
