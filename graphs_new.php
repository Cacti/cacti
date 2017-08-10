<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
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

include('./include/auth.php');
include_once('./lib/data_query.php');
include_once('./lib/api_data_source.php');
include_once('./lib/utility.php');
include_once('./lib/sort.php');
include_once('./lib/html_form_template.php');
include_once('./lib/template.php');

/* set default action */
set_default_action();
switch (get_request_var('action')) {
	case 'save':
		form_save();

		break;
	case 'query_reload':
		host_reload_query();

		header('Location: graphs_new.php?host_id=' . get_request_var('host_id') . '&header=false');
		break;
	case 'ajax_hosts':
		get_allowed_ajax_hosts(false, false);

		break;
	case 'ajax_hosts_noany':
		get_allowed_ajax_hosts(false);

		break;
	case 'ajax_save':
		save_default_query_option();

		break;
	case 'ajax_save_filter':
		save_user_filter();

		break;
	default:
		top_header();
		graphs();
		bottom_footer();

		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function save_default_query_option() {
	$data_query = get_filter_request_var('query');
	$default    = get_filter_request_var('item');

	set_user_setting('default_sgg_' . $data_query, $default);

	print __('Default Settings Saved') . "\n";
}

function save_user_filter() {
	$rows = get_filter_request_var('rows');

	if ($rows == -1) {
		$rows = read_config_option('num_rows_table');
	}

	$graph_type = get_filter_request_var('graph_type');

	set_user_setting('num_rows_table', $rows);
	set_user_setting('graph_type', $graph_type);
}

function form_save() {
	if (isset_request_var('save_component_graph')) {
		/* summarize the 'create graph from host template/snmp index' stuff into an array */
		foreach ($_POST as $var => $val) {
			if (preg_match('/^cg_(\d+)$/', $var, $matches)) {
				$selected_graphs['cg']{$matches[1]}{$matches[1]} = true;
			} elseif (preg_match('/^cg_g$/', $var)) {
				if (get_nfilter_request_var('cg_g') > 0) {
					$selected_graphs['cg']{get_nfilter_request_var('cg_g')}{get_nfilter_request_var('cg_g')} = true;
				}
			} elseif (preg_match('/^sg_(\d+)_([a-f0-9]{32})$/', $var, $matches)) {
				$selected_graphs['sg']{$matches[1]}{get_nfilter_request_var('sgg_' . $matches[1])}{$matches[2]} = true;
			}
		}

		if (!isset_request_var('host_id')) {
			$host_id = 0;
		} else {
			$host_id = get_filter_request_var('host_id');
		}

		if (!isset_request_var('host_template_id')) {
			$host_template_id = 0;
		} else {
			$host_template_id = get_filter_request_var('host_template_id');
		}

		if (isset($selected_graphs)) {
			host_new_graphs($host_id, $host_template_id, $selected_graphs);
			exit;
		}

		header('Location: graphs_new.php?host_id=' . $host_id . '&header=false');
	}

	if (isset_request_var('save_component_new_graphs')) {
		host_new_graphs_save(get_filter_request_var('host_id'));

		header('Location: graphs_new.php?host_id=' . get_filter_request_var('host_id') . '&header=false');
	}
}

/* -------------------
    Data Query Functions
   ------------------- */

function host_reload_query() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('host_id');
	/* ==================================================== */

	run_data_query(get_request_var('host_id'), get_request_var('id'));
}

/* -------------------
    New Graph Functions
   ------------------- */

