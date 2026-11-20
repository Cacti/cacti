#!/usr/bin/env php
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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

namespace Cacti\Test\RemoveSpikesHelp;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

// The version banner normally queries the configured installation.
function display_version() : void {
}

\cacti_fixture_source_function(dirname(__DIR__, 3) . '/cli/removespikes.php', 'display_help', __NAMESPACE__);
ob_start();
display_help();
$help = ob_get_clean();

foreach (['-n|--number=N', '--backup', 'stddev|fill|float|absolute', '--outlier-start', '--outlier-end'] as $option) {
	if (!str_contains($help, $option)) {
		throw new \RuntimeException('Missing from removespikes help: ' . $option);
	}
}

foreach (['-N|--number', '--outliers', '--percent', 'variance'] as $option) {
	if (str_contains($help, $option)) {
		throw new \RuntimeException('Unsupported option in removespikes help: ' . $option);
	}
}
