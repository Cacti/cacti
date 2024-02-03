#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

require(__DIR__ . '/../include/cli_check.php');
require_once($config['base_path'] . '/lib/api_automation_tools.php');
require_once($config['base_path'] . '/lib/api_automation.php');
require_once($config['base_path'] . '/lib/api_data_source.php');
require_once($config['base_path'] . '/lib/api_graph.php');
require_once($config['base_path'] . '/lib/api_device.php');
require_once($config['base_path'] . '/lib/api_tree.php');
require_once($config['base_path'] . '/lib/data_query.php');
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/snmp.php');
require_once($config['base_path'] . '/lib/sort.php');
require_once($config['base_path'] . '/lib/template.php');
require_once($config['base_path'] . '/lib/utility.php');

/* switch to main database for cli's */
if ($config['poller_id'] > 1) {
	db_switch_remote_to_main();
}

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (cacti_sizeof($parms)) {
	/* setup defaults */
	$graph_type    = '';
	$templateGraph = array();
	$dsGraph       = array();
	$dsGraph['snmpFieldSpec']  = '';
	$dsGraph['snmpQueryId']    = '';
	$dsGraph['snmpQueryType']  = '';
	$dsGraph['snmpField']      = array();
	$dsGraph['snmpValue']      = array();
	$dsGraph['snmpValueRegex'] = array();
	$dsGraph['reindex_method'] = DATA_QUERY_AUTOINDEX_BACKWARDS_UPTIME;

	$input_fields  = array();
	$values['cg']  = array();

	$hosts          = getHosts();
	$graphTemplates = getGraphTemplates();

	$graphTitle = '';
	$cgInputFields = '';

	$host_id     	= 0;
	$template_id 	= 0;
	$hostTemplateId = 0;
	$force      	= 0;

	$listHosts       		= false;
	$listGraphTemplates 	= false;
	$listSNMPFields  		= false;
	$listSNMPValues  		= false;
	$listQueryTypes  		= false;
	$listSNMPQueries 		= false;
	$listInputFields 		= false;

	$quietMode       = false;

	$shortopts = 'VvHh';

	$longopts = array(
		'host-id::',
		'graph-type::',
		'graph-template-id::',

		'graph-title::',
		'host-template-id::',
		'input-fields::',
		'snmp-query-id::',
		'snmp-query-type-id::',
		'snmp-field::',
		'snmp-value::',
		'snmp-value-regex::',
		'reindex-method::',

		'list-hosts',
		'list-snmp-fields',
		'list-snmp-values',
		'list-query-types',
		'list-snmp-queries',
		'force',
		'quiet',
		'list-input-fields',
		'list-graph-templates',
		'version',
		'help'
	);

	$options = getopt($shortopts, $longopts);

	foreach($options as $arg => $value) {
		$allow_multi = false;

		switch($arg) {
		case 'graph-type':
			$graph_type = $value;

			break;
		case 'graph-title':
			$graphTitle = $value;

			break;
		case 'graph-template-id':
			$template_id = $value;

			break;
		case 'host-template-id':
			$hostTemplateId = $value;

			break;
		case 'host-id':
			$host_id = $value;

			break;
		case 'input-fields':
			$cgInputFields = $value;

			break;
		case 'snmp-query-id':
			$dsGraph['snmpQueryId'] = $value;

			break;
		case 'snmp-query-type-id':
			$dsGraph['snmpQueryType'] = $value;

			break;
		case 'snmp-field':
			if (!is_array($value)) {
				$value = array($value);
			}

			$dsGraph['snmpField'] = $value;
			$allow_multi = true;

			break;
		case 'snmp-value-regex':
			if (!is_array($value)) {
				$value = array($value);
			}

			foreach($value as $item) {
				if (!validate_is_regex($item)) {
					print "ERROR: Regex specified '$item', is not a valid Regex!\n";
					exit(1);
				}
			}

			$dsGraph['snmpValueRegex'] = $value;
			$allow_multi = true;

			break;
		case 'snmp-value':
			if (!is_array($value)) {
				$value = array($value);
			}

			$dsGraph['snmpValue'] = $value;
			$allow_multi = true;

			break;
		case 'reindex-method':
			if (is_numeric($value) &&
				($value >= DATA_QUERY_AUTOINDEX_NONE) &&
				($value <= DATA_QUERY_AUTOINDEX_FIELD_VERIFICATION)) {
				$dsGraph['reindex_method'] = $value;
			} else {
				switch (strtolower($value)) {
					case 'none':
						$dsGraph['reindex_method'] = DATA_QUERY_AUTOINDEX_NONE;
						break;
					case 'uptime':
						$dsGraph['reindex_method'] = DATA_QUERY_AUTOINDEX_BACKWARDS_UPTIME;
						break;
					case 'index':
						$dsGraph['reindex_method'] = DATA_QUERY_AUTOINDEX_INDEX_NUM_CHANGE;
						break;
					case 'fields':
						$dsGraph['reindex_method'] = DATA_QUERY_AUTOINDEX_FIELD_VERIFICATION;
						break;
					default:
						print "ERROR: You must supply a valid reindex method for this graph!\n";
						exit(1);
				}
			}

			break;
		case 'list-hosts':
			$listHosts = true;

			break;
		case 'list-snmp-fields':
			$listSNMPFields = true;

			break;
		case 'list-snmp-values':
			$listSNMPValues = true;

			break;
		case 'list-query-types':
			$listQueryTypes = true;

			break;
		case 'list-snmp-queries':
			$listSNMPQueries = true;

			break;
		case 'force':
			$force = true;

			break;
		case 'quiet':
			$quietMode = true;

			break;
		case 'list-input-fields':
			$listInputFields = true;

			break;
		case 'list-graph-templates':
			$listGraphTemplates = true;

			break;
		case 'version':
		case 'V':
		case 'v':
			display_version();
			exit(0);
		case 'help':
		case 'H':
		case 'h':
			display_help();
			exit(0);
		default:
			print "ERROR: Invalid Argument: ($arg)\n\n";
			display_help();
			exit(1);
		}

		if (!$allow_multi && isset($value) && is_array($value)) {
			print "ERROR: Multiple values specified for non-multi argument: ($arg)\n\n";
			exit(1);
		}
	}

	if ($listGraphTemplates) {
		/* is a Host Template Id is given, print the related Graph Templates */
		if ($hostTemplateId > 0) {
			$graphTemplates = getGraphTemplatesByHostTemplate($hostTemplateId);
			if (!cacti_sizeof($graphTemplates)) {
				print "ERROR: You must supply a valid --host-template-id before you can list its graph templates\n";
				print "Try --list-graph-template-id --host-template-id=[ID]\n";
				exit(1);
			}
		}

		displayGraphTemplates($graphTemplates, $quietMode);

		exit(0);
	}

	if ($listInputFields) {
		if ($template_id > 0) {
			$input_fields = getInputFields($template_id, $quietMode);
			displayInputFields($input_fields, $quietMode);
		} else {
			print "ERROR: You must supply an graph-template-id before you can list its input fields\n";
			print "Try --graph-template-id=[ID] --list-input-fields\n";
			exit(1);
		}

		exit(0);
	}

	if ($listHosts) {
		displayHosts($hosts, $quietMode);
		exit(0);
	}

	/* get the existing snmp queries */
	$snmpQueries = getSNMPQueries();

	if ($listSNMPQueries) {
		displaySNMPQueries($snmpQueries, $quietMode);
		exit(0);
	}

	/* Some sanity checking... */
	if ($dsGraph['snmpQueryId'] != '') {
		if (!isset($snmpQueries[$dsGraph['snmpQueryId']])) {
			print 'ERROR: Unknown snmp-query-id (' . $dsGraph['snmpQueryId'] . ")\n";
			print "Try --list-snmp-queries\n";
			exit(1);
		}

		/* get the snmp query types for comparison */
		$snmp_query_types = getSNMPQueryTypes($dsGraph['snmpQueryId']);

		if ($listQueryTypes) {
			displayQueryTypes($snmp_query_types, $quietMode);
			exit(0);
		}

		if ($dsGraph['snmpQueryType'] != '') {
			if (!isset($snmp_query_types[$dsGraph['snmpQueryType']])) {
				print 'ERROR: Unknown snmp-query-type-id (' . $dsGraph['snmpQueryType'] . ")\n";
				print 'Try --snmp-query-id=' . $dsGraph['snmpQueryId'] . " --list-query-types\n";
				exit(1);
			}
		}

		if (!($listHosts ||			# you really want to create a new graph
			$listSNMPFields || 		# add this check to avoid reindexing on any list option
			$listSNMPValues ||
			$listQueryTypes ||
			$listSNMPQueries ||
			$listInputFields)) {

			/* if data query is not yet associated,
			 * add it and run it once to get the cache filled */

			/* is this data query already associated (independent of the reindex method)? */
			$exists_already = db_fetch_cell_prepared('SELECT COUNT(host_id)
				FROM host_snmp_query
				WHERE host_id = ?
				AND snmp_query_id = ?',
				array($host_id, $dsGraph['snmpQueryId']));

			if ((isset($exists_already)) &&
				($exists_already > 0)) {
				/* yes: do nothing, everything's fine */
			} else {
				db_execute_prepared('REPLACE INTO host_snmp_query
					(host_id, snmp_query_id, reindex_method)
					VALUES (?, ?, ?)',
					array($host_id, $dsGraph['snmpQueryId'], $dsGraph['reindex_method']));

				/* recache snmp data, this is time consuming,
				 * but should happen only once even if multiple graphs
				 * are added for the same data query
				 * because we checked above, if dq was already associated */
				run_data_query($host_id, $dsGraph['snmpQueryId']);
			}
		}
	}

	/* Verify the host's existence */
	if (!isset($hosts[$host_id]) || $host_id == 0) {
		print "ERROR: Unknown Host ID ($host_id)\n";
		print "Try --list-hosts\n";
		exit(1);
	}

	/* process the snmp fields */
	if ($graph_type == 'dq' || $listSNMPFields || $listSNMPValues) {
		$snmpFields = getSNMPFields($host_id, $dsGraph['snmpQueryId']);

		if ($listSNMPFields) {
			displaySNMPFields($snmpFields, $host_id, $quietMode);
			exit(0);
		}

		$snmpValues = array();

		/* More sanity checking */
		/* Testing SnmpValues and snmpFields args */
		if ($dsGraph['snmpValue'] and $dsGraph['snmpValueRegex'] ) {
			print "ERROR: You can't supply --snmp-value and --snmp-value-regex at the same time\n";
			exit(1);
		}

		$nbSnmpFields      = cacti_sizeof($dsGraph['snmpField']);
		$nbSnmpValues      = cacti_sizeof($dsGraph['snmpValue']);
		$nbSnmpValuesRegex = cacti_sizeof($dsGraph['snmpValueRegex']);

		if ($nbSnmpValues) {
			if ($nbSnmpFields != $nbSnmpValues) {
				print "ERROR: number of --snmp-field and --snmp-value does not match\n";
				exit(1);
			}
		} elseif ($nbSnmpValuesRegex) {
			if ($nbSnmpFields != $nbSnmpValuesRegex) {
				print "ERROR: number of --snmp-field ($nbSnmpFields) and --snmp-value-regex ($nbSnmpValuesRegex) does not match\n";
				exit(1);
			}
		} elseif (!$listSNMPValues) {
			print "ERROR: You must supply a --snmp-value or --snmp-value-regex option with --snmp-field\n";
			exit(1);
		}

		$index_filter = 0;
		foreach($dsGraph['snmpField'] as $snmpField) {
			if ($snmpField != '') {
				if (!isset($snmpFields[$snmpField] )) {
					print 'ERROR: Unknown snmp-field ' . $dsGraph['snmpField'] . " for host $host_id\n";
					print "Try --list-snmp-fields\n";
					exit(1);
				}
			}

			$snmpValues = getSNMPValues($host_id, $snmpField, $dsGraph['snmpQueryId']);

			$snmpValue      = '';
			$snmpValueRegex = '';

			if ($dsGraph['snmpValue']) {
				$snmpValue 	= $dsGraph['snmpValue'][$index_filter];
			} else {
				$snmpValueRegex = $dsGraph['snmpValueRegex'][$index_filter];
			}

			if ($snmpValue) {
				$ok = false;

				foreach ($snmpValues as $snmpValueKnown => $snmpValueSet) {
					if ($snmpValue == $snmpValueKnown) {
						$ok = true;
						break;
					}
				}

				if (!$ok) {
					print "ERROR: Unknown snmp-value for field $snmpField - $snmpValue\n";
					print "Try --snmp-field=$snmpField --list-snmp-values\n";
					exit(1);
				}
			} elseif ($snmpValueRegex) {
				$ok = false;

				foreach ($snmpValues as $snmpValueKnown => $snmpValueSet) {
					if (preg_match("/$snmpValueRegex/i", $snmpValueKnown)) {
						$ok = true;
						break;
					}
				}

				if (!$ok) {
					print "ERROR: Unknown snmp-value for field $snmpField - $snmpValue\n";
					print "Try --snmp-field=$snmpField --list-snmp-values\n";
					exit(1);
				}
			}

			$index_filter++;
		}

		if ($listSNMPValues)  {
			if (!$dsGraph['snmpField']) {
				print "ERROR: You must supply an snmp-field before you can list its values\n";
				print "Try --list-snmp-fields\n";
				exit(1);
			}

			if (cacti_sizeof($dsGraph['snmpField'])) {
				foreach($dsGraph['snmpField'] as $snmpField) {
					if ($snmpField = "") {
						print "ERROR: You must supply a valid snmp-field before you can list its values\n";
						print "Try --list-snmp-fields\n";
						exit(1);
					}

					displaySNMPValues($snmpValues, $host_id, $snmpField, $quietMode);
				}
			}

			exit(0);
		}
	}

	if (!isset($graphTemplates[$template_id])) {
		print 'ERROR: Unknown graph-template-id (' . $template_id . ")\n";
		print "Try --list-graph-templates\n";
		exit(1);
	}

	if ((!isset($template_id)) || (!isset($host_id))) {
		print "ERROR: Must have at least a host-id and a graph-template-id\n\n";
		display_help();
		exit(1);
	}

	if ($cgInputFields != '') {
		$fields = explode(' ', $cgInputFields);
		if ($template_id > 0) {
			$input_fields = getInputFields($template_id, $quietMode);
		}

		if (cacti_sizeof($fields)) {
			foreach ($fields as $option) {
				$data_template_id = 0;
				$option_value = explode('=', $option);

				if (substr_count($option_value[0], ':')) {
					$compound = explode(':', $option_value[0]);
					$data_template_id = $compound[0];
					$field_name       = $compound[1];
				} else {
					$field_name       = $option_value[0];
				}

				/* check for the input fields existence */
				$field_found = false;
				if (cacti_sizeof($input_fields)) {
					foreach ($input_fields as $key => $row) {
						if (substr_count($key, $field_name)) {
							if ($data_template_id == 0) {
								$data_template_id = $row['data_template_id'];
							}

							$field_found = true;

							break;
						}
					}
				}

				if (!$field_found) {
					print 'ERROR: Unknown input-field (' . $field_name . ")\n";
					print "Try --list-input-fields\n";
					exit(1);
				}

				$value = $option_value[1];

				$values['cg'][$template_id]['custom_data'][$data_template_id][$input_fields[$data_template_id . ':' . $field_name]['data_input_field_id']] = $value;
			}
		}
	}

	$returnArray = array();

	if ($graph_type == 'cg') {
		$existsAlready = db_fetch_cell_prepared('SELECT gl.id
			FROM graph_local AS gl
			INNER JOIN graph_templates AS gt
			ON gt.id = gl.graph_template_id
			WHERE graph_template_id = ?
			AND host_id = ?
			AND multiple = \'\'',
			array($template_id, $host_id));

		if ((isset($existsAlready)) &&
			($existsAlready > 0) &&
			(!$force)) {
			$dataSourceId  = db_fetch_cell_prepared('SELECT dtr.local_data_id
				FROM graph_templates_item AS gti
				INNER JOIN data_template_rrd AS dtr
				ON gti.task_item_id = dtr.id
				WHERE gti.local_graph_id = ?
				LIMIT 1',
				array($existsAlready));

			print "NOTE: Not Adding Graph - this graph already exists - graph-id: ($existsAlready) - data-source-id: ($dataSourceId)\n";
			exit(1);
		} else {
			$returnArray = create_complete_graph_from_template($template_id, $host_id, null, $values['cg']);
			$dataSourceId = '';
		}

		if ($graphTitle != '') {
			if (isset($returnArray['local_graph_id'])) {
				db_execute_prepared('UPDATE graph_templates_graph
					SET title = ?
					WHERE local_graph_id = ?',
					array($graphTitle, $returnArray['local_graph_id']));

				update_graph_title_cache($returnArray['local_graph_id']);
			}
		}

		if (is_array($returnArray) && cacti_sizeof($returnArray)) {
			if (cacti_sizeof($returnArray['local_data_id'])) {
				foreach($returnArray['local_data_id'] as $item) {
					push_out_host($host_id, $item);

					if ($dataSourceId != '') {
						$dataSourceId .= ', ' . $item;
					} else {
						$dataSourceId = $item;
					}
				}
			}

			/* add this graph template to the list of associated graph templates for this host */
			db_execute_prepared('REPLACE INTO host_graph
				(host_id, graph_template_id) VALUES
				(?, ?)',
				array($host_id , $template_id));

			print 'Graph Added - Graph[' . $returnArray['local_graph_id'] . "] - DS[$dataSourceId]\n";
		} else {
			print "Graph Not Added due to whitelist check failure.\n";
		}
	} elseif ($graph_type == 'ds') {
		if (($dsGraph['snmpQueryId'] == '') || ($dsGraph['snmpQueryType'] == '') || (cacti_sizeof($dsGraph['snmpField']) == 0) ) {
			print "ERROR: For graph-type of 'ds' you must supply more options\n";
			display_help();
			exit(1);
		}

		$snmp_query_array = array();
		$snmp_query_array['snmp_query_id']       = $dsGraph['snmpQueryId'];
		$snmp_query_array['snmp_index_on']       = get_best_data_query_index_type($host_id, $dsGraph['snmpQueryId']);
		$snmp_query_array['snmp_query_graph_id'] = $dsGraph['snmpQueryType'];

		$req = 'SELECT distinct snmp_index
			FROM host_snmp_cache
			WHERE host_id=' . $host_id . '
			AND snmp_query_id=' . $dsGraph['snmpQueryId'];

		$index_snmp_filter = 0;
		if (cacti_sizeof($dsGraph['snmpField'])) {
			foreach ($dsGraph['snmpField'] as $snmpField) {
				$req  .= ' AND snmp_index IN (
					SELECT DISTINCT snmp_index FROM host_snmp_cache WHERE host_id=' . $host_id . ' AND field_name = ' . db_qstr($snmpField);

				if (isset($dsGraph['snmpValue'][$index_snmp_filter])) {
					$req .= ' AND field_value = ' . db_qstr($dsGraph['snmpValue'][$index_snmp_filter]). ')';
				} elseif (isset($dsGraph['snmpValueRegex'][$index_snmp_filter])) {
					$req .= ' AND field_value REGEXP "' . addslashes($dsGraph['snmpValueRegex'][$index_snmp_filter]) . '")';
				}

				$index_snmp_filter++;
			}
		}

		$snmp_indexes = db_fetch_assoc($req);

		if (cacti_sizeof($snmp_indexes)) {
			foreach ($snmp_indexes as $snmp_index) {
				$snmp_query_array['snmp_index'] = $snmp_index['snmp_index'];

				$existsAlready = db_fetch_cell_prepared('SELECT gl.id
					FROM graph_local AS gl
					INNER JOIN graph_templates AS gt
					ON gt.id = gl.graph_template_id
					WHERE graph_template_id = ?
					AND host_id = ?
					AND snmp_query_id = ?
					AND snmp_index = ?
					AND multiple = \'\'',
					array($template_id, $host_id, $dsGraph['snmpQueryId'], $snmp_query_array['snmp_index']));

				if (isset($existsAlready) && $existsAlready > 0) {
					if ($graphTitle != '') {
						db_execute_prepared('UPDATE graph_templates_graph
							SET title = ?
							WHERE local_graph_id = ?',
							array($graphTitle, $existsAlready));

						update_graph_title_cache($existsAlready);
					}

					$dataSourceId = db_fetch_cell_prepared('SELECT dtr.local_data_id
						FROM graph_templates_item AS gti
						INNER JOIN data_template_rrd AS dtr
						ON gti.task_item_id = dtr.id
						WHERE gti.local_graph_id = ?
						LIMIT 1',
						array($existsAlready));

					print "NOTE: Not Adding Graph - this graph already exists - graph-id: ($existsAlready) - data-source-id: ($dataSourceId)\n";

					continue;
				}

				$isempty = array(); /* Suggested Values are not been implemented */

				$returnArray = create_complete_graph_from_template($template_id, $host_id, $snmp_query_array, $isempty);

				if ($returnArray !== false) {
					if ($graphTitle != '') {
						db_execute_prepared('UPDATE graph_templates_graph
							SET title = ?
							WHERE local_graph_id = ?',
							array($graphTitle, $returnArray['local_graph_id']));

						update_graph_title_cache($returnArray['local_graph_id']);
					}

					$dataSourceId = db_fetch_cell_prepared('SELECT dtr.local_data_id
						FROM graph_templates_item AS gti
						INNER JOIN data_template_rrd AS dtr
						ON gti.task_item_id = dtr.id
						WHERE gti.local_graph_id = ?
						LIMIT 1',
						array($returnArray['local_graph_id']));

					foreach($returnArray['local_data_id'] as $item) {
						push_out_host($host_id, $item);

						if ($dataSourceId != '') {
							$dataSourceId .= ', ' . $item;
						} else {
							$dataSourceId = $item;
						}
					}

					print 'Graph Added - Graph[' . $returnArray['local_graph_id'] . "] - DS[$dataSourceId]\n";
				} else {
					print "Graph Not Added due to whitelist check failure.\n";
				}
			}
		} else {
			$err_msg = 'ERROR: Could not find one of more snmp-fields ' . implode(',', $dsGraph['snmpField']) . ' with values (';

			if (cacti_sizeof($dsGraph['snmpValue'])) {
				$err_msg .= implode(',',$dsGraph['snmpValue']);
			} else {
				$err_msg .= implode(',',$dsGraph['snmpValueRegex']);
			}
			$err_msg .= ') for host-id ' . $host_id . ' (' . $hosts[$host_id]['hostname'] . ")\n";

			print $err_msg;
			print 'Try --host-id=' . $host_id . " --list-snmp-fields\n";
			exit(1);
		}
	} else {
		print "ERROR: Graph Types must be either 'cg' or 'ds'\n";
		exit(1);
	}

	exit(0);
} else {
	display_help();
	exit(1);
}

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Add Graphs Utility, Version $version, " . COPYRIGHT_YEARS . "\n";
}

