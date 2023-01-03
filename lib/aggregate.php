<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

function aggregate_build_children_url($local_graph_id, $graph_start = -1, $graph_end = -1, $rra_id = -1) {
	global $config;

	aggregate_prune_graphs($local_graph_id);

	$aggregate_data = db_fetch_row_prepared('SELECT *
		FROM aggregate_graphs
		WHERE local_graph_id = ?',
		array($local_graph_id));

	if (cacti_sizeof($aggregate_data)) {
		$local_graph_ids = array_rekey(
			db_fetch_assoc_prepared('SELECT local_graph_id
				FROM aggregate_graphs_items
				WHERE aggregate_graph_id = ?',
				array($aggregate_data['id'])
			), 'local_graph_id', 'local_graph_id'
		);

		if (cacti_sizeof($local_graph_ids)) {
			$graph_select = 'graph_add=';

			foreach($local_graph_ids as $graph) {
				$graph_select .= $graph . '%2C';
			}

			return "<a class='hyperLink aggregates' href='" . html_escape($config['url_path'] . 'graph_view.php?reset=1&page=1&graph_template_id=-1&host_id=-1&filter=&style=selective&action=preview' . ($graph_start >= 0 ? '&graph_start=' . $graph_start:'') . ($graph_end >= 0 ? '&graph_end=' . $graph_end:'') . ($rra_id >= 0 ? '&rra_id=' . $rra_id:'') . '&' . $graph_select) . "'><img src='" . $config['url_path'] . "images/view_aggregate_children.png' alt='' title='" . __esc('Display Graphs from this Aggregate') . "'></a><br/>" . PHP_EOL;
		}
	}
}

