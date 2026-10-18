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

/*
 * The aggregate helpers in lib/aggregate.php that the native library fixture
 * does not reach: the drill-down link to member graphs, color template
 * duplication, the sequence helper, the graph type conversion and the
 * totalling guard for an unusable CDEF. Each one runs unmodified against a
 * sqlite-backed default connection, and the developer debug log is pointed at
 * a temporary file so the messages these helpers emit can be read back.
 */

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 2) . '/lib/database.php';
require_once dirname(__DIR__, 2) . '/lib/aggregate.php';

/**
 * Two MySQL behaviors sqlite lacks at the connection boundary: a zero written
 * to an AUTO_INCREMENT key allocates the next id (sql_save() sends 0, quoted
 * when sqlite reports the type as INTEGER, for new rows), and an UPDATE may qualify its SET column with the table name.
 */
class AggregateHelpersPDO extends FakeMySQLPDO {
	public function prepare(string $query, array $options = []): PDOStatement|false {
		return parent::prepare($this->mysqlSemantics($query), $options);
	}

	public function exec(string $statement): int|false {
		return parent::exec($this->mysqlSemantics($statement));
	}

	private function mysqlSemantics(string $sql): string {
		$sql = preg_replace('/^(\s*INSERT INTO \w+ \(`(?:id|color_template_id|color_template_item_id)`[^)]*\)\s*VALUES\s*\()\x27?0\x27?([,)])/i', '$1NULL$2', $sql);

		return preg_replace('/\bSET\s+graph_templates_item\./i', 'SET ', $sql);
	}
}

function aggregate_helpers_seed() : AggregateHelpersPDO {
	$conn = new AggregateHelpersPDO();

	$conn->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
	$conn->exec('CREATE TABLE graph_local (id INTEGER PRIMARY KEY)');
	$conn->exec('CREATE TABLE aggregate_graphs (id INTEGER PRIMARY KEY, aggregate_template_id INTEGER,
		local_graph_id INTEGER, title_format TEXT)');
	$conn->exec('CREATE TABLE aggregate_graphs_items (aggregate_graph_id INTEGER, local_graph_id INTEGER, sequence INTEGER)');
	$conn->exec('CREATE TABLE graph_templates_item (id INTEGER PRIMARY KEY, local_graph_id INTEGER,
		task_item_id INTEGER, graph_type_id INTEGER, sequence INTEGER, cdef_id INTEGER)');
	$conn->exec('CREATE TABLE data_template_rrd (id INTEGER PRIMARY KEY)');
	$conn->exec("CREATE TABLE color_templates (color_template_id INTEGER PRIMARY KEY AUTOINCREMENT,
		name TEXT NOT NULL DEFAULT '', graphs INTEGER NOT NULL DEFAULT 0, templates INTEGER NOT NULL DEFAULT 0)");
	$conn->exec('CREATE TABLE color_template_items (color_template_item_id INTEGER PRIMARY KEY AUTOINCREMENT,
		color_template_id INTEGER NOT NULL DEFAULT 0, color_id INTEGER NOT NULL DEFAULT 0,
		sequence INTEGER NOT NULL DEFAULT 0)');
	$conn->exec('CREATE TABLE cdef (id INTEGER PRIMARY KEY, hash TEXT, system INTEGER, name TEXT)');
	$conn->exec('CREATE TABLE cdef_items (id INTEGER PRIMARY KEY, hash TEXT, cdef_id INTEGER,
		sequence INTEGER, type INTEGER, value TEXT)');

	return $conn;
}

function aggregate_helpers_wire(PDO $conn) : void {
	global $database_hostname, $database_port, $database_default, $database_sessions;

	$database_hostname                                                        = 'aggregate-helpers-host';
	$database_port                                                            = 'aggregate-helpers-port';
	$database_default                                                         = 'aggregate-helpers-db';
	$database_sessions["$database_hostname:$database_port:$database_default"] = $conn;
}

function aggregate_helpers_log() : string {
	clearstatcache();

	return is_file($GLOBALS['aggregate_helpers_log']) ? (string) file_get_contents($GLOBALS['aggregate_helpers_log']) : '';
}

beforeEach(function () {
	global $database_hostname, $database_port, $database_default, $database_sessions, $config;

	$this->aggregate_helpers_prior = [
		'database_hostname' => $database_hostname ?? null,
		'database_port'     => $database_port ?? null,
		'database_default'  => $database_default ?? null,
		'database_sessions' => $database_sessions ?? [],
		'options_cli'       => $config[OPTIONS_CLI] ?? null,
		'options_web'       => $_SESSION[OPTIONS_WEB] ?? null,
	];

	$GLOBALS['aggregate_helpers_log'] = tempnam(sys_get_temp_dir(), 'cacti-aggregate-log-');

	// Options are served from the per-process cache so cacti_log() never has
	// to reach the database while it is writing.
	$options = [
		'log_destination'        => 1,
		'log_verbosity'          => POLLER_VERBOSITY_DEVDBG,
		'path_cactilog'          => $GLOBALS['aggregate_helpers_log'],
		'selective_debug'        => '',
		'selective_plugin_debug' => '',
	];

	$config[OPTIONS_CLI]   = array_merge($config[OPTIONS_CLI] ?? [], $options);
	$_SESSION[OPTIONS_WEB] = array_merge($_SESSION[OPTIONS_WEB] ?? [], $options);

	$this->conn = aggregate_helpers_seed();
	aggregate_helpers_wire($this->conn);
});