function display_help() {
	display_version();

	print "\nusage: add_graphs.php --graph-type=[cg|ds] --graph-template-id=[ID]\n";
	print "    --host-id=[ID] [--graph-title=title] [graph options] [--force] [--quiet]\n\n";
	print "Cacti utility for creating graphs via a command line interface.  This utility can\n";
	print "create both Data Query (ds) type Graphs as well as Graph Template (cg) type graphs.\n\n";
	print "For Non Data Query (cg) Graphs:\n";
	print "    [--input-fields=\"[data-template-id:]field-name=value ...\"] [--force]\n\n";
	print "    --input-fields  If your data template allows for custom input data, you may specify that\n";
	print "                    here.  The data template id is optional and applies where two input fields\n";
	print "                    have the same name.\n";
	print "    --force         If you set this flag, then new cg graphs will be created, even though they\n";
	print "                    may already exist\n\n";
	print "For Data Query (ds) Graphs:\n";
	print "    --snmp-query-id=[ID] --snmp-query-type-id=[ID] --snmp-field=[SNMP Field] \n";
	print "                         --snmp-value=[SNMP Value] | --snmp-value-regex=[REGEX]\n";
	print "    [--graph-title=S]       Defaults to what ever is in the Graph Template/Data Template.\n";
	print "    [--reindex-method=N]    The reindex method to be used for that data query.\n";
	print "                            NOTE: If Data Query is already associated, the reindex method will NOT be changed.\n\n";
	print "    Valid --reindex-methods include\n";
	print "        0|None   = No reindexing\n";
	print "        1|Uptime = Uptime goes Backwards (Default)\n";
	print "        2|Index  = Index Count Changed\n";
	print "        3|Fields = Verify all Fields\n\n";
	print "    NOTE: You may supply multiples of the --snmp-field and --snmp-value | --snmp-value-regex arguments.\n\n";
	print "List Options:\n";
	print "    --list-hosts\n";
	print "    --list-graph-templates [--host-template-id=[ID]]\n";
	print "    --list-input-fields --graph-template-id=[ID]\n";
	print "    --list-snmp-queries\n";
	print "    --list-query-types  --snmp-query-id [ID]\n";
	print "    --list-snmp-fields  --host-id=[ID] [--snmp-query-id=[ID]]\n";
	print "    --list-snmp-values  --host-id=[ID] [--snmp-query-id=[ID]] --snmp-field=[Field]\n\n";
}
