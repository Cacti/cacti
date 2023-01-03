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

function initialize_realtime_step_and_window() {
	if (!isset($_SESSION['sess_realtime_dsstep'])) {
		$_SESSION['sess_realtime_dsstep'] = read_config_option('realtime_interval');
	}

	if (!isset($_SESSION['sess_realtime_window'])) {
		$_SESSION['sess_realtime_window'] = read_config_option('realtime_gwindow');
	}
}

function set_default_graph_action() {
	if (!isset_request_var('action')) {
		/* setup the default action */
		if (!isset($_SESSION['sess_graph_view_action'])) {
			switch(read_user_setting('default_view_mode')) {
				case '1':
					if (is_view_allowed('show_tree')) {
						set_request_var('action', 'tree');
					}

					break;
				case '2':
					if (is_view_allowed('show_list')) {
						set_request_var('action', 'list');
					}

					break;
				case '3':
					if (is_view_allowed('show_preview')) {
						set_request_var('action', 'preview');
					}

					break;

				default:
					break;
			}
		} elseif (in_array($_SESSION['sess_graph_view_action'], array('tree', 'list', 'preview'), true)) {
			if (is_view_allowed('show_' . $_SESSION['sess_graph_view_action'])) {
				set_request_var('action', $_SESSION['sess_graph_view_action']);
			}
		}
	}

	if (!isset_request_var('action')) {
		if (is_view_allowed('show_tree')) {
			set_request_var('action', 'tree');
		} elseif (is_view_allowed('show_preview')) {
			set_request_var('action', 'preview');
		} elseif (is_view_allowed('show_list')) {
			set_request_var('action', 'list');
		} else {
			set_request_var('action', '');
		}
	}

	if (get_nfilter_request_var('action') != 'get_node') {
		$_SESSION['sess_graph_view_action'] = get_nfilter_request_var('action');
	}
}

function html_graph_validate_preview_request_vars() {
	/* ================= input validation and session storage ================= */
	$filters = array(
		'graphs' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => read_user_setting('preview_graphs_per_page', 20)
			),
		'page' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'graph_template_id' => array(
			'filter'  => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'pageset' => true,
			'default' => '-1',
			),
		'columns' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => read_user_setting('num_columns', '2')
			),
		'host_id' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'rfilter' => array(
			'filter'  => FILTER_VALIDATE_IS_REGEX,
			'pageset' => true,
			'default' => '',
			),
		'thumbnails' => array(
			'filter'  => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'default' => read_user_setting('thumbnail_section_preview', '') == 'on' ? 'true':'false'
			),
		'graph_list' => array(
			'filter'  => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'default' => ''
			),
		'graph_add' => array(
			'filter'  => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'default' => ''
			),
		'graph_remove' => array(
			'filter'  => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'default' => ''
			),
		'style' => array(
			'filter'  => FILTER_DEFAULT,
			'default' => ''
			)
	);

	validate_store_request_vars($filters, 'sess_grview');
	/* ================= input validation ================= */
}