afterEach(function () {
	global $database_hostname, $database_port, $database_default, $database_sessions, $config;

	$prior             = $this->aggregate_helpers_prior;
	$database_hostname = $prior['database_hostname'];
	$database_port     = $prior['database_port'];
	$database_default  = $prior['database_default'];
	$database_sessions = $prior['database_sessions'];

	if ($prior['options_cli'] === null) {
		unset($config[OPTIONS_CLI]);
	} else {
		$config[OPTIONS_CLI] = $prior['options_cli'];
	}

	if ($prior['options_web'] === null) {
		unset($_SESSION[OPTIONS_WEB]);
	} else {
		$_SESSION[OPTIONS_WEB] = $prior['options_web'];
	}

	@unlink($GLOBALS['aggregate_helpers_log']);
});

test('the drill-down link selects every member graph and carries the time span', function () {
	$this->conn->exec('INSERT INTO graph_local (id) VALUES (40), (41), (42)');
	$this->conn->exec("INSERT INTO aggregate_graphs VALUES (7, 1, 40, 'Aggregate')");
	$this->conn->exec('INSERT INTO aggregate_graphs_items VALUES (7, 41, 1), (7, 42, 2)');

	$link = aggregate_build_children_url(40, 1700000000, 1700086400, 3);

	expect($link)->toStartWith("<a class='hyperLink aggregates' href='")
		->and($link)->toContain('graph_view.php?reset=1&amp;page=1&amp;graph_template_id=-1')
		->and($link)->toContain('&amp;style=selective&amp;action=preview')
		->and($link)->toContain('&amp;graph_start=1700000000&amp;graph_end=1700086400&amp;rra_id=3')
		->and($link)->toContain('&amp;graph_add=41%2C42%2C')
		->and($link)->toContain("title='Display Graphs from this Aggregate'")
		->and($link)->toEndWith('</i></a><br>' . PHP_EOL);
});

test('the drill-down link omits unset time span parameters', function () {
	$this->conn->exec('INSERT INTO graph_local (id) VALUES (40), (41)');
	$this->conn->exec("INSERT INTO aggregate_graphs VALUES (7, 1, 40, 'Aggregate')");
	$this->conn->exec('INSERT INTO aggregate_graphs_items VALUES (7, 41, 1)');

	$link = aggregate_build_children_url(40);

	expect($link)->toContain('&amp;graph_add=41%2C')
		->and($link)->not->toContain('graph_start=')
		->and($link)->not->toContain('graph_end=')
		->and($link)->not->toContain('rra_id=');
});

test('a graph that is not an aggregate has no drill-down link', function () {
	$this->conn->exec('INSERT INTO graph_local (id) VALUES (40)');

	expect(aggregate_build_children_url(40))->toBe('');
});

test('duplicating a color template copies its name and every color in order', function () {
	$this->conn->exec("INSERT INTO color_templates (color_template_id, name) VALUES (3, 'Blues')");
	$this->conn->exec('INSERT INTO color_template_items (color_template_id, color_id, sequence) VALUES (3, 11, 1), (3, 12, 2), (9, 99, 1)');

	duplicate_color_template(3, 'Copy of <template_title>');

	$copy = $this->conn->query('SELECT color_template_id, name FROM color_templates WHERE color_template_id <> 3')->fetchAll(PDO::FETCH_ASSOC);

	expect($copy)->toHaveCount(1)
		->and($copy[0]['name'])->toBe('Copy of Blues');

	$items = $this->conn->query('SELECT color_id, sequence FROM color_template_items WHERE color_template_id = ' . (int) $copy[0]['color_template_id'] . ' ORDER BY sequence')->fetchAll(PDO::FETCH_ASSOC);

	expect($items)->toBe([
		['color_id' => 11, 'sequence' => 1],
		['color_id' => 12, 'sequence' => 2],
	]);

	expect(aggregate_helpers_log())->toContain('duplicate_color_template called. Color Template Id: 3 Title: Copy of <template_title>')
		->and(aggregate_helpers_log())->toContain('duplicate_color_template called. Id:3 Title: Copy of <template_title> Replaced: Copy of Blues');
});

