<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 3) . '/Helpers/FixtureCoverage.php';
$root = dirname(__DIR__, 4);
require $root . '/include/global_constants.php';
require $root . '/lib/rrd.php';

/** No settings are needed by the helpers under test. */
function read_config_option($name, $force = false) {
	return '';
}

/** Count like Cacti's helper without loading lib/functions.php. */
function cacti_sizeof($value) {
	return is_array($value) || $value instanceof Countable ? count($value) : 0;
}

/** Name a DEF the way lib/functions.php does. */
function generate_graph_def_name(int $graph_item_id) : string {
	return strtr(strval($graph_item_id), '0123456789', 'abcdefghij');
}

/** Every RRD holds every CF except the files the fixture marks as AVERAGE only. */
function generate_graph_best_cf($local_data_id, $requested_cf) {
	if ($local_data_id <= 0 || in_array($local_data_id, $GLOBALS['average_only'], true)) {
		return 1;
	}

	return (int) $requested_cf;
}

/** Serve each aggregate member's graph items, joined in member order, from the fixture tables. */
function db_fetch_assoc_prepared($sql, $params = []) {
	$GLOBALS['query_count']++;

	$rows = [];

	foreach ($GLOBALS['members'][$params[0]] ?? [] as $member) {
		foreach ($GLOBALS['items'][$member['local_graph_id']] ?? [] as $item) {
			$rows[] = ['member_graph_id' => strval($member['local_graph_id'])] + $item;
		}
	}

	return $rows;
}

function item($type, $cf, $dtr, $local_data_id, $ds) {
	return [
		'graph_type_id'              => $type,
		'consolidation_function_id'  => $cf,
		'data_template_rrd_id'       => $dtr,
		'local_data_id'              => $local_data_id,
		'data_source_name'           => $ds,
	];
}

// aggregate 50 has members 20 and 35; each graphs in and out, then a MAX line of in
$GLOBALS['average_only'] = [];
$GLOBALS['members']      = [50 => [['local_graph_id' => 20], ['local_graph_id' => 35]]];
$GLOBALS['items']        = [
	20 => [
		item(GRAPH_ITEM_TYPE_AREA, 1, 201, 21, 'in'),
		item(GRAPH_ITEM_TYPE_GPRINT, 4, 201, 21, 'in'),
		item(GRAPH_ITEM_TYPE_STACK, 1, 202, 21, 'out'),
		item(GRAPH_ITEM_TYPE_LINE1, 3, 201, 21, 'in'),
		item(GRAPH_ITEM_TYPE_COMMENT, 1, null, null, null),
	],
	35 => [
		item(GRAPH_ITEM_TYPE_AREA, 1, 351, 36, 'in'),
		item(GRAPH_ITEM_TYPE_GPRINT, 4, 351, 36, 'in'),
		item(GRAPH_ITEM_TYPE_STACK, 1, 352, 36, 'out'),
		item(GRAPH_ITEM_TYPE_LINE1, 3, 351, 36, 'in'),
	],
];

// the aggregate's own DEF indexes: member 20 is a,b,c and member 35 is d,e,f
$cache = ['201:1' => 0, '201:3' => 2, '202:1' => 1, '351:1' => 3, '351:3' => 5, '352:1' => 4];

$GLOBALS['query_count'] = 0;

$map     = rrdtool_aggregate_def_map(50);
$map_sql = $GLOBALS['query_count'];
$now     = time() - 300;

// the aggregate skipped the items that draw each member's out data source
$skipped = $cache;
unset($skipped['202:1'], $skipped['352:1']);

$results = [
	'map'           => $map,
	'not_aggregate' => rrdtool_aggregate_def_map(99),
	'first_member'  => rrdtool_aggregate_cdef_defs('b,a,-', 201, $map, $cache, 300),
	'second'        => rrdtool_aggregate_cdef_defs('b,a,-', 352, $map, $cache, 300),
	'special'       => rrdtool_aggregate_cdef_defs('CURRENT_DATA_SOURCE,c,/', 351, $map, $cache, 300),
	'total'         => rrdtool_aggregate_cdef_defs('SIMILAR_DATA_SOURCES_NODUPS,a,-', 0, $map, $cache, 300),
	'total_now'     => $now,
	'untouched'     => rrdtool_aggregate_cdef_defs('cdefe,0,EQ,j,ab,UN,IF', 352, $map, $cache, 300),
	'unresolved'    => rrdtool_aggregate_cdef_defs('a,8,*', 777, ['owner' => [], 'members' => [[]]], [], 300),
	'single_total'  => rrdtool_aggregate_cdef_defs('a,8,*', 777, ['owner' => [], 'members' => [[[201, 1]]]], $cache, 300),
	'cfs'           => [],
	'queries'       => $map_sql,
	'skipped'       => rrdtool_aggregate_cdef_defs('b,a,-', 201, $map, $skipped, 300),
	'skipped_total' => rrdtool_aggregate_cdef_defs('b,8,*', 0, $map, $skipped, 300),
];

