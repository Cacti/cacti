<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Only library lines are gated, and whole-file vendor evidence does not fit
// the runner's memory limit.
if (function_exists('xdebug_set_filter')) {
	xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_INCLUDE, [dirname(__DIR__, 3) . '/lib/']);
}
require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';

require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/Helpers/BufferedPDOStatement.php';
require_once dirname(__DIR__, 2) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 3) . '/include/global.php';
require_once CACTI_PATH_LIBRARY . '/aggregate.php';

/**
 * Two MySQL behaviors sqlite lacks at the connection boundary: a zero or empty value
 * written to an AUTO_INCREMENT key allocates the next id, and an UPDATE may qualify
 * its SET column with the table name.
 */
class AggregateCoveragePDO extends FakeMySQLPDO {
	/**
	 * Applies the MySQL semantics, then prepares the query.
	 *
	 * @param string              $query   The SQL statement
	 * @param array<string,mixed> $options Driver options
	 *
	 * @return PDOStatement|false The prepared statement
	 */
	public function prepare(string $query, array $options = []) : PDOStatement|false {
		return parent::prepare($this->mysqlSemantics($query), $options);
	}

	/**
	 * Applies the MySQL semantics, then executes the statement.
	 *
	 * @param string $statement The SQL statement
	 *
	 * @return int|false The affected row count
	 */
	public function exec(string $statement) : int|false {
		return parent::exec($this->mysqlSemantics($statement));
	}

	/**
	 * Turns a zero or empty AUTO_INCREMENT key into NULL and drops the table qualifier
	 * from a graph_templates_item SET column.
	 *
	 * @param string $sql The SQL statement
	 *
	 * @return string The rewritten statement
	 */
	private function mysqlSemantics(string $sql) : string {
		$sql = preg_replace('/^(\s*INSERT INTO \w+ \(`(?:id|color_template_id|color_template_item_id)`[^)]*\)\s*VALUES\s*\()(?:\x27?0\x27?|\x27\x27)([,)])/i', '$1NULL$2', $sql);

		return preg_replace('/\bSET\s+graph_templates_item\./i', 'SET ', $sql);
	}
}

/**
 * Stands in for the graph rebuild in lib/api_aggregate.php, which renders
 * every member graph; the conversion only needs to know whether it failed.
 *
 * @param int $aggregate_template_id The aggregate template to push out
 * @param int $local_graph_id        The graph being rebuilt
 *
 * @return bool Always false, so the conversion reports a failed rebuild
 */
function push_out_aggregates(int $aggregate_template_id, int $local_graph_id = 0) : bool {
	$GLOBALS['aggregate_push_calls'][] = [$aggregate_template_id, $local_graph_id];

	return false;
}

$count = 0;

/**
 * Counts one assertion and throws when the values are not identical.
 *
 * @param mixed  $actual   The observed value
 * @param mixed  $expected The required value
 * @param string $message  What the assertion checks
 *
 * @return void
 */
function aggregate_expect(mixed $actual, mixed $expected, string $message) : void {
	global $count;
	$count++;

	if ($actual !== $expected) {
		throw new RuntimeException($message . ': ' . var_export($actual, true) . ' != ' . var_export($expected, true));
	}
}

/**
 * Builds a fresh sqlite database with the tables the aggregate helpers use
 * and makes it the default Cacti connection.
 *
 * @return AggregateCoveragePDO The seeded connection
 */
