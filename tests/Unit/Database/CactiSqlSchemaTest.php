<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

declare(strict_types = 1);

require_once dirname(__DIR__, 2) . '/Helpers/CactiSqlSchema.php';

use Cacti\Tests\Helpers\CactiSqlSchema;

/*
 * Schema checks that read cacti.sql as text, so they need no database.
 * Known exceptions are recorded in tests/tools/cacti_sql_schema_baseline.json.
 * A new violation fails; a baseline entry that no longer applies is ignored.
 */

function cacti_sql_text(): string {
	$sql = file_get_contents(CACTI_PATH_BASE . '/cacti.sql');

	expect($sql)->toBeString();

	return $sql;
}

function cacti_sql_baseline(): array {
	return json_decode(file_get_contents(CACTI_PATH_BASE . '/tests/tools/cacti_sql_schema_baseline.json'), true, 512, JSON_THROW_ON_ERROR);
}

function empty_schema_baseline(): array {
	return ['missing_primary_key' => [], 'memory_tables' => [], 'key_column_types' => [], 'zero_date_defaults' => []];
}

const SCHEMA_HEADER = "CREATE TABLE `good` (\n  `id` int(10) unsigned NOT NULL,\n  `host_id` int(10) unsigned NOT NULL default '0',\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB;\n";

it('parses every CREATE TABLE statement in cacti.sql', function () {
	$sql    = cacti_sql_text();
	$tables = CactiSqlSchema::parse($sql);

	expect(count($tables))->toBe(CactiSqlSchema::countStatements($sql))
		->and(count($tables))->toBeGreaterThan(100);

	foreach ($tables as $name => $table) {
		expect($table['columns'])->not->toBeEmpty("table $name has no columns");
	}
});

it('handles quoted and unquoted names, mixed case types and key variants', function () {
	$tables = CactiSqlSchema::parse(
		"CREATE TABLE plain (\n  id INT NOT NULL,\n  `max-access` varchar(50) NOT NULL default 'not-accessible',\n  amount decimal(10,2) default NULL comment 'a, (b',\n  PRIMARY KEY USING BTREE (id),\n  UNIQUE KEY u (id),\n  INDEX i (id),\n  FULLTEXT KEY f (amount))\n  ENGINE=MEMORY;\n"
	);

	expect($tables)->toHaveKey('plain')
		->and($tables['plain']['engine'])->toBe('memory')
		->and($tables['plain']['has_primary_key'])->toBeTrue()
		->and(array_keys($tables['plain']['columns']))->toBe(['id', 'max-access', 'amount'])
		->and($tables['plain']['columns']['id']['type'])->toBe('int')
		->and($tables['plain']['columns']['amount']['type'])->toBe('decimal(10,2)');
});

it('passes cacti.sql against the recorded baseline', function () {
	$messages = CactiSqlSchema::violations(CactiSqlSchema::parse(cacti_sql_text()), cacti_sql_baseline());

	expect($messages)->toBe([], implode("\n", $messages));
});

it('fails a table without a primary key', function () {
	$sql = SCHEMA_HEADER . "CREATE TABLE `nokey` (\n  `id` int(10) unsigned NOT NULL\n) ENGINE=InnoDB;\n";

	expect(CactiSqlSchema::violations(CactiSqlSchema::parse($sql), empty_schema_baseline()))
		->toHaveCount(1)
		->and(CactiSqlSchema::violations(CactiSqlSchema::parse($sql), empty_schema_baseline())[0])
		->toContain('Table nokey has no PRIMARY KEY')->toContain('baseline');
});

it('fails an engine other than InnoDB or MEMORY', function () {
	$sql = SCHEMA_HEADER . "CREATE TABLE `old` (\n  `id` int(10) unsigned NOT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=MyISAM;\n";

	expect(CactiSqlSchema::violations(CactiSqlSchema::parse($sql), empty_schema_baseline())[0])
		->toContain("Table old uses engine 'myisam'");
});

it('fails an unrecorded MEMORY table and accepts a recorded one', function () {
	$sql      = SCHEMA_HEADER . "CREATE TABLE `scratch` (\n  `id` int(10) unsigned NOT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=MEMORY;\n";
	$baseline = empty_schema_baseline();

	expect(CactiSqlSchema::violations(CactiSqlSchema::parse($sql), $baseline)[0])->toContain('Table scratch uses the MEMORY engine');

	$baseline['memory_tables'] = [['table' => 'scratch', 'reason' => 'test']];

	expect(CactiSqlSchema::violations(CactiSqlSchema::parse($sql), $baseline))->toBe([]);
});

it('fails a key column that is not int(10) unsigned and accepts a recorded one', function () {
	$sql      = SCHEMA_HEADER . "CREATE TABLE `fresh` (\n  `id` int(10) unsigned NOT NULL,\n  `host_id` mediumint(8) unsigned NOT NULL default '0',\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB;\n";
	$baseline = empty_schema_baseline();

	expect(CactiSqlSchema::violations(CactiSqlSchema::parse($sql), $baseline)[0])
		->toContain('Column fresh.host_id is mediumint(8) unsigned');

	$baseline['key_column_types'] = [['table' => 'fresh', 'column' => 'host_id', 'type' => 'mediumint(8) unsigned', 'reason' => 'test']];

	expect(CactiSqlSchema::violations(CactiSqlSchema::parse($sql), $baseline))->toBe([]);

	$baseline['key_column_types'][0]['type'] = 'int(11)';

	expect(CactiSqlSchema::violations(CactiSqlSchema::parse($sql), $baseline))->toHaveCount(1);
});

it('fails a new zero-date default and allows the recorded count', function () {
	$sql      = SCHEMA_HEADER . "CREATE TABLE `dated` (\n  `id` int(10) unsigned NOT NULL,\n  `a` timestamp NOT NULL default '0000-00-00 00:00:00',\n  `b` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB;\n";
	$baseline = empty_schema_baseline();

	expect(CactiSqlSchema::violations(CactiSqlSchema::parse($sql), $baseline)[0])
		->toContain('Table dated has 2 columns defaulting')->toContain('baseline allows 0');

	$baseline['zero_date_defaults'] = ['dated' => 1];

	expect(CactiSqlSchema::violations(CactiSqlSchema::parse($sql), $baseline)[0])->toContain('baseline allows 1');

	$baseline['zero_date_defaults'] = ['dated' => 2];

	expect(CactiSqlSchema::violations(CactiSqlSchema::parse($sql), $baseline))->toBe([]);
});

it('ignores baseline entries that no longer apply', function () {
	$baseline = [
		'missing_primary_key' => [['table' => 'gone', 'reason' => 'x']],
		'memory_tables'       => [['table' => 'gone', 'reason' => 'x']],
		'key_column_types'    => [['table' => 'gone', 'column' => 'host_id', 'type' => 'int(11)', 'reason' => 'x']],
		'zero_date_defaults'  => ['gone' => 3],
	];

	expect(CactiSqlSchema::violations(CactiSqlSchema::parse(SCHEMA_HEADER), $baseline))->toBe([]);
});
