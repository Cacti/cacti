<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007-2015 The Cacti Group                                 |
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

$device_actions = array(
	1 => 'Add Device'
);

if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'discover') {
	print "<div id='findhosts'>";
	html_start_box('<strong>Discovery Results</strong>', '100%', $colors['header'], '3', 'center', '');
	echo '<pre>';
	print "Executiong FindHosts\n";

	//print shell_exec("php -q " . $config['base_path'] . '/plugins/discovery/findhosts.php --force --debug');
	echo "</pre>";
	html_end_box();
	print "</div>\n";
}

define('MAX_DISPLAY_PAGES', 21);

$os_arr     = array_rekey(db_fetch_assoc('SELECT DISTINCT os FROM automation_devices'), 'os', 'os');
$status_arr = array('Down', 'Up');

/* ================= input validation ================= */
input_validate_input_number(get_request_var('page'));
input_validate_input_number(get_request_var('rows'));
/* ==================================================== */

/* clean up status string */
if (isset($_REQUEST['status'])) {
	$_REQUEST['status'] = sanitize_search_string(get_request_var('status'));
}

/* clean up snmp string */
if (isset($_REQUEST['snmp'])) {
	$_REQUEST['snmp'] = sanitize_search_string(get_request_var('snmp'));
}

/* clean up os string */
if (isset($_REQUEST['os'])) {
	$_REQUEST['os'] = sanitize_search_string(get_request_var('os'));
}

/* clean up filter string */
if (isset($_REQUEST['filter'])) {
	$_REQUEST['filter'] = sanitize_search_string(get_request_var('filter'));
}

/* clean up sort_column */
if (isset($_REQUEST['sort_column'])) {
	$_REQUEST['sort_column'] = sanitize_search_string(get_request_var('sort_column'));
}

/* clean up search string */
if (isset($_REQUEST['sort_direction'])) {
	$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var('sort_direction'));
}

/* if the user pushed the 'clear' button */
if (isset($_REQUEST['clear'])) {
	kill_session_var('sess_autom_current_page');
	kill_session_var('sess_autom_status');
	kill_session_var('sess_autom_snmp');
	kill_session_var('sess_autom_os');
	kill_session_var('sess_autom_filter');
	kill_session_var('sess_default_rows');
	kill_session_var('sess_autom_sort_column');
	kill_session_var('sess_autom_sort_direction');

	unset($_REQUEST['page']);
	unset($_REQUEST['status']);
	unset($_REQUEST['snmp']);
	unset($_REQUEST['os']);
	unset($_REQUEST['filter']);
	unset($_REQUEST['rows']);
	unset($_REQUEST['sort_column']);
	unset($_REQUEST['sort_direction']);
}else{
	$changed = 0;
	$changed += check_changed('snmp',   'sess_autom_snmp');
	$changed += check_changed('status', 'sess_autom_status');
	$changed += check_changed('os',     'sess_autom_os');
	$changed += check_changed('rows',   'sess_default_rows');
	$changed += check_changed('filter', 'sess_autom_filter');

	if ($changed) {
		$_REQUEST['page'] = 1;
	}
}

/* remember these search fields in session vars so we don't have to keep passing them around */
load_current_session_value('page', 'sess_autom_current_page', '1');
load_current_session_value('status', 'sess_autom_status', '');
load_current_session_value('snmp', 'sess_autom_snmp', '');
load_current_session_value('os', 'sess_autom_os', '');
load_current_session_value('filter', 'sess_autom_filter', '');
load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));
load_current_session_value('sort_column', 'sess_autom_sort_column', 'hostname');
load_current_session_value('sort_direction', 'sess_autom_sort_direction', 'ASC');

$sql_where  = '';
$status     = get_request_var_request('status');
$snmp       = get_request_var_request('snmp');
$os         = get_request_var_request('os');
$filter     = get_request_var_request('filter');

if ($status == 'Down') {
	$sql_where .= 'WHERE up=0';
}else if ($status == 'Up') {
	$sql_where .= 'WHERE up=1';
}

if ($snmp == 'Down') {
	$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . 'snmp=0';
}else if ($snmp == 'Up') {
	$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . 'snmp=1';
}

if ($os != '' && in_array($os, $os_arr)) {
	$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . "os='$os'";
}

if ($filter != '') {
	$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . "(hostname LIKE '%$filter%' OR ip LIKE '%$filter%')";
}