function html_graph_preview_filter($page, $action, $devices_where = '', $templates_where = '') {
	global $graphs_per_page, $realtime_window, $realtime_refresh, $graph_timeshifts, $graph_timespans, $config;

	initialize_realtime_step_and_window();

	?>
	<tr class='even noprint'>
		<td class='noprint'>
		<form id='form_graph_view' method='post' action='<?php print $page;?>?action=<?php print $action;?>'>
			<table id='device' class='filterTable'>
				<tr>
					<?php print html_host_filter(get_request_var('host_id'), 'applyGraphFilter', $devices_where);?>
					<td>
						<?php print __('Template');?>
					</td>
					<td>
						<select id='graph_template_id' multiple style='opacity:0.1;overflow-y:auto;overflow-x:hide;height:0px;'>
							<option value='-1'<?php if (get_request_var('graph_template_id') == '-1') {?> selected<?php }?>><?php print __('All Graphs & Templates');?></option>
							<option value='0'<?php if (get_request_var('graph_template_id') == '0') {?> selected<?php }?>><?php print __('Not Templated');?></option>
							<?php
							// suppress total rows collection
							$total_rows = -1;

	$graph_templates = get_allowed_graph_templates('', 'name', '', $total_rows);

	if (cacti_sizeof($graph_templates)) {
		$selected    = explode(',', get_request_var('graph_template_id'));

		foreach ($graph_templates as $gt) {
			$found = db_fetch_cell_prepared('SELECT id
										FROM graph_local
										WHERE graph_template_id = ? LIMIT 1',
				array($gt['id']));

			if ($found) {
				print "<option value='" . $gt['id'] . "'";

				if (cacti_sizeof($selected)) {
					if (in_array($gt['id'], $selected, true)) {
						print ' selected';
					}
				}
				print '>';
				print html_escape($gt['name']) . "</option>\n";
			}
		}
	}
	?>
						</select>
					</td>
					<td>
						<span>
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='go' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
							<?php if (is_view_allowed('graph_settings')) {?>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='save' value='<?php print __esc('Save');?>' title='<?php print __esc('Save the current Graphs, Columns, Thumbnail, Preset, and Timeshift preferences to your profile');?>'>
							<?php }?>
						<span>
					</td>
					<td id='text'></td>
				</tr>
			</table>
			<table id='search' class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='rfilter' size='55' value='<?php print html_escape_request_var('rfilter');?>'>
					</td>
					<td>
						<?php print __('Graphs');?>
					</td>
					<td>
						<select id='graphs' onChange='applyGraphFilter()'>
							<?php
	if (cacti_sizeof($graphs_per_page)) {
		foreach ($graphs_per_page as $key => $value) {
			print "<option value='" . $key . "'";

			if (get_request_var('graphs') == $key) {
				print ' selected';
			} print '>' . $value . "</option>\n";
		}
	}
	?>
						</select>
					</td>
					<td>
						<?php print __('Columns');?>
					</td>
					<td>
						<select id='columns' onChange='applyGraphFilter()'>
							<option value='1'<?php if (get_request_var('columns') == '1') {?> selected<?php }?>><?php print __('%d Column', 1);?></option>
							<option value='2'<?php if (get_request_var('columns') == '2') {?> selected<?php }?>><?php print __('%d Columns', 2);?></option>
							<option value='3'<?php if (get_request_var('columns') == '3') {?> selected<?php }?>><?php print __('%d Columns', 3);?></option>
							<option value='4'<?php if (get_request_var('columns') == '4') {?> selected<?php }?>><?php print __('%d Columns', 4);?></option>
							<option value='5'<?php if (get_request_var('columns') == '5') {?> selected<?php }?>><?php print __('%d Columns', 5);?></option>
							<option value='6'<?php if (get_request_var('columns') == '6') {?> selected<?php }?>><?php print __('%d Columns', 6);?></option>
						</select>
					</td>
					<td>
						<span>
							<input id='thumbnails' type='checkbox' onClick='applyGraphFilter()' <?php print((get_request_var('thumbnails') == 'true') ? 'checked':'');?>>
							<label for='thumbnails'><?php print __('Thumbnails');?></label>
						</span>
					</td>
				</tr>
			</table>
		</form>
		</td>
	</tr>
	<tr class='even noprint'>
		<td class='noprint'>
		<form id='form_timespan_selector' action='<?php print $page;?>?action=preview' method='post' action='<?php print $page;?>'>
			<table class='filterTable'>
				<tr id='timespan'>
					<td>
						<?php print __('Presets');?>
					</td>
					<td>
						<select id='predefined_timespan' onChange='applyGraphTimespan()'>
							<?php
	$graph_timespans = array_merge(array(GT_CUSTOM => __('Custom')), $graph_timespans);

	$start_val = 0;
	$end_val   = cacti_sizeof($graph_timespans);

	if (cacti_sizeof($graph_timespans)) {
		foreach ($graph_timespans as $value => $text) {
			print "<option value='$value'";

			if ($_SESSION['sess_current_timespan'] == $value) {
				print ' selected';
			} print '>' . html_escape($text) . '</option>';
		}
	}
	?>
						</select>
					</td>
					<td>
						<?php print __('From');?>
					</td>
					<td>
						<span>
							<input type='text' class='ui-state-default ui-corner-all' id='date1' size='18' value='<?php print(isset($_SESSION['sess_current_date1']) ? $_SESSION['sess_current_date1'] : '');?>'>
							<i id='startDate' class='calendar fa fa-calendar-alt' title='<?php print __esc('Start Date Selector');?>'></i>
						</span>
					</td>
					<td>
						<?php print __('To');?>
					</td>
					<td>
						<span>
							<input type='text' class='ui-state-default ui-corner-all' id='date2' size='18' value='<?php print(isset($_SESSION['sess_current_date2']) ? $_SESSION['sess_current_date2'] : '');?>'>
							<i id='endDate' class='calendar fa fa-calendar-alt' title='<?php print __esc('End Date Selector');?>'></i>
						</span>
					</td>
					<td>
						<span>
							<i class='shiftArrow fa fa-backward' onClick='timeshiftGraphFilterLeft()' title='<?php print __esc('Shift Time Backward');?>'></i>
							<select id='predefined_timeshift' name='predefined_timeshift' title='<?php print __esc('Define Shifting Interval');?>'>
								<?php
		$start_val = 1;
	$end_val    = cacti_sizeof($graph_timeshifts) + 1;

	if (cacti_sizeof($graph_timeshifts) > 0) {
		for ($shift_value=$start_val; $shift_value < $end_val; $shift_value++) {
			print "<option value='$shift_value'";

			if ($_SESSION['sess_current_timeshift'] == $shift_value) {
				print ' selected';
			} print '>' . html_escape($graph_timeshifts[$shift_value]) . '</option>';
		}
	}
	?>
							</select>
							<i class='shiftArrow fa fa-forward' onClick='timeshiftGraphFilterRight()' title='<?php print __esc('Shift Time Forward');?>'></i>
						</span>
					</td>
					<td>
						<span>
							<input id='tsrefresh' type='button' class='ui-button ui-corner-all ui-widget' value='<?php print __esc('Refresh');?>' name='button_refresh_x' title='<?php print __esc('Refresh selected time span');?>' onClick='refreshGraphTimespanFilter()'>
							<input id='tsclear' type='button' class='ui-button ui-corner-all ui-widget' value='<?php print __esc('Clear');?>' title='<?php print __esc('Return to the default time span');?>' onClick='clearGraphTimespanFilter()'>
						</span>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr id='realtime' style='display:none;'>
					<td>
						<?php print __('Window');?>
					</td>
					<td>
						<select name='graph_start' id='graph_start' onChange='realtimeGrapher()'>
						<?php
						foreach ($realtime_window as $interval => $text) {
							printf('<option value="%d"%s>%s</option>', $interval, $interval == $_SESSION['sess_realtime_window'] ? 'selected="selected"' : '', $text);
						}
	?>
						</select>
					</td>
					<td>
						<?php print __('Interval');?>
					</td>
					<td>
						<select name='ds_step' id='ds_step' onChange="realtimeGrapher()">
							<?php
		foreach ($realtime_refresh as $interval => $text) {
			printf('<option value="%d"%s>%s</option>', $interval, $interval == $_SESSION['sess_realtime_dsstep'] ? ' selected="selected"' : '', $text);
		}
	?>
						</select>
					</td>
					<td>
						<input type='button' class='ui-button ui-corner-all ui-widget' id='realtimeoff' value='<?php print __esc('Stop');?>'>
					</td>
					<td class='center' colspan='6'>
						<span id='countdown'></span>
					</td>
					<td>
						<input id='future' type='hidden' value='<?php print read_config_option('allow_graph_dates_in_future');?>'></input>
					</td>
				</tr>
			</table>
		</form>
		<script type='text/javascript'>

    	var refreshIsLogout = false;
		var refreshMSeconds = <?php print read_user_setting('page_refresh') * 1000;?>;
		var graph_start     = <?php print get_current_graph_start();?>;
		var graph_end       = <?php print get_current_graph_end();?>;
		var timeOffset      = <?php print date('Z');?>;
		var pageAction      = '<?php print $action;?>';
		var graphPage       = '<?php print $page;?>';
		var date1Open       = false;
		var date2Open       = false;

		function initPage() {
			<?php html_graph_template_multiselect();?>

			$('#startDate').click(function() {
				if (date1Open) {
					date1Open = false;
					$('#date1').datetimepicker('hide');
				} else {
					date1Open = true;
					$('#date1').datetimepicker('show');
				}
			});

			$('#endDate').click(function() {
				if (date2Open) {
					date2Open = false;
					$('#date2').datetimepicker('hide');
				} else {
					date2Open = true;
					$('#date2').datetimepicker('show');
				}
			});

			$('#date1').datetimepicker({
				minuteGrid: 10,
				stepMinute: 1,
				showAnim: 'slideDown',
				numberOfMonths: 1,
				timeFormat: 'HH:mm',
				dateFormat: 'yy-mm-dd',
				showButtonPanel: false
			});

			$('#date2').datetimepicker({
				minuteGrid: 10,
				stepMinute: 1,
				showAnim: 'slideDown',
				numberOfMonths: 1,
				timeFormat: 'HH:mm',
				dateFormat: 'yy-mm-dd',
				showButtonPanel: false
			});
		}

		$(function() {
			$('#go').off('click').on('click', function(event) {
				event.preventDefault();
				applyGraphFilter();
			});

			$('#clear').off('click').on('click', function() {
				clearGraphFilter();
			});

			$('#save').off('click').on('click', function() {
				 saveGraphFilter('preview');
			});

			$.when(initPage()).done(function() {
				initializeGraphs();
			});
		});

		</script>
		<?php html_spikekill_js();?>
		</td>
	</tr>
	<?php
}