function api_aggregate_convert_template($graphs) {
	$aggregate_template_id = get_nfilter_request_var('aggregate_template_id');
	$aggregate_template    = db_fetch_row_prepared('SELECT *
		FROM aggregate_graph_templates
		WHERE id = ?',
		array($aggregate_template_id));

	foreach($graphs as $graph) {
		$save                          = array();
		$save['id']                    = '';
		$save['local_graph_id']        = $graph;
		$save['aggregate_template_id'] = $aggregate_template_id;
		$save['template_propogation']  = 'on';
		$save['title_format']          = db_fetch_cell_prepared('SELECT title_cache FROM graph_templates_graph WHERE local_graph_id = ?', array($graph));
		$save['graph_template_id']     = $aggregate_template['graph_template_id'];
		$save['gprint_prefix']         = $aggregate_template['gprint_prefix'];
		$save['graph_type']            = $aggregate_template['graph_type'];
		$save['total']                 = $aggregate_template['total'];
		$save['total_type']            = $aggregate_template['total_type'];
		$save['total_prefix']          = $aggregate_template['total_prefix'];
		$save['order_type']            = $aggregate_template['order_type'];

		$id = sql_save($save, 'aggregate_graphs');

		$task_items = array_rekey(
			db_fetch_assoc_prepared('SELECT DISTINCT task_item_id
				FROM graph_templates_item
				WHERE local_graph_id = ?
				ORDER BY sequence',
				array($graph)
			), 'task_item_id', 'task_item_id'
		);

		$task_items = implode(',', $task_items);
		$member_graphs = array_rekey(db_fetch_assoc("SELECT DISTINCT local_graph_id
			FROM graph_templates_item
			WHERE task_item_id IN ($task_items)
			AND graph_template_id>0"), 'local_graph_id', 'local_graph_id');

		$sequence = 1;

		foreach($member_graphs as $mg) {
			db_execute_prepared('REPLACE INTO aggregate_graphs_items
				(aggregate_graph_id, local_graph_id, sequence)
				VALUES (?, ?, ?)',
				array($id, $mg, $sequence));
			$sequence++;
		}

		push_out_aggregates($aggregate_template_id, $graph);
	}
}

function api_aggregate_associate($local_graph_id, $graphs) {
	$aggregate_template = db_fetch_cell_prepared('SELECT aggregate_template_id
		FROM aggregate_graphs
		WHERE local_graph_id = ?',
		array($local_graph_id));

	$aggregate_id = db_fetch_cell_prepared('SELECT id
		FROM aggregate_graphs
		WHERE local_graph_id = ?',
		array($local_graph_id));

	if (!empty($aggregate_id)) {
		$max_sequence = db_fetch_cell_prepared('SELECT MAX(sequence)
			FROM aggregate_graphs_items
			WHERE aggregate_graph_id = ?',
			array($aggregate_id));

		if ($max_sequence == '') {
			$max_sequence = 1;
		}

		foreach($graphs as $graph) {
			db_execute_prepared('REPLACE INTO aggregate_graphs_items
				(aggregate_graph_id, local_graph_id, sequence)
				VALUES (?, ?, ?)',
				array($aggregate_id, $graph, $max_sequence));

			$max_sequence++;
		}

		push_out_aggregates($aggregate_template, $local_graph_id);
	}
}

function api_aggregate_disassociate($local_graph_id, $graphs) {
	$aggregate_template = db_fetch_cell_prepared('SELECT aggregate_template_id
		FROM aggregate_graphs
		WHERE local_graph_id = ?',
		array($local_graph_id));

	$aggregate_id = db_fetch_cell_prepared('SELECT id
		FROM aggregate_graphs
		WHERE local_graph_id = ?',
		array($local_graph_id));

	if (!empty($aggregate_id)) {
		foreach($graphs as $graph) {
			db_execute_prepared('DELETE FROM aggregate_graphs_items
				WHERE aggregate_graph_id = ?
				AND local_graph_id = ?',
				array($aggregate_id, $graph));
		}

		push_out_aggregates($aggregate_template, $local_graph_id);
	}
}

function api_aggregate_create($aggregate_name, $graphs, $agg_template_id = 0) {
	/* get the first aggregate graph */
	if ($agg_template_id == 0) {
		$agg_template = db_fetch_row_prepared('SELECT *
			FROM aggregate_graphs
			WHERE local_graph_id = ?',
			array($graphs[0]));

		/* get graph items */
		$graph_items = db_fetch_assoc('SELECT DISTINCT local_graph_id
			FROM aggregate_graphs_items
			WHERE aggregate_graph_id IN(
				SELECT id
				FROM aggregate_graphs
				WHERE ' . array_to_sql_or($graphs, 'local_graph_id') . ')');
	} else {
		$agg_template = db_fetch_row_prepared('SELECT *
			FROM aggregate_graph_templates
			WHERE id = ?',
			array($agg_template_id));

		/* unset when dealing with a template */
		unset($agg_template['name']);

		$agg_template['aggregate_template_id'] = $agg_template_id;
		$agg_template['template_propogation']  = 'on';

		/* get graph items */
		foreach($graphs as $graph) {
			$graph_items[]['local_graph_id'] = $graph;
		}
	}

	if (cacti_sizeof($agg_template)) {
		/* create new graph in cacti tables */
		$graph_template_graph = db_fetch_row_prepared('SELECT *
			FROM graph_templates_graph
			WHERE local_graph_id = ?',
			array($graphs[0]));

		$graph_template_id = $graph_template_graph['graph_template_id'];

		$local_graph_id = aggregate_graph_save(0, $graph_template_id, $aggregate_name, $agg_template_id);

		/* create new graph in aggregate table */
		$save = array();
		$save = $agg_template;
		$save['id'] = 0;
		$save['local_graph_id'] = $local_graph_id;
		$save['title_format']   = $aggregate_name;

		$agg_id = sql_save($save, 'aggregate_graphs');

		if (cacti_sizeof($graph_items)) {
			$aggs = 1;
			$sql  = '';
			foreach($graph_items as $i) {
				$sql .= ($aggs > 1 ? ',':'') . "($agg_id, " . $i['local_graph_id'] . ", $aggs)";
				$aggs++;
			}

			db_execute("INSERT INTO aggregate_graphs_items
				(aggregate_graph_id, local_graph_id, sequence) VALUES $sql");
		}

		# update title cache
		if (!empty($_local_graph_id)) {
			update_graph_title_cache($local_graph_id);
		}

		push_out_aggregates($agg_template['aggregate_template_id'], $local_graph_id);
	}
}


/**
 * aggregate_error_handler	- PHP error handler
 * @param int $errno		- error id
 * @param string $errmsg	- error message
 * @param string $filename	- file name
 * @param int $linenum		- line of error
 * @param array $vars		- additional variables
 */
function aggregate_error_handler($errno, $errmsg, $filename, $linenum, $vars = []) {
	$errno = $errno & error_reporting();

	# return if error handling disabled by @
	if($errno == 0) return;

	# define constants not available with PHP 4
	if(!defined('E_STRICT'))            define('E_STRICT', 2048);
	if(!defined('E_RECOVERABLE_ERROR')) define('E_RECOVERABLE_ERROR', 4096);

	if (read_config_option('log_verbosity') >= POLLER_VERBOSITY_DEBUG) {
		/* define all error types */
		$errortype = array(
			E_ERROR             => 'Error',
			E_WARNING           => 'Warning',
			E_PARSE             => 'Parsing Error',
			E_NOTICE            => 'Notice',
			E_CORE_ERROR        => 'Core Error',
			E_CORE_WARNING      => 'Core Warning',
			E_COMPILE_ERROR     => 'Compile Error',
			E_COMPILE_WARNING   => 'Compile Warning',
			E_USER_ERROR        => 'User Error',
			E_USER_WARNING      => 'User Warning',
			E_USER_NOTICE       => 'User Notice',
			E_STRICT            => 'Runtime Notice',
			E_RECOVERABLE_ERROR => 'Catchable Fatal Error'
		);

		/* create an error string for the log */
		$err = "ERRNO:'"  . $errno   . "' TYPE:'"    . $errortype[$errno] .
			"' MESSAGE:'" . $errmsg  . "' IN FILE:'" . $filename .
			"' LINE NO:'" . $linenum . "'";

		/* let's ignore some lesser issues */
		if (substr_count($errmsg, 'date_default_timezone')) return;
		if (substr_count($errmsg, 'Only variables')) return;

		/* log the error to the Cacti log */
		cacti_log('PROGERR: ' . $err, false, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);
		print('PROGERR: ' . $err . '<br><pre>');

		# backtrace, if available
		cacti_debug_backtrace('AGGREGATE', true);

		if (isset($GLOBALS['error_fatal'])) {
			if($GLOBALS['error_fatal'] & $errno) die("Fatal error $errno" . PHP_EOL);
		}
	}

	return;
}

/**
 * get_next_sequence 			- returns the next available sequence id
 *
 * @param int $id 				- the current id
 * @param string $field 		- the field name that contains the target id
 * @param string $table_name 	- the table name that contains the target id
 * @param string $group_query 	- an SQL 'where' clause to limit the query
 + @returns int					- the next available sequence id
 */
function get_next_sequence($id, $field, $table_name, $group_query, $key_field='id') {
	cacti_log(__FUNCTION__ . '  called. Id: ' . $id . ' field: ' . $field . ' table: ' . $table_name, true, 'AGGREGATE', POLLER_VERBOSITY_DEVDBG);

	if (empty($id)) {
		$data = db_fetch_row("SELECT max($field)+1 AS seq FROM $table_name WHERE $group_query");

		if ($data['seq'] == '') {
			return 1;
		} else {
			return $data['seq'];
		}
	} else {
		$data = db_fetch_row("SELECT $field FROM $table_name WHERE $key_field = id");
		return $data[$field];
	}
}

/**
 * find out, if this is a pure STACKed graph
 * @param int $_local_graph_id	- graph to be examined
 * @return bool					- true, if pure STACKed graph
 */
function aggregate_is_pure_stacked_graph($_local_graph_id) {
	cacti_log(__FUNCTION__ . ' local_graph: ' . $_local_graph_id, true, 'AGGREGATE', POLLER_VERBOSITY_DEVDBG);

	$_pure_stacked_graph = false;

	if (!empty($_local_graph_id)) {
		# fetch all AREA graph items
		$_count = db_fetch_cell_prepared('SELECT COUNT(id)
			FROM graph_templates_item
			WHERE graph_templates_item.local_graph_id = ?
			AND graph_templates_item.graph_type_id IN (?, ?, ?, ?)',
			array($_local_graph_id, GRAPH_ITEM_TYPE_AREA, GRAPH_ITEM_TYPE_LINE1, GRAPH_ITEM_TYPE_LINE2, GRAPH_ITEM_TYPE_LINE3));

		cacti_log(__FUNCTION__ . ' #AREA/LINEx items: ' . $_count, true, 'AGGREGATE', POLLER_VERBOSITY_DEVDBG);

		/* if there's at least one AREA item, this is NOT a pure LINEx graph
		 * if there's NO AREA item, this IS a pure LINEx graph
		 * in case we find STACKs, there must be at least one AREA as well, or the graph itself is malformed
		 * this would fail on a PURE GPRINT/HRULE/VRULE graph as well, but that is malformed, too
		 */
		$_pure_stacked_graph = ($_count == 0);
	}

	return $_pure_stacked_graph;
}

/**
 * find out, if graph has a STACK
 * @param int $_local_graph_id	- graph to be examined
 * @return bool					- true, if pure STACKed graph
 */
function aggregate_is_stacked_graph($_local_graph_id) {
	cacti_log(__FUNCTION__ . ' local_graph: ' . $_local_graph_id, true, 'AGGREGATE', POLLER_VERBOSITY_DEVDBG);

	$_pure_stacked_graph = false;

	if (!empty($_local_graph_id)) {
		# fetch all AREA graph items
		$_count = db_fetch_cell_prepared('SELECT COUNT(id)
			FROM graph_templates_item
			WHERE graph_templates_item.local_graph_id = ?
			AND graph_templates_item.graph_type_id = ?',
			array($_local_graph_id, GRAPH_ITEM_TYPE_STACK));

		cacti_log(__FUNCTION__ . ' #AREA/LINEx items: ' . $_count, true, 'AGGREGATE', POLLER_VERBOSITY_DEVDBG);

		/* if there's at least one AREA item, this is NOT a pure LINEx graph
		 * if there's NO AREA item, this IS a pure LINEx graph
		 * in case we find STACKs, there must be at least one AREA as well, or the graph itself is malformed
		 * this would fail on a PURE GPRINT/HRULE/VRULE graph as well, but that is malformed, too
		 */
		$_stacked_graph = ($_count > 0);
	}

	return $_stacked_graph;
}

function aggregate_conditional_convert_graph_type($_graph_id, $_old_type, $_new_type) {
	cacti_log(__FUNCTION__ . '  called: graph: ' . $_graph_id . ' old item type: ' . $_old_type . ' new item type: ' . $_new_type, true, 'AGGREGATE', POLLER_VERBOSITY_DEVDBG);

	if (!empty($_graph_id) && !empty($_old_type)) {
		/* fetch the first item of requested graph_type */
		$_graph_item_id = db_fetch_cell_prepared('SELECT id
			FROM graph_templates_item AS gti
			WHERE gti.local_graph_id = ?
			AND gti.graph_type_id = ?
			ORDER BY sequence
			LIMIT 1',
			array($_graph_id, $_old_type));

		/* and update it to the new graph_type */
		db_execute_prepared('UPDATE graph_templates_item
			SET graph_templates_item.graph_type_id = ?
			WHERE graph_templates_item.id = ?',
			array($_new_type, $_graph_item_id));
	}
}

function aggregate_change_graph_type($graph_index, $old_graph_type, $new_graph_type) {
	cacti_log(__FUNCTION__ . ' called. Index ' . $graph_index . ' old type ' . $old_graph_type . ' Graph Type: ' . $new_graph_type, true, 'AGGREGATE', POLLER_VERBOSITY_DEVDBG);

	/* LEGEND entries and xRULEs stay unchanged
	 * xRULEs honestly do not make much sense on an aggregated graph, though */
	switch ($old_graph_type) {
		case GRAPH_ITEM_TYPE_GPRINT:
		case GRAPH_ITEM_TYPE_GPRINT_LAST:
		case GRAPH_ITEM_TYPE_GPRINT_MIN:
		case GRAPH_ITEM_TYPE_GPRINT_MAX:
		case GRAPH_ITEM_TYPE_GPRINT_AVERAGE:
		case GRAPH_ITEM_TYPE_TEXTALIGN:
		case GRAPH_ITEM_TYPE_TIC:
		case GRAPH_ITEM_TYPE_COMMENT:
		case GRAPH_ITEM_TYPE_HRULE:
		case GRAPH_ITEM_TYPE_VRULE:
			return $old_graph_type;
			break;
	}

	/* this item is eligible to a type change */
	switch ($new_graph_type) {
		case AGGREGATE_GRAPH_TYPE_KEEP:
			/* keep entry as defined by the Graph */
			return $old_graph_type;
			break;
		case GRAPH_ITEM_TYPE_STACK:
			/* create an AREA/STACK graph
			 * pay attention to AREA handling!
			 * any AREA/STACKed graph needs a base AREA entry
			 * but e.g. a graph that prints on both negative and positive y-axis may hold two AREAs
			 * so it's a good idea to keep all AREA entries of the first aggregated elementary graph (index 0)*/
			if ($graph_index == 0 &&
				($old_graph_type == GRAPH_ITEM_TYPE_STACK ||
				$old_graph_type == GRAPH_ITEM_TYPE_LINESTACK ||
				$old_graph_type == GRAPH_ITEM_TYPE_LINE1 ||
				$old_graph_type == GRAPH_ITEM_TYPE_LINE2 ||
				$old_graph_type == GRAPH_ITEM_TYPE_LINE3)) {
				/* if the graph type is a stack and the item is 1, it must be converted to area */
				return GRAPH_ITEM_TYPE_AREA;
			} elseif ($graph_index > 0 && $old_graph_type == GRAPH_ITEM_TYPE_AREA) {
				/* if the graph type is a stack and the item is 1, it must be converted to area */
				return GRAPH_ITEM_TYPE_STACK;
			} elseif ($graph_index == 0 && $old_graph_type == GRAPH_ITEM_TYPE_AREA) {
				/* don't change (multi-)AREAs on the first graph */
				return $old_graph_type;
			} else {
				/* this is either
				 * - not the first graph, any item type:	convert to STACK
				 * - the first graph and a LINEx item: 		convert to STACK
				 */
				return GRAPH_ITEM_TYPE_STACK;
			}

			/* for a pure LINEx graph,
			 * this will result in a pure STACKed graph, without any AREA
			 * we will take care of this at the very end, after adding the last graph to the aggregate, during post-processing
			 */
			break;
		case AGGREGATE_GRAPH_TYPE_KEEP_STACKED:
			/* Like GRAPH_ITEM_TYPE_STACK but don't convert first item to AREA */
			if ($graph_index == 0) {
				if ($old_graph_type == GRAPH_ITEM_TYPE_STACK) {
					return GRAPH_ITEM_TYPE_AREA;
				}
				return $old_graph_type;
			} else {
				return GRAPH_ITEM_TYPE_STACK;
			}
			break;
		case AGGREGATE_GRAPH_TYPE_LINE1_STACK:
			if ($graph_index == 0) {
				return GRAPH_ITEM_TYPE_LINE1;
			} else {
				return GRAPH_ITEM_TYPE_LINESTACK;
			}
			break;
		case AGGREGATE_GRAPH_TYPE_LINE2_STACK:
			if ($graph_index == 0) {
				return GRAPH_ITEM_TYPE_LINE2;
			} else {
				return GRAPH_ITEM_TYPE_LINESTACK;
			}
			break;
		case AGGREGATE_GRAPH_TYPE_LINE3_STACK:
			if ($graph_index == 0) {
				return GRAPH_ITEM_TYPE_LINE3;
			} else {
				return GRAPH_ITEM_TYPE_LINESTACK;
			}
			break;
		case GRAPH_ITEM_TYPE_LINE1:
		case GRAPH_ITEM_TYPE_LINE2:
		case GRAPH_ITEM_TYPE_LINE3:
		case GRAPH_ITEM_TYPE_LINESTACK:
			return $new_graph_type;
			break;
	}
}

/**
 * duplicate_color_template				- duplicate color template
 *
 * @param int $_color_template_id		- id of the base color template
 * @param string $color_template_title	- title of the duplicated color template
 */
function duplicate_color_template($_color_template_id, $color_template_title) {
	cacti_log(__FUNCTION__ . ' called. Color Template Id: ' . $_color_template_id . ' Title: ' . $color_template_title, true, 'AGGREGATE', POLLER_VERBOSITY_DEVDBG);

	/* fetch data from table color_templates */
	$color_template = db_fetch_row_prepared('SELECT *
		FROM color_templates
		WHERE color_template_id = ?',
		array($_color_template_id));

	/* fetch data from table color_template_items */
	$color_template_items = db_fetch_assoc_prepared('SELECT *
		FROM color_template_items
		WHERE color_template_id = ?',
		array($_color_template_id));

	$save = array();

	/* create new entry: color_templates */
	$save['color_template_id'] = 0;

	/* substitute the title variable */
	$save['name'] = str_replace('<template_title>', $color_template['name'], $color_template_title);

	cacti_log(__FUNCTION__ . ' called. Id:' . $_color_template_id . ' Title: ' . $color_template_title . ' Replaced: ' . $save['name'], true, 'AGGREGATE', POLLER_VERBOSITY_DEVDBG);

	$new_color_template_id = sql_save($save, 'color_templates', 'color_template_id');

	/* create new entry(s): color_template_items */
	if (cacti_sizeof($color_template_items)) {
		foreach ($color_template_items as $color_template_item) {
			$save = array();
			$save['color_template_item_id'] = 0;
			$save['color_template_id'] = $new_color_template_id;
			$save['color_id'] = $color_template_item['color_id'];
			$save['sequence'] = $color_template_item['sequence'];

			cacti_log(__FUNCTION__ . ' called. Id:' . $new_color_template_id . ' Color: ' . $save['color_id'] . ' sequence: ' . $save['sequence'], true, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);

			$new_color_template_item_id = sql_save($save, 'color_template_items', 'color_template_item_id');
		}
	}
}

/**
 * aggregate_cdef_make0			- return the id of a 'Make 0' cdef, create that cdef if necessary
 */
function aggregate_cdef_make0() {
	global $config;

	include_once($config['base_path'] . '/lib/cdef.php');

	cacti_log(__FUNCTION__ . ' called', true, 'AGGREGATE', POLLER_VERBOSITY_DEVDBG);

	# magic name of new cdef
	$magic   = '_MAKE 0';

	# search the 'magic' cdef
	$cdef_id = db_fetch_cell_prepared('SELECT id
		FROM cdef
		WHERE name = ?',
		array($magic));

	if (isset($cdef_id) && $cdef_id > 0) {
		return $cdef_id;	# hoping, that nobody changed the cdef_items!
	}

	# create a new cdef entry
	$save           = array();
	$save['id']     = 0;
	$save['hash']   = get_hash_cdef(0);
	$save['system'] = 1;
	$save['name']   = $magic;

	# save the cdef itself
	$new_cdef_id  = sql_save($save, 'cdef');

	cacti_log(__FUNCTION__ . ' created new cdef: ' . $new_cdef_id . ' name: ' . $magic, true, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);

	# create a new cdef item entry
	$save             = array();
	$save['id']       = 0;
	$save['hash']     = get_hash_cdef(0, 'cdef_item');
	$save['cdef_id']  = $new_cdef_id;
	$save['sequence'] = 1;
	$save['type']     = 6; # this will be replaced by a define as soon as it exists for a pure text field
	$save['value']    = 'CURRENT_DATA_SOURCE,0,*';

	# save the cdef item, there's only one!
	$cdef_item_id = sql_save($save, 'cdef_items');

	cacti_log(__FUNCTION__ . ' created new cdef item: ' . $cdef_item_id, true, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);

	return $new_cdef_id;
}

/**
 * aggregate_cdef_totalling			- create a totalling CDEF, if need be
 * @param int $_new_graph_id		- id of new graph
 * @param int $_graph_item_sequence	- current graph item sequence
 * @param int $_total_type			- what type of totalling is required?
 */
function aggregate_cdef_totalling($_new_graph_id, $_graph_item_sequence, $_total_type) {
	global $config;

	include_once($config['base_path'] . '/lib/cdef.php');

	cacti_log(__FUNCTION__ . ' called. Working on Graph: ' . $_new_graph_id . ' sequence: ' .  $_graph_item_sequence  . ' totalling: ' . $_total_type, true, 'AGGREGATE', POLLER_VERBOSITY_DEVDBG);

	# take graph item data for the totalling items
	if (!empty($_new_graph_id)) {
		$sql = "SELECT id, cdef_id
			FROM graph_templates_item
			WHERE local_graph_id=$_new_graph_id
			AND sequence>=$_graph_item_sequence
			ORDER BY sequence";

		cacti_log('sql: ' . $sql, true, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);

		$graph_template_items = db_fetch_assoc($sql);
	}

	# now get the list of cdefs
	$sql = 'SELECT id, name FROM cdef ORDER BY id';

	cacti_log(__FUNCTION__ . ' sql: ' . $sql, true, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);

	$_cdefs = db_fetch_assoc($sql); # index the cdefs by their id's
	$cdefs  = array();

	# build cdefs array to allow for indexing on cdef_id
	foreach ($_cdefs as $_cdef) {
		$cdefs[$_cdef['id']]['id'] = $_cdef['id'];
		$cdefs[$_cdef['id']]['name'] = $_cdef['name'];
		$cdefs[$_cdef['id']]['cdef_text'] = get_cdef($_cdef['id']);
	}

	# add pseudo CDEF for CURRENT_DATA_SOURCE, in case CDEF=NONE
	# we then may apply the standard CDEF procedure to create a new CDEF
	$cdefs[0]['id']        = 0;
	$cdefs[0]['name']      = 'Items';
	$cdefs[0]['cdef_text'] = 'CURRENT_DATA_SOURCE';

	/* new CDEF(s) are required! */
	$num_items = cacti_sizeof($graph_template_items);
	if ($num_items > 0) {
		$i = 0;
		foreach ($graph_template_items as $graph_template_item) {
			# current cdef
			$cdef_id   = $graph_template_item['cdef_id'];
			$cdef_name = $cdefs[$cdef_id]['name'];
			$cdef_text = $cdefs[$cdef_id]['cdef_text'];

			cacti_log(__FUNCTION__ . ' cdef id: ' . $cdef_id . ' name: ' . $cdef_name . ' value: ' . $cdef_text, true, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);

			# new cdef
			$new_cdef_text = 'INVALID';	# in case sth goes wrong
			switch ($_total_type) {
				case AGGREGATE_TOTAL_TYPE_SIMILAR:
					$new_cdef_text = str_replace('CURRENT_DATA_SOURCE', 'SIMILAR_DATA_SOURCES_NODUPS', $cdef_text);
					break;
				case AGGREGATE_TOTAL_TYPE_ALL:
					$new_cdef_text = str_replace('CURRENT_DATA_SOURCE', 'ALL_DATA_SOURCES_NODUPS', $cdef_text);
					break;
			}

			# is the new cdef already present?
			$new_cdef_id = '';
			foreach ($cdefs as $cdef) {
				cacti_log(__FUNCTION__ . ' verify matching cdef: ' . $cdef['id'] . ' on: ' . $cdef['cdef_text'], true, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);

				if ($cdef['cdef_text'] == $new_cdef_text) {
					$new_cdef_id = $cdef['id'];
					cacti_log(__FUNCTION__ . ' matching cdef: ' . $new_cdef_id, true, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);
					# leave on first match
					break;
				}
			}

			# in case, we have NO match
			if (empty($new_cdef_id)) {
				# create a new cdef entry
				$save           = array();
				$save['id']     = 0;
				$save['hash']   = get_hash_cdef(0);
				$save['system'] = 1;
				$new_cdef_name  = 'INVALID ' . $cdef_name; # in case anything goes wrong

				switch ($_total_type) {
					case AGGREGATE_TOTAL_TYPE_SIMILAR:
						$new_cdef_name = '_AGGREGATE SIMILAR ' . $cdef_name;
						break;
					case AGGREGATE_TOTAL_TYPE_ALL:
						$new_cdef_name = '_AGGREGATE ALL ' . $cdef_name;
						break;
				}

				$save['name']   = $new_cdef_name;

				# save the cdef itself
				$new_cdef_id  = sql_save($save, 'cdef');

				cacti_log(__FUNCTION__ . ' created new cdef: ' . $new_cdef_id . ' name: ' . $new_cdef_name . ' value: ' . $new_cdef_text, true, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);

				# create a new cdef item entry
				$save             = array();
				$save['id']       = 0;
				$save['hash']     = get_hash_cdef(0, 'cdef_item');
				$save['cdef_id']  = $new_cdef_id;
				$save['sequence'] = 1;
				$save['type']     = 6; # this will be replaced by a define as soon as it exists for a pure text field
				$save['value']    = $new_cdef_text;

				# save the cdef item, there's only one!
				$cdef_item_id     = sql_save($save, 'cdef_items');

				cacti_log(__FUNCTION__ . ' created new cdef item: ' . $cdef_item_id, true, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);

				# now extend the cdef array to learn the newly entered cdef for the next loop
				$cdefs[$new_cdef_id]['id']        = $new_cdef_id;
				$cdefs[$new_cdef_id]['name']      = $new_cdef_name;
				$cdefs[$new_cdef_id]['cdef_text'] = $new_cdef_text;
			}

			# now that we have a new cdef id, update record accordingly
			$sql = "UPDATE graph_templates_item
				SET cdef_id=$new_cdef_id
				WHERE id=" . $graph_template_item["id"];

			cacti_log(__FUNCTION__ . ' sql: ' . $sql, true, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);

			$ok = db_execute($sql);

			cacti_log(__FUNCTION__ . ' updated new cdef id: ' . $new_cdef_id . ' for item: ' . $graph_template_item['id'], true, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);
		}
	}
}

/** auto_hr			- set a new hr when items are skipped
 * @param array $s	- array of skipped items
 * @param array $h	- array of items with HR
 * returns array	- array with new HR markers
 */
function auto_hr($s, $h) {
	cacti_log(__FUNCTION__ . ' called', true, 'AGGREGATE', POLLER_VERBOSITY_DEVDBG);

	# start at end of array, both arrays are from 1 .. cacti_count(array)
	$i = cacti_count($h);

	# make sure, that last item always has a HR, even if template does not have any
	$h[$i] = true;

	do {
		# if skipped item has a HR
		if (isset($s[$i]) && ($s[$i] > 0) && $h[$i]) {
			# set previous item (if any) to HR
			if (isset($h[$i-1])) $h[$i-1] = $h[$i];
		}
	} while($i-- > 0);

	return $h;
}

/** auto_title					- generate a title suggested to the user
 * @param int $_local_graph_id	- the id of the graph stanza
 * returns string				- the title
 */
function auto_title($_local_graph_id) {
	cacti_log(__FUNCTION__ . ' called. Local Graph Id: ' . $_local_graph_id, true, 'AGGREGATE', POLLER_VERBOSITY_DEVDBG);

	# apply given graph title, but drop host and query variables
	$graph_title = 'Aggregate ';
	$graph_title .= db_fetch_cell_prepared('SELECT title
		FROM graph_templates_graph
		WHERE local_graph_id = ?',
		array($_local_graph_id));

	cacti_log('title:' . $graph_title, true, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);

	# remove all '- |query_*|' occurrences
	$pattern = '/-?\s+\|query_\w+\|/';
	$graph_title = preg_replace($pattern, '', $graph_title);

	cacti_log('title:' . $graph_title, true, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);

	# remove all '- |host_*|' occurrences
	$pattern = '/-?\s+\|host_\w+\|/';
	$graph_title = preg_replace($pattern, '', $graph_title);

	cacti_log('title:' . $graph_title, true, 'AGGREGATE', POLLER_VERBOSITY_DEBUG);

	return $graph_title;
}

function api_aggregate_remove_multi($graphs) {
	global $config;

	include_once($config['base_path'] . '/lib/api_graph.php');

	if (cacti_sizeof($graphs)) {
		foreach($graphs as $graph) {
			$ag = db_fetch_cell_prepared('SELECT id
				FROM aggregate_graphs
				WHERE local_graph_id = ?',
				array($graph));

			db_execute_prepared('DELETE FROM aggregate_graphs
				WHERE local_graph_id = ?',
				array($graph));

			db_execute_prepared('DELETE FROM aggregate_graphs_items
				WHERE aggregate_graph_id = ?',
				array($ag));

			db_execute_prepared('DELETE FROM aggregate_graphs_graph_item
				WHERE aggregate_graph_id = ?',
				array($ag));
		}

		api_graph_remove_multi($graphs);
	}
}

/* To-do remove orphaned elements */
function aggregate_prune_graphs($local_graph_id = 0) {
	$aggregate_graphs = array();
	$local_graph_ids  = array();

	$sql_where        = '';
	if ($local_graph_id > 0) {
		$sql_where = "AND pagi.local_graph_id=$local_graph_id";
	}

	// Phase 1 Aggregate Graphs
	$pruneme = db_fetch_assoc("SELECT
		pagi.aggregate_graph_id,
		pagi.local_graph_id
		FROM aggregate_graphs_items AS pagi
		LEFT JOIN graph_local AS gl
		ON pagi.local_graph_id=gl.id
		WHERE gl.id IS NULL
		$sql_where");

	foreach($pruneme as $p) {
		$local_graph_ids[$p['local_graph_id']]      = $p['local_graph_id'];
		$aggregate_graphs[$p['aggregate_graph_id']] = $p['aggregate_graph_id'];
	}

	if (cacti_sizeof($local_graph_ids)) {
		db_execute('DELETE FROM aggregate_graphs_items
			WHERE local_graph_id IN (' . implode(',', $local_graph_ids) . ')');
	}

	if (cacti_sizeof($aggregate_graphs) || $local_graph_id > 0) {
		if ($local_graph_id > 0) {
			$agg_graph = db_fetch_cell_prepared('SELECT id
				FROM aggregate_graphs
				WHERE local_graph_id = ?',
				array($local_graph_id));

			if (!empty($agg_graph)) {
				$aggregate_graphs[$agg_graph] = $agg_graph;
			}
		}

		// Phase 2 - Graphs based upon aggregates
		foreach($aggregate_graphs as $a) {
			$graph_id = db_fetch_cell_prepared('SELECT local_graph_id
				FROM aggregate_graphs
				WHERE id = ?',
				array($a));

			$bad_graph_items = array_rekey(db_fetch_assoc_prepared('SELECT gti.id
				FROM graph_templates_item AS gti
				LEFT JOIN data_template_rrd AS dtr
				ON gti.task_item_id=dtr.id
				WHERE gti.local_graph_id = ?
				AND dtr.id IS NULL
				AND gti.graph_type_id IN (4,5,6,7,8,9)', array($graph_id)), 'id', 'id');

			if (cacti_sizeof($bad_graph_items)) {
				db_execute('DELETE FROM graph_templates_item
					WHERE id IN (' . implode(',', $bad_graph_items) . ')');
			}
		}
	}
}

function api_aggregate_convert_to_graph($graphs) {
	if (cacti_sizeof($graphs)) {
		foreach($graphs as $graph) {
			$ag = db_fetch_cell_prepared('SELECT id
				FROM aggregate_graphs
				WHERE local_graph_id = ?',
				array($graph));

			db_execute_prepared('DELETE FROM aggregate_graphs
				WHERE local_graph_id = ?',
				array($graph));

			db_execute_prepared('DELETE FROM aggregate_graphs_items
				WHERE aggregate_graph_id = ?',
				array($ag));

			db_execute_prepared('DELETE FROM aggregate_graphs_graph_item
				WHERE aggregate_graph_id = ?',
				array($ag));

			$graph_template = db_fetch_cell_prepared('SELECT MAX(gl.graph_template_id)
				FROM graph_local AS gl
				INNER JOIN graph_templates_item AS gti
				ON gl.id = gti.local_graph_id
				INNER JOIN data_template_rrd AS dtr
				ON dtr.id = gti.task_item_id
				WHERE dtr.id IN (
					SELECT DISTINCT dtr.id FROM graph_local AS gl
					INNER JOIN graph_templates_item AS gti
					ON gti.local_graph_id = gl.id
					INNER JOIN data_template_rrd AS dtr
					ON dtr.id = gti.task_item_id
					WHERE gl.id = ?
				)',
				array($graph));

			if ($graph_template > 0) {
				db_execute_prepared('UPDATE graph_local
					SET graph_template_id = ?
					WHERE id = ?',
					array($graph_template, $graph));
			}
		}
	}
}
