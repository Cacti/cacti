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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

include('./include/auth.php');
include_once('./lib/api_data_source.php');
include_once('./lib/api_graph.php');
include_once('./lib/api_tree.php');
include_once('./lib/data_query.php');
include_once('./lib/html_form_template.php');
include_once('./lib/html_graph.php');
include_once('./lib/sort.php');
include_once('./lib/snmp.php');
include_once('./lib/poller.php');
include_once('./lib/template.php');
include_once('./lib/utility.php');

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

function store_get_selected_dq_index($snmp_query_id) {
	// Always restore the last used filter, otherwise, use the default
	if (!is_numeric($snmp_query_id)) {
		return false;
	} elseif (isset_request_var('sgg_' . $snmp_query_id)) {
		$selected = get_filter_request_var('sgg_' . $snmp_query_id);
	} elseif (isset($_SESSION['sess_sgg_' . $snmp_query_id])) {
		$selected = $_SESSION['sess_sgg_' . $snmp_query_id];
	} else {
		$selected = read_user_setting('default_sgg_' . $snmp_query_id);
	}

	$_SESSION['sess_sgg_' . $snmp_query_id] = $selected;

	return $selected;
}

function form_save() {
	if (isset_request_var('save_component_graph')) {
		$form_data = array();

		/* summarize the 'create graph from host template/snmp index' stuff into an array */
		foreach ($_POST as $var => $val) {
			/* save form data */
			$form_data[$var] = $val;

			if (preg_match('/^cg_(\d+)$/', $var, $matches)) {
				$selected_graphs['cg'][$matches[1]][$matches[1]] = true;
			} elseif (preg_match('/^cg_g$/', $var)) {
				if (get_nfilter_request_var('cg_g') > 0) {
					$selected_graphs['cg'][get_nfilter_request_var('cg_g')][get_nfilter_request_var('cg_g')] = true;
				}
			} elseif (preg_match('/^sg_(\d+)_([a-f0-9]{32})$/', $var, $matches)) {
				$selected_graphs['sg'][$matches[1]][get_nfilter_request_var('sgg_' . $matches[1])][$matches[2]] = true;
			}

			if (strpos($var, 'sgg_') !== false) {
				$snmp_query_id = str_replace('sgg_', '', $var);

				input_validate_input_number($snmp_query_id);

				store_get_selected_dq_index($snmp_query_id);
			}
		}

		/* save the json_encoded form data in case of an error */
		$form_data['header'] = false;

		$_SESSION['sess_graphs_new_form'] = json_encode($form_data);
		$_SESSION['sess_grn_returnto']    = 'graphs_new.php';

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
			html_graph_new_graphs('graphs_new.php', $host_id, $host_template_id, $selected_graphs);
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
	$selected_graphs_array = cacti_unserialize(stripslashes(get_nfilter_request_var('selected_graphs_array')));

	$values = array();
	$form_data = array();

	/* form an array that contains all of the data on the previous form */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^g_(\d+)_(\d+)_(\w+)/', $var, $matches)) { // 1: snmp_query_id, 2: graph_template_id, 3: field_name
			if (empty($matches[1])) { // this is a new graph from template field
				$values['cg'][$matches[2]]['graph_template'][$matches[3]] = $val;
			} else { // this is a data query field
				$values['sg'][$matches[1]][$matches[2]]['graph_template'][$matches[3]] = $val;
			}
		} elseif (preg_match('/^gi_(\d+)_(\d+)_(\d+)_(\w+)/', $var, $matches)) { // 1: snmp_query_id, 2: graph_template_id, 3: graph_template_input_id, 4:field_name
			/* ================= input validation ================= */
			input_validate_input_number($matches[3]);
			/* ==================================================== */

			/* we need to find out which graph items will be affected by saving this particular item */
			$item_list = db_fetch_assoc_prepared('SELECT
				graph_template_item_id
				FROM graph_template_input_defs
				WHERE graph_template_input_id = ?',
				array($matches[3]));

			/* loop through each item affected and update column data */
			if (cacti_sizeof($item_list)) {
				foreach ($item_list as $item) {
					if (empty($matches[1])) { // this is a new graph from template field
						$values['cg'][$matches[2]]['graph_template_item'][$item['graph_template_item_id']][$matches[4]] = $val;
					} else { // this is a data query field
						$values['sg'][$matches[1]][$matches[2]]['graph_template_item'][$item['graph_template_item_id']][$matches[4]] = $val;
					}
				}
			}
		} elseif (preg_match('/^d_(\d+)_(\d+)_(\d+)_(\w+)/', $var, $matches)) { // 1: snmp_query_id, 2: graph_template_id, 3: data_template_id, 4:field_nam
			if (empty($matches[1])) { // this is a new graph from template field
				$values['cg'][$matches[2]]['data_template'][$matches[3]][$matches[4]] = $val;
			} else { // this is a data query field
				$values['sg'][$matches[1]][$matches[2]]['data_template'][$matches[3]][$matches[4]] = $val;
			}
		} elseif (preg_match('/^c_(\d+)_(\d+)_(\d+)_(\d+)/', $var, $matches)) {
			/**
			 * Custom Data.  Need to validate these against the input regular expressions
			 *
			 * Index offsets
			 * 1: snmp_query_id, 2: graph_template_id, 3: data_template_id, 4:data_input_field_id
			 */
			$input_field_id = $matches[4];
			$idata = db_fetch_row_prepared('SELECT *
				FROM data_input_fields
				WHERE id = ?',
				array($input_field_id));

			$val = form_input_validate($val, $var, $idata['regexp_match'], $idata['allow_nulls'], 3);

			if (empty($matches[1])) { // this is a new graph from template field
				$values['cg'][$matches[2]]['custom_data'][$matches[3]][$matches[4]] = $val;
			} else { // this is a data query field
				$values['sg'][$matches[1]][$matches[2]]['custom_data'][$matches[3]][$matches[4]] = $val;
			}
		} elseif (preg_match('/^di_(\d+)_(\d+)_(\d+)_(\d+)_(\w+)/', $var, $matches)) { // 1: snmp_query_id, 2: graph_template_id, 3: data_template_id, 4:local_data_template_rrd_id, 5:field_name
			if (empty($matches[1])) { // this is a new graph from template field
				$values['cg'][$matches[2]]['data_template_item'][$matches[4]][$matches[5]] = $val;
			} else { // this is a data query field
				$values['sg'][$matches[1]][$matches[2]]['data_template_item'][$matches[4]][$matches[5]] = $val;
			}
		}
	}

	if (!is_error_message()) {
		debug_log_clear('new_graphs');

		foreach ($selected_graphs_array as $form_type => $form_array) {
			$current_form_type = $form_type;

			foreach ($form_array as $form_id1 => $form_array2) {
				/* enumerate information from the arrays stored in post variables */
				create_save_graph($host_id, $form_type, $form_id1, $form_array2, $values);
			}
		}
	} else {
		$form_data = $_SESSION['sess_graphs_new_form'];
		kill_session_var('sess_graphs_new_form');

		?>
		<script type='text/javascript'>
		var formData=<?php print $form_data;?>;

		$(function() {
			loadPageUsingPost('graphs_new.php', formData);
		});
		</script>
		<?php

		exit;
	}
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
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
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
		$host   = db_fetch_row_prepared('SELECT id, description, hostname, host_template_id
			FROM host
			WHERE id = ?',
			array(get_request_var('host_id')));

		if (cacti_sizeof($host)) {
			$name = db_fetch_cell_prepared('SELECT name
				FROM host_template
				WHERE id = ?',
				array($host['host_template_id']));

			$header =  __esc('New Graphs for [ %s ] (%s %s)', $host['description'], $host['hostname'], (!empty($host['host_template_id']) ? $name:''));
		} else {
			$header =  __('New Graphs for [ All Devices ]');
			$host['id'] = -1;
			$host['host_template_id'] = 0;
		}
	} else {
		$host['id'] = 0;
		$host['host_template_id'] = 0;
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

		$.get(strURL)
			.done(function(data) {
				$('#text').show().text('<?php print __('Filter Settings Saved');?>').fadeOut(2000);
			})
			.fail(function(data) {
				getPresentHTTPError(data);
			});
	}

	$(function() {
		$('[id^="reload"]').click(function(data) {
			$(this).addClass('fa-spin');
			loadPageNoHeader('graphs_new.php?action=query_reload&header=false&id='+$(this).attr('data-id')+'&host_id='+$('#host_id').val());
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

								$snmp_queries = db_fetch_assoc_prepared('SELECT sq.id, sq.name
									FROM snmp_query AS sq
									INNER JOIN host_snmp_query AS hsq
									ON hsq.snmp_query_id = sq.id
									WHERE hsq.host_id = ?
									ORDER BY sq.name',
									array($host['id']));

								if (cacti_sizeof($snmp_queries) > 0) {
									foreach ($snmp_queries as $query) {
										print "<option value='" . $query['id'] . "'"; if (get_request_var('graph_type') == $query['id']) { print ' selected'; } print '>' . html_escape($query['name']) . '</option>';
									}
								}
								?>
							</select>
						</td>
						<td>
							<span>
								<input type='submit' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='save' value='<?php print __esc('Save');?>' title='<?php print __esc('Save Filters');?>'>
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
							<input type='text' class='ui-state-default ui-corner-all' id='filter' name='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
						</td>
						<td>
							<?php print __('Rows');?>
						</td>
						<td>
							<select id='rows' name='rows' onChange='applyFilter()'>
								<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
								<?php
								if (cacti_sizeof($item_rows) > 0) {
									foreach ($item_rows as $key => $value) {
										print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . '</option>';
									}
								}
								?>
							</select>
						</td>
					</tr>
				</table>
			</td>
			<td class='textInfo right'>
				<span class='linkMarker'>*</span><a class='hyperLink' href='<?php print html_escape('host.php?action=edit&id=' . get_request_var('host_id'));?>'><?php print __('Edit this Device');?></a><br>
				<span class='linkMarker'>*</span><a class='hyperLink' href='<?php print html_escape('host.php?action=edit');?>'><?php print __('Create New Device');?></a><br>
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

	$total_rows = cacti_sizeof(db_fetch_assoc_prepared('SELECT graph_template_id
		FROM host_graph
		WHERE host_id = ?',
		array(get_request_var('host_id'))));

	$i = 0;

	if (get_request_var('changed')) {
		foreach($snmp_queries as $query) {
			kill_session_var('sess_grn_page' . $query['id']);
			unset_request_var('page' . $query['id']);
			load_current_session_value('page' . $query['id'], 'sess_grn_page' . $query['id'], '1');
		}
	}

	if (get_request_var('graph_type') > 0) {
		/* validate the page filter */
		if (isset_request_var('page' . get_request_var('graph_type'))) {
			get_filter_request_var('page' . get_request_var('graph_type'));
		}

		load_current_session_value('page' . get_request_var('graph_type'), 'sess_grn_page' . get_request_var('graph_type'), '1');
	} else if (get_request_var('graph_type') == -2) {
		foreach($snmp_queries as $query) {
			/* validate the page filter */
			if (isset_request_var('page' . $query['id'])) {
				get_filter_request_var('page' . $query['id']);
			}

			load_current_session_value('page' . $query['id'], 'sess_grn_page' . $query['id'], '1');
		}
	}

	$script = "<script type='text/javascript'>\nvar created_graphs = new Array();\n";

	if (get_request_var('graph_type') < 0) {
		html_start_box(__('New Graph Template'), '100%', '', '3', 'center', '');

		$available_graph_templates = db_fetch_assoc_prepared('SELECT gt.id, gt.name
			FROM graph_templates AS gt
			LEFT JOIN snmp_query_graph AS sqg
			ON sqg.graph_template_id = gt.id
			WHERE sqg.name IS NULL
			AND gt.id NOT IN (SELECT graph_template_id FROM host_graph WHERE host_id = ?)
			AND gt.multiple = ""
			UNION
			SELECT id, name
			FROM graph_templates AS gt
			WHERE multiple = "on"
			ORDER BY name',
			array(get_request_var('host_id'))
		);

		/* create a row at the bottom that lets the user create any graph they choose */
		print "<tr class='even'>
			<td class='left' style='width:1%'>";
			form_dropdown('cg_g', $available_graph_templates, 'name', 'id', '', __('(Select a graph type to create)'), '', 'textArea');

		print '</td>
				<td class="left">
					<input type="submit" class="create ui-button ui-corner-all ui-widget ui-state-active" id="submit" value="' . __('Create') . '" role="button">
				</td>
			</tr>';

		html_end_box();

		html_start_box(__('Graph Templates'), '100%', '', '3', 'center', '');

		print "<tr class='tableHeader'>
				<th class='tableSubHeaderColumn'>" . __('Graph Template Name') . "</th>
				<th class='tableSubHeaderCheckbox'><input class='checkbox' type='checkbox' id='all_cg' title='" . __esc('Select All') . "' onClick='selectAll(\"sg\",this.checked)'><label class='formCheckboxLabel' title='" . __esc('Select All Rows'). "' for='all_cg'></label></th>
			</tr>";

		if (get_request_var('filter') != '') {
			$sql_where = 'AND gt.name LIKE ' . db_qstr('%' . get_request_var('filter') . '%');
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
				GROUP BY gl.graph_template_id',
				array($host['id']));

			if (cacti_sizeof($template_graphs)) {
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
		if (cacti_sizeof($graph_templates)) {
			foreach ($graph_templates as $graph_template) {
				$query_row = $graph_template['graph_template_id'];

				print "<tr id='gt_line$query_row' style='display:table-row' class='selectable " . (($i % 2 == 0) ? 'odd' : 'even') . "'>"; $i++;
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

		html_end_box();
	}

	if (get_request_var('graph_type') != -1 && !isempty_request_var('host_id')) {
		$params   = array();
		$params[] = $host['id'];
		if (get_request_var('graph_type') != -2) {
			$params[] = get_request_var('graph_type');
			$sql = ' AND sq.id = ?';
		} else {
			$sql = '';
		}

		$snmp_queries = db_fetch_assoc_prepared("SELECT sq.id, sq.name
			FROM snmp_query AS sq
			INNER JOIN host_snmp_query AS hsq
			ON hsq.snmp_query_id = sq.id
			WHERE hsq.host_id = ?
			$sql
			ORDER BY sq.name",
			$params);

		if (cacti_sizeof($snmp_queries)) {
			foreach ($snmp_queries as $snmp_query) {
				unset($total_rows);

				if (!get_request_var('changed')) {
					$page = get_filter_request_var('page' . $snmp_query['id']);
				} else {
					$page = 1;
				}

				$xml_array = get_data_query_array($snmp_query['id']);

				$num_input_fields   = 0;
				$num_visible_fields = 0;
				$message_raised     = false;

				if (cacti_sizeof($xml_array)) {
					/* loop through once so we can find out how many input fields there are */
					if (isset($xml_array['fields'])) {
						foreach ($xml_array['fields'] as $field_name => $field_array) {
							if (!is_array($field_array)) {
								if (!$message_raised) {
									raise_message('xmlerror', __('Error Parsing Data Query Resource XML file for Data Query \'%s\' with id of \'%s\'', $snmp_query['id']), MESSAGE_LEVEL_ERROR);
									$message_raised = true;
								}
							} elseif (isset($field_array['direction'])) {
								if ($field_array['direction'] == 'input' || $field_array['direction'] == 'input-output') {
									$num_input_fields++;

									if (!isset($total_rows)) {
										$total_rows = db_fetch_cell_prepared('SELECT COUNT(*)
											FROM host_snmp_cache
											WHERE host_id = ?
											AND snmp_query_id = ?
											AND field_name = ?',
											array($host['id'], $snmp_query['id'], $field_name));
									}
								}
							} else {
								raise_message('xmlfielderr' . $field_name, __('Error Parsing Data Query Resource XML file for Data Query \'%s\' with id \'%s\'.  Field Name \'%s\' missing a \'direction\' attribute', $snmp_query['name'], $snmp_query['id'], $field_name), MESSAGE_LEVEL_ERROR);
							}
						}
					} elseif (!$message_raised) {
						raise_message('xmlerror', __('Error Parsing Data Query Resource XML file for Data Query \'%s\' with id \'%s\'', $snmp_query['name'], $snmp_query['id']), MESSAGE_LEVEL_ERROR);
						$message_raised = true;
					}
				}

				if (!isset($total_rows)) {
					$total_rows = 0;
				}

				$snmp_query_graphs = db_fetch_assoc_prepared('SELECT id, name
					FROM snmp_query_graph
					WHERE snmp_query_id = ?
					ORDER BY name',
					array($snmp_query['id']));

				if (cacti_sizeof($snmp_query_graphs)) {
					foreach ($snmp_query_graphs as $snmp_query_graph) {
						$created_graphs = db_fetch_assoc_prepared('SELECT DISTINCT snmp_index
							FROM graph_local
							WHERE snmp_query_graph_id = ?
							AND host_id = ?',
							array($snmp_query_graph['id'], $host['id']));

						$script .= 'created_graphs[' . $snmp_query_graph['id'] . '] = new Array(';

						$cg_ctr = 0;
						if (cacti_sizeof($created_graphs)) {
							foreach ($created_graphs as $created_graph) {
								$script .= ($cg_ctr > 0 ? ',' : '') . "'" . encode_data_query_index($created_graph['snmp_index']) . "'";
								++$cg_ctr;
							}
						}

						$script .= ")\n";
					}
				}

				print "<div class='cactiTable'>
					<div>
						<div class='cactiTableTitle'>
							<span>" . __esc('Data Query [%s]', $snmp_query['name']) . "</span>
						</div>
						<div class='cactiTableButton'>
							<span class='reloadquery fa fa-sync' id='reload" . $snmp_query['id'] . "' data-id='" . $snmp_query['id'] . "'></span>
						</div>
					</div>
				</div>";

				if (cacti_sizeof($xml_array)) {
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

						if (cacti_sizeof($indexes)) {
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

					if (get_request_var('filter') == '' || (get_request_var('filter') != '' && cacti_sizeof($indexes))) {
						/* determine the sort order */
						if (isset($xml_array['index_order_type'])) {
							if ($xml_array['index_order_type'] == 'numeric') {
								$sql_order = 'ORDER BY CAST(snmp_index AS unsigned)';
							} else if ($xml_array['index_order_type'] == 'alphabetic') {
								$sql_order = 'ORDER BY snmp_index';
							} else if ($xml_array['index_order_type'] == 'natural') {
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
							AND snmp_query_id = ?',
							array($host['id'], $snmp_query['id']));

						/* build magic query */
						$sql_query  = 'SELECT host_id, snmp_query_id, snmp_index';
						$num_visible_fields = cacti_sizeof($field_names);
						$i = 0;
						if (cacti_sizeof($field_names)) {
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
						if (cacti_sizeof($snmp_query_indexes)) {
							$snmp_query_indexes = db_fetch_assoc($sql_query_worder);
						}

						$total_rows = cacti_sizeof(db_fetch_assoc($rows_query));

						if (($page - 1) * $rows > $total_rows) {
							$page = 1;
							set_request_var('page' . $query['id'], $page);
							load_current_session_value('page' . $query['id'], 'sess_grn_page' . $query['id'], '1');
						}

						$nav = html_nav_bar('graphs_new.php', MAX_DISPLAY_PAGES, $page, $rows, $total_rows, 15, __('Items'), 'page' . $snmp_query['id']);

						print $nav;

						html_start_box('', '100%', '', '3', 'center', '');

						foreach ($xml_array['fields'] as $field_name => $field_array) {
							if (($field_array['direction'] == 'input' || $field_array['direction'] == 'input-output') && cacti_sizeof($field_names)) {
								foreach($field_names as $row) {
									if ($row['field_name'] == $field_name) {
										$html_dq_header .= "<th class='tableSubHeaderColumn'>" . $field_array['name'] . '</th>';
										break;
									}
								}
							}
						}

						if (!cacti_sizeof($snmp_query_indexes)) {
							print "<tr class='odd'><td>" . __('This Data Query returned 0 rows, perhaps there was a problem executing this Data Query.') . "<a href='" . html_escape('host.php?action=query_verbose&header=true&id=' . $snmp_query['id'] . '&host_id=' . $host['id']) . "'>" . __('You can run this Data Query in debug mode') . "</a> " . __('From there you can get more information.') . '</td></tr>';
						} else {
							print "<tr class='tableHeader'>
									$html_dq_header
									<th class='tableSubHeaderCheckbox'><input class='checkbox' id='all_" . $snmp_query['id'] . "' type='checkbox' name='all_" . $snmp_query['id'] . "' title='" . __esc('Select All') . "' onClick='selectAll(\"sg_" . $snmp_query['id'] . "\",this.checked)'><label class='formCheckboxLabel' title='" . __esc('Select All Rows'). "' for='all_" . $snmp_query['id'] . "'></label></th>
								</tr>";
						}

						/* disable graph creation if there are no associated Graph Templates */
						$enabled = db_fetch_cell_prepared('SELECT COUNT(*)
							FROM snmp_query_graph
							WHERE snmp_query_id = ?',
							array($snmp_query['id']));

						if (!$enabled) {
							$disabled_text = __esc('The index is disabled due to the Data Query having no associated Graph Templates.');
						} else {
							$disabled_text = '';
						}

						$row_counter    = 0;
						$column_counter = 0;
						$fields         = array_rekey($field_names, 'field_name', 'field_name');

						if (cacti_sizeof($snmp_query_indexes)) {
							foreach($snmp_query_indexes as $row) {
								$query_row = $snmp_query['id'] . '_' . encode_data_query_index($row['snmp_index']);

								if ($enabled) {
									print "<tr id='dqline$query_row' class='selectable " . (($row_counter % 2 == 0) ? 'odd' : 'even') . "'>"; $i++;
								} else {
									print "<tr id='nodqline$query_row' title='$disabled_text' class='selectable notemplate " . (($row_counter % 2 == 0) ? 'odd' : 'even') . "'>"; $i++;
								}

								$column_counter = 0;
								foreach ($xml_array['fields'] as $field_name => $field_array) {
									if ($field_array['direction'] == 'input' || $field_array['direction'] == 'input-output') {
										if (in_array($field_name, $fields)) {
											if (isset($row[$field_name])) {
												print "<td><span class='textOverflow' id='text$query_row" . '_' . $column_counter . "'>" . filter_value($row[$field_name], get_request_var('filter')) . '</span></td>';
											} else {
												print "<td><span class='textOverflow' id='text$query_row" . '_' . $column_counter . "'></span></td>";
											}

											$column_counter++;
										}
									}
								}

								print "<td style='width:1%;' class='checkbox'>";

								if ($enabled) {
									print "<input class='checkbox' type='checkbox' name='sg_$query_row' id='sg_$query_row'><label class='formCheckboxLabel' for='sg_$query_row'></label>";
								} else {
									print "<input class='checkbox' type='checkbox' disabled name='sg_$query_row' id='sg_$query_row'><label class='formCheckboxLabel' for='sg_$query_row'></label>";
								}
								print '</td>';
								print '</tr>';

								$row_counter++;
							}
						}
					} else {
						html_start_box('', '100%', '', '3', 'center', '');

						print "<tr class='odd'><td class='textError'>" . __('Search Returned no Rows.') . '</td></tr>';
					}
				} else {
					html_start_box('', '100%', '', '3', 'center', '');

					print "<tr class='odd'><td class='textError'>" . __('Error in Data Query.  This could be due to the following: File Permissions, or a missing or improperly formatted Data Query XML file.') . '</td></tr>';
				}

				html_end_box();

				/* draw the graph template drop down here */
				$data_query_graphs = db_fetch_assoc_prepared('SELECT id, name
					FROM snmp_query_graph
					WHERE snmp_query_id = ?
					ORDER BY name',
					array($snmp_query['id']));

				if (cacti_sizeof($data_query_graphs) == 1) {
					print "<input type='hidden' id='sgg_" . $snmp_query['id'] . "' name='sgg_" . $snmp_query['id'] . "' value='" . $data_query_graphs[0]['id'] . "'>";
				} elseif (cacti_sizeof($data_query_graphs) > 1) {
					print "<div class='break'></div>";

					html_start_box('', '100%', '', '3', 'center', '');

					$selected = store_get_selected_dq_index($snmp_query['id']);

					print "<tr>
						<td>
							<img src='" . $config['url_path'] . "images/arrow.gif' alt=''>
						</td>
						<td class='right' style='width:100%'>
							" . __('Select a Graph Type to Create') . "
						</td>
						<td class='right'>
							<input type='button' class='ui-button ui-corner-all ui-widget default' id='default_" .  $snmp_query['id'] . "' value='" . __esc('Set Default') . "' title='" . __esc('Make selection default') . "'>
						</td>
						<td class='right'>
							<select class='dqselect' name='sgg_" . $snmp_query['id'] . "' id='sgg_" . $snmp_query['id'] . "' onChange='dqUpdateDeps(" . $snmp_query['id'] . ',' . (isset($column_counter) ? $column_counter:'') . ");'>
								"; html_create_list($data_query_graphs, 'name', 'id', $selected); print "
							</select>
						</td>
					</tr>";

					html_end_box();
				}

				$script .= 'dqUpdateDeps(' . $snmp_query['id'] . ',' . $num_visible_fields . ");\n";
			}
		}
	}

	if ($script != '') {
		$script .= "$('.default').click(function() { $.get('graphs_new.php?action=ajax_save&query=" . (isset($snmp_query['id']) ? $snmp_query['id']:'') . "'+'&item='+$(\".dqselect\").val()).fail(function(data) { getPresentHTTPError(data); });}); $('tr.notemplate').tooltip();</script>";
		print $script;
	}

	form_hidden_box('save_component_graph', '1', '');

	if (!isempty_request_var('host_id')) {
		form_hidden_box('host_id', $host['id'], '0');
		form_hidden_box('host_template_id', $host['host_template_id'], '0');
	}

	if (isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] != '') {
		$referer_url = parse_url($_SERVER['HTTP_REFERER']);

		if ($_SERVER['SERVER_NAME'] != $referer_url['host']) {
			/* Potential security exploit 1 */
			set_request_var('returnto', 'host.php');
		} elseif (strpos($_SERVER['HTTP_REFERER'], 'graphs_new') === false) {
			set_request_var('returnto', basename($_SERVER['HTTP_REFERER']));
		} else {
			set_request_var('returnto', 'host.php');
		}
	} elseif (isset_request_var('returnto') && get_nfilter_request_var('returnto') != '') {
		$returnto_url = parse_url(get_nfilter_request_var('returnto'));

		if ($_SERVER['SERVER_NAME'] != $returnto_url['host']) {
			/* Potential security exploit 2 */
			set_request_var('returnto', 'host.php');
		}
	}

	load_current_session_value('returnto', 'sess_grn_returnto', '');

	if (strpos(get_nfilter_request_var('returnto'), 'host.php') === false) {
		set_request_var('returnto', '');
	}

	form_save_button(get_nfilter_request_var('returnto'), 'create');
}

