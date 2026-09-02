<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

// db_add_column() reads $column['NULL']. PHP array keys are case sensitive, so
// a lowercase 'null' key is silently discarded and the column is created
// nullable even though the upgrade asked for NOT NULL.
test('upgrade column definitions spell the NULL key in upper case', function () {
	$upgrades = glob(dirname(__DIR__, 3) . '/install/upgrades/*.php');

	expect($upgrades)->not->toBeEmpty();

	foreach ($upgrades as $upgrade) {
		expect(file_get_contents($upgrade))
			->not->toContain("'null' =>", "lowercase 'null' key in " . basename($upgrade));
	}
});

test('the column builder still reads the upper case key', function () {
	$database = file_get_contents(dirname(__DIR__, 3) . '/lib/database.php');

	expect($database)->toContain("\$column['NULL']");
});
