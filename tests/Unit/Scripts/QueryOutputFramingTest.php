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

require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

foreach (array('cpu', 'partitions') as $script) {
	cacti_fixture_source_function(dirname(__DIR__, 3) . '/scripts/query_host_' . $script . '.php', 'reindex', 'CactiQueryOutput' . ucfirst($script));
}

test('host queries keep each walked value within one output record', function ($script) {
	$reindex = 'CactiQueryOutput' . ucfirst($script) . '\\reindex';
	$rows = array(
		array('value' => 'ordinary value'),
		array('value' => "volume\r\nlabel\ncontinued"),
		array('value' => "12\n"),
		array('value' => 0),
		array('value' => ''),
	);

	$values = $reindex($rows);
	expect($values)->toBe(array('ordinary value', 'volumelabelcontinued', '12', '0', ''));
	expect(explode(PHP_EOL, implode(PHP_EOL, $values) . PHP_EOL))->toHaveCount(count($rows) + 1);
	expect($reindex(array()))->toBe(array());
})->with(array('cpu', 'partitions'));