if (isset($_REQUEST['export'])) {
	$result = db_fetch_assoc("SELECT * FROM automation_devices $sql_where order by hash");

	header('Content-type: application/csv');
	header('Content-Disposition: attachment; filename=discovery_results.csv');
	print "Host,IP,System Name,System Location,System Contact,System Description,OS,Uptime,SNMP,Status\n";

	foreach ($result as $host) {
		if ($host['sysUptime'] != 0) {
			$days = intval($host['sysUptime']/8640000);
			$hours = intval(($host['sysUptime'] - ($days * 8640000)) / 360000);
			$uptime = $days . ' days ' . $hours . ' hours';
		} else {
			$uptime = '';
		}
		foreach($host as $h=>$r) {
			$host['$h'] = str_replace(',','',$r);
		}
		print ($host['hostname'] == '' ? 'Not Detected':$host['hostname']) . ',';
		print $host['ip'] . ',';
		print export_data($host['sysName']) . ',';
		print export_data($host['sysLocation']) . ',';
		print export_data($host['sysContact']) . ',';
		print export_data($host['sysDescr']) . ',';
		print export_data($host['os']) . ',';
		print export_data($uptime) . ',';
		print ($host['snmp'] == 1 ? 'Up':'Down') . ',';
		print ($host['up'] == 1 ? 'Up':'Down') . "\n";
	}
	exit;
}

top_header();

