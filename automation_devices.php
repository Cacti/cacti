<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2016 The Cacti Group                                 |
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

set_default_action();

if (get_request_var('action') == 'purge') {
	get_filter_request_var('network');
	
	db_execute('TRUNCATE TABLE automation_devices' . (get_request_var('network') > 0 ? 'WHERE network_id=' . get_request_var('network'):''));

	header('Location: automation_devices.php?header=false');

	exit;
}

$os_arr     = array_rekey(db_fetch_assoc('SELECT DISTINCT os FROM automation_devices WHERE os IS NOT NULL AND os!=""'), 'os', 'os');
$status_arr = array('Down', 'Up');
$networks   = array_rekey(db_fetch_assoc('SELECT an.id, an.name 
	FROM automation_networks AS an
	INNER JOIN automation_devices AS ad
	ON an.id=ad.network_id 
	ORDER BY name'), 'id', 'name');

/* ================= input validation and session storage ================= */
$filters = array(
	'rows' => array(
		'filter' => FILTER_VALIDATE_INT, 
		'pageset' => true,
		'default' => read_config_option('num_rows_table')
		),
	'page' => array(
		'filter' => FILTER_VALIDATE_INT, 
		'default' => '1'
		),
	'filter' => array(
		'filter' => FILTER_CALLBACK, 
		'pageset' => true,
		'default' => '', 
		'options' => array('options' => 'sanitize_search_string')
		),
	'sort_column' => array(
		'filter' => FILTER_CALLBACK, 
		'default' => 'hostname', 
		'options' => array('options' => 'sanitize_search_string')
		),
	'sort_direction' => array(
		'filter' => FILTER_CALLBACK, 
		'default' => 'ASC', 
		'options' => array('options' => 'sanitize_search_string')
		),
	'status' => array(
		'filter' => FILTER_CALLBACK, 
		'pageset' => true,
		'default' => '', 
		'options' => array('options' => 'sanitize_search_string')
		),
	'network' => array(
		'filter' => FILTER_CALLBACK, 
		'pageset' => true,
		'default' => '', 
		'options' => array('options' => 'sanitize_search_string')
		),
	'snmp' => array(
		'filter' => FILTER_CALLBACK, 
		'pageset' => true,
		'default' => '', 
		'options' => array('options' => 'sanitize_search_string')
		),
	'os' => array(
		'filter' => FILTER_CALLBACK, 
		'pageset' => true,
		'default' => '', 
		'options' => array('options' => 'sanitize_search_string')
		)
);

validate_store_request_vars($filters, 'sess_autom');
/* ================= input validation ================= */

$sql_where  = '';
$status     = get_request_var('status');
$network    = get_request_var('network');
$snmp       = get_request_var('snmp');
$os         = get_request_var('os');
$filter     = get_request_var('filter');

if ($status == 'Down') {
	$sql_where .= 'WHERE up=0';
}else if ($status == 'Up') {
	$sql_where .= 'WHERE up=1';
}

if ($network > 0) {
	$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . 'network_id=' . $network;
}

if ($snmp == 'Down') {
	$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . 'snmp=0';
}else if ($snmp == 'Up') {
	$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . 'snmp=1';
}

if ($os != '-1' && in_array($os, $os_arr)) {
	$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . "os='$os'";
}

if ($filter != '') {
	$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . "(hostname LIKE '%$filter%' OR ip LIKE '%$filter%')";
}

if (isset_request_var('export')) {
	$result = db_fetch_assoc("SELECT * FROM automation_devices $sql_where order by INET_ATON(ip)");

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

$page    = get_request_var('page');
if (get_request_var('rows') == '-1') {
	$per_row = read_config_option('num_rows_table');
}else{
	$per_row = get_request_var('rows');
}

$sortby  = get_request_var('sort_column');
if ($sortby=='ip') {
	$sortby = 'INET_ATON(ip)';
}

$sql_query = "SELECT *, FROM_UNIXTIME(time) AS mytime
	FROM automation_devices
	$sql_where
	ORDER BY " . $sortby . ' ' . get_request_var('sort_direction') . '
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
					<input type='text' id='filter' size='25' value='<?php print get_request_var('filter');?>'>
				</td>
				<td>
					Network
				</td>
				<td>
					<select id='network' onChange='applyFilter()'>
						<option value='-1' <?php if (get_request_var('network') == -1) {?> selected<?php }?>>Any</option>
						<?php
						if (sizeof($networks)) {
						foreach ($networks as $key => $name) {
							print "<option value='" . $key . "'"; if (get_request_var('network') == $key) { print ' selected'; } print '>' . $name . "</option>\n";
						}
						}
						?>
					</select>
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
					<input type='button' id='purge' value='Purge' title='Purge Discovered Devices'>
				</td>
			</table>
			<table class='filterTable'>
				<td>
					Status
				</td>
				<td>
					<select id='status' onChange='applyFilter()'>
						<option value='-1' <?php if (get_request_var('status') == '') {?> selected<?php }?>>Any</option>
						<?php
						if (sizeof($status_arr)) {
						foreach ($status_arr as $st) {
							print "<option value='" . $st . "'"; if (get_request_var('status') == $st) { print ' selected'; } print '>' . $st . "</option>\n";
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
						<option value='-1' <?php if (get_request_var('os') == '') {?> selected<?php }?>>Any</option>
						<?php
						if (sizeof($os_arr)) {
						foreach ($os_arr as $st) {
							print "<option value='" . $st . "'"; if (get_request_var('os') == $st) { print ' selected'; } print '>' . $st . "</option>\n";
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
						<option value='-1' <?php if (get_request_var('snmp') == '') {?> selected<?php }?>>Any</option>
						<?php
						if (sizeof($status_arr)) {
						foreach ($status_arr as $st) {
							print "<option value='" . $st . "'"; if (get_request_var('snmp') == $st) { print ' selected'; } print '>' . $st . "</option>\n";
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
						<option value='-1' <?php if (get_request_var('rows') == '-1') {?> selected<?php }?>>Default</option>
						<?php
						if (sizeof($item_rows) > 0) {
						foreach ($item_rows as $key => $value) {
							print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . "</option>\n";
						}
						}
						?>
					</select>
				</td>
				<input type='hidden' id='page' value='<?php print get_request_var('page');?>'>
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

		$('#purge').click(function() {
			loadPageNoHeader('automation_devices.php?header=false&action=purge&network_id='+$('#network').val());
		});

		$('#export').click(function() {
			document.location = 'automation_devices.php?export=1';
		});
	});
	
	function clearFilter() {
		loadPageNoHeader('automation_devices.php?header=false&clear=1');
	}

	function applyFilter() {
		strURL  = 'automation_devices.php?header=false';
		strURL += '&status=' + $('#status').val();
		strURL += '&network=' + $('#network').val();
		strURL += '&snmp=' + $('#snmp').val();
		strURL += '&os=' + $('#os').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&rows=' + $('#rows').val();

		loadPageNoHeader(strURL);
	}

	</script>
	</td>
</tr>
<?php
html_end_box();

form_start('automation_devices.php', 'automation_devices');

html_start_box('', '100%', $colors['header'], '3', 'center', '');

/* generate page list */
$nav = html_nav_bar('automation_devices.php', MAX_DISPLAY_PAGES, get_request_var('page'), $per_row, $total_rows, 12, 'Devices', 'page', 'main');

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
	'up' => array('display' => 'Status', 'align' => 'right', 'sort' => 'ASC'),
	'mytime' => array('display' => 'Last Check', 'align' => 'right', 'sort' => 'DESC'));

html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

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
		form_alternate_row('line' . $host['ip'], true);

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

		form_selectable_cell((strlen(get_request_var('filter')) ? preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($host['hostname'])) : htmlspecialchars($host['hostname'])), $host['ip']);
		form_selectable_cell((strlen(get_request_var('filter')) ? preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($host['ip'])) : htmlspecialchars($host['ip'])), $host['ip']);
		form_selectable_cell(snmp_data($host['sysName']), $host['ip'], '', 'text-align:left');
		form_selectable_cell(snmp_data($host['sysLocation']), $host['ip'], '', 'text-align:left');
		form_selectable_cell(snmp_data($host['sysContact']), $host['ip'], '', 'text-align:left');
		form_selectable_cell(snmp_data($host['sysDescr']), $host['ip'], '', 'text-align:left');
		form_selectable_cell(snmp_data($host['os']), $host['ip'], '', 'text-align:left');
		form_selectable_cell(snmp_data($uptime), $host['ip'], '', 'text-align:right');
		form_selectable_cell($status[$host['snmp']], $host['ip'], '', 'text-align:right');
		form_selectable_cell($status[$host['up']], $host['ip'], '', 'text-align:right');
		form_selectable_cell(substr($host['mytime'],0,16), $host['ip'], '', 'text-align:right');
		form_checkbox_cell($host['ip'], $host['ip']);
		form_end_row();
	}
}else{
	print "<tr class='even'><td colspan=11>No Devices Found</td></tr>";
}

print $nav;

html_end_box(false);

/* draw the dropdown containing a list of available actions for this form */
draw_actions_dropdown($device_actions);

form_end();

bottom_footer();

function snmp_data($item) {
	if ($item == '') {
		return 'N/A';
	}else{
		return htmlspecialchars(str_replace(':',' ', $item));
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