function html_graph_new_graphs($page, $host_id, $host_template_id, $selected_graphs_array) {
	$snmp_query_id     = 0;
	$num_output_fields = array();
	$output_started    = false;

	foreach ($selected_graphs_array as $form_type => $form_array) {
		foreach ($form_array as $form_id1 => $form_array2) {
			ob_start();

			$count = html_graph_custom_data($host_id, $host_template_id, $snmp_query_id, $form_type, $form_id1, $form_array2);

			if (array_sum($count)) {
				if (!$output_started) {
					$output_started = true;

					top_header();
				}

				ob_end_flush();
			} else {
				ob_end_clean();
			}
		}
	}

	/* no fields were actually drawn on the form; just save without prompting the user */
	if (!$output_started) {
		/* since the user didn't actually click "Create" to POST the data; we have to
		pretend like they did here */
		set_request_var('save_component_new_graphs', '1');
		set_request_var('selected_graphs_array', serialize($selected_graphs_array));

		host_new_graphs_save($host_id);

		header('Location: ' . $page . '?host_id=' . $host_id);

		exit;
	}

	form_hidden_box('host_template_id', $host_template_id, '0');
	form_hidden_box('host_id', $host_id, '0');
	form_hidden_box('save_component_new_graphs', '1', '');
	form_hidden_box('selected_graphs_array', serialize($selected_graphs_array), '');

	if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'graphs_new') === false) {
		set_request_var('returnto', basename($_SERVER['HTTP_REFERER']));
	}

	load_current_session_value('returnto', 'sess_grn_returnto', '');

	form_save_button(get_nfilter_request_var('returnto'));

	bottom_footer();
}