function aggregate_reset() : AggregateCoveragePDO {
	global $database_sessions, $db_column_type_cache;

	$conn = new AggregateCoveragePDO();
	$conn->setAttribute(PDO::ATTR_STATEMENT_CLASS, [BufferedPDOStatement::class, []]);

	$conn->exec('CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT)');
	$conn->exec('CREATE TABLE graph_local (id INTEGER PRIMARY KEY)');
	$conn->exec('CREATE TABLE aggregate_graphs (id integer PRIMARY KEY, aggregate_template_id integer,
		template_propogation TEXT, local_graph_id integer, title_format TEXT, graph_template_id integer,
		gprint_prefix TEXT, graph_type integer, total integer, total_type integer, total_prefix TEXT,
		order_type integer)');
	$conn->exec('CREATE TABLE aggregate_graphs_items (aggregate_graph_id INTEGER, local_graph_id INTEGER, sequence INTEGER,
		PRIMARY KEY (aggregate_graph_id, local_graph_id))');
	$conn->exec('CREATE TABLE aggregate_graph_templates (id INTEGER PRIMARY KEY, graph_template_id INTEGER,
		gprint_prefix TEXT, graph_type INTEGER, total INTEGER, total_type INTEGER, total_prefix TEXT, order_type INTEGER)');
	$conn->exec('CREATE TABLE graph_templates_graph (local_graph_id INTEGER PRIMARY KEY, title_cache TEXT)');
	$conn->exec('CREATE TABLE graph_templates_item (id INTEGER PRIMARY KEY, local_graph_id INTEGER, graph_template_id INTEGER,
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

	$GLOBALS['database_hostname'] = 'aggregate-fixture';
	$GLOBALS['database_port']     = 0;
	$GLOBALS['database_default']  = 'aggregate-fixture';
	$database_sessions            = ['aggregate-fixture:0:aggregate-fixture' => $conn];
	$db_column_type_cache         = [];

	file_put_contents($GLOBALS['aggregate_log'], '');

	return $conn;
}

/**
 * Reads what cacti_log() wrote since the last reset.
 *
 * @return string The log contents
 */
function aggregate_log() : string {
	clearstatcache();

	return (string) file_get_contents($GLOBALS['aggregate_log']);
}

// Options come from the per-process cache, so cacti_log() writes the
// developer debug messages to a file the fixture can read back.
$GLOBALS['aggregate_log']   = tempnam(sys_get_temp_dir(), 'cacti-aggregate-log-');
$GLOBALS['no_http_headers'] = true;
$_SESSION                   = [];
$options                    = [
	'log_destination'        => 1,
	'log_verbosity'          => POLLER_VERBOSITY_DEVDBG,
	'path_cactilog'          => $GLOBALS['aggregate_log'],
	'selective_debug'        => '',
	'selective_plugin_debug' => '',
	'log_validation'         => '',
];
$config[OPTIONS_CLI]   = array_merge($config[OPTIONS_CLI] ?? [], $options);
$_SESSION[OPTIONS_WEB] = $options;

try {
	// The drill-down link selects every member graph and carries the time span.
	$conn = aggregate_reset();
	$conn->exec('INSERT INTO graph_local (id) VALUES (40), (41), (42)');
	$conn->exec("INSERT INTO aggregate_graphs (id, aggregate_template_id, local_graph_id, title_format) VALUES (7, 1, 40, 'Aggregate')");
	$conn->exec('INSERT INTO aggregate_graphs_items VALUES (7, 41, 1), (7, 42, 2)');
	$link = aggregate_build_children_url(40, 1700000000, 1700086400, 3);
	aggregate_expect(str_starts_with($link, "<a class='hyperLink aggregates' href='"), true, 'The drill-down is a hyperlink');
	aggregate_expect(str_contains($link, '&amp;graph_start=1700000000&amp;graph_end=1700086400&amp;rra_id=3'), true, 'The link carries the time span');
	aggregate_expect(str_contains($link, '&amp;graph_add=41%2C42%2C'), true, 'The link selects every member graph');
	aggregate_expect(str_contains($link, "title='Display Graphs from this Aggregate'"), true, 'The link has a title');
	aggregate_expect(str_ends_with($link, '</i></a><br>' . PHP_EOL), true, 'The link closes its markup');

	$link = aggregate_build_children_url(40);
	aggregate_expect(str_contains($link, 'graph_start='), false, 'An unset start is omitted');
	aggregate_expect(str_contains($link, 'rra_id='), false, 'An unset RRA is omitted');
	aggregate_expect(aggregate_build_children_url(41), '', 'A member graph that is not an aggregate has no link');

	// A conversion to a missing aggregate template migrates nothing.
	$conn                  = aggregate_reset();
	$_REQUEST              = ['aggregate_template_id' => '5'];
	$_GET                  = $_REQUEST;
	api_aggregate_convert_template([40]);
	aggregate_expect($_SESSION[SESS_MESSAGES]['aggregate_template_error']['message'] ?? null, 'The selected Aggregate Template was not found! No graphs were migrated.', 'A missing template is reported');
	aggregate_expect((int) $conn->query('SELECT COUNT(*) FROM aggregate_graphs')->fetchColumn(), 0, 'A missing template writes no aggregate');
	unset($_SESSION[SESS_MESSAGES]);

	// A conversion saves the aggregate and its member graphs before the rebuild.
	$conn->exec("INSERT INTO aggregate_graph_templates VALUES (5, 9, 'pre', 7, 1, 2, 'Total', 1)");
	$conn->exec("INSERT INTO graph_templates_graph VALUES (40, 'Traffic Aggregate')");
	$conn->exec('INSERT INTO graph_templates_item (id, local_graph_id, graph_template_id, task_item_id, sequence) VALUES
		(1, 40, 0, 100, 1), (2, 40, 0, 101, 2), (3, 41, 9, 100, 1), (4, 42, 9, 101, 1), (5, 43, 9, 999, 1)');
	$GLOBALS['aggregate_push_calls'] = [];
	api_aggregate_convert_template([40]);
	$row = $conn->query('SELECT aggregate_template_id, local_graph_id, title_format, graph_template_id, gprint_prefix, total_prefix FROM aggregate_graphs')->fetchAll(PDO::FETCH_ASSOC);
	aggregate_expect($row, [['aggregate_template_id' => 5, 'local_graph_id' => 40, 'title_format' => 'Traffic Aggregate', 'graph_template_id' => 9, 'gprint_prefix' => 'pre', 'total_prefix' => 'Total']], 'The aggregate takes the graph title and template fields');
	aggregate_expect($conn->query('SELECT local_graph_id, sequence FROM aggregate_graphs_items ORDER BY sequence')->fetchAll(PDO::FETCH_KEY_PAIR), [41 => 1, 42 => 2], 'The members sharing a task item are attached in order');
	aggregate_expect($GLOBALS['aggregate_push_calls'], [[5, 40]], 'The aggregate is rebuilt from its template');
	aggregate_expect(str_contains(aggregate_log(), 'ERROR: Failed to rebuild aggregate graph after template conversion.'), true, 'A failed rebuild is logged');

	// Duplicating a color template copies its name and every color in order.
	$conn = aggregate_reset();
	$conn->exec("INSERT INTO color_templates (color_template_id, name) VALUES (3, 'Blues')");
	$conn->exec('INSERT INTO color_template_items (color_template_id, color_id, sequence) VALUES (3, 11, 1), (3, 12, 2), (9, 99, 1)');
	duplicate_color_template(3, 'Copy of <template_title>');
	$copy = $conn->query('SELECT color_template_id, name FROM color_templates WHERE color_template_id <> 3')->fetchAll(PDO::FETCH_ASSOC);
	aggregate_expect(count($copy), 1, 'One color template is created');
	aggregate_expect($copy[0]['name'], 'Copy of Blues', 'The title placeholder is replaced');
	$items = $conn->query('SELECT color_id, sequence FROM color_template_items WHERE color_template_id = ' . (int) $copy[0]['color_template_id'] . ' ORDER BY sequence')->fetchAll(PDO::FETCH_ASSOC);
	aggregate_expect($items, [['color_id' => 11, 'sequence' => 1], ['color_id' => 12, 'sequence' => 2]], 'Every color is copied in order');
	aggregate_expect(str_contains(aggregate_log(), 'duplicate_color_template called. Id:3 Title: Copy of <template_title> Replaced: Copy of Blues'), true, 'The replacement is logged');

	// The next sequence follows the highest one in the group and starts at one.
	$conn->exec('INSERT INTO color_template_items (color_template_id, color_id, sequence) VALUES (5, 13, 20)');
	aggregate_expect(get_next_sequence(0, 'sequence', 'color_template_items', 'color_template_id = 5'), 21, 'The next sequence follows the highest');
	aggregate_expect(get_next_sequence(0, 'sequence', 'color_template_items', 'color_template_id = 8'), 1, 'An empty group starts at one');
	aggregate_expect(str_contains(aggregate_log(), 'get_next_sequence  called. Id: 0 field: sequence table: color_template_items'), true, 'The lookup is logged');

	// A conditional type conversion changes only the first item of the old type.
	$conn = aggregate_reset();
	$conn->exec('INSERT INTO graph_templates_item (id, local_graph_id, graph_type_id, sequence) VALUES
		(1, 40, ' . GRAPH_ITEM_TYPE_STACK . ', 2), (2, 40, ' . GRAPH_ITEM_TYPE_STACK . ', 1), (3, 41, ' . GRAPH_ITEM_TYPE_STACK . ', 1)');
	aggregate_conditional_convert_graph_type(40, GRAPH_ITEM_TYPE_STACK, GRAPH_ITEM_TYPE_AREA);
	aggregate_expect($conn->query('SELECT id, graph_type_id FROM graph_templates_item ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR), [1 => GRAPH_ITEM_TYPE_STACK, 2 => GRAPH_ITEM_TYPE_AREA, 3 => GRAPH_ITEM_TYPE_STACK], 'Only the first item of the old type converts');
	aggregate_expect(str_contains(aggregate_log(), 'aggregate_conditional_convert_graph_type  called: graph: 40 old item type: ' . GRAPH_ITEM_TYPE_STACK . ' new item type: ' . GRAPH_ITEM_TYPE_AREA), true, 'The conversion is logged');

	// Legend items keep their type while drawable items take the requested one.
	aggregate_expect(aggregate_change_graph_type(0, GRAPH_ITEM_TYPE_GPRINT, GRAPH_ITEM_TYPE_LINE1), GRAPH_ITEM_TYPE_GPRINT, 'A legend item keeps its type');
	aggregate_expect(aggregate_change_graph_type(1, GRAPH_ITEM_TYPE_LINE1, GRAPH_ITEM_TYPE_LINE2), GRAPH_ITEM_TYPE_LINE2, 'A line takes the requested type');
	aggregate_expect(str_contains(aggregate_log(), 'aggregate_change_graph_type called. Index 1 old type ' . GRAPH_ITEM_TYPE_LINE1 . ' Graph Type: ' . GRAPH_ITEM_TYPE_LINE2), true, 'The type change is logged');

	// The Make 0 CDEF is created once and then reused.
	$make0 = aggregate_cdef_make0();
	aggregate_expect($conn->query('SELECT name FROM cdef WHERE id = ' . $make0)->fetchColumn(), '_MAKE 0', 'The Make 0 CDEF is created');
	aggregate_expect($conn->query('SELECT value FROM cdef_items WHERE cdef_id = ' . $make0)->fetchColumn(), 'CURRENT_DATA_SOURCE,0,*', 'The Make 0 CDEF multiplies by zero');
	aggregate_expect(aggregate_cdef_make0(), $make0, 'The Make 0 CDEF is reused');
	aggregate_expect((int) $conn->query('SELECT COUNT(*) FROM cdef_items')->fetchColumn(), 1, 'Reusing the Make 0 CDEF adds no item');

	// Totalling refuses an item whose CDEF cannot be resolved and changes nothing.
	$conn = aggregate_reset();
	$conn->exec('INSERT INTO graph_templates_item (id, local_graph_id, graph_type_id, sequence, cdef_id) VALUES
		(1, 40, ' . GRAPH_ITEM_TYPE_AREA . ', 1, 0), (2, 40, ' . GRAPH_ITEM_TYPE_STACK . ', 2, 77)');
	aggregate_expect(aggregate_cdef_totalling(40, 1, AGGREGATE_TOTAL_TYPE_ALL), false, 'An unresolvable CDEF stops totalling');
	aggregate_expect(array_map('intval', $conn->query('SELECT cdef_id FROM graph_templates_item ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)), [0, 77], 'No item is changed');
	aggregate_expect((int) $conn->query('SELECT COUNT(*) FROM cdef')->fetchColumn(), 0, 'No CDEF is created');
	aggregate_expect(str_contains(aggregate_log(), 'aggregate_cdef_totalling called. Working on Graph: 40 sequence: 1 totalling: ' . AGGREGATE_TOTAL_TYPE_ALL), true, 'The totalling call is logged');
	aggregate_expect(str_contains(aggregate_log(), 'aggregate_cdef_totalling could not apply totals due to invalid or empty CDEF id 77'), true, 'The invalid CDEF is logged');

	// Totalling a plain item creates one ALL_DATA_SOURCES CDEF and points the items at it.
	$conn = aggregate_reset();
	$conn->exec('INSERT INTO graph_templates_item (id, local_graph_id, graph_type_id, sequence, cdef_id) VALUES
		(1, 40, ' . GRAPH_ITEM_TYPE_AREA . ', 1, 0), (2, 40, ' . GRAPH_ITEM_TYPE_GPRINT . ', 2, 0)');
	aggregate_expect(aggregate_cdef_totalling(40, 1, AGGREGATE_TOTAL_TYPE_ALL), true, 'A plain item is totalled');
	$cdefs = $conn->query('SELECT id, name FROM cdef')->fetchAll(PDO::FETCH_ASSOC);
	aggregate_expect(count($cdefs), 1, 'One totalling CDEF is created and reused');
	aggregate_expect($cdefs[0]['name'], '_AGGREGATE ALL Items', 'The CDEF is named for its total type');
	aggregate_expect($conn->query('SELECT value FROM cdef_items WHERE cdef_id = ' . (int) $cdefs[0]['id'])->fetchColumn(), 'ALL_DATA_SOURCES_NODUPS', 'The CDEF totals every data source');
	aggregate_expect(array_map('intval', $conn->query('SELECT cdef_id FROM graph_templates_item ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)), [(int) $cdefs[0]['id'], (int) $cdefs[0]['id']], 'Every item points at the totalling CDEF');
} finally {
	unlink($GLOBALS['aggregate_log']);
}

print json_encode(['passed' => $count], JSON_THROW_ON_ERROR) . "\n";
