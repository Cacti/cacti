<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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
		} elseif (in_array($_SESSION['sess_graph_view_action'], array('tree', 'list', 'preview'))) {
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
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => read_user_setting('preview_graphs_per_page', 20)
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'graph_template_id' => array(
			'filter' => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'pageset' => true,
			'default' => read_user_setting('graph_template_id', 0)
			),
		'columns' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => read_user_setting('num_columns', 2)
			),
		'host_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'rfilter' => array(
			'filter' => FILTER_VALIDATE_IS_REGEX,
			'pageset' => true,
			'default' => '',
			),
		'thumbnails' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'default' => read_user_setting('thumbnail_section_preview', '') == 'on' ? 'true':'false'
			),
		'graph_list' => array(
			'filter' => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'default' => ''
			),
		'graph_add' => array(
			'filter' => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'default' => ''
			),
		'graph_remove' => array(
			'filter' => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'default' => ''
			),
		'style' => array(
			'filter' => FILTER_DEFAULT,
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
							<option value='0'<?php if (get_request_var('graph_template_id') == '0') {?> selected<?php }?>><?php print __('All Graphs & Templates');?></option>
							<?php
							$graph_templates = get_allowed_graph_templates();
							if (sizeof($graph_templates)) {
								$selected    = explode(',', get_request_var('graph_template_id'));
								foreach ($graph_templates as $gt) {
									$found = db_fetch_cell_prepared('SELECT id
										FROM graph_local
										WHERE graph_template_id = ? LIMIT 1',
										array($gt['id']));

									if ($found) {
										print "<option value='" . $gt['id'] . "'";
										if (sizeof($selected)) {
											if (in_array($gt['id'], $selected)) {
												print ' selected';
											}
										}
										print '>';
										print $gt['name'] . "</option>\n";
									}
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>' onClick='applyGraphFilter()'>
							<input type='button' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>' onClick='clearGraphFilter()'>
							<?php if (is_view_allowed('graph_settings')) {?>
							<input type='button' id='save' value='<?php print __esc('Save');?>' title='<?php print __esc('Save the current Graphs, Columns, Thumbnail, Preset, and Timeshift preferences to your profile');?>' onClick='saveGraphFilter("preview")'>
							<?php }?>
						<span>
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
						<input type='text' id='rfilter' size='30' value='<?php print html_escape_request_var('rfilter');?>' onChange='applyGraphFilter()'>
					</td>
					<td>
						<?php print __('Graphs');?>
					</td>
					<td>
						<select id='graphs' onChange='applyGraphFilter()'>
							<?php
							if (sizeof($graphs_per_page)) {
							foreach ($graphs_per_page as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var('graphs') == $key) { print ' selected'; } print '>' . $value . "</option>\n";
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
							<input id='thumbnails' type='checkbox' onClick='applyGraphFilter()' <?php print ((get_request_var('thumbnails') == 'true') ? 'checked':'');?>>
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
							$graph_timespans[GT_CUSTOM] = __('Custom');
							$start_val = 0;
							$end_val = sizeof($graph_timespans);

							if (sizeof($graph_timespans)) {
								foreach($graph_timespans as $value => $text) {
									print "<option value='$value'"; if ($_SESSION['sess_current_timespan'] == $value) { print ' selected'; } print '>' . $text . "</option>\n";
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
							<input type='text' id='date1' size='18' value='<?php print (isset($_SESSION['sess_current_date1']) ? $_SESSION['sess_current_date1'] : '');?>'>
							<i id='startDate' class='calendar fa fa-calendar' title='<?php print __esc('Start Date Selector');?>'></i>
						</span>
					</td>
					<td>
						<?php print __('To');?>
					</td>
					<td>
						<span>
							<input type='text' id='date2' size='18' value='<?php print (isset($_SESSION['sess_current_date2']) ? $_SESSION['sess_current_date2'] : '');?>'>
							<i id='endDate' class='calendar fa fa-calendar' title='<?php print __esc('End Date Selector');?>'></i>
						</span>
					</td>
					<td>
						<span>
							<i class='shiftArrow fa fa-backward' onClick='timeshiftGraphFilterLeft()' title='<?php print __esc('Shift Time Backward');?>'></i>
							<select id='predefined_timeshift' name='predefined_timeshift' title='<?php print __esc('Define Shifting Interval');?>'>
								<?php
								$start_val = 1;
								$end_val = sizeof($graph_timeshifts)+1;
								if (sizeof($graph_timeshifts) > 0) {
									for ($shift_value=$start_val; $shift_value < $end_val; $shift_value++) {
										print "<option value='$shift_value'"; if ($_SESSION['sess_current_timeshift'] == $shift_value) { print ' selected'; } print '>' . title_trim($graph_timeshifts[$shift_value], 40) . "</option>\n";
									}
								}
								?>
							</select>
							<i class='shiftArrow fa fa-forward' onClick='timeshiftGraphFilterRight()' title='<?php print __esc('Shift Time Forward');?>'></i>
						</span>
					</td>
					<td>
						<span>
							<input id='tsrefresh' type='button' value='<?php print __esc('Refresh');?>' name='button_refresh_x' title='<?php print __esc('Refresh selected time span');?>' onClick='refreshGraphTimespanFilter()'>
							<input id='tsclear' type='button' value='<?php print __esc('Clear');?>' title='<?php print __esc('Return to the default time span');?>' onClick='clearGraphTimespanFilter()'>
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
						<select name='graph_start' id='graph_start' onChange='imageOptionsChanged("timespan")'>
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
						<select name='ds_step' id='ds_step' onChange="imageOptionsChanged('interval')">
							<?php
							foreach ($realtime_refresh as $interval => $text) {
								printf('<option value="%d"%s>%s</option>', $interval, $interval == $_SESSION['sess_realtime_dsstep'] ? ' selected="selected"' : '', $text);
							}
							?>
						</select>
					</td>
					<td>
						<input type='button' id='realtimeoff' value='<?php print __esc('Stop');?>'>
					</td>
					<td align='center' colspan='6'>
						<span id='countdown'></span>
					</td>
					<td>
						<input id='future' type='hidden' value='<?php print read_config_option('allow_graph_dates_in_future');?>'></input>
					</td>
				</tr>
			</table>
		</form>
		<script type='text/javascript'>

    	var refreshIsLogout=false;
		var refreshMSeconds=<?php print read_user_setting('page_refresh')*1000;?>;
		var graph_start=<?php print get_current_graph_start();?>;
		var graph_end=<?php print get_current_graph_end();?>;
		var timeOffset=<?php print date('Z');?>;
		var pageAction = '<?php print $action;?>';
		var graphPage  = '<?php print $page;?>';
		var date1Open = false;
		var date2Open = false;

		function initPage() {
			var msWidth = 100;
			$('#graph_template_id option').each(function() {
				if ($(this).textWidth() > msWidth) {
					msWidth = $(this).textWidth();
				}
				$('#graph_template_id').css('width', msWidth+120+'px');
			});

			$('#graph_template_id').hide().multiselect({
				height: 300,
				noneSelectedText: '<?php print __('All Graphs & Templates');?>',
				selectedText: function(numChecked, numTotal, checkedItems) {
					myReturn = numChecked + ' <?php print __('Templates Selected');?>';
					$.each(checkedItems, function(index, value) {
						if (value.value == '0') {
							myReturn='<?php print __('All Graphs & Templates');?>';
							return false;
						}
					});
					return myReturn;
				},
				checkAllText: '<?php print __('All');?>',
				uncheckAllText: '<?php print __('None');?>',
				uncheckall: function() {
					$(this).multiselect('widget').find(':checkbox:first').each(function() {
						$(this).prop('checked', true);
					});
				},
				open: function(event, ui) {
					$("input[type='search']:first").focus();
				},
				close: function(event, ui) {
					applyGraphFilter();
				},
				click: function(event, ui) {
					checked=$(this).multiselect('widget').find('input:checked').length;

					if (ui.value == 0) {
						if (ui.checked == true) {
							$('#graph_template_id').multiselect('uncheckAll');
							$(this).multiselect('widget').find(':checkbox:first').each(function() {
								$(this).prop('checked', true);
							});
						}
					}else if (checked == 0) {
						$(this).multiselect('widget').find(':checkbox:first').each(function() {
							$(this).click();
						});
					}else if ($(this).multiselect('widget').find('input:checked:first').val() == '0') {
						if (checked > 0) {
							$(this).multiselect('widget').find(':checkbox:first').each(function() {
								$(this).click();
								$(this).prop('disable', true);
							});
						}
					}
				}
			}).multiselectfilter({
				label: '<?php print __('Search');?>',
				placeholder: '<?php print __('Enter keyword');?>',
				width: msWidth
			});

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
			$.when(initPage())
			.pipe(function() {
				initializeGraphs();
			});
		});

		</script>
		<?php html_spikekill_js();?>
		</td>
	</tr>
	<?php
}

