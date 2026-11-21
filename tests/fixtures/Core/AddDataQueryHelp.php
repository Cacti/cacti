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

namespace Cacti\Test\AddDataQueryHelp;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

// The version banner normally queries the configured installation.
function display_version() : void {
}

$source = file_get_contents(dirname(__DIR__, 3) . '/cli/add_data_query.php');

if ($source === false) {
	throw new \RuntimeException('Unable to read add_data_query.php');
}

\cacti_fixture_source_function(dirname(__DIR__, 3) . '/cli/add_data_query.php', 'display_help', __NAMESPACE__);
ob_start();
display_help();
$help = ob_get_clean();

// The help must describe the behavior the script has: an error for the same
// reindex method, an update and reindex for a different one.
foreach (['using the same reindex method', 'reports an error and changes nothing', 'the method is updated and the data query is reindexed'] as $phrase) {
	if (!str_contains($help, $phrase)) {
		throw new \RuntimeException('Missing from add_data_query help: ' . $phrase);
	}
}

if (str_contains($help, 'it will be reindexed.')) {
	throw new \RuntimeException('add_data_query help still promises an unconditional reindex');
}

// The script must still stop with an error for the same reindex method.
if (!preg_match('/ERROR: Data Query is already associated.*?exit\(1\);/s', $source)) {
	throw new \RuntimeException('add_data_query no longer exits with an error for an existing association');
}
