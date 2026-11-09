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

namespace CactiSelectedItemsFixture;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

// Run the checked-in selected_items decoder against canonical lists and the
// encodings it must refuse, so every decision branch executes.
\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/functions.php', 'sanitize_unserialize_selected_items', __NAMESPACE__);

$cases = [
	[serialize([1, 2, 3]), [1, 2, 3]],
	[serialize(['4', '']), ['4', '']],
	[serialize([1, 2]) . 'x', false],
	[serialize([1, 2]) . '<b>', false],
	['a:1:{i:0;i:+5;}', false],
	[serialize([1 => 5, 2 => 6]), false],
	[serialize(['id' => 5]), false],
	[serialize([[1]]), false],
	[serialize(['1 OR 1']), false],
	['a:1:{i:0;O:8:"stdClass":0:{}}', false],
	['s:1:"a";', false],
	['', false],
	[[1, 2], false],
];

foreach ($cases as $index => [$input, $expected]) {
	if (sanitize_unserialize_selected_items($input) !== $expected) {
		throw new \RuntimeException('selected_items decision mismatch for case ' . $index);
	}
}

print json_encode(['passed' => count($cases)], JSON_THROW_ON_ERROR) . "\n";