function html_graph_custom_data($host_id, $host_template_id, $snmp_query_id, $form_type, $form_id1, $form_array2) {
	/* ================= input validation ================= */
	input_validate_input_number($form_id1, 'form_id1');
	/* ==================================================== */

	$num_output_fields = array();
	$display           = false;

	if ($form_type == 'cg') {
		$graph_template_id   = $form_id1;
		$graph_template_name = db_fetch_cell_prepared('SELECT name
			FROM graph_templates
			WHERE id = ?',
			array($graph_template_id));

		if (graph_template_has_override($graph_template_id)) {
			$display = true;
			$header  = __('Create Graph from %s', html_escape($graph_template_name));
		}
	} elseif ($form_type == 'sg') {
		foreach ($form_array2 as $form_id2 => $form_array3) {
			/* ================= input validation ================= */
			input_validate_input_number($snmp_query_id, 'snmp_query_id');
			input_validate_input_number($form_id2, 'form_id2');
			/* ==================================================== */

			$snmp_query_id       = $form_id1;
			$snmp_query_graph_id = $form_id2;
			$num_graphs          = cacti_sizeof($form_array3);

			$snmp_query = db_fetch_cell_prepared('SELECT name
				FROM snmp_query
				WHERE id = ?',
				array($snmp_query_id));

			$graph_template_id = db_fetch_cell_prepared('SELECT graph_template_id
				FROM snmp_query_graph
				WHERE id = ?',
				array($snmp_query_graph_id));
		}

		if (graph_template_has_override($graph_template_id)) {
			$display = true;

			if ($num_graphs > 1) {
				$header = __('Create %s Graphs from %s', $num_graphs, html_escape($snmp_query));
			} else {
				$header = __('Create Graph from %s', html_escape($snmp_query));
			}
		}
	}

	if ($display) {
		form_start('graphs_new.php', 'new_graphs');

		html_start_box($header, '100%', '', '3', 'center', '');
	}

	/* ================= input validation ================= */
	input_validate_input_number($graph_template_id, 'graph_template_id');
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

	$graph_template = db_fetch_row_prepared('SELECT gt.name AS graph_template_name, gtg.*
		FROM graph_templates AS gt
		INNER JOIN graph_templates_graph AS gtg
		ON gt.id = gtg.graph_template_id
		WHERE gt.id = ?
		AND gtg.local_graph_id = 0',
		array($graph_template_id));

	array_push($num_output_fields, draw_nontemplated_fields_graph($graph_template_id, $graph_template, "g_$snmp_query_id" . '_' . $graph_template_id . '_|field|', __('Graph [Template: %s]', html_escape($graph_template['graph_template_name'])), true, false, (isset($snmp_query_graph_id) ? $snmp_query_graph_id : 0)));

	array_push($num_output_fields, draw_nontemplated_fields_graph_item($graph_template_id, 0, 'gi_' . $snmp_query_id . '_' . $graph_template_id . '_|id|_|field|', __('Graph Items [Template: %s]', html_escape($graph_template['graph_template_name'])), true));

	/* DRAW: Data Sources */
	if (cacti_sizeof($data_templates)) {
		foreach ($data_templates as $data_template) {
			array_push($num_output_fields, draw_nontemplated_fields_data_source($data_template['data_template_id'], 0, $data_template, 'd_' . $snmp_query_id . '_' . $graph_template_id . '_' . $data_template['data_template_id'] . '_|field|', __('Data Source [Template: %s]', html_escape($data_template['data_template_name'])), true, false, (isset($snmp_query_graph_id) ? $snmp_query_graph_id : 0)));

			$data_template_items = db_fetch_assoc_prepared('SELECT
				data_template_rrd.*
				FROM data_template_rrd
				WHERE data_template_rrd.data_template_id = ?
				AND local_data_id = 0',
				array($data_template['data_template_id']));

			array_push($num_output_fields, draw_nontemplated_fields_data_source_item($data_template['data_template_id'], $data_template_items, 'di_' . $snmp_query_id . '_' . $graph_template_id . '_' . $data_template['data_template_id'] . '_|id|_|field|', '', true, false, false, (isset($snmp_query_graph_id) ? $snmp_query_graph_id : 0)));
			array_push($num_output_fields, draw_nontemplated_fields_custom_data($data_template['id'], 'c_' . $snmp_query_id . '_' . $graph_template_id . '_' . $data_template['data_template_id'] . '_|id|', __('Custom Data [Template: %s]', html_escape($data_template['data_template_name'])), true, false, $snmp_query_id));
		}
	}

	if ($display) {
		html_end_box(false);
	}

	return $num_output_fields;
}
