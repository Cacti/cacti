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

require_once __DIR__ . '/../lib/Installer/DependencyPreflight.php';

$preflightErrors = (new \Cacti\Installer\DependencyPreflight(dirname(__DIR__)))->errors();

if ($preflightErrors !== []) {
	if (PHP_SAPI === 'cli') {
		fwrite(STDERR, implode(PHP_EOL, $preflightErrors) . PHP_EOL);
	} else {
		http_response_code(503);

		if (defined('CACTI_INSTALL_JSON')) {
			header('Content-Type: application/json');
			header('Cache-Control: no-store');
			print json_encode(['error' => implode(PHP_EOL, $preflightErrors), 'errors' => $preflightErrors], JSON_UNESCAPED_SLASHES);
		} else {
			header('Content-Type: text/plain; charset=UTF-8');
			print implode(PHP_EOL, $preflightErrors);
		}
	}

	exit(1);
}
