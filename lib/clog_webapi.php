<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2015 The Cacti Group                                 |
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

function clog_get_graphs_from_datasource($local_data_id) {
	return array_rekey(db_fetch_assoc("SELECT DISTINCT graph_templates_graph.local_graph_id AS id,
		graph_templates_graph.title_cache AS name
		FROM (graph_templates_graph
		INNER JOIN graph_templates_item
		ON graph_templates_graph.local_graph_id=graph_templates_item.local_graph_id)
		INNER JOIN data_template_rrd
		ON graph_templates_item.task_item_id=data_template_rrd.id
		WHERE graph_templates_graph.local_graph_id>0
		AND data_template_rrd.local_data_id=$local_data_id"), 'id', 'name');
}

function clog_purge_logfile() {
	global $config;

	$logfile = read_config_option('path_cactilog');

	if ($logfile == '') {
		$logfile = './log/cacti.log';
	}

	if (file_exists($logfile)) {
		if (is_writable($logfile)) {
			$timestamp = date('m/d/Y h:i:s A');
			$log_fh = fopen($logfile, 'w');
			fwrite($log_fh, $timestamp . " - WEBUI: Cacti Log Cleared from Web Management Interface\n");
			fclose($log_fh);
			raise_message('clog_purged');
		}else{
			raise_message('clog_permissions');
		}
	}else{
		raise_message('clog_missing');
	}
}

function clog_view_logfile() {
	global $config, $colors, $log_tail_lines, $page_refresh_interval, $refresh;

	$logfile = read_config_option('path_cactilog');

	if ($logfile == '') {
		$logfile = './log/cacti.log';
	}

	/* helps determine output color */
	$linecolor = true;

	input_validate_input_number(get_request_var_request('tail_files'));
	input_validate_input_number(get_request_var_request('message_type'));
	input_validate_input_number(get_request_var_request('refresh'));
	input_validate_input_number(get_request_var_request('reverse'));

	/* enable page refreshes */
	kill_session_var('custom');

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear'])) {
		kill_session_var('sess_clog_tail_lines');
		kill_session_var('sess_clog_message_type');
		kill_session_var('sess_clog_filter');
		kill_session_var('sess_clog_refresh');
		kill_session_var('sess_clog_reverse');

		unset($_REQUEST['tail_lines']);
		unset($_REQUEST['message_type']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['refresh']);
		unset($_REQUEST['reverse']);
	}

	load_current_session_value('tail_lines', 'sess_clog_tail_lines', read_config_option('num_rows_log'));
	load_current_session_value('message_type', 'sess_clog_message_type', '-1');
	load_current_session_value('filter', 'sess_clog_filter', '');
	load_current_session_value('refresh', 'sess_clog_refresh', read_config_option('log_refresh_interval'));
	load_current_session_value('reverse', 'sess_clog_reverse', 1);

	$_REQUEST['page_referrer'] = 'view_logfile';
	load_current_session_value('page_referrer', 'page_referrer', 'view_logfile');

	$refresh['seconds'] = $_REQUEST['refresh'];
	$refresh['page']    = $config['url_path'] . 'clog.php';
	if ((isset($_REQUEST['purge_continue'])) && (clog_admin())) clog_purge_logfile();

	general_header();

	if ((isset($_REQUEST['purge'])) && (clog_admin())) {
		html_start_box('<strong>Purge</strong>', '50%', '', '3', 'center', '');

		print "	
			<form action='clog.php' autocomplete='off' method='post'>
			<tr>
				<td class='textArea'>
					<p>Click \"Continue\" to purge the cacti log file.<br><br><br>Note: If logging is set to Cacti and Syslog, the log information will remain in Syslog.</p>
				</td>
			</tr>
			<tr>
				<td colspan='2' align='right' bgcolor='#eaeaea'>
					<input id='cancel' type='button' value='Cancel'>&nbsp
					<input id='pc' type='button' name='purge_continue' value='Continue' title='Purge cacti.log'>
					<script type='text/javascript'>
					$('#pc').click(function() {
						url='?purge_continue=1&header=false';
						$.get(location.pathname+url, function(data) {
							$('#main').html(data);
							applySkin();
						});
					});

					$('#cancel').click(function() {
						url='?header=false';
						$.get(location.pathname+url, function(data) {
							$('#main').html(data);
							applySkin();
						});
					});

					$(function() {
						applySkin();
					});
					</script>
				</td>
			</tr>
			";
		html_end_box();
		return;	
	}

	html_start_box('<strong>Log File Filters</strong>', '100%', $colors['header'], '3', 'center', '');
	filter();
	html_end_box();

	/* read logfile into an array and display */
	$logcontents   = tail_file($logfile, $_REQUEST['tail_lines'], $_REQUEST['message_type'], $_REQUEST['filter']);
	$exclude_regex = read_config_option('clog_exclude', true);

	if ($_REQUEST['reverse'] == 1) {
		$logcontents = array_reverse($logcontents);
	}

	if (!clog_admin()) {
		if (strlen($exclude_regex)) {
			$ad_filter = ' - Admin Filter in Affect';
		}else{
			$ad_filter = ' - No Admin Filter in Affect';
		}
	}else{
		$ad_filter = ' - Admin View';
	}

	if ($_REQUEST['message_type'] > 0) {
		$start_string = '<strong>Log File</strong> [Total Lines: ' . sizeof($logcontents) . $ad_filter . ' - Additional Filter in Affect]';
	}else{
		$start_string = '<strong>Log File</strong> [Total Lines: ' . sizeof($logcontents) . $ad_filter . ' - No Other Filter in Affect]';
	}

	html_start_box($start_string, '100%', $colors['header'], '3', 'center', '');

	$i = 0;
	$j = 0;
	$linecolor = false;

	foreach ($logcontents as $item) {
		$host_start = strpos($item, 'Device[');
		$ds_start   = strpos($item, 'DS[');

		$new_item = '';

		if ((!$host_start) && (!$ds_start)) {
			$new_item = $item;
		}else{
			while ($host_start) {
				$host_end    = strpos($item, ']', $host_start);
				$host_id     = substr($item, $host_start+7, $host_end-($host_start+7));
				$new_item   .= substr($item, 0, $host_start + 7) . "<a href='" . $config['url_path'] . 'host.php?action=edit&id=' . $host_id . "'>" . substr($item, $host_start + 5, $host_end-($host_start + 7)) . '</a>';
				$host_description = db_fetch_cell("SELECT description FROM host WHERE id=$host_id");
				$new_item   .= '] Description[' . $host_description . '';
				$item        = substr($item, $host_end);
				$host_start  = strpos($item, 'Device[');
			}

			$ds_start = strpos($item, 'DS[');
			while ($ds_start) {
				$ds_end    = strpos($item, ']', $ds_start);
				$ds_id     = substr($item, $ds_start+3, $ds_end-($ds_start+3));
				$graph_ids = clog_get_graphs_from_datasource($ds_id);
				$graph_add = '&graph_add=';

				if (sizeof($graph_ids)) {
					$new_item  .= substr($item, 0, $ds_start + 3) .
						"<a href='" . $config['url_path'] . 'data_sources.php?action=ds_edit&id=' . $ds_id . "'>" . substr($item, $ds_start + 3, $ds_end-($ds_start + 3)) . '</a>' .
						"] Graphs[<a href='" . $config['url_path'] . 'graph_view.php?page=1&style=selective&action=preview';

					$i = 0;
					$titles = '';
					foreach($graph_ids as $key => $title) {
						$new_item .= '&graph_' . $key . '=' . $key;
						$graph_add .= ($i > 0 ? htmlspecialchars('%2C') : '') . $key;
						$i++;
						if (strlen($titles)) {
							$titles .= ",'" . $title . "'";
						}else{
							$titles .= "'"  . $title . "'";
						}
					}
					$new_item  .= $graph_add . "' title='View Graphs'>" . $titles . '</a>';
				}

				$item      = substr($item, $ds_end);
				$ds_start  = strpos($item, 'DS[');
			}

			$new_item = $new_item . $item;
		}

		/* get the background color */
		if ((substr_count($new_item, 'ERROR')) || (substr_count($new_item, 'FATAL'))) {
			$bgcolor = 'FF3932';
		}elseif (substr_count($new_item, 'WARN')) {
			$bgcolor = 'EACC00';
		}elseif (substr_count($new_item, ' SQL ')) {
			$bgcolor = '6DC8FE';
		}elseif (substr_count($new_item, 'DEBUG')) {
			$bgcolor = 'C4FD3D';
		}elseif (substr_count($new_item, 'STATS')) {
			$bgcolor = '96E78A';
		}else{
			if ($linecolor) {
				$bgcolor = 'CCCCCC';
			}else{
				$bgcolor = 'FFFFFF';
			}
			$linecolor = !$linecolor;
		}

		/* respect the exclusion filter */
		$show = true;
		if ((!clog_admin()) && (@preg_match($exclude_regex, $new_item))) {
			$show = false;
		}
		if ($show) {
		?>
		<tr bgcolor='#<?php print $bgcolor;?>'>
			<td>
				<?php print $new_item;?>
			</td>
		</tr>
		<?php
		$j++;
		$i++;
		}

		if ($j > 1000) {
			?>
			<tr class='even'>
				<td>
					<?php print '>>>>  LINE LIMIT OF 1000 LINES REACHED!!  <<<<';?>
				</td>
			</tr>
			<?php

			break;
		}
	}

	html_end_box();

	bottom_footer();
}

function filter() {
	global $page_refresh_interval, $log_tail_lines;
	?>
	<tr class='even'>
		<form name='form_logfile'>
		<td>
			<table cellpadding='2' cellspacing='0'>
				<tr>
					<td width='85' style='white-space: nowrap;'>
						Tail Lines
					</td>
					<td>
						<select id='tail_lines' name='tail_lines'>
							<?php
							foreach($log_tail_lines AS $tail_lines => $display_text) {
								print "<option value='" . $tail_lines . "'"; if ($_REQUEST['tail_lines'] == $tail_lines) { print ' selected'; } print '>' . $display_text . "</option>\n";
							}
							?>
						</select>
					</td>
					<td style='white-space: nowrap;'>
						Message Type
					</td>
					<td width='1'>
						<select id='message_type' name='message_type'>
							<option value='-1'<?php if ($_REQUEST['message_type'] == '-1') {?> selected<?php }?>>All</option>
							<option value='1'<?php if ($_REQUEST['message_type'] == '1') {?> selected<?php }?>>Stats</option>
							<option value='2'<?php if ($_REQUEST['message_type'] == '2') {?> selected<?php }?>>Warnings</option>
							<option value='3'<?php if ($_REQUEST['message_type'] == '3') {?> selected<?php }?>>Errors</option>
							<option value='4'<?php if ($_REQUEST['message_type'] == '4') {?> selected<?php }?>>Debug</option>
							<option value='5'<?php if ($_REQUEST['message_type'] == '5') {?> selected<?php }?>>SQL Calls</option>
						</select>
					</td>
					<td>
						<input type='button' id='go' name='go' value='Go' alt='Go' border='0' align='absmiddle'>
					</td>
					<td>
						<input type='button' id='clear' name='clear' value='Clear' alt='Clear' border='0' align='absmiddle'>
					</td>
					<td>
						<?php if (clog_admin()) {?><input type='button' id='purge' name='purge' value='Purge' alt='Purge' border='0' align='absmiddle'><?php }?>
					</td>
				</tr>
				<tr>
					<td width='85'>
						Refresh
					</td>
					<td>
						<select id='refresh' name='refresh'>
							<?php
							foreach($page_refresh_interval AS $seconds => $display_text) {
								print "<option value='" . $seconds . "'"; if ($_REQUEST['refresh'] == $seconds) { print ' selected'; } print '>' . $display_text . "</option>\n";
							}
							?>
						</select>
					</td>
					<td style='white-space: nowrap;'>
						Display Order
					</td>
					<td>
						<select id='reverse' name='reverse'>
							<option value='1'<?php if ($_REQUEST['reverse'] == '1') {?> selected<?php }?>>Newest First</option>
							<option value='2'<?php if ($_REQUEST['reverse'] == '2') {?> selected<?php }?>>Oldest First</option>
						</select>
					</td>
				</tr>
			</table>
			<table cellpadding='2' cellspacing='0'>
				<tr>
					<td width='85'>
						SearchRegex
					</td>
					<td>
						<input id='filter' type='text' name='filter' size='75' value='<?php print $_REQUEST['filter'];?>'>
					</td>
				</tr>
			</table>
			<script type='text/javascript'>
			$('#filter').change(function() {
				refreshFilter();
			});

			$('#reverse').change(function() {
				refreshFilter();
			});

			$('#refresh').change(function() {
				refreshFilter();
			});

			$('#message_type').change(function() {
				refreshFilter();
			});

			$('#tail_lines').change(function() {
				refreshFilter();
			});

			$('#go').click(function() {
				refreshFilter();
			});

			$('#clear').click(function() {
				clearFilter();
			});

			$('#purge').click(function() {
				var url='?purge=1&header=false';
				$.get(basename(location.pathname)+url, function(data) {
					$('#main').html(data);
					applySkin();
				});
			});

			function clearFilter() {
				var url='?clear=1&header=false';
				$.get(basename(location.pathname)+url, function(data) {
					$('#main').html(data);
					applySkin();
				});
			}

			function refreshFilter() {
				refreshMSeconds=$('#refresh').val()*1000;

				var url='?filter='+ $('#filter').val()+
					'&reverse='+$('#reverse').val()+
					'&refresh='+$('#refresh').val()+
					'&message_type='+$('#message_type').val()+
					'&tail_lines='+$('#tail_lines').val()+
					'&header=false';

				$.get(basename(location.pathname)+url, function(data) {
					$('#main').html(data);
					applySkin();
				});
			}
			</script>
		</form>
		</td>
	</tr>
	<?php
}