function host_new_graphs_save($host_id) {
	$selected_graphs_array = unserialize(stripslashes(get_nfilter_request_var('selected_graphs_array')));

	/* form an array that contains all of the data on the previous form */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^g_(\d+)_(\d+)_(\w+)/', $var, $matches)) { // 1: snmp_query_id, 2: graph_template_id, 3: field_name
			if (empty($matches[1])) { // this is a new graph from template field
				$values['cg']{$matches[2]}['graph_template']{$matches[3]} = $val;
			} else { // this is a data query field
				$values['sg']{$matches[1]}{$matches[2]}['graph_template']{$matches[3]} = $val;
			}
		} elseif (preg_match('/^gi_(\d+)_(\d+)_(\d+)_(\w+)/', $var, $matches)) { // 1: snmp_query_id, 2: graph_template_id, 3: graph_template_input_id, 4:field_name
			/* ================= input validation ================= */
			input_validate_input_number($matches[3]);
			/* ==================================================== */

			/* we need to find out which graph items will be affected by saving this particular item */
			$item_list = db_fetch_assoc_prepared('SELECT
				graph_template_item_id
				FROM graph_template_input_defs
				WHERE graph_template_input_id = ?', array($matches[3]));

			/* loop through each item affected and update column data */
			if (sizeof($item_list)) {
				foreach ($item_list as $item) {
					if (empty($matches[1])) { // this is a new graph from template field
						$values['cg']{$matches[2]}['graph_template_item']{$item['graph_template_item_id']}{$matches[4]} = $val;
					} else { // this is a data query field
						$values['sg']{$matches[1]}{$matches[2]}['graph_template_item']{$item['graph_template_item_id']}{$matches[4]} = $val;
					}
				}
			}
		} elseif (preg_match('/^d_(\d+)_(\d+)_(\d+)_(\w+)/', $var, $matches)) { // 1: snmp_query_id, 2: graph_template_id, 3: data_template_id, 4:field_nam
			if (empty($matches[1])) { // this is a new graph from template field
				$values['cg']{$matches[2]}['data_template']{$matches[3]}{$matches[4]} = $val;
			} else { // this is a data query field
				$values['sg']{$matches[1]}{$matches[2]}['data_template']{$matches[3]}{$matches[4]} = $val;
			}
		} elseif (preg_match('/^c_(\d+)_(\d+)_(\d+)_(\d+)/', $var, $matches)) { // 1: snmp_query_id, 2: graph_template_id, 3: data_template_id, 4:data_input_field_id
			if (empty($matches[1])) { // this is a new graph from template field
				$values['cg']{$matches[2]}['custom_data']{$matches[3]}{$matches[4]} = $val;
			} else { // this is a data query field
				$values['sg']{$matches[1]}{$matches[2]}['custom_data']{$matches[3]}{$matches[4]} = $val;
			}
		} elseif (preg_match('/^di_(\d+)_(\d+)_(\d+)_(\d+)_(\w+)/', $var, $matches)) { // 1: snmp_query_id, 2: graph_template_id, 3: data_template_id, 4:local_data_template_rrd_id, 5:field_name
			if (empty($matches[1])) { // this is a new graph from template field
				$values['cg']{$matches[2]}['data_template_item']{$matches[4]}{$matches[5]} = $val;
			} else { // this is a data query field
				$values['sg']{$matches[1]}{$matches[2]}['data_template_item']{$matches[4]}{$matches[5]} = $val;
			}
		}
	}

	debug_log_clear('new_graphs');

	foreach ($selected_graphs_array as $form_type => $form_array) {
		$current_form_type = $form_type;

		foreach ($form_array as $form_id1 => $form_array2) {
			/* enumerate information from the arrays stored in post variables */

			/* ================= input validation ================= */
			input_validate_input_number($form_id1);
			/* ==================================================== */

			if ($form_type == 'cg') {
				$graph_template_id = $form_id1;
			} elseif ($form_type == 'sg') {
				foreach ($form_array2 as $form_id2 => $form_array3) {
					/* ================= input validation ================= */
					input_validate_input_number($form_id2);
					/* ==================================================== */

					$snmp_index_array = $form_array3;

					$snmp_query_array['snmp_query_id'] = $form_id1;
					$snmp_query_array['snmp_index_on'] = get_best_data_query_index_type($host_id, $form_id1);
					$snmp_query_array['snmp_query_graph_id'] = $form_id2;
				}

				$graph_template_id = db_fetch_cell_prepared('SELECT graph_template_id
					FROM snmp_query_graph
					WHERE id = ?',
					array($snmp_query_array['snmp_query_graph_id']));
			}

			if ($current_form_type == 'cg') {
				$snmp_query_array = array();

				$return_array = create_complete_graph_from_template($graph_template_id, $host_id, $snmp_query_array, $values['cg']);

				debug_log_insert('new_graphs', __('Created graph: %s', get_graph_title($return_array['local_graph_id'])));

				/* lastly push host-specific information to our data sources */
				if (sizeof($return_array['local_data_id'])) { # we expect at least one data source associated
					foreach($return_array['local_data_id'] as $item) {
						push_out_host($host_id, $item);
					}
				} else {
					debug_log_insert('new_graphs', __('ERROR: no Data Source associated. Check Template'));
				}
			} elseif ($current_form_type == 'sg') {
				foreach ($snmp_index_array as $snmp_index => $true) {
					$snmp_query_array['snmp_index'] = decode_data_query_index($snmp_index, $snmp_query_array['snmp_query_id'], $host_id);

					$return_array = create_complete_graph_from_template($graph_template_id, $host_id, $snmp_query_array, $values['sg']{$snmp_query_array['snmp_query_id']});

					debug_log_insert('new_graphs', __('Created graph: %s', get_graph_title($return_array['local_graph_id'])));

					/* lastly push host-specific information to our data sources */
					if (sizeof($return_array['local_data_id'])) { # we expect at least one data source associated
						foreach($return_array['local_data_id'] as $item) {
							push_out_host($host_id, $item);
						}
					} else {
						debug_log_insert('new_graphs', __('ERROR: no Data Source associated. Check Template'));
					}
				}
			}
		}
	}
}