test('the next sequence follows the highest one in the group and starts at one', function () {
	$this->conn->exec('INSERT INTO color_template_items (color_template_id, color_id, sequence) VALUES (3, 11, 4), (3, 12, 9), (5, 13, 20)');

	expect(get_next_sequence(0, 'sequence', 'color_template_items', 'color_template_id = 3'))->toBe(10)
		->and(get_next_sequence(0, 'sequence', 'color_template_items', 'color_template_id = 8'))->toBe(1)
		->and(aggregate_helpers_log())->toContain('get_next_sequence  called. Id: 0 field: sequence table: color_template_items');
});

test('a conditional type conversion changes only the first item of the old type', function () {
	$this->conn->exec('INSERT INTO graph_templates_item (id, local_graph_id, graph_type_id, sequence) VALUES
		(1, 40, ' . GRAPH_ITEM_TYPE_STACK . ', 2),
		(2, 40, ' . GRAPH_ITEM_TYPE_STACK . ', 1),
		(3, 41, ' . GRAPH_ITEM_TYPE_STACK . ', 1)');

	aggregate_conditional_convert_graph_type(40, GRAPH_ITEM_TYPE_STACK, GRAPH_ITEM_TYPE_AREA);

	$types = $this->conn->query('SELECT id, graph_type_id FROM graph_templates_item ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);

	expect($types)->toBe([1 => GRAPH_ITEM_TYPE_STACK, 2 => GRAPH_ITEM_TYPE_AREA, 3 => GRAPH_ITEM_TYPE_STACK])
		->and(aggregate_helpers_log())->toContain('aggregate_conditional_convert_graph_type  called: graph: 40 old item type: ' . GRAPH_ITEM_TYPE_STACK . ' new item type: ' . GRAPH_ITEM_TYPE_AREA);
});

test('legend items keep their type while drawable items take the requested one', function () {
	expect(aggregate_change_graph_type(0, GRAPH_ITEM_TYPE_GPRINT, GRAPH_ITEM_TYPE_LINE1))->toBe(GRAPH_ITEM_TYPE_GPRINT)
		->and(aggregate_change_graph_type(1, GRAPH_ITEM_TYPE_LINE1, GRAPH_ITEM_TYPE_LINE2))->toBe(GRAPH_ITEM_TYPE_LINE2)
		->and(aggregate_helpers_log())->toContain('aggregate_change_graph_type called. Index 1 old type ' . GRAPH_ITEM_TYPE_LINE1);
});

test('totalling refuses an item whose CDEF cannot be resolved and changes nothing', function () {
	$this->conn->exec('INSERT INTO graph_templates_item (id, local_graph_id, graph_type_id, sequence, cdef_id) VALUES
		(1, 40, ' . GRAPH_ITEM_TYPE_AREA . ', 1, 0),
		(2, 40, ' . GRAPH_ITEM_TYPE_STACK . ', 2, 77)');

	expect(aggregate_cdef_totalling(40, 1, AGGREGATE_TOTAL_TYPE_ALL))->toBeFalse()
		->and($this->conn->query('SELECT cdef_id FROM graph_templates_item ORDER BY id')->fetchAll(PDO::FETCH_COLUMN))->toBe([0, 77])
		->and((int) $this->conn->query('SELECT COUNT(*) FROM cdef')->fetchColumn())->toBe(0)
		->and(aggregate_helpers_log())->toContain('aggregate_cdef_totalling called. Working on Graph: 40 sequence: 1 totalling: ' . AGGREGATE_TOTAL_TYPE_ALL)
		->and(aggregate_helpers_log())->toContain('aggregate_cdef_totalling could not apply totals due to invalid or empty CDEF id 77');
});

test('totalling a plain item creates one ALL_DATA_SOURCES CDEF and points the items at it', function () {
	$this->conn->exec('INSERT INTO graph_templates_item (id, local_graph_id, graph_type_id, sequence, cdef_id) VALUES
		(1, 40, ' . GRAPH_ITEM_TYPE_AREA . ', 1, 0),
		(2, 40, ' . GRAPH_ITEM_TYPE_GPRINT . ', 2, 0)');

	expect(aggregate_cdef_totalling(40, 1, AGGREGATE_TOTAL_TYPE_ALL))->toBeTrue();

	$cdefs = $this->conn->query('SELECT id, name FROM cdef')->fetchAll(PDO::FETCH_ASSOC);

	expect($cdefs)->toHaveCount(1)
		->and($cdefs[0]['name'])->toBe('_AGGREGATE ALL Items')
		->and($this->conn->query('SELECT value FROM cdef_items WHERE cdef_id = ' . (int) $cdefs[0]['id'])->fetchColumn())->toBe('ALL_DATA_SOURCES_NODUPS')
		->and(array_map('intval', $this->conn->query('SELECT cdef_id FROM graph_templates_item ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)))->toBe([(int) $cdefs[0]['id'], (int) $cdefs[0]['id']]);
});