$total_rows = db_fetch_cell("SELECT
	COUNT(*)
	FROM automation_devices
	$sql_where");

$page    = get_request_var_request('page');
if (get_request_var_request('rows') == '-1') {
	$per_row = read_config_option('num_rows_table');
}else{
	$per_row = get_request_var_request('rows');
}

$sortby  = get_request_var_request('sort_column');
if ($sortby=='ip') {
	$sortby = 'INET_ATON(ip)';
}

$sql_query = "SELECT *
	FROM automation_devices
	$sql_where
	ORDER BY " . $sortby . ' ' . get_request_var_request('sort_direction') . '
	LIMIT ' . ($per_row*($page-1)) . ',' . $per_row;

$result = db_fetch_assoc($sql_query);

html_start_box('<strong>Discovery Filters</strong>', '100%', $colors['header'], '3', 'center', '');

?>
<tr class='even'>
	<td class='noprint'>
	<form id='form_devices' method='get' action='automation_devices.php'>
		<table class='filterTable'>
			<tr class='noprint'>
				<td>
					Search
				</td>
				<td>
					<input type='text' id='filter' size='25' value='<?php print get_request_var_request('filter');?>'>
				</td>
				<td>
					Status
				</td>
				<td>
					<select id='status' onChange='applyFilter()'>
						<option value='<?php if (get_request_var_request('status') == '') {?>' selected<?php }?>>Any</option>
						<?php
						if (sizeof($status_arr)) {
						foreach ($status_arr as $st) {
							print "<option value='" . $st . "'"; if (get_request_var_request('status') == $st) { print ' selected'; } print '>' . $st . "</option>\n";
						}
						}
						?>
					</select>
				</td>
				<td>
					OS
				</td>
				<td>
					<select id='os' onChange='applyFilter()'>
						<option value='<?php if (get_request_var_request('os') == '') {?>' selected<?php }?>>Any</option>
						<?php
						if (sizeof($os_arr)) {
						foreach ($os_arr as $st) {
							print "<option value='" . $st . "'"; if (get_request_var_request('os') == $st) { print ' selected'; } print '>' . $st . "</option>\n";
						}
						}
						?>
					</select>
				</td>
				<td>
					SNMP
				</td>
				<td>
					<select id='snmp' onChange='applyFilter()'>
						<option value='<?php if (get_request_var_request('snmp') == '') {?>' selected<?php }?>>Any</option>
						<?php
						if (sizeof($status_arr)) {
						foreach ($status_arr as $st) {
							print "<option value='" . $st . "'"; if (get_request_var_request('snmp') == $st) { print ' selected'; } print '>' . $st . "</option>\n";
						}
						}
						?>
					</select>
				</td>
				<td>
					Devices
				</td>
				<td>
					<select id='rows' onChange='applyFilter()'>
						<option value='-1'<?php if (get_request_var_request('rows') == '-1') {?> selected<?php }?>>Default</option>
						<?php
						if (sizeof($item_rows) > 0) {
						foreach ($item_rows as $key => $value) {
							print "<option value='" . $key . "'"; if (get_request_var_request('rows') == $key) { print ' selected'; } print '>' . $value . "</option>\n";
						}
						}
						?>
					</select>
				</td>
				<td>
					<input type='button' id='refresh' value='Go' title='Set/Refresh Filters'>
				</td>
				<td>
					<input type='button' id='clear' value='Clear' title='Reset fields to defaults'>
				</td>
				<td>
					<input type='button' id='export' value='Export' title='Export to a file'>
				</td>
				<td>
					<input type='button' id='discover' value='Discover' title='Force a Discovery Run'>
				</td>
				<td id='message'></td>
				<td id='page' value='<?php print $_REQUEST['page'];?>'>
			</tr>
		</table>
	</form>
	<script type='text/javascript'>

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_devices').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});

		$('#export').click(function() {
			document.location = 'automation_devices.php?export=1';
		});

		$('#discover').click(function(data) {
			$('#message').text('Running Discovery');
			pulsate('#message');
			$.get('automation_devices.php?action=discover&header=false', function(data) {
				$('#message').text('Finished');
				$('#main').html(data);
				applySkin();
			});
		});
	});
	
	function clearFilter() {
		$.get('automation_devices.php?header=false&clear=1', function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	function applyFilter() {
		strURL = 'automation_devices.php?header=false';
		strURL = strURL + '&status=' + $('#status').val();
		strURL = strURL + '&snmp=' + $('#snmp').val();
		strURL = strURL + '&os=' + $('#os').val();
		strURL = strURL + '&filter=' + $('#filter').val();
		strURL = strURL + '&rows=' + $('#rows').val();

		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	</script>
	</td>
</tr>
<?php
html_end_box();

html_start_box('', '100%', $colors['header'], '3', 'center', '');

/* generate page list */
$nav = html_nav_bar('automation_devices.php', MAX_DISPLAY_PAGES, get_request_var_request('page'), $per_row, $total_rows, 11, 'Devices', 'page', 'main');

print $nav;

$display_text = array(
	'hostname' => array('display' => 'Device Name', 'align' => 'left', 'sort' => 'ASC'),
	'ip' => array('display' => 'IP', 'align' => 'left', 'sort' => 'ASC'),
	'sysName' => array('display' => 'SNMP Name', 'align' => 'left', 'sort' => 'ASC'),
	'sysLocation' => array('display' => 'Location', 'align' => 'left', 'sort' => 'ASC'),
	'sysContact' => array('display' => 'Contact', 'align' => 'left', 'sort' => 'ASC'),
	'sysDescr' => array('display' => 'Description', 'align' => 'left', 'sort' => 'ASC'),
	'os' => array('display' => 'OS', 'align' => 'left', 'sort' => 'ASC'),
	'time' => array('display' => 'Uptime', 'align' => 'right', 'sort' => 'DESC'),
	'snmp' => array('display' => 'SNMP', 'align' => 'right', 'sort' => 'DESC'),
	'up' => array('display' => 'Status', 'align' => 'right', 'sort' => 'ASC'));

html_header_sort_checkbox($display_text, get_request_var_request('sort_column'), get_request_var_request('sort_direction'), false);

$snmp_version        = read_config_option('snmp_ver');
$snmp_port           = read_config_option('snmp_port');
$snmp_timeout        = read_config_option('snmp_timeout');
$snmp_username       = read_config_option('snmp_username');
$snmp_password       = read_config_option('snmp_password');
$max_oids            = read_config_option('max_get_size');
$ping_method         = read_config_option('ping_method');
$availability_method = read_config_option('availability_method');

$i=0;
$status = array('<font color=red>Down</font>','<font color=green>Up</font>');
if (sizeof($result)) {
	foreach($result as $host) {
		form_alternate_row('line' . $host['hash'], true);

		if ($host['sysUptime'] != 0) {
			$days = intval($host['sysUptime']/8640000);
			$hours = intval(($host['sysUptime'] - ($days * 8640000)) / 360000);
			$uptime = $days . 'd:' . $hours . 'h';
		} else {
			$uptime = '';
		}

		if ($host['hostname'] == '') {
			$host['hostname'] = 'Not Detected';
		}

		form_selectable_cell((strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($host['hostname'])) : htmlspecialchars($host['hostname'])), $host['hash']);
		form_selectable_cell((strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($host['ip'])) : htmlspecialchars($host['ip'])), $host['hash']);
		form_selectable_cell(snmp_data($host['sysName']), $host['hash'], '', 'text-align:left');
		form_selectable_cell(snmp_data($host['sysLocation']), $host['hash'], '', 'text-align:left');
		form_selectable_cell(snmp_data($host['sysContact']), $host['hash'], '', 'text-align:left');
		form_selectable_cell(snmp_data($host['sysDescr']), $host['hash'], '', 'text-align:left');
		form_selectable_cell(snmp_data($host['os']), $host['hash'], '', 'text-align:left');
		form_selectable_cell(snmp_data($uptime), $host['hash'], '', 'text-align:right');
		form_selectable_cell($status[$host['snmp']], $host['hash'], '', 'text-align:right');
		form_selectable_cell($status[$host['up']], $host['hash'], '', 'text-align:right');
		form_checkbox_cell($host['ip'], $host['hash']);
		form_end_row();
	}
}else{
	print "<tr class='even'><td colspan=11>No Devices Found</td></tr>";
}

print $nav;

html_end_box(false);

/* draw the dropdown containing a list of available actions for this form */
draw_actions_dropdown($device_actions);

print "</form>\n";

bottom_footer();

function snmp_data($item) {
	if ($item == '') {
		return 'N/A';
	}else{
		return title_trim(htmlspecialchars($item), read_config_option('max_title_length'));
	}
}

function export_data($item) {
	if ($item == '') {
		return 'N/A';
	}else{
		return $item;
	}
}

?>