function host_new_graphs($host_id, $host_template_id, $selected_graphs_array) {
	/* we use object buffering on this page to allow redirection to another page if no
	fields are actually drawn */
	ob_start();

	top_header();

	form_start('graphs_new.php');

	$snmp_query_id = 0;
	$num_output_fields = array();

	foreach ($selected_graphs_array as $form_type => $form_array) {
		foreach ($form_array as $form_id1 => $form_array2) {
			/* ================= input validation ================= */
			input_validate_input_number($form_id1);
			/* ==================================================== */

			if ($form_type == 'cg') {
				$graph_template_id = $form_id1;

				html_start_box(__("Create Graph from %s", db_fetch_cell_prepared('SELECT name FROM graph_templates WHERE id = ?', array($graph_template_id))), '100%', '', '3', 'center', '');
			} elseif ($form_type == 'sg') {
				foreach ($form_array2 as $form_id2 => $form_array3) {
					/* ================= input validation ================= */
					input_validate_input_number($snmp_query_id);
					input_validate_input_number($form_id2);
					/* ==================================================== */

					$snmp_query_id = $form_id1;
					$snmp_query_graph_id = $form_id2;
					$num_graphs = sizeof($form_array3);

					$snmp_query = db_fetch_row_prepared('SELECT snmp_query.name
						FROM snmp_query
						WHERE snmp_query.id = ?',
						array($snmp_query_id));

					$graph_template_id = db_fetch_cell_prepared('SELECT graph_template_id FROM snmp_query_graph WHERE id = ?', array($snmp_query_graph_id));
				}

				if ($num_graphs > 1) {
					$header = __('Created %s Graphs from %s', $num_graphs, htmlspecialchars(db_fetch_cell_prepared('SELECT name FROM snmp_query WHERE id = ?', array($snmp_query_id))));
				} else {
					$header = __('Created 1 Graph from %s', htmlspecialchars(db_fetch_cell_prepared('SELECT name FROM snmp_query WHERE id = ?', array($snmp_query_id))));
				}

				/* DRAW: Data Query */
				html_start_box($header, '100%', '', '3', 'center', '');
			}

			/* ================= input validation ================= */
			input_validate_input_number($graph_template_id);
			/* ==================================================== */

			$data_templates = db_fetch_assoc_prepared('SELECT
				data_template.name AS data_template_name,
				data_template_rrd.data_source_name,
				data_template_data.*
				FROM (data_template, data_template_rrd, data_template_data, graph_templates_item)
				WHERE graph_templates_item.task_item_id = data_template_rrd.id
				AND data_template_rrd.data_template_id = data_template.id
				AND data_template_data.data_template_id = data_template.id
				AND data_template_rrd.local_data_id = 0
				AND data_template_data.local_data_id = 0
				AND graph_templates_item.local_graph_id = 0
				AND graph_templates_item.graph_template_id = ?
				GROUP BY data_template.id
				ORDER BY data_template.name',
				array($graph_template_id));

			$graph_template = db_fetch_row_prepared('SELECT
				graph_templates.name AS graph_template_name,
				graph_templates_graph.*
				FROM (graph_templates, graph_templates_graph)
				WHERE graph_templates.id = graph_templates_graph.graph_template_id
				AND graph_templates.id = ?
				AND graph_templates_graph.local_graph_id = 0',
				array($graph_template_id));

			$graph_template_name = db_fetch_cell_prepared('SELECT name
				FROM graph_templates
				WHERE id = ?',
				array($graph_template_id));

			array_push($num_output_fields, draw_nontemplated_fields_graph($graph_template_id, $graph_template, "g_$snmp_query_id" . '_' . $graph_template_id . '_|field|', __('Graph [Template: %s]', htmlspecialchars($graph_template['graph_template_name'])), false, false, (isset($snmp_query_graph_id) ? $snmp_query_graph_id : 0)));
			array_push($num_output_fields, draw_nontemplated_fields_graph_item($graph_template_id, 0, 'gi_' . $snmp_query_id . '_' . $graph_template_id . '_|id|_|field|', __('Graph Items [Template: %s]', htmlspecialchars($graph_template_name)), false));

			/* DRAW: Data Sources */
			if (sizeof($data_templates)) {
				foreach ($data_templates as $data_template) {
					array_push($num_output_fields, draw_nontemplated_fields_data_source($data_template['data_template_id'], 0, $data_template, 'd_' . $snmp_query_id . '_' . $graph_template_id . '_' . $data_template['data_template_id'] . '_|field|', __('Data Source [Template: %s]', htmlspecialchars($data_template['data_template_name'])), false, false, (isset($snmp_query_graph_id) ? $snmp_query_graph_id : 0)));

					$data_template_items = db_fetch_assoc_prepared('SELECT
						data_template_rrd.*
						FROM data_template_rrd
						WHERE data_template_rrd.data_template_id = ?
						AND local_data_id = 0',
						array($data_template['data_template_id']));

					array_push($num_output_fields, draw_nontemplated_fields_data_source_item($data_template['data_template_id'], $data_template_items, 'di_' . $snmp_query_id . '_' . $graph_template_id . '_' . $data_template['data_template_id'] . '_|id|_|field|', '', false, false, false, (isset($snmp_query_graph_id) ? $snmp_query_graph_id : 0)));
					array_push($num_output_fields, draw_nontemplated_fields_custom_data($data_template['id'], 'c_' . $snmp_query_id . '_' . $graph_template_id . '_' . $data_template['data_template_id'] . '_|id|', __('Custom Data [Template: %s]', htmlspecialchars($data_template['data_template_name'])), false, false, $snmp_query_id));
				}
			}

			html_end_box(false);
		}
	}

	/* no fields were actually drawn on the form; just save without prompting the user */
	if (array_sum($num_output_fields) == 0) {
		ob_end_clean();

		/* since the user didn't actually click "Create" to POST the data; we have to
		pretend like they did here */
		set_request_var('save_component_new_graphs', '1');
		set_request_var('selected_graphs_array', serialize($selected_graphs_array));

		host_new_graphs_save($host_id);

		header('Location: graphs_new.php?host_id=' . $host_id . '&header=false');
		exit;
	}

	/* flush the current output buffer to the browser */
	ob_end_flush();

	form_hidden_box('host_template_id', $host_template_id, '0');
	form_hidden_box('host_id', $host_id, '0');
	form_hidden_box('save_component_new_graphs', '1', '');
	print "<input type='hidden' name='selected_graphs_array' value='" . serialize($selected_graphs_array) . "'>\n";

	if (!substr_count($_SERVER['HTTP_REFERER'], 'graphs_new')) {
		set_request_var('returnto', basename($_SERVER['HTTP_REFERER']));
	}
	load_current_session_value('returnto', 'sess_grn_returnto', '');

	form_save_button(get_nfilter_request_var('returnto'));

	bottom_footer();
}