// the CF each item type's DEF is built with
$last = [];

foreach ([
	item(GRAPH_ITEM_TYPE_GPRINT, 4, 201, 21, 'in'),
	item(GRAPH_ITEM_TYPE_LINE1, 3, 201, 21, 'in'),
	item(GRAPH_ITEM_TYPE_GPRINT, 4, 201, 21, 'in'),
	item(GRAPH_ITEM_TYPE_GPRINT_AVERAGE, 1, 201, 21, 'in'),
	item(GRAPH_ITEM_TYPE_GPRINT_LAST, 4, 201, 21, 'in'),
	item(GRAPH_ITEM_TYPE_GPRINT_MAX, 3, 201, 21, 'in'),
	item(GRAPH_ITEM_TYPE_GPRINT_MIN, 2, 201, 21, 'in'),
	item(GRAPH_ITEM_TYPE_HRULE, 3, 201, 21, 'in'),
] as $graph_item) {
	$results['cfs'][] = rrdtool_graph_item_cf($graph_item, $last);
}

// a member whose RRD lacks MAX draws its MAX line from AVERAGE, so it has one DEF fewer
$GLOBALS['average_only'] = [36];
$results['average_only'] = rrdtool_aggregate_def_map(50)['members'][1];
$GLOBALS['average_only'] = [];

// both member graphs may legitimately read the same first data source
$member_35            = $GLOBALS['items'][35];
$GLOBALS['items'][35] = [
	item(GRAPH_ITEM_TYPE_AREA, 1, 201, 21, 'in'),
	item(GRAPH_ITEM_TYPE_STACK, 1, 352, 36, 'out'),
];

$shared                      = rrdtool_aggregate_def_map(50);
$results['shared_same']      = rrdtool_aggregate_cdef_defs('a,8,*', 201, $shared, $cache, 300);
$results['shared_different'] = rrdtool_aggregate_cdef_defs('a,b,+', 201, $shared, $cache, 300);
$results['shared_missing']   = rrdtool_aggregate_cdef_defs('c,8,*', 201, $shared, $cache, 300);
$GLOBALS['items'][35]        = $member_35;

