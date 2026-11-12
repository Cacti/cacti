<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 3) . '/Helpers/RequestCoverage.php';
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
function generate_graph_def_name($graph_item_id) {
	return strtr(strval($graph_item_id), '0123456789', 'abcdefghij');
}

/** Every RRD holds every CF except the files the fixture marks as AVERAGE only. */
function generate_graph_best_cf($local_data_id, $requested_cf, $ds_step = 60) {
	if ($local_data_id <= 0 || in_array($local_data_id, $GLOBALS['average_only'], true)) {
		return 1;
	}

	return (int) $requested_cf;
}

/** Serve aggregate members, and each member's graph items, from the fixture tables. */
function db_fetch_assoc_prepared($sql, $params = array()) {
	if (strpos($sql, 'aggregate_graphs_items') !== false) {
		return $GLOBALS['members'][$params[0]] ?? array();
	}

	return $GLOBALS['items'][$params[0]] ?? array();
}

function item($type, $cf, $dtr, $local_data_id, $ds) {
	return array(
		'graph_type_id'              => $type,
		'consolidation_function_id'  => $cf,
		'data_template_rrd_id'       => $dtr,
		'local_data_id'              => $local_data_id,
		'data_source_name'           => $ds,
		'local_data_template_rrd_id' => 900 + $dtr % 10,
	);
}

/* aggregate 50 has members 20 and 35; each graphs in and out, then a MAX line of in */
$GLOBALS['average_only'] = array();
$GLOBALS['members']      = array(50 => array(array('local_graph_id' => 20), array('local_graph_id' => 35)));
$GLOBALS['items']        = array(
	20 => array(
		item(GRAPH_ITEM_TYPE_AREA, 1, 201, 21, 'in'),
		item(GRAPH_ITEM_TYPE_GPRINT, 4, 201, 21, 'in'),
		item(GRAPH_ITEM_TYPE_STACK, 1, 202, 21, 'out'),
		item(GRAPH_ITEM_TYPE_LINE1, 3, 201, 21, 'in'),
		item(GRAPH_ITEM_TYPE_COMMENT, 1, null, null, null),
	),
	35 => array(
		item(GRAPH_ITEM_TYPE_AREA, 1, 351, 36, 'in'),
		item(GRAPH_ITEM_TYPE_GPRINT, 4, 351, 36, 'in'),
		item(GRAPH_ITEM_TYPE_STACK, 1, 352, 36, 'out'),
		item(GRAPH_ITEM_TYPE_LINE1, 3, 351, 36, 'in'),
	),
);

/* the aggregate's own DEF indexes: member 20 is a,b,c and member 35 is d,e,f */
$cache = array(201 => array(1 => '0', 3 => '2'), 202 => array(1 => '1'), 351 => array(1 => '3', 3 => '5'), 352 => array(1 => '4'));

$map = rrdtool_aggregate_def_map(50, 300);
$now = time() - 300;

$results = array(
	'map'          => $map,
	'not_aggregate' => rrdtool_aggregate_def_map(99, 300),
	'first_member' => rrdtool_aggregate_cdef_defs('b,a,-', 201, $map, $cache, 300),
	'second'       => rrdtool_aggregate_cdef_defs('b,a,-', 352, $map, $cache, 300),
	'special'      => rrdtool_aggregate_cdef_defs('CURRENT_DATA_SOURCE,c,/', 351, $map, $cache, 300),
	'total'        => rrdtool_aggregate_cdef_defs('SIMILAR_DATA_SOURCES_NODUPS,a,-', 0, $map, $cache, 300),
	'total_now'    => $now,
	'untouched'    => rrdtool_aggregate_cdef_defs('cdefe,0,EQ,j,ab,UN,IF', 352, $map, $cache, 300),
	'unresolved'   => rrdtool_aggregate_cdef_defs('a,8,*', 777, array('owner' => array(), 'members' => array(array())), array(), 300),
	'single_total' => rrdtool_aggregate_cdef_defs('a,8,*', 777, array('owner' => array(), 'members' => array(array(array(201, 1)))), $cache, 300),
	'cfs'          => array(),
);

/* the CF each item type's DEF is built with */
$last = array();
foreach (array(
	item(GRAPH_ITEM_TYPE_GPRINT, 4, 201, 21, 'in'),
	item(GRAPH_ITEM_TYPE_LINE1, 3, 201, 21, 'in'),
	item(GRAPH_ITEM_TYPE_GPRINT, 4, 201, 21, 'in'),
	item(GRAPH_ITEM_TYPE_GPRINT_AVERAGE, 1, 201, 21, 'in'),
	item(GRAPH_ITEM_TYPE_GPRINT_LAST, 4, 201, 21, 'in'),
	item(GRAPH_ITEM_TYPE_GPRINT_MAX, 3, 201, 21, 'in'),
	item(GRAPH_ITEM_TYPE_GPRINT_MIN, 2, 201, 21, 'in'),
	item(GRAPH_ITEM_TYPE_HRULE, 3, 201, 21, 'in'),
) as $graph_item) {
	$results['cfs'][] = rrdtool_graph_item_cf($graph_item, 300, $last);
}

/* a member whose RRD lacks MAX draws its MAX line from AVERAGE, so it has one DEF fewer */
$GLOBALS['average_only'] = array(36);
$results['average_only'] = rrdtool_aggregate_def_map(50, 300)['members'][1];

print json_encode($results) . PHP_EOL;