/* -------------------
    Graph Functions
   ------------------- */

function graphs() {
	global $config, $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'host_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => db_fetch_cell('SELECT id FROM host ORDER BY description, hostname LIMIT 1')
			),
		'graph_type' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_user_setting('graph_type', read_config_option('default_graphs_new_dropdown'), true)
			)
	);

	validate_store_request_vars($filters, 'sess_grn');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_user_setting('num_rows_table', read_config_option('num_rows_table'), true);
	} else {
		$rows = get_request_var('rows');
	}

	if (!isempty_request_var('host_id')) {
		$host   = db_fetch_row_prepared('SELECT id, description, hostname, host_template_id FROM host WHERE id = ?', array(get_request_var('host_id')));

		if (sizeof($host)) {
			$header =  __('New Graphs for [ %s ]', htmlspecialchars($host['description']) . ' (' . htmlspecialchars($host['hostname']) . ') ' .
			(!empty($host['host_template_id']) ? htmlspecialchars(db_fetch_cell_prepared('SELECT name FROM host_template WHERE id = ?', array($host['host_template_id']))):''));
		} else {
			$header =  __('New Graphs for [ All Devices ]');
			$host['id'] = -1;
		}
	} else {
		$host['id'] = 0;
		$header = __('New Graphs for None Host Type');
	}

	html_start_box($header, '100%', '', '3', 'center', '');

	print '<tr><td class="even">';

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL  = '?graph_type=' + $('#graph_type').val();
		strURL += '&host_id=' + $('#host_id').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		loadPageNoHeader('graphs_new.php?clear=true&header=false');
	}

	function saveFilter() {
		strURL = 'graphs_new.php?action=ajax_save_filter' +
			'&rows='     + $('#rows').val() +
			'&graph_type=' + $('#graph_type').val();

		$.get(strURL, function(data) {
			$('#text').show().text('<?php print __('Filter Settings Saved');?>').fadeOut(2000);
		});
	}

	$(function() {
		$('[id^="reload"]').click(function(data) {
			$(this).addClass('fa-spin');
			loadPageNoHeader('graphs_new.php?action=query_reload&id='+$(this).attr('data-id')+'&host_id='+$('#host_id').val());
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#save').click(function() {
			saveFilter();
		});

		$('#graphs_new').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<form id='graphs_new' action='graphs_new.php'>
		<table class='cactiTable'>
			<tr><td style='width:70%;'>
				<table class='filterTable'>
					<tr>
						<?php print html_host_filter(get_request_var('host_id'), 'applyFilter', '', true, true);?>
						<td>
							<?php print __('Graph Types');?>
						</td>
						<td>
							<select id='graph_type' name='graph_type' onChange='applyFilter()'>
								<option value='-2'<?php if (get_request_var('graph_type') == '-2') {?> selected<?php }?>><?php print __('All');?></option>
								<option value='-1'<?php if (get_request_var('graph_type') == '-1') {?> selected<?php }?>><?php print __('Graph Template Based');?></option>
								<?php

								$snmp_queries = db_fetch_assoc_prepared('SELECT
									snmp_query.id,
									snmp_query.name
									FROM (snmp_query, host_snmp_query)
									WHERE host_snmp_query.snmp_query_id = snmp_query.id
									AND host_snmp_query.host_id = ?
									ORDER BY snmp_query.name',
									array($host['id']));

								if (sizeof($snmp_queries) > 0) {
									foreach ($snmp_queries as $query) {
										print "<option value='" . $query['id'] . "'"; if (get_request_var('graph_type') == $query['id']) { print ' selected'; } print '>' . $query['name'] . "</option>\n";
									}
								}
								?>
							</select>
						</td>
						<td>
							<span>
								<input id='refresh' type='submit' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
								<input id='clear' type='button' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
								<input id='save' type='button' value='<?php print __esc('Save');?>' title='<?php print __esc('Save Filters');?>'>
							</span>
						</td>
						<td id='text'></td>
					</tr>
				</table>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search');?>
						</td>
						<td>
							<input id='filter' type='text' name='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
						</td>
						<td>
							<?php print __('Rows');?>
						</td>
						<td>
							<select id='rows' name='rows' onChange='applyFilter()'>
								<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
								<?php
								if (sizeof($item_rows) > 0) {
									foreach ($item_rows as $key => $value) {
										print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
									}
								}
								?>
							</select>
						</td>
					</tr>
				</table>
			</td>
			<td class='textInfo right'>
				<span class='linkMarker'>*</span><a class='hyperLink' href='<?php print htmlspecialchars('host.php?action=edit&id=' . get_request_var('host_id'));?>'><?php print __('Edit this Device');?></a><br>
				<span class='linkMarker'>*</span><a class='hyperLink' href='<?php print htmlspecialchars('host.php?action=edit');?>'><?php print __('Create New Device');?></a><br>
				<?php api_plugin_hook('graphs_new_top_links'); ?>
			</td>
		</tr>
		</table>
	</form>
	</td>
	</tr>
	<?php

	html_end_box();

	form_start('graphs_new.php', 'chk');

	$total_rows = sizeof(db_fetch_assoc_prepared('SELECT graph_template_id FROM host_graph WHERE host_id = ?', array(get_request_var('host_id'))));

	$i = 0;

	if (get_request_var('changed')) {
		foreach($snmp_queries as $query) {
			kill_session_var('sess_grn_page' . $query['id']);
			unset_request_var('page' . $query['id']);
			load_current_session_value('page' . $query['id'], 'sess_grn_page' . $query['id'], '1');
		}
	}

	if (get_request_var('graph_type') > 0) {
		load_current_session_value('page' . get_request_var('graph_type'), 'sess_grn_page' . get_request_var('graph_type'), '1');
	}else if (get_request_var('graph_type') == -2) {
		foreach($snmp_queries as $query) {
			load_current_session_value('page' . $query['id'], 'sess_grn_page' . $query['id'], '1');
		}
	}

	$script = "<script type='text/javascript'>\nvar created_graphs = new Array();\n";

	if (get_request_var('graph_type') < 0) {
		print "<div class='cactiTable'><div><div class='cactiTableTitle'><span>" . __('Graph Templates') . "</span></div><div class='cactiTableButton'><span></span></div></div></div>\n";

		html_start_box('', '100%', '', '3', 'center', '');

		print "<tr class='tableHeader'>
				<th class='tableSubHeaderColumn'>" . __('Graph Template Name') . "</th>
				<th class='tableSubHeaderCheckbox'><input class='checkbox' type='checkbox' id='all_cg' title='" . __esc('Select All') . "' onClick='SelectAll(\"sg\",this.checked)'><label class='formCheckboxLabel' title='" . __esc('Select All Rows'). "' for='all_cg'></label></th>\n
			</tr>\n";

		if (get_request_var('filter') != '') {
			$sql_where = 'AND gt.name LIKE "%' . get_request_var('filter') . '%"';
		} else {
			$sql_where = '';
		}

		if (!isempty_request_var('host_id')) {
			$template_graphs = db_fetch_assoc_prepared('SELECT
				DISTINCT gl.graph_template_id
				FROM graph_local AS gl
				INNER JOIN graph_templates AS gt
				ON gt.id=gl.graph_template_id
				WHERE gl.host_id = ?
				AND gt.multiple = ""
				AND gl.snmp_query_id = 0
				GROUP BY gl.graph_template_id', array($host['id']));

			if (sizeof($template_graphs)) {
				$script .= 'var gt_created_graphs = new Array(';

				$cg_ctr = 0;
				foreach ($template_graphs as $template_graph) {
					$script .= (($cg_ctr > 0) ? ',' : '') . "'" . $template_graph['graph_template_id'] . "'";

					$cg_ctr++;
				}

				$script .= ");\n";
			} else {
				$script .= 'var gt_created_graphs = new Array();';
			}
		}

		$graph_templates = db_fetch_assoc_prepared("SELECT
			gt.id AS graph_template_id,
			gt.name AS graph_template_name
			FROM host_graph AS hg
			INNER JOIN graph_templates AS gt
			ON hg.graph_template_id=gt.id
			WHERE hg.host_id = ?
			$sql_where
			ORDER BY gt.name",
			array(get_request_var('host_id'))
		);

		/* create a row for each graph template associated with the host template */
		if (sizeof($graph_templates)) {
			foreach ($graph_templates as $graph_template) {
				$query_row = $graph_template['graph_template_id'];

				print "<tr id='gt_line$query_row' class='selectable " . (($i % 2 == 0) ? 'odd' : 'even') . "'>"; $i++;
				print "<td>
					<span id='gt_text$query_row" . "_0'>" . filter_value($graph_template['graph_template_name'], get_request_var('filter')) . "</span>
					</td>
					<td class='checkbox' style='width:1%;'>
						<input class='checkbox' type='checkbox' name='cg_$query_row' id='cg_$query_row'>
						<label class='formCheckboxLabel' for='cg_$query_row'></label>
					</td>
				</tr>";
			}
		}

		html_end_box(false);

		html_start_box('', '100%', '', '3', 'center', '');

		$available_graph_templates = db_fetch_assoc_prepared('
			(
				SELECT graph_templates.id, graph_templates.name
				FROM graph_templates
				LEFT JOIN snmp_query_graph
				ON snmp_query_graph.graph_template_id = graph_templates.id
				WHERE snmp_query_graph.name IS NULL
				AND graph_templates.id NOT IN (SELECT graph_template_id FROM host_graph WHERE host_id = ?)
				AND graph_templates.multiple = ""
			) UNION (
				SELECT id, name FROM graph_templates WHERE multiple = "on"
			)
			ORDER BY name',
			array(get_request_var('host_id'))
		);

		/* create a row at the bottom that lets the user create any graph they choose */
		print "<tr class='even'>
			<td style='width:1%;'><i>" . __('Create') . "</i></td>
			<td class='left'>";
			form_dropdown('cg_g', $available_graph_templates, 'name', 'id', '', __('(Select a graph type to create)'), '', 'textArea');

		print '</td>
			</tr>';

		html_end_box(false);
	}

	if (get_request_var('graph_type') != -1 && !isempty_request_var('host_id')) {
		$snmp_queries = db_fetch_assoc('SELECT
			snmp_query.id,
			snmp_query.name
			FROM (snmp_query,host_snmp_query)
			WHERE host_snmp_query.snmp_query_id=snmp_query.id
			AND host_snmp_query.host_id=' . $host['id'] .
			(get_request_var('graph_type') != -2 ? ' AND snmp_query.id=' . get_request_var('graph_type') : '') . '
			ORDER BY snmp_query.name');

		if (sizeof($snmp_queries)) {
			foreach ($snmp_queries as $snmp_query) {
				unset($total_rows);

				if (!get_request_var('changed')) {
					$page = get_filter_request_var('page' . $snmp_query['id']);
				} else {
					$page = 1;
				}

				$xml_array = get_data_query_array($snmp_query['id']);

				$num_input_fields = 0;
				$num_visible_fields = 0;

				if ($xml_array != false) {
					/* loop through once so we can find out how many input fields there are */
					foreach ($xml_array['fields'] as $field_name => $field_array) {
						if ($field_array['direction'] == 'input' || $field_array['direction'] == 'input-output') {
							$num_input_fields++;

							if (!isset($total_rows)) {
								$total_rows = db_fetch_cell_prepared('SELECT count(*)
									FROM host_snmp_cache
									WHERE host_id = ?
									AND snmp_query_id = ?
									AND field_name = ?',
									array($host['id'], $snmp_query['id'], $field_name));
							}
						}
					}
				}

				if (!isset($total_rows)) {
					$total_rows = 0;
				}

				$snmp_query_graphs = db_fetch_assoc_prepared('SELECT
					snmp_query_graph.id,snmp_query_graph.name
					FROM snmp_query_graph
					WHERE snmp_query_graph.snmp_query_id = ?
					ORDER BY snmp_query_graph.name', array($snmp_query['id']));

				if (sizeof($snmp_query_graphs)) {
					foreach ($snmp_query_graphs as $snmp_query_graph) {
						$created_graphs = db_fetch_assoc_prepared('SELECT DISTINCT
							gl.snmp_index
							FROM graph_local AS gl
							WHERE gl.snmp_query_graph_id = ?
							AND gl.host_id = ?',
							array($snmp_query_graph['id'], $host['id']));

						$script .= 'created_graphs[' . $snmp_query_graph['id'] . '] = new Array(';

						$cg_ctr = 0;
						if (sizeof($created_graphs)) {
							foreach ($created_graphs as $created_graph) {
								$script .= ($cg_ctr > 0 ? ',' : '') . "'" . encode_data_query_index($created_graph['snmp_index']) . "'";
								++$cg_ctr;
							}
						}

						$script .= ")\n";
					}
				}

				print "<div class='cactiTable'><div><div class='cactiTableTitle'><span>" . __('Data Query [%s]', $snmp_query['name']) . "</span></div><div class='cactiTableButton'><span class='reloadquery fa fa-refresh' id='reload" . $snmp_query['id'] . "' data-id='" . $snmp_query['id'] . "'></span></div></div></div>\n";

				if ($xml_array != false) {
					$html_dq_header = '';

					/* if there is a where clause, get the matching snmp_indexes */
					$sql_where = '';
					if (get_request_var('filter') != '') {
						$sql_where = '';
						$indexes = db_fetch_assoc_prepared('SELECT DISTINCT snmp_index
							FROM host_snmp_cache
							WHERE field_value LIKE ?
							AND snmp_query_id = ?
							AND host_id = ?',
							array('%' . get_request_var('filter') . '%', $snmp_query['id'], $host['id']));

						if (sizeof($indexes)) {
							foreach($indexes as $index) {
								if ($sql_where != '') {
									$sql_where .= ", '" . $index['snmp_index'] . "'";
								} else {
									$sql_where .= " AND snmp_index IN('" . $index['snmp_index'] . "'";
								}
							}

							$sql_where .= ')';
						}
					}

					if (get_request_var('filter') == '' || (get_request_var('filter') != '' && sizeof($indexes))) {
						/* determine the sort order */
						if (isset($xml_array['index_order_type'])) {
							if ($xml_array['index_order_type'] == 'numeric') {
								$sql_order = 'ORDER BY CAST(snmp_index AS unsigned)';
							}else if ($xml_array['index_order_type'] == 'alphabetic') {
								$sql_order = 'ORDER BY snmp_index';
							}else if ($xml_array['index_order_type'] == 'natural') {
								$sql_order = 'ORDER BY INET_ATON(snmp_index)';
							} else {
								$sql_order = '';
							}
						} else {
							$sql_order = '';
						}

						/* get the unique field values from the database */
						$field_names = db_fetch_assoc_prepared('SELECT DISTINCT field_name
							FROM host_snmp_cache
							WHERE host_id = ?
							AND snmp_query_id = ?', array($host['id'], $snmp_query['id']));

						/* build magic query */
						$sql_query  = 'SELECT host_id, snmp_query_id, snmp_index';
						$num_visible_fields = sizeof($field_names);
						$i = 0;
						if (sizeof($field_names)) {
							foreach($field_names as $column) {
								$field_name = $column['field_name'];
								$sql_query .= ", MAX(CASE WHEN field_name='$field_name' THEN field_value ELSE NULL END) AS '$field_name'";
								$i++;
							}
						}

						$sql_query_worder = $sql_query . ' FROM host_snmp_cache
							WHERE host_id=' . $host['id'] . '
							AND snmp_query_id=' . $snmp_query['id'] . "
							$sql_where
							GROUP BY host_id, snmp_query_id, snmp_index
							$sql_order
							LIMIT " . ($rows*($page-1)) . ',' . $rows;

						$sql_query .= ' FROM host_snmp_cache
							WHERE host_id=' . $host['id'] . '
							AND snmp_query_id=' . $snmp_query['id'] . "
							$sql_where
							GROUP BY host_id, snmp_query_id, snmp_index
							LIMIT " . ($rows*($page-1)) . ',' . $rows;

						$rows_query = 'SELECT host_id, snmp_query_id, snmp_index
							FROM host_snmp_cache
							WHERE host_id=' . $host['id'] . '
							AND snmp_query_id=' . $snmp_query['id'] . "
							$sql_where
							GROUP BY host_id, snmp_query_id, snmp_index";

						$snmp_query_indexes = db_fetch_assoc($sql_query);
						if (sizeof($snmp_query_indexes)) {
							$snmp_query_indexes = db_fetch_assoc($sql_query_worder);
						}

						$total_rows = sizeof(db_fetch_assoc($rows_query));

						if (($page - 1) * $rows > $total_rows) {
							$page = 1;
							set_request_var('page' . $query['id'], $page);
							load_current_session_value('page' . $query['id'], 'sess_grn_page' . $query['id'], '1');
						}

						$nav = html_nav_bar('graphs_new.php', MAX_DISPLAY_PAGES, $page, $rows, $total_rows, 15, 'Items', 'page' . $snmp_query['id']);

						print $nav;

						html_start_box('', '100%', '', '3', 'center', '');

						foreach ($xml_array['fields'] as $field_name => $field_array) {
							if (($field_array['direction'] == 'input' || $field_array['direction'] == 'input-output') && sizeof($field_names)) {
								foreach($field_names as $row) {
									if ($row['field_name'] == $field_name) {
										$html_dq_header .= "<th class='tableSubHeaderColumn'>" . $field_array['name'] . "</th>\n";
										break;
									}
								}
							}
						}

						if (!sizeof($snmp_query_indexes)) {
							print "<tr class='odd'><td>" . __('This Data Query returned 0 rows, perhaps there was a problem executing this Data Query.') . "<a href='" . htmlspecialchars('host.php?action=query_verbose&header=true&id=' . $snmp_query['id'] . '&host_id=' . $host['id']) . "'>" . __('You can run this Data Query in debug mode') . "</a> " . __('From there you can get more information.') . "</td></tr>\n";
						} else {
							print "<tr class='tableHeader'>
									$html_dq_header
									<th class='tableSubHeaderCheckbox'><input class='checkbox' id='all_" . $snmp_query['id'] . "' type='checkbox' name='all_" . $snmp_query['id'] . "' title='" . __esc('Select All') . "' onClick='SelectAll(\"sg_" . $snmp_query['id'] . "\",this.checked)'><label class='formCheckboxLabel' title='" . __esc('Select All Rows'). "' for='all_" . $snmp_query['id'] . "'></label></th>\n
								</tr>\n";
						}

						$row_counter    = 0;
						$column_counter = 0;
						$fields         = array_rekey($field_names, 'field_name', 'field_name');
						if (sizeof($snmp_query_indexes)) {
							foreach($snmp_query_indexes as $row) {
								$query_row = $snmp_query['id'] . '_' . encode_data_query_index($row['snmp_index']);

								print "<tr id='dqline$query_row' class='selectable " . (($row_counter % 2 == 0) ? 'odd' : 'even') . "'>"; $i++;

								$column_counter = 0;
								foreach ($xml_array['fields'] as $field_name => $field_array) {
									if ($field_array['direction'] == 'input' || $field_array['direction'] == 'input-output') {
										if (in_array($field_name, $fields)) {
											if (isset($row[$field_name])) {
												print "<td><span id='text$query_row" . '_' . $column_counter . "'>" . filter_value($row[$field_name], get_request_var('filter')) . '</span></td>';
											} else {
												print "<td><span id='text$query_row" . '_' . $column_counter . "'></span></td>";
											}

											$column_counter++;
										}
									}
								}

								print "<td style='width:1%;' class='checkbox'>";
								print "<input class='checkbox' type='checkbox' name='sg_$query_row' id='sg_$query_row'><label class='formCheckboxLabel' for='sg_$query_row'></label>";
								print '</td>';
								print "</tr>\n";

								$row_counter++;
							}
						}
					} else {
						html_start_box('', '100%', '', '3', 'center', '');

						print "<tr class='odd'><td class='textError'>" . __('Search Returned no Rows.') . "</td></tr>\n";
					}
				} else {
					html_start_box('', '100%', '', '3', 'center', '');

					print "<tr class='odd'><td class='textError'>" . __('Error in data query.') . "</td></tr>\n";
				}

				html_end_box(false);

				/* draw the graph template drop down here */
				$data_query_graphs = db_fetch_assoc_prepared('SELECT
					snmp_query_graph.id, snmp_query_graph.name
					FROM snmp_query_graph
					WHERE snmp_query_graph.snmp_query_id = ?
					ORDER BY snmp_query_graph.name', array($snmp_query['id']));

				if (sizeof($data_query_graphs) == 1) {
					echo "<input type='hidden' id='sgg_" . $snmp_query['id'] . "' name='sgg_" . $snmp_query['id'] . "' value='" . $data_query_graphs[0]['id'] . "'>\n";
				} elseif (sizeof($data_query_graphs) > 1) {
					print "<div class='break'></div>\n";

					html_start_box('', '100%', '', '3', 'center', '');

					print "<tr>
						<td>
							<img src='" . $config['url_path'] . "images/arrow.gif' alt=''>
						</td>
						<td class='right' style='width:100%'>
							" . __('Select a Graph Type to Create') . "
						</td>
						<td class='right'>
							<input type='button' class='default' id='default_" .  $snmp_query['id'] . "' value='" . __esc('Set Default') . "' title='" . __esc('Make selection default') . "'>
						</td>
						<td class='right'>
							<select class='dqselect' name='sgg_" . $snmp_query['id'] . "' id='sgg_" . $snmp_query['id'] . "' onChange='dqUpdateDeps(" . $snmp_query['id'] . ',' . (isset($column_counter) ? $column_counter:'') . ");'>
								"; html_create_list($data_query_graphs,'name','id', read_user_setting('default_sgg_' . $snmp_query['id'])); print "
							</select>
						</td>
					</tr>\n";

					html_end_box(false);
				}

				$script .= 'dqUpdateDeps(' . $snmp_query['id'] . ',' . $num_visible_fields . ");\n";
			}
		}
	}

	if ($script != '') {
		$script .= "$('.default').click(function() { $.get('graphs_new.php?action=ajax_save&query=" . (isset($snmp_query['id']) ? $snmp_query['id']:'') . "'+'&item='+$(\".dqselect\").val()) });</script>\n";
		print $script;
	}

	form_hidden_box('save_component_graph', '1', '');

	if (!isempty_request_var('host_id')) {
		form_hidden_box('host_id', $host['id'], '0');
		form_hidden_box('host_template_id', $host['host_template_id'], '0');
	}

	if (isset($_SERVER['HTTP_REFERER']) && !substr_count($_SERVER['HTTP_REFERER'], 'graphs_new')) {
		set_request_var('returnto', basename($_SERVER['HTTP_REFERER']));
	}

	load_current_session_value('returnto', 'sess_grn_returnto', '');
	if (substr_count(get_nfilter_request_var('returnto'), 'host.php') == 0) {
		set_request_var('returnto', '');
	}

	form_save_button(get_nfilter_request_var('returnto'), 'create');
}