if (($argv[1] ?? '') === 'real-rrd') {
	$binary    = getenv('RRDTOOL_BINARY');
	$directory = sys_get_temp_dir() . '/cacti-aggregate-' . bin2hex(random_bytes(8));

	mkdir($directory);

	$run = static function (array $arguments) use ($binary) {
		$process = proc_open(array_merge([$binary], $arguments), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		$output  = stream_get_contents($pipes[1]);
		$error   = stream_get_contents($pipes[2]);

		fclose($pipes[1]);
		fclose($pipes[2]);

		if (proc_close($process) !== 0) {
			throw new RuntimeException($error);
		}

		return $output;
	};

	try {
		$GLOBALS['members'][60] = [];
		$rrd_cache              = [];
		$definitions            = [];

		// three members graph the constant values 1, 10 and 100 as DEFs a, b and c
		foreach ([1, 10, 100] as $index => $value) {
			$id = 601 + $index;

			$GLOBALS['members'][60][] = ['local_graph_id' => $id];
			$GLOBALS['items'][$id]    = [item(GRAPH_ITEM_TYPE_AREA, 1, $id, $id, 'value')];
			$rrd_cache["$id:1"]       = $index;

			$rrd = $directory . '/' . $id . '.rrd';

			$run(['create', $rrd, '--start', '1699999980', '--step', '60', 'DS:value:GAUGE:120:U:U', 'RRA:AVERAGE:0.5:1:20']);
			$run(['update', $rrd, '1700000040:' . $value, '1700000100:' . $value, '1700000160:' . $value, '1700000220:' . $value]);

			$definitions[] = 'DEF:' . generate_graph_def_name($index) . '=' . $rrd . ':value:AVERAGE';
		}

		$unknown        = str_replace('CURRENT_DATA_SOURCE', 'a', $results['shared_different']);
		$unknown_export = $run(array_merge(['xport', '--start', '1700000040', '--end', '1700000160', '--step', '60'], $definitions, ['CDEF:unknown=' . $unknown, 'XPORT:unknown:unknown']));
		$unknown_xml    = new SimpleXMLElement($unknown_export);

		$results['rrd_unknown'] = (string) $unknown_xml->data->row[0]->v === 'NaN';

		$rrd_map = rrdtool_aggregate_def_map(60);

		foreach ([601, 602, 603, 0] as $index => $id) {
			$definitions[] = 'CDEF:member' . $index . '=' . rrdtool_aggregate_cdef_defs('a,1,*', $id, $rrd_map, $rrd_cache, 60);
		}

		$export = $run(array_merge(['xport', '--start', '1700000040', '--end', '1700000160', '--step', '60'], $definitions, ['XPORT:member0:first', 'XPORT:member1:second', 'XPORT:member2:third', 'XPORT:member3:total']));
		$rows   = (new SimpleXMLElement($export))->data->row;
		$values = [];

		foreach ($rows[count($rows) - 1]->v as $value) {
			if (!is_numeric((string) $value)) {
				throw new RuntimeException('RRDtool returned an unknown value');
			}

			$values[] = (float) $value;
		}

		$results['rrd_values'] = $values;
	} finally {
		foreach (glob($directory . '/*') as $rrd) {
			unlink($rrd);
		}

		rmdir($directory);
	}
}

// rrdtool_function_graph() needs RRDtool and a database, so run the statements that call these
// helpers from its own source at their own line numbers, as FallbackSourceIdentity.php does.
$file      = $root . '/lib/rrd.php';
$source    = file_get_contents($file);
$temporary = [];

foreach ([
	'cf'    => '/^\t+\$graph_cf = rrdtool_graph_item_cf\(.*\n(?:.*\n){2}\t+\$graph_items\[\$key\]\[\'cf_reference\'\] = \$graph_cf;$/m',
	'reset' => '/^\t\$i +=  ?0;\n\t\$aggregate_def_map = null;$/m',
	'cdef'  => '/^(\t+)if \(preg_match\(\'\/\(\^\|,\)\[a-j\]\+\(,\|\$\)\/\', \$cdef_string\)\) \{\n.*?^\1\}$/ms',
] as $name => $pattern) {
	if (preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
		throw new RuntimeException("Cannot locate the $name statements in rrdtool_function_graph()");
	}
	$path = tempnam(sys_get_temp_dir(), 'cacti-aggregate-cdef-');
	file_put_contents($path, '<?php ' . str_repeat("\n", substr_count(substr($source, 0, $match[0][1]), "\n")) . $match[0][0]);
	$GLOBALS['cacti_fixture_source_map'][$path] = $file;
	$temporary[$name]                           = $path;
}

try {
	$last_graph_cf = [];
	$graph_items   = [item(GRAPH_ITEM_TYPE_LINE1, 3, 201, 21, 'in')];
	$key           = 0;
	$graph_item    = $graph_items[0];
	require $temporary['cf'];

	$aggregate_def_map = [];
	require $temporary['reset'];
	$reset = [$i, $aggregate_def_map === null];

	$local_graph_id = 50;
	$graph_item     = ['data_template_rrd_id' => 352];
	$cf_ds_cache    = $cache;
	$rra_seconds    = 300;
	$cdef_string    = 'b,a,-';
	require $temporary['cdef'];

	$results['graph'] = [
		'cf_reference' => $graph_items[0]['cf_reference'],
		'reset'        => $reset,
		'aggregate'    => $cdef_string,
	];

	$aggregate_def_map = null;
	$local_graph_id    = 99;
	$cdef_string       = 'b,a,-';
	require $temporary['cdef'];

	$results['graph']['not_aggregate'] = $cdef_string;
} finally {
	foreach ($temporary as $path) {
		unlink($path);
	}
}

print json_encode($results) . PHP_EOL;
