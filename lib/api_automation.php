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

function display_matching_hosts($rule, $rule_type, $url) {
	global $device_actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('host_template_id'));
	input_validate_input_number(get_request_var_request('hpage'));
	input_validate_input_number(get_request_var_request('host_status'));
	input_validate_input_number(get_request_var_request('rows'));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var_request('filter'));
	}

	/* clean up sort_column */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var_request('sort_column'));
	}

	/* clean up search string */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var_request('sort_direction'));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear_x'])) {
		kill_session_var('sess_automation_device_current_page');
		kill_session_var('sess_automation_device_filter');
		kill_session_var('sess_automation_device_host_template_id');
		kill_session_var('sess_automation_host_status');
		kill_session_var('sess_automation_rows');
		kill_session_var('sess_automation_host_sort_column');
		kill_session_var('sess_automation_host_sort_direction');

		unset($_REQUEST['hpage']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['host_template_id']);
		unset($_REQUEST['host_status']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
	}

	if ((!empty($_SESSION['sess_automation_host_status'])) && (!empty($_REQUEST['host_status']))) {
		if ($_SESSION['sess_automation_host_status'] != $_REQUEST['host_status']) {
			$_REQUEST['hpage'] = 1;
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('hpage', 'sess_automation_device_current_page', '1');
	load_current_session_value('filter', 'sess_automation_device_filter', '');
	load_current_session_value('host_template_id', 'sess_automation_device_host_template_id', '-1');
	load_current_session_value('host_status', 'sess_automation_host_status', '-1');
	load_current_session_value('rows', 'sess_automation_rows', read_config_option('num_rows_table'));
	load_current_session_value('sort_column', 'sess_automation_host_sort_column', 'description');
	load_current_session_value('sort_direction', 'sess_automation_host_sort_direction', 'ASC');

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST['rows'] == -1) {
		$_REQUEST['rows'] = read_config_option('num_rows_table');
	}

	?>
	<script type='text/javascript'>
	function applyViewDeviceFilterChange(objForm) {
		strURL = '<?php print $url;?>' + '&host_status=' + objForm.host_status.value;
		strURL = strURL + '&host_template_id=' + objForm.host_template_id.value;
		strURL = strURL + '&rows=' + objForm.rows.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		document.location = strURL;
	}
	</script>
	<?php

	html_start_box('<strong>Matching Devices</strong>', '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
			<form method='post' name='form_automation_host' action='<?php print htmlspecialchars($url);?>'>
				<table cellpadding='2' cellspacing='0'>
					<tr>
						<td width='50'>
							Type
						</td>
						<td>
							<select name='host_template_id' onChange='applyViewDeviceFilterChange(document.form_automation_host)'>
								<option value='-1'<?php if (get_request_var_request('host_template_id') == '-1') {?> selected<?php }?>>Any</option>
								<option value='0'<?php if (get_request_var_request('host_template_id') == '0') {?> selected<?php }?>>None</option>
								<?php
								$host_templates = db_fetch_assoc('select id,name from host_template order by name');
	
								if (sizeof($host_templates) > 0) {
								foreach ($host_templates as $host_template) {
									print "<option value='" . $host_template['id'] . "'"; if (get_request_var_request('host_template_id') == $host_template['id']) { print ' selected'; } print '>' . $host_template['name'] . "</option>\n";
								}
								}
								?>
							</select>
						</td>
						<td>
							Status
						</td>
						<td>
							<select name='host_status' onChange='applyViewDeviceFilterChange(document.form_automation_host)'>
								<option value='-1'<?php if (get_request_var_request('host_status') == '-1') {?> selected<?php }?>>Any</option>
								<option value='-3'<?php if (get_request_var_request('host_status') == '-3') {?> selected<?php }?>>Enabled</option>
								<option value='-2'<?php if (get_request_var_request('host_status') == '-2') {?> selected<?php }?>>Disabled</option>
								<option value='-4'<?php if (get_request_var_request('host_status') == '-4') {?> selected<?php }?>>Not Up</option>
								<option value='3'<?php if (get_request_var_request('host_status') == '3') {?> selected<?php }?>>Up</option>
								<option value='1'<?php if (get_request_var_request('host_status') == '1') {?> selected<?php }?>>Down</option>
								<option value='2'<?php if (get_request_var_request('host_status') == '2') {?> selected<?php }?>>Recovering</option>
								<option value='0'<?php if (get_request_var_request('host_status') == '0') {?> selected<?php }?>>Unknown</option>
							</select>
						</td>
						<td>
							<input id='refresh' type='button' value='Go'>
						</td>
						<td>
							<input id='clear' type='button' value='Clear'>
						</td>
					</tr>
					<tr>
						<td>
							Search
						</td>
						<td>
							<input type='text' id='filter' size='25' value='<?php print get_request_var_request('filter');?>'>
						</td>
						<td>
							Devices
						</td>
						<td>
							<select name='rows' onChange='applyViewDeviceFilterChange(document.form_automation_host)'>
								<option value='-1'<?php if (get_request_var_request('rows') == '-1') {?> selected<?php }?>>Default</option>
								<?php
								if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='". $key . "'"; if (get_request_var_request('rows') == $key) { print ' selected'; } print '>' . $value . '</option>\n';
								}
								}
								?>
							</select>
						</td>
					</tr>
				</table>
				<input type='hidden' id='page' value='<?php print $_REQUEST['page'];?>'>
			</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	if (strlen($_REQUEST['filter'])) {
		$sql_where = "WHERE (h.hostname LIKE '%%" . get_request_var_request('filter') . "%%' OR h.description LIKE '%%" . get_request_var_request('filter') . "%%' OR ht.name LIKE '%%" . get_request_var_request('filter') . "%%')";
	}else{
		$sql_where = '';
	}

	if (get_request_var_request('host_status') == '-1') {
		/* Show all items */
	}elseif (get_request_var_request('host_status') == '-2') {
		$sql_where .= (strlen($sql_where) ? " AND h.disabled='on'" : "WHERE h.disabled='on'");
	}elseif (get_request_var_request('host_status') == '-3') {
		$sql_where .= (strlen($sql_where) ? " AND h.disabled=''" : "WHERE h.disabled=''");
	}elseif (get_request_var_request('host_status') == '-4') {
		$sql_where .= (strlen($sql_where) ? " AND (h.status!='3' or h.disabled='on')" : "WHERE (h.status!='3' or h.disabled='on')");
	}else {
		$sql_where .= (strlen($sql_where) ? ' AND (h.status=' . get_request_var_request('host_status') . " AND h.disabled = '')" : "WHERE (h.status=" . get_request_var_request('host_status') . " AND h.disabled = '')");
	}

	if (get_request_var_request('host_template_id') == '-1') {
		/* Show all items */
	}elseif (get_request_var_request('host_template_id') == '0') {
		$sql_where .= (strlen($sql_where) ? ' AND h.host_template_id=0' : 'WHERE h.host_template_id=0');
	}elseif (!empty($_REQUEST['host_template_id'])) {
		$sql_where .= (strlen($sql_where) ? ' AND h.host_template_id=' . get_request_var_request('host_template_id') : 'WHERE h.host_template_id=' . get_request_var_request('host_template_id'));
	}

	html_start_box('', '100%', '', '3', 'center', '');

	$host_graphs       = array_rekey(db_fetch_assoc('SELECT host_id, count(*) as graphs FROM graph_local GROUP BY host_id'), 'host_id', 'graphs');
	$host_data_sources = array_rekey(db_fetch_assoc('SELECT host_id, count(*) as data_sources FROM data_local GROUP BY host_id'), 'host_id', 'data_sources');

	/* build magic query, for matching hosts JOIN tables host and host_template */
	$sql_query = 'SELECT h.id AS host_id, h.hostname, h.description, h.disabled, 
		h.status, ht.name AS host_template_name 
		FROM host AS h
		LEFT JOIN host_template AS ht
		ON (h.host_template_id=ht.id) ';

	$hosts = db_fetch_assoc($sql_query);

	/* get the WHERE clause for matching hosts */
	if (strlen($sql_where)) {
		$sql_filter = ' AND (' . build_matching_objects_filter($rule['id'], $rule_type) . ')';
	} else {
		$sql_filter = ' WHERE (' . build_matching_objects_filter($rule['id'], $rule_type) .')';
	}

	/* now we build up a new query for counting the rows */
	$rows_query = $sql_query . $sql_where . $sql_filter;
	$total_rows = sizeof(db_fetch_assoc($rows_query));

	$sortby = get_request_var_request('sort_column');
	if ($sortby=='hostname') {
		$sortby = 'INET_ATON(hostname)';
	}

	$sql_query = $rows_query .
		' ORDER BY ' . $sortby . ' ' . get_request_var_request('sort_direction') .
		' LIMIT ' . (get_request_var_request('rows')*(get_request_var_request('hpage')-1)) . ',' . get_request_var_request('rows');
	$hosts = db_fetch_assoc($sql_query);
	
	$nav = html_nav_bar(htmlspecialchars($url . '&filter=' . get_request_var_request('filter')), MAX_DISPLAY_PAGES, get_request_var_request('page'), get_request_var_request('rows'), $total_rows, 7, 'Devices', 'page', 'main');

	print $nav;

	$display_text = array(
		'description' => array('Description', 'ASC'),
		'hostname' => array('Hostname', 'ASC'),
		'status' => array('Status', 'ASC'),
		'host_template_name' => array('Device Template Name', 'ASC'),
		'id' => array('ID', 'ASC'),
		'nosort1' => array('Graphs', 'ASC'),
		'nosort2' => array('Data Sources', 'ASC'),
	);

	html_header_sort(
		$display_text, 
		get_request_var_request('sort_column'), 
		get_request_var_request('sort_direction'), 
		'1', 
		$url . '?action=edit&id=' . get_request_var_request('id') . '&page=' . get_request_var_request('page')
	);

	if (sizeof($hosts)) {
		foreach ($hosts as $host) {
			form_alternate_row('line' . $host['host_id'], true);
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars('host.php?action=edit&id=' . $host['host_id']) . "'>" .
				(strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", $host['description']) : $host['description']) . '</a>', $host['host_id']);
			form_selectable_cell((strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", $host['hostname']) : $host['hostname']), $host['host_id']);
			form_selectable_cell(get_colored_device_status(($host['disabled'] == 'on' ? true : false), $host['status']), $host['host_id']);
			form_selectable_cell((strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", $host['host_template_name']) : $host['host_template_name']), $host['host_id']);
			form_selectable_cell(round(($host['host_id']), 2), $host['host_id']);
			form_selectable_cell((isset($host_graphs[$host['host_id']]) ? $host_graphs[$host['host_id']] : 0), $host['host_id']);
			form_selectable_cell((isset($host_data_sources[$host['host_id']]) ? $host_data_sources[$host['host_id']] : 0), $host['host_id']);
			form_end_row();
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td colspan='8'><em>No Matching Devices</em></td></tr>";
	}
	html_end_box(true);

	print "</form>\n";
}



function display_matching_graphs($rule, $rule_type, $url) {
	global $graph_actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('host_id'));
	input_validate_input_number(get_request_var_request('rows'));
	input_validate_input_number(get_request_var_request('template_id'));
	input_validate_input_number(get_request_var_request('page'));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var_request('filter'));
	}

	/* clean up sort_column string */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var_request('sort_column'));
	}

	/* clean up sort_direction string */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var_request('sort_direction'));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear_x'])) {
		kill_session_var('sess_automation_graph_current_page');
		kill_session_var('sess_automation_graph_filter');
		kill_session_var('sess_automation_graph_sort_column');
		kill_session_var('sess_automation_graph_sort_direction');
		kill_session_var('sess_automation_graph_host_id');
		kill_session_var('sess_automation_rows');
		kill_session_var('sess_automation_graph_template_id');

		unset($_REQUEST['page']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
		unset($_REQUEST['host_id']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['template_id']);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_automation_graph_current_page', '1');
	load_current_session_value('filter', 'sess_automation_graph_filter', '');
	load_current_session_value('sort_column', 'sess_automation_graph_sort_column', 'title_cache');
	load_current_session_value('sort_direction', 'sess_automation_graph_sort_direction', 'ASC');
	load_current_session_value('host_id', 'sess_automation_graph_host_id', '-1');
	load_current_session_value('rows', 'sess_automation_rows', read_config_option('num_rows_table'));
	load_current_session_value('template_id', 'sess_automation_graph_template_id', '-1');

	/* if the number of rows is -1, set it to the default */
	if (get_request_var_request('rows') == -1) {
		$_REQUEST['rows'] = read_config_option('num_rows_table');
	}

	?>
	<script type='text/javascript'>
	<!--

	function applyGraphsFilterChange(objForm) {
		strURL = <?php print $url;?>'&host_id=' + objForm.host_id.value;
		strURL = strURL + '&rows=' + objForm.rows.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		strURL = strURL + '&template_id=' + objForm.template_id.value;
		document.location = strURL;
	}

	-->
	</script>
	<?php

	html_start_box('<strong>Matching Graphs</strong>', '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
			<form method='post' name='form_automation_graph' action='<?php print htmlspecialchars($url);?>'>
				<table cellpadding='2' cellspacing='0'>
					<tr>
						<td width='50'>
							Device
						</td>
						<td>
							<select id='host_id'>
								<option value='-1'<?php if (get_request_var_request('host_id') == '-1') {?> selected<?php }?>>Any</option>
								<option value='0'<?php if (get_request_var_request('host_id') == '0') {?> selected<?php }?>>None</option>
								<?php
								$hosts = get_allowed_hosts();
		
								if (sizeof($hosts) > 0) {
								foreach ($hosts as $host) {
									print "<option value=' " . $host['id'] . "'"; if (get_request_var_request('host_id') == $host['id']) { print ' selected'; } print '>' . title_trim($host['name'], 40) . "</option>\n";
								}
								}
								?>
							</select>
						</td>
						<td>
							Template
						</td>
						<td>
							<select id='template_id'>
								<option value='-1'<?php if (get_request_var_request('template_id') == '-1') {?> selected<?php }?>>Any</option>
								<option value='0'<?php if (get_request_var_request('template_id') == '0') {?> selected<?php }?>>None</option>
								<?php
								$templates = get_allowed_graph_templates();

								if (sizeof($templates) > 0) {
								foreach ($templates as $template) {
									print "<option value=' " . $template['id'] . "'"; if (get_request_var_request('template_id') == $template['id']) { print ' selected'; } print '>' . title_trim($template['name'], 40) . "</option>\n";
								}
								}
								?>
							</select>
						</td>
						<td>
							<input id='refresh' type='button' value='Go'>
						</td>
						<td>
							<input id='clear' type='button' value='Clear'>
						</td>
					</tr>
					<tr>
						<td>
							Search
						</td>
						<td>
							<input id='filter' type='text' size='25' value='<?php print get_request_var_request('filter');?>'>
						</td>
						<td>
							Devices
						</td>
						<td>
							<select id='rows'>
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
					</tr>
				</table>
				<input type='hidden' id='page' value='<?php print $_REQUEST['page'];?>'>
			</form>
		</td>
	</tr>
	<?php

	html_end_box(false);

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var_request('filter'))) {
		$sql_where = "WHERE (gtg.title_cache like '%%" . get_request_var_request('filter') . "%%'" .
			" OR gt.name like '%%" . get_request_var_request('filter') . "%%')";
	}else{
		$sql_where = '';
	}

	if (get_request_var_request('host_id') == '-1') {
		/* Show all items */
	}elseif (get_request_var_request('host_id') == '0') {
		$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' gl.host_id=0';
	}elseif (!empty($_REQUEST['host_id'])) {
		$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' gl.host_id=' . get_request_var_request('host_id');
	}

	if (get_request_var_request('template_id') == '-1') {
		/* Show all items */
	}elseif (get_request_var_request('template_id') == '0') {
		$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' gtg.graph_template_id=0';
	}elseif (!empty($_REQUEST['template_id'])) {
		$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') .' gtg.graph_template_id=' . get_request_var_request('template_id');
	}

	/* get the WHERE clause for matching graphs */
	$sql_filter = build_matching_objects_filter($rule['id'], $rule_type);

	html_start_box('', '100%', '', '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT COUNT(gtg.id)
		FROM graph_local AS gl
		INNER JOIN graph_templates_graph AS gtg
		ON gl.graph_template_id=gtg.id
		LEFT JOIN graph_templates 
		ON gl.graph_template_id=gt.id
		LEFT JOIN host AS h
		ON gl.host_id = h.id
		LEFT JOIN host_template AS ht
		ON (h.host_template_id=ht.id)
		$sql_where " . (strlen($sql_filter) ? "AND ($sql_filter)":""));

	$sql = "SELECT h.id AS host_id, h.hostname, h.description, 
		h.disabled, h.status, ht.name AS host_template_name, 
		gtg.id, gtg.local_graph_id, gtg.height, gtg.width, 
		gtg.title_cache, gt.name 
		FROM graph_local AS gl
		INNER JOIN graph_templates_graph AS gtg
		ON gl.graph_template_id=gtg.id
		LEFT JOIN graph_templates AS gt
		ON gl.graph_template_id=gt.id
		LEFT JOIN host AS h
		ON gl.host_id = h.id
		LEFT JOIN host_template AS ht
		ON h.host_template_id = ht.id
		$sql_where " . (strlen($sql_filter) ? "AND ($sql_filter)":'') . '
		ORDER BY ' . $_REQUEST['sort_column'] . ' ' . get_request_var_request('sort_direction') . '
		LIMIT ' . (get_request_var_request('rows')*(get_request_var_request('page')-1)) . ',' . get_request_var_request('rows');

	$graph_list = db_fetch_assoc($sql);

	$nav = html_nav_bar(htmlspecialchars($url . '&filter=' . get_request_var_request('filter')), MAX_DISPLAY_PAGES, get_request_var_request('page'), get_request_var_request('rows'), $total_rows, 8, 'Devices', 'page', 'main');

	$display_text = array(
		'description' => array('Device Description', 'ASC'),
		'hostname' => array('Hostname', 'ASC'),
		'host_template_name' => array('Device Template Name', 'ASC'),
		'status' => array('Status', 'ASC'),
		'title_cache' => array('Graph Title', 'ASC'),
		'local_graph_id' => array('Graph ID', 'ASC'),
		'name' => array('Graph Template Name', 'ASC'),
	);

	html_header_sort(
		$display_text, 
		get_request_var_request('sort_column'), 
		get_request_var_request('sort_direction'),
		'1', 
		$url . '?action=edit&id=' . get_request_var_request('id') . '&page=' . get_request_var_request('page')
	);

	if (sizeof($graph_list)) {
		foreach ($graph_list as $graph) {
			$template_name = ((empty($graph['name'])) ? '<em>None</em>' : $graph['name']);
			form_alternate_row_color('line' . $graph['local_graph_id'], true);

			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars("host.php?action=edit&id=" . $graph['host_id']) . "'>" .
				(strlen(get_request_var_request('filter')) ? reg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", $graph['description']) : $graph['description']) . '</a>', $graph['host_id']);
			form_selectable_cell((strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", $graph['hostname']) : $graph['hostname']), $graph['host_id']);
			form_selectable_cell((strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue;'>\\1</span>", $graph['host_template_name']) : $graph['host_template_name']), $graph['host_id']);
			form_selectable_cell(get_colored_device_status(($graph['disabled'] == 'on' ? true : false), $graph['status']), $graph['host_id']);

			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars('graphs.php?action=graph_edit&id=' . $graph['local_graph_id']) . "' title='" . htmlspecialchars($graph['title_cache']) . "'>" . ((get_request_var_request('filter') != '') ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", title_trim($graph['title_cache'], read_config_option('max_title_graph'))) : title_trim($graph['title_cache'], read_config_option('max_title_graph'))) . '</a>', $graph['local_graph_id']);
			form_selectable_cell($graph['local_graph_id'], $graph['local_graph_id']);
			form_selectable_cell(((get_request_var_request('filter') != '') ? preg_replace('(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue;'>\\1</span>", $template_name) : $template_name) . '</a>', $graph['local_graph_id']);
			form_end_row();
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print '<tr><td><em>No Graphs Found</em></td></tr>';
	}

	html_end_box(true);

	print "</form>\n";
}


function display_new_graphs($rule) {
	global $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('page'));
	/* ==================================================== */

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear_x'])) {
		kill_session_var('sess_automation_graph_current_page');

		unset($_REQUEST['page']);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_automation_graph_current_page', '1');

	$rule_items = array();
	$created_graphs = array();
	$created_graphs = get_created_graphs($rule);

	$total_rows = 0;
	$num_input_fields = 0;
	$num_visible_fields = 0;
	$row_limit = read_config_option('num_rows_table');

	/* if any of the settings changed, reset the page number */
	$changed = 0;
	$changed += check_changed('id',					'sess_automation_graph_rule_id');
	$changed += check_changed('snmp_query_id',		'sess_automation_graph_rule_snmp_query_id');

	if (!$changed) {
		$page = $_REQUEST['page'];
	}else{
		$page = 1;
	}

	$sql = 'SELECT snmp_query.id, snmp_query.name, snmp_query.xml_path FROM snmp_query WHERE snmp_query.id=' . $rule['snmp_query_id'];

	$snmp_query = db_fetch_row($sql);

	/*
	 * determine number of input fields, if any
	 * for a dropdown selection
	 */
	$xml_array = get_data_query_array($rule['snmp_query_id']);
	if ($xml_array != false) {
		/* loop through once so we can find out how many input fields there are */
		reset($xml_array['fields']);
		while (list($field_name, $field_array) = each($xml_array['fields'])) {
			if ($field_array['direction'] == 'input') {
				$num_input_fields++;

				if (!isset($total_rows)) {
					$sql = 'SELECT count(*) FROM host_snmp_cache WHERE snmp_query_id=' . $rule['snmp_query_id'] . ' ' .  "AND field_name='$field_name'";
					$total_rows = db_fetch_cell($sql);
				}
			}
		}
	}

	if (!isset($total_rows)) {
		$total_rows = 0;
	}

	html_start_box('<strong>Data Queries</strong> [ ' . $snmp_query['name'] . ']', '100%', '', '3', 'center', '');

	if ($xml_array != false) {
		$html_dq_header = '';
		$snmp_query_indexes = array();
		$sql = 'SELECT * FROM automation_graph_rule_items WHERE rule_id=' . $rule['id'] . ' ORDER BY sequence';
		$rule_items = db_fetch_assoc($sql);

		/*
		 * main sql
		 */
		if (isset($xml_array['index_order_type'])) {
			$sql_order = build_sort_order($xml_array['index_order_type'], 'automation_host');
			$sql_query = build_data_query_sql($rule) . ' ' . $sql_order;
		} else {
			$sql_query = build_data_query_sql($rule);
		}

		$sql_filter	= build_rule_item_filter($rule_items, 'a.');

		/* now we build up a new query for counting the rows */
		$rows_query = "SELECT * FROM ($sql_query) AS a " . ($sql_filter != '' ? "WHERE ($sql_filter)":'');
		$total_rows = sizeof(db_fetch_assoc($rows_query));

		if ($total_rows < (get_request_var_request('rows')*(get_request_var_request('page')-1))+1) {
			$_REQUEST['page'] = 1;
		}

		$sql_query = $rows_query . ' LIMIT ' . ($row_limit*(get_request_var_request('page')-1)) . ',' . $row_limit;

		$snmp_query_indexes = db_fetch_assoc($sql_query);

		$nav = html_nav_bar(htmlspecialchars('automation_graph_rules.php&filter=' . get_request_var_request('filter')), MAX_DISPLAY_PAGES, get_request_var_request('page'), $row_limit, $total_rows, 30, 'Data Queries', 'page', 'main');

		print $nav;

		/*
		 * print the Data Query table's header
		 * number of fields has to be dynamically determined
		 * from the Data Query used
		 */
		# we want to print the host name as the first column
		$new_fields['automation_host'] = array('name' => 'Hostname', 'direction' => 'input');
		$new_fields['status'] = array('name' => 'Device Status', 'direction' => 'input');
		$xml_array['fields'] = $new_fields + $xml_array['fields'];
		reset($xml_array['fields']);

		$field_names = get_field_names($rule['snmp_query_id']);
		array_unshift($field_names, array('field_name' => 'status'));
		array_unshift($field_names, array('field_name' => 'automation_host'));

		$display_text = array();
		while (list($field_name, $field_array) = each($xml_array['fields'])) {
			if ($field_array['direction'] == 'input') {
				foreach($field_names as $row) {
					if ($row['field_name'] == $field_name) {
						$display_text[] = $field_array['name'];
						break;
					}
				}
			}
		}

		html_header($display_text);

		if (!sizeof($snmp_query_indexes)) {
			print "<tr colspan='6'><td>There are no Devices that match this rule.</td></tr>\n";
		}else{
			print "<tr colspan='6'>" . $html_dq_header . "</tr>\n";
		}

		/*
		 * list of all entries
		 */
		$row_counter    = 0;
		$fields         = array_rekey($field_names, 'field_name', 'field_name');
		if (sizeof($snmp_query_indexes) > 0) {
			foreach($snmp_query_indexes as $row) {
				form_alternate_row("line$row_counter", true);

				if (isset($created_graphs{$row['host_id']}{$row['snmp_index']})) {
					$style = ' style="color: grey"';
				} else {
					$style = ' style="color: black"';
				}
				$column_counter = 0;
				reset($xml_array['fields']);
				while (list($field_name, $field_array) = each($xml_array['fields'])) {
					if ($field_array['direction'] == 'input') {
						if (in_array($field_name, $fields)) {
							if (isset($row[$field_name])) {
								if ($field_name == 'status') {
									form_selectable_cell(get_colored_device_status(($row['disabled'] == 'on' ? true : false), $row['status']), 'status');
								} else {
									print "<td><span id='text$row_counter" . '_' . $column_counter . "' $style>" . $row[$field_name] . "</span></td>";
								}
							}else{
								print "<td><span id='text$row_counter" . '_' . $column_counter . "' $style></span></td>";
							}
							$column_counter++;
						}
					}
				}
				print "</tr>\n";
				$row_counter++;
			}
		}
		if ($total_rows > $row_limit) {print $nav;}

	} else {
		print "<tr><td colspan='2' style='color: red;'>Error in data query</td></tr>\n";
	}

	print '</table>';
	print '<br>';

}


function display_matching_trees ($rule_id, $rule_type, $item, $url) {
	global $automation_tree_header_types;
	global $device_actions, $item_rows;

	cacti_log(__FUNCTION__ . " called: $rule_id/$rule_type", false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
	
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('host_template_id'));
	input_validate_input_number(get_request_var_request('page'));
	input_validate_input_number(get_request_var_request('host_status'));
	input_validate_input_number(get_request_var_request('rows'));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var_request('filter'));
	}

	/* clean up sort_column */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var_request('sort_column'));
	}

	/* clean up search string */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var_request('sort_direction'));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear_x'])) {
		kill_session_var('sess_automation_tree_current_page');
		kill_session_var('sess_automation_tree_filter');
		kill_session_var('sess_automation_tree_host_template_id');
		kill_session_var('sess_automation_tree_host_status');
		kill_session_var('sess_automation_tree_rows');
		kill_session_var('sess_automation_tree_sort_column');
		kill_session_var('sess_automation_tree_sort_direction');

		unset($_REQUEST['page']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['host_template_id']);
		unset($_REQUEST['host_status']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
	}

	if ((!empty($_SESSION['sess_automation_host_status'])) && (!empty($_REQUEST['host_status']))) {
		if ($_SESSION['sess_automation_host_status'] != $_REQUEST['host_status']) {
			$_REQUEST['page'] = 1;
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_automation_tree_current_page', '1');
	load_current_session_value('filter', 'sess_automation_tree_filter', '');
	load_current_session_value('host_template_id', 'sess_automation_tree_host_template_id', '-1');
	load_current_session_value('host_status', 'sess_automation_tree_host_status', '-1');
	load_current_session_value('rows', 'sess_automation_tree_rows', read_config_option('num_rows_table'));
	load_current_session_value('sort_column', 'sess_automation_tree_sort_column', 'description');
	load_current_session_value('sort_direction', 'sess_automation_tree_sort_direction', 'ASC');

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST['rows'] == -1) {
		$_REQUEST['rows'] = read_config_option('num_rows_table');
	}

	?>
	<script type='text/javascript'>
	<!--

	function applyViewDeviceFilterChange(objForm) {
		strURL = '<?php print $url;?>' + '&host_status=' + objForm.host_status.value;
		strURL = strURL + '&host_template_id=' + objForm.host_template_id.value;
		strURL = strURL + '&rows=' + objForm.rows.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		document.location = strURL;
	}

	-->
	</script>
	<?php

	print "<form method='post' name='form_automation_tree' action='" . htmlspecialchars($url) . "'>";
	html_start_box('<strong>Matching Items</strong>', '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
			<table cellpadding='2' cellspacing='0'>
				<tr>
					<td width='50'>
						Type
					</td>
					<td>
						<select name='host_template_id' onChange='applyViewDeviceFilterChange(document.form_automation_tree)'>
							<option value='-1'<?php if (get_request_var_request('host_template_id') == '-1') {?> selected<?php }?>>Any</option>
							<option value='0'<?php if (get_request_var_request('host_template_id') == '0') {?> selected<?php }?>>None</option>
							<?php
							$host_templates = db_fetch_assoc('select id,name from host_template order by name');

							if (sizeof($host_templates) > 0) {
							foreach ($host_templates as $host_template) {
								print "<option value='" . $host_template['id'] . "'"; if (get_request_var_request('host_template_id') == $host_template['id']) { print ' selected'; } print '>' . $host_template['name'] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						Status
					</td>
					<td>
						<select name='host_status' onChange='applyViewDeviceFilterChange(document.form_automation_tree)'>
							<option value='-1'<?php if (get_request_var_request('host_status') == '-1') {?> selected<?php }?>>Any</option>
							<option value='-3'<?php if (get_request_var_request('host_status') == '-3') {?> selected<?php }?>>Enabled</option>
							<option value='-2'<?php if (get_request_var_request('host_status') == '-2') {?> selected<?php }?>>Disabled</option>
							<option value='-4'<?php if (get_request_var_request('host_status') == '-4') {?> selected<?php }?>>Not Up</option>
							<option value='3'<?php if (get_request_var_request('host_status') == '3') {?> selected<?php }?>>Up</option>
							<option value='1'<?php if (get_request_var_request('host_status') == '1') {?> selected<?php }?>>Down</option>
							<option value='2'<?php if (get_request_var_request('host_status') == '2') {?> selected<?php }?>>Recovering</option>
							<option value='0'<?php if (get_request_var_request('host_status') == '0') {?> selected<?php }?>>Unknown</option>
						</select>
					</td>
					<td>
						<input type='image' src='images/button_go.gif' alt='Go' align='middle'>
					</td>
					<td>
						<input type='image' src='images/button_clear.gif' name='clear' alt='Clear' align='middle'>
					</td>
				</tr>
				<tr>
					<td>
						Search
					</td>
					<td>
						<input type='text' name='filter' size='25' value='<?php print get_request_var_request('filter');?>'>
					</td>
					<td style='white-space: nowrap;'>
						Data Queries
					</td>
					<td>
						<select name='rows' onChange='applyViewDeviceFilterChange(document.form_automation_tree)'>
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
				</tr>
			</table>
		</td>
	</tr>
	<?php

	html_end_box(false);
	form_hidden_box('page', '1', '');

	/* build magic query, for matching hosts JOIN tables host and host_template */
	$leaf_type = db_fetch_cell('SELECT leaf_type FROM automation_tree_rules WHERE id=' . $rule_id);
	if ($leaf_type == TREE_ITEM_TYPE_HOST) {
		$sql_tables = 'FROM host AS h
			LEFT JOIN host_template AS ht
			ON (h.host_template_id=ht.id)';

		$sql_where = 'WHERE 1=1 ';
	} elseif ($leaf_type == TREE_ITEM_TYPE_GRAPH) {
		$sql_tables = 'FROM host AS h
			LEFT JOIN host_template AS ht
			ON h.host_template_id=ht.id 
			LEFT JOIN graph_local AS gl
			ON h.id=gl.host_id 
			LEFT JOIN graph_templates AS gt
			ON (gl.graph_template_id=gt.id) 
			LEFT JOIN graph_templates_graph AS gtg
			ON (gl.id=gtg.local_graph_id)';

		$sql_where = 'WHERE gtg.local_graph_id>0 ';
	}

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var_request('filter'))) {
		$sql_where .= " AND (h.hostname LIKE '%%" . get_request_var_request('filter') . "%%' OR h.description LIKE '%%" . get_request_var_request('filter') . "%%' OR ht.name LIKE '%%" . get_request_var_request('filter') . "%%')";
	}

	if (get_request_var_request('host_status') == '-1') {
		/* Show all items */
	}elseif (get_request_var_request('host_status') == '-2') {
		$sql_where .= " AND h.disabled='on'";
	}elseif (get_request_var_request('host_status') == '-3') {
		$sql_where .= " AND h.disabled=''";
	}elseif (get_request_var_request('host_status') == '-4') {
		$sql_where .= " AND (h.status!='3' or h.disabled='on')";
	}else {
		$sql_where .= ' AND (h.status=' . get_request_var_request('host_status') . " AND h.disabled = '')";
	}

	if (get_request_var_request('host_template_id') == '-1') {
		/* Show all items */
	}elseif (get_request_var_request('host_template_id') == '0') {
		$sql_where .= ' AND h.host_template_id=0';
	}elseif (!empty($_REQUEST['host_template_id'])) {
		$sql_where .= ' AND h.host_template_id=' . get_request_var_request('host_template_id');
	}

	/* get the WHERE clause for matching hosts */
	$sql_filter = build_matching_objects_filter($rule_id, AUTOMATION_RULE_TYPE_TREE_MATCH);

	$templates = array();
	$sql_field = $item['field'] . ' AS source ';

	/* now we build up a new query for counting the rows */
	$rows_query = "SELECT h.id AS host_id, h.hostname, h.description, 
		h.disabled, h.status, ht.name AS host_template_name, $sql_field  
		$sql_tables
		$sql_where AND ($sql_filter)";

	$total_rows = sizeof(db_fetch_assoc($rows_query));

	$sortby = get_request_var_request('sort_column');
	if ($sortby=='h.hostname') {
		$sortby = 'INET_ATON(h.hostname)';
	}

	$sql_query = "$rows_query ORDER BY $sortby " . 
		get_request_var_request('sort_direction') . ' LIMIT ' . 
		(get_request_var_request('rows')*(get_request_var_request('page')-1)) . ',' . get_request_var_request('rows');

	$templates = db_fetch_assoc($sql_query);

	cacti_log(__FUNCTION__ . " templates sql: $sql_query", false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
	
	$nav = html_nav_bar(htmlspecialchars($url . '&filter=' . get_request_var_request('filter')), MAX_DISPLAY_PAGES, get_request_var_request('page'), get_request_var_request('rows'), $total_rows, 8, 'Devices', 'page', 'main');

	html_start_box('', '100%', '', '3', 'center', '');

	print $nav;

	$display_text = array(
		'description' => array('Description', 'ASC'),
		'hostname' => array('Hostname', 'ASC'),
		'host_template_name' => array('Device Template Name', 'ASC'),
		'status' => array('Status', 'ASC'),
		'source' => array($item['field'], 'ASC'),
		'result' => array('Result', 'ASC'),
	);

	html_header_sort(
		$display_text, 
		get_request_var_request('sort_column'), 
		get_request_var_request('sort_direction'), 
		'1', 
		$url . '?action=edit&id=' . get_request_var_request('id') . '&page=' . get_request_var_request('page')
	);

	$i = 0;
	if (sizeof($templates) > 0) {
		foreach ($templates as 	$template) {
			cacti_log(__FUNCTION__ . ' template: ' . serialize($template), false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
			$replacement = automation_string_replace($item['search_pattern'], $item['replace_pattern'], $template['source']);
			/* build multiline <td> entry */

			$repl = '';
			for ($j=0; sizeof($replacement); $j++) {
				if ($j > 0) {
					$repl .= '<br>';
					$repl .= str_pad('', $j*3, '-') . '&nbsp;' . array_shift($replacement);
				} else {
					$repl  = array_shift($replacement);
				}
			}
			cacti_log(__FUNCTION__ . " replacement: $repl", false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
			
			form_alternate_row('line' . $template['host_id'], true);
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars("host.php?action=edit&id=" . $template['host_id']) . "'>" .
				(strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", $template['description']) : $template['description']) . '</a>', $template['host_id']);
			form_selectable_cell((strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", $template['hostname']) : $template['hostname']), $template['host_id']);
			form_selectable_cell((strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", $template['host_template_name']) : $template['host_template_name']), $template['host_id']);
			form_selectable_cell(get_colored_device_status(($template['disabled'] == 'on' ? true : false), $template['status']), $template['host_id']);
			form_selectable_cell($template['source'], $template['host_id']);
			form_selectable_cell($repl, $template['host_id']);
			form_end_row();
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr><td colspan='6'><em>No Items</em></td></tr>";
	}
	html_end_box(true);

	print "</form>\n";
}

function display_match_rule_items($title, $rule_id, $rule_type, $module) {
	global $automation_op_array, $automation_oper, $automation_tree_header_types;

	$items = db_fetch_assoc("SELECT * 
		FROM automation_match_rule_items 
		WHERE rule_id=$rule_id 
		AND rule_type=$rule_type 
		ORDER BY sequence");

	html_start_box("<strong>$title</strong>", '100%', '', '3', 'center', $module . '?action=item_edit&id=' . $rule_id . '&rule_type=' . $rule_type);

	html_header(array('Item', 'Sequence', 'Operation', 'Field', 'Operator', 'Pattern', 'Actions'), 2, false);

	$i = 0;
	if (sizeof($items)) {
		foreach ($items as $item) {
			$operation = ($item['operation'] != 0) ? $automation_oper{$item['operation']} : '&nbsp;';

			form_alternate_row(); $i++;
			$form_data = '<td><a class="linkEditMain" href="' . htmlspecialchars($module . '?action=item_edit&id=' . $rule_id. '&item_id=' . $item['id'] . '&rule_type=' . $rule_type) . '">Item#' . $i . '</a></td>';
			$form_data .= '<td>' . 	$item['sequence'] . '</td>';
			$form_data .= '<td>' . 	$operation . '</td>';
			$form_data .= '<td>' . 	$item['field'] . '</td>';
			$form_data .= '<td>' . 	((isset($item['operator']) && $item['operator'] > 0) ? $automation_op_array['display']{$item['operator']} : '') . '</td>';
			$form_data .= '<td>' . 	$item['pattern'] . '</td>';
			$form_data .= '<td style="white-space:nowrap;">
				<a href="' . htmlspecialchars($module . '?action=item_movedown&item_id=' . $item['id'] . '&id=' . $rule_id . '&rule_type=' . $rule_type) . '">
					<img src="images/move_down.gif" border="0" alt="Move Down">
				</a>
				<a href="' . htmlspecialchars($module . '?action=item_moveup&item_id=' . $item['id'] .	'&id=' . $rule_id .	'&rule_type=' . $rule_type) . '">
					<img src="images/move_up.gif" border="0" alt="Move Up">
				</a></td>';

			$form_data .= '<td align="right" style="white-space:nowrap;">
				<a href="' . htmlspecialchars($module . '?action=item_remove&item_id=' . $item['id'] .	'&id=' . $rule_id .	'&rule_type=' . $rule_type) . '">
					<img src="images/delete_icon.gif" border="0" width="10" height="10" alt="Delete">
				</a></td>
			</tr>';

			print $form_data;
		}
	} else {
		print "<tr><td colspan='7'><em>No Device Selection Criteria</em></td></tr>\n";
	}

	html_end_box(true);
}


function display_graph_rule_items($title, $rule_id, $rule_type, $module) {
	global $automation_op_array, $automation_oper, $automation_tree_header_types;

	$items = db_fetch_assoc("SELECT * FROM automation_graph_rule_items WHERE rule_id=$rule_id ORDER BY sequence");

	html_start_box("<strong>$title</strong>", '100%', '', '3', 'center', $module . '?action=item_edit&id=' . $rule_id . '&rule_type=' . $rule_type);

	html_header(array('Item', 'Sequence', 'Operation', 'Field', 'Operator', 'Pattern', 'Actions'), 2, false);

	$i = 0;
	if (sizeof($items)) {
		foreach ($items as $item) {
			#print '<pre>'; print_r($item); print '</pre>';
			$operation = ($item['operation'] != 0) ? $automation_oper{$item['operation']} : '&nbsp;';

			form_alternate_row(); $i++;
			$form_data = '<td><a class="linkEditMain" href="' . htmlspecialchars($module . '?action=item_edit&id=' . $rule_id. '&item_id=' . $item['id'] . '&rule_type=' . $rule_type) . '">Item#' . $i . '</a></td>';
			$form_data .= '<td>' . 	$item['sequence'] . '</td>';
			$form_data .= '<td>' . 	$operation . '</td>';
			$form_data .= '<td>' . 	$item['field'] . '</td>';
			$form_data .= '<td>' . 	(($item['operator'] > 0 || $item['operator'] == '') ? $automation_op_array['display']{$item['operator']} : '') . '</td>';
			$form_data .= '<td>' . 	$item['pattern'] . '</td>';
			$form_data .= '<td style="white-space:nowrap;">
				<a href="' . htmlspecialchars($module . '?action=item_movedown&item_id=' . $item['id'] . '&id=' . $rule_id .	'&rule_type=' . $rule_type) . '">
					<img src="images/move_down.gif" border="0" alt="Move Down">
				</a>
				<a href="' . htmlspecialchars($module . '?action=item_moveup&item_id=' . $item['id'] .	'&id=' . $rule_id .	'&rule_type=' . $rule_type) . '">
					<img src="images/move_up.gif" border="0" alt="Move Up">
				</a></td>';

			$form_data .= '<td align="right" style="white-space:nowrap;">
				<a href="' . htmlspecialchars($module . '?action=item_remove&item_id=' . $item['id'] .	'&id=' . $rule_id .	'&rule_type=' . $rule_type) . '">
					<img src="images/delete_icon.gif" border="0" width="10" height="10" alt="Delete">
				</a></td>
			</tr>';

			print $form_data;
		}
	} else {
		print "<tr><td colspan='7'><em>No Graph Creation Criteria</em></td></tr>\n";
	}

	html_end_box(true);

}


function display_tree_rule_items($title, $rule_id, $item_type, $rule_type, $module) {
	global $automation_tree_header_types, $tree_sort_types, $host_group_types;

	$items = db_fetch_assoc("SELECT * FROM automation_tree_rule_items WHERE rule_id=$rule_id ORDER BY sequence");

	html_start_box("<strong>$title</strong>", '100%', '', '3', 'center', $module . '?action=item_edit&id=' . $rule_id . '&rule_type=' . $rule_type);

	html_header(array('Item', 'Sequence', 'Field Name', 'Sorting Type', 'Propagate Changes', 'Search Pattern', 'Replace Pattern', 'Actions'), 2, false);

	$i = 0;
	if (sizeof($items)) {
		foreach ($items as $item) {
			#print '<pre>'; print_r($item); print '</pre>';
			$field_name = ($item['field'] === AUTOMATION_TREE_ITEM_TYPE_STRING) ? $automation_tree_header_types[AUTOMATION_TREE_ITEM_TYPE_STRING] : $item['field'];

			form_alternate_row(); $i++;
			$form_data = '<td><a class="linkEditMain" href="' . htmlspecialchars($module . '?action=item_edit&id=' . $rule_id. '&item_id=' . $item['id'] . '&rule_type=' . $rule_type) . '">Item#' . $i . '</a></td>';
			$form_data .= '<td>' . 	$item['sequence'] . '</td>';
			$form_data .= '<td>' . 	$field_name . '</td>';
			$form_data .= '<td>' . 	$tree_sort_types{$item['sort_type']} . '</td>';
			$form_data .= '<td>' . 	($item['propagate_changes'] ? 'Yes' : 'No') . '</td>';
			$form_data .= '<td>' . 	$item['search_pattern'] . '</td>';
			$form_data .= '<td>' . 	$item['replace_pattern'] . '</td>';
			$form_data .= '<td style="white-space:nowrap;">
				<a href="' . htmlspecialchars($module . '?action=item_movedown&item_id=' . $item['id'] . '&id=' . $rule_id .	'&rule_type=' . $rule_type) . '">
					<img src="images/move_down.gif" border="0" alt="Move Down">
				</a>
				<a href="' . htmlspecialchars($module . '?action=item_moveup&item_id=' . $item['id'] .	'&id=' . $rule_id .	'&rule_type=' . $rule_type) . '">
					<img src="images/move_up.gif" border="0" alt="Move Up">
				</a></td>';

			$form_data .= '<td align="right" style="white-space:nowrap;">
				<a href="' . htmlspecialchars($module . '?action=item_remove&item_id=' . $item['id'] .	'&id=' . $rule_id .	'&rule_type=' . $rule_type) . '">
					<img src="images/delete_icon.gif" border="0" width="10" height="10" alt="Delete">
				</a></td></tr>';

			print $form_data;
		}
	} else {
		print "<tr><td><em>No Tree Creation Criteria</em></td></tr>\n";
	}

	html_end_box(true);
}


function duplicate_automation_graph_rules($_id, $_title) {
	global $fields_automation_graph_rules_edit1, $fields_automation_graph_rules_edit2, $fields_automation_graph_rules_edit3;

	$rule = db_fetch_row("SELECT * FROM automation_graph_rules WHERE id=$_id");
	$match_items = db_fetch_assoc("SELECT * FROM automation_match_rule_items WHERE rule_id=$_id AND rule_type=" . AUTOMATION_RULE_TYPE_GRAPH_MATCH);
	$rule_items = db_fetch_assoc("SELECT * FROM automation_graph_rule_items WHERE rule_id=$_id");

	$fields_automation_graph_rules_edit = $fields_automation_graph_rules_edit1 + $fields_automation_graph_rules_edit2 + $fields_automation_graph_rules_edit3;
	$save = array();
	reset($fields_automation_graph_rules_edit);
	while (list($field, $array) = each($fields_automation_graph_rules_edit)) {
		if (!preg_match('/^hidden/', $array['method'])) {
			$save[$field] = $rule[$field];
		}
	}

	/* substitute the title variable */
	$save['name'] = str_replace('<rule_name>', $rule['name'], $_title);
	/* create new rule */
	$save['enabled'] = '';	# no new rule accidentally taking action immediately
	$save['id'] = 0;
	$rule_id = sql_save($save, 'automation_graph_rules');

	/* create new match items */
	if (sizeof($match_items) > 0) {
		foreach ($match_items as $match_item) {
			$save = $match_item;
			$save['id'] = 0;
			$save['rule_id'] = $rule_id;
			$match_item_id = sql_save($save, 'automation_match_rule_items');
		}
	}

	/* create new rule items */
	if (sizeof($rule_items) > 0) {
		foreach ($rule_items as $rule_item) {
			$save = $rule_item;
			$save['id'] = 0;
			$save['rule_id'] = $rule_id;
			$rule_item_id = sql_save($save, 'automation_graph_rule_items');
		}
	}
}


function duplicate_automation_tree_rules($_id, $_title) {
	global $fields_automation_tree_rules_edit1, $fields_automation_tree_rules_edit2, $fields_automation_tree_rules_edit3;

	$rule = db_fetch_row("SELECT * FROM automation_tree_rules WHERE id=$_id");
	$match_items = db_fetch_assoc("SELECT * FROM automation_match_rule_items WHERE rule_id=$_id AND rule_type=" . AUTOMATION_RULE_TYPE_TREE_MATCH);
	$rule_items = db_fetch_assoc("SELECT * FROM automation_tree_rule_items WHERE rule_id=$_id");

	$fields_automation_tree_rules_edit = $fields_automation_tree_rules_edit1 + $fields_automation_tree_rules_edit2 + $fields_automation_tree_rules_edit3;
	$save = array();
	reset($fields_automation_tree_rules_edit);
	while (list($field, $array) = each($fields_automation_tree_rules_edit)) {
		if (!preg_match('/^hidden/', $array['method'])) {
			$save[$field] = $rule[$field];
		}
	}

	/* substitute the title variable */
	$save['name'] = str_replace('<rule_name>', $rule['name'], $_title);
	/* create new rule */
	$save['enabled'] = '';	# no new rule accidentally taking action immediately
	$save['id'] = 0;
	$rule_id = sql_save($save, 'automation_tree_rules');

	/* create new match items */
	if (sizeof($match_items) > 0) {
		foreach ($match_items as $rule_item) {
			$save = $rule_item;
			$save['id'] = 0;
			$save['rule_id'] = $rule_id;
			$rule_item_id = sql_save($save, 'automation_match_rule_items');
		}
	}

	/* create new action rule items */
	if (sizeof($rule_items) > 0) {
		foreach ($rule_items as $rule_item) {
			$save = $rule_item;
			/* make sure, that regexp is correctly masked */
			$save['search_pattern'] = mysql_real_escape_string($rule_item['search_pattern']);
			$save['replace_pattern'] = mysql_real_escape_string($rule_item['replace_pattern']);
			$save['id'] = 0;
			$save['rule_id'] = $rule_id;
			$rule_item_id = sql_save($save, 'automation_tree_rule_items');
		}
	}
}


function build_data_query_sql($rule) {
	cacti_log(__FUNCTION__ . ' called: ' . serialize($rule), false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

	$sql_query = '';

	$field_names = get_field_names($rule['snmp_query_id']);
	$sql_query  = 'SELECT h.hostname AS automation_host, host_id, h.disabled, h.status, snmp_query_id, snmp_index ';
	$num_visible_fields = sizeof($field_names);
	$i = 0;

	if (sizeof($field_names) > 0) {
		foreach($field_names as $column) {
			$field_name = $column['field_name'];
			$sql_query .= ", MAX(CASE WHEN field_name='$field_name' THEN field_value ELSE NULL END) AS '$field_name'";
			$i++;
		}
	}

	/* take matching hosts into account */
	$sql_where = build_matching_objects_filter($rule['id'], AUTOMATION_RULE_TYPE_GRAPH_MATCH);

	/* build magic query, for matching hosts JOIN tables host and host_template */
	$sql_query .= ' FROM host_snmp_cache AS hsc
		LEFT JOIN host AS h
		ON (hsc.host_id=h.id)
		LEFT JOIN host_template AS ht
		ON (h.host_template_id=ht.id)
		WHERE snmp_query_id=' . $rule['snmp_query_id'] . "
		AND ($sql_where)
		GROUP BY host_id, snmp_query_id, snmp_index";

	#print '<pre>'; print $sql_query; print'</pre>';
	cacti_log(__FUNCTION__ . ' returns: ' . $sql_query, false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

	return $sql_query;
}

function build_matching_objects_filter($rule_id, $rule_type) {
	cacti_log(__FUNCTION__ . " called rule id: $rule_id", false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
	
	$sql_filter = '';

	/* create an SQL which queries all host related tables in a huge join
	 * this way, we may add any where clause that might be added via
	 *  'Matching Device' match
	 */
	$sql = "SELECT * 
		FROM automation_match_rule_items
		WHERE rule_id=$rule_id
		AND rule_type=$rule_type
		ORDER BY sequence";

	$rule_items = db_fetch_assoc($sql);

	#print '<pre>Items: $sql<br>'; print_r($rule_items); print '</pre>';

	if (sizeof($rule_items)) {
		#	$sql_order = build_sort_order($xml_array['index_order_type'], 'automation_host');
		#	$sql_query = build_data_query_sql($rule);
		$sql_filter	= build_rule_item_filter($rule_items);
		#	print 'SQL Query: ' . $sql_query . '<br>';
		#	print 'SQL Filter: ' . $sql_filter . '<br>';
	} else {
		/* force empty result set if no host matching rule item present */
		$sql_filter = ' (1 != 1)';
	}

	cacti_log(__FUNCTION__ . ' returns: ' . $sql_filter, false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

	return $sql_filter;
}

function build_rule_item_filter($automation_rule_items, $prefix = '') {
	global $automation_op_array, $automation_oper;

	cacti_log(__FUNCTION__ . ' called: ' . serialize($automation_rule_items) . ", prefix: $prefix", false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
	
	$sql_filter = '';
	if(sizeof($automation_rule_items)) {
		$sql_filter = ' ';

		foreach($automation_rule_items as $automation_rule_item) {
			# AND|OR|(|)
			if ($automation_rule_item['operation'] != AUTOMATION_OPER_NULL) {
				$sql_filter .= ' ' . $automation_oper[$automation_rule_item['operation']];
			}
			# right bracket ')' does not come with a field
			if ($automation_rule_item['operation'] == AUTOMATION_OPER_RIGHT_BRACKET) {
				continue;
			}
			# field name
			if ($automation_rule_item['field'] != '') {
				$sql_filter .= (' ' . $prefix . $automation_rule_item['field']);
				#
				$sql_filter .= ' ' . $automation_op_array['op'][$automation_rule_item['operator']] . ' ';
				if ($automation_op_array['binary'][$automation_rule_item['operator']]) {
					$sql_filter .= ("'" . $automation_op_array['pre'][$automation_rule_item['operator']]  . mysql_real_escape_string($automation_rule_item['pattern']) . $automation_op_array['post'][$automation_rule_item['operator']] . "'");
				}
			}
		}
	}

	cacti_log(__FUNCTION__ . ' returns: ' . $sql_filter, false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

	return $sql_filter;
}

/*
 * build_sort_order
 * @arg $index_order	sort order given by e.g. xml_array[index_order_type]
 * @arg $default_order	default order if any
 * return				sql sort order string
 */
function build_sort_order($index_order, $default_order = '') {
	cacti_log(__FUNCTION__ . " called: $index_order/$default_order", false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
	
	$sql_order = $default_order;

	/* determine the sort order */
	if (isset($index_order)) {
		if ($index_order == 'numeric') {
			$sql_order .= ', CAST(snmp_index AS unsigned)';
		}else if ($index_order == 'alphabetic') {
			$sql_order .= ', snmp_index';
		}else if ($index_order == 'natural') {
			$sql_order .= ', INET_ATON(snmp_index)';
		}
	}

	/* if ANY order is requested */
	if (strlen($sql_order)) {
		$sql_order = 'ORDER BY ' . $sql_order;
	}

	cacti_log(__FUNCTION__ . " returns: $sql_order", false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

	return $sql_order;
}

/**
 * get an array of hosts matching a host_match rule
 * @param array $rule		- rule
 * @param int $rule_type	- rule type
 * @param string $sql_where - additional where clause
 * @return array			- array of matching hosts
 */
function get_matching_hosts($rule, $rule_type, $sql_where='') {
	cacti_log(__FUNCTION__ . ' called: ' . serialize($rule) . ' type: ' . $rule_type, false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
	
	/* build magic query, for matching hosts JOIN tables host and host_template */
	$sql_query = 'SELECT h.id AS host_id, h.hostname, h.description, 
		h.disabled, h.status, ht.name AS host_template_name 
		FROM host AS h
		LEFT JOIN host_template AS ht
		ON (h.host_template_id=ht.id) ';

	/* get the WHERE clause for matching hosts */
	$sql_filter = ' WHERE (' . build_matching_objects_filter($rule['id'], $rule_type) .')';
	if (strlen($sql_where)) {
		$sql_filter .= ' AND ' . $sql_where;
	}

	return db_fetch_assoc($sql_query . $sql_filter);
}


/**
 * get an array of graphs matching a graph_match rule
 * @param array $rule		- rule
 * @param int $rule_type	- rule type
 * @param string $sql_where - additional where clause
 * @return array			- matching graphs
 */
function get_matching_graphs($rule, $rule_type, $sql_where='') {
	cacti_log(__FUNCTION__ . ' called: ' . serialize($rule) . ' type: ' . $rule_type, false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

	$sql_query = 'SELECT h.id AS host_id, h.hostname, h.description, h.disabled, 
		h.status, ht.name AS host_template_name, gtg.id, 
		gtg.local_graph_id, gtg.height, gtg.width, gtg.title_cache, gt.name 
		FROM graph_local AS gl
		INNER JOIN graph_templates_graph AS gtg
		LEFT JOIN graph_templates AS gt
		ON (gl.graph_template_id=gt.id)
		LEFT JOIN host AS h
		ON (gl.host_id=h.id)
		LEFT JOIN host_template AS ht
		ON (h.host_template_id=ht.id)';
	
	/* get the WHERE clause for matching graphs */
	$sql_filter = 'WHERE gl.id=gtg.local_graph_id AND ' . build_matching_objects_filter($rule['id'], $rule_type);

	if (strlen($sql_where)) {
		$sql_filter .= ' AND ' . $sql_where;
	}

	return db_fetch_assoc($sql_query . $sql_filter);
}

/*
 * get_created_graphs
 * @arg $rule		provide snmp_query_id, graph_type_id
 * return			all graphs that have already been created for the given selection
 */
function get_created_graphs($rule) {
	cacti_log(__FUNCTION__ . ' called: ' . serialize($rule), false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
	
	$sql = 'SELECT sqg.id 
		FROM snmp_query_graph AS sqg
		WHERE sqg.snmp_query_id=' . $rule['snmp_query_id'] . ' 
		AND sqg.id=' . $rule['graph_type_id'];

	$snmp_query_graph_id = db_fetch_cell($sql);

	/* take matching hosts into account */
	$sql_where = build_matching_objects_filter($rule['id'], AUTOMATION_RULE_TYPE_GRAPH_MATCH);

	/* build magic query, for matching hosts JOIN tables host and host_template */
	$sql = "SELECT DISTINCT dl.host_id, dl.snmp_index 
		FROM (data_local AS dl,data_template_data AS dtd) 
		LEFT JOIN host As h
		ON (dl.host_id=h.id) 
		LEFT JOIN host_template AS ht
		ON (h.host_template_id=ht.id) 
		LEFT JOIN data_input_data AS did
		ON (dtd.id=did.data_template_data_id) 
		LEFT JOIN data_input_fields AS dif
		ON (did.data_input_field_id=dif.id) 
		WHERE dl.id=dtd.local_data_id 
		AND dif.type_code='output_type' 
		AND did.value='" . $snmp_query_graph_id . "' 
		AND ($sql_where)";

	$graphs = db_fetch_assoc($sql);

	# rearrange items to ease indexed access
	$items = array();
	if(sizeof($graphs)) {
		foreach ($graphs as $graph) {
			$items{$graph['host_id']}{$graph['snmp_index']} = $graph['snmp_index'];
		}
	}

	return $items;

}


function get_query_fields($table, $excluded_fields) {
	cacti_log(__FUNCTION__ . ' called', false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
	
	$sql = 'SHOW COLUMNS FROM ' . $table;
	$fields = array_rekey(db_fetch_assoc($sql), 'Field', 'Type');
	#print '<pre>'; print_r($fields); print '</pre>';
	# remove unwanted entries
	$fields = array_minus($fields, $excluded_fields);

	# now reformat entries for use with draw_edit_form
	if (sizeof($fields)) {
		foreach ($fields as $key => $value) {
			switch($table) {
			case 'host':
				$table = 'h';
				break;
			case 'host_template':
				$table = 'ht';
				break;
			case 'graph_templates':
				$table = 'gt';
				break;
			}

			# we want to know later which table was selected
			$new_key = $table . '.' . $key;
			# give the user a hint abou the data type of the column
			$new_fields[$new_key] = strtoupper($table) . ': ' . $key . ' - ' . $value;
		}
	}

	return $new_fields;
}


/*
 * get_field_names
 * @arg $snmp_query_id	snmp query id
 * return				all field names for that snmp query, taken from snmp_cache
 */
function get_field_names($snmp_query_id) {
	cacti_log(__FUNCTION__ . " called: $snmp_query_id", false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
	
	/* get the unique field values from the database */
	$sql = 'SELECT DISTINCT field_name FROM host_snmp_cache WHERE snmp_query_id=' . $snmp_query_id;
	$field_names = db_fetch_assoc($sql);

	return db_fetch_assoc($sql);
}

function array_to_list($array, $sql_column) {
	/* if the last item is null; pop it off */
	if ((empty($array{count($array)-1})) && (sizeof($array) > 1)) {
		array_pop($array);
	}

	if (count($array) > 0) {
		$sql = '(';

		for ($i=0;($i<count($array));$i++) {
			$sql .=  $array[$i][$sql_column];

			if (($i+1) < count($array)) {
				$sql .= ',';
			}
		}

		$sql .= ')';

		cacti_log(__FUNCTION__ . " returns: $sql", false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
		return $sql;
	}
}

function array_minus($big_array, $small_array) {
	# remove all unwanted fields
	if (sizeof($small_array)) {
		foreach($small_array as $exclude) {
			if (array_key_exists($exclude, $big_array)) {
				unset($big_array[$exclude]);
			}
		}
	}

	return $big_array;
}


function automation_string_replace($search, $replace, $target) {
	$repl = preg_replace('/' . $search . '/i', $replace, $target);
	return preg_split('/\\\\n/', $repl, -1, PREG_SPLIT_NO_EMPTY);
}


function global_item_edit($rule_id, $rule_item_id, $rule_type) {
	global $config, $fields_automation_match_rule_item_edit, $fields_automation_graph_rule_item_edit;
	global $fields_automation_tree_rule_item_edit, $automation_tree_header_types;
	global $automation_op_array;

	switch ($rule_type) {
		case AUTOMATION_RULE_TYPE_GRAPH_MATCH:
			$title = 'Device Match Rule';
			$item_table = 'automation_match_rule_items';
			$sql_and = ' AND rule_type=' . $rule_type;
			$tables = array ('host', 'host_templates');
			$automation_rule = db_fetch_row('SELECT * FROM automation_graph_rules WHERE id=' . $rule_id);

			$_fields_rule_item_edit = $fields_automation_match_rule_item_edit;
			$query_fields  = get_query_fields('host_template', array('id', 'hash'));
			$query_fields += get_query_fields('host', array('id', 'host_template_id'));

			$_fields_rule_item_edit['field']['array'] = $query_fields;
			$module = 'automation_graph_rules.php';
			break;

		case AUTOMATION_RULE_TYPE_GRAPH_ACTION:
			$title = 'Create Graph Rule';
			$tables = array(AUTOMATION_RULE_TABLE_XML);
			$item_table = 'automation_graph_rule_items';
			$sql_and = '';
			$automation_rule = db_fetch_row('SELECT * FROM automation_graph_rules WHERE id=' . $rule_id);

			$_fields_rule_item_edit = $fields_automation_graph_rule_item_edit;
			$xml_array = get_data_query_array($automation_rule['snmp_query_id']);
			reset($xml_array['fields']);
			$fields = array();
			if(sizeof($xml_array)) {
				foreach($xml_array['fields'] as $key => $value) {
					# ... work on all input fields
					if(isset($value['direction']) && (strtolower($value['direction']) == 'input')) {
						$fields[$key] = $key . ' - ' . $value['name'];
					}
				}
				$_fields_rule_item_edit['field']['array'] = $fields;
			}
			$module = 'automation_graph_rules.php';
			break;

		case AUTOMATION_RULE_TYPE_TREE_MATCH:
			$item_table = 'automation_match_rule_items';
			$sql_and = ' AND rule_type=' . $rule_type;
			$automation_rule = db_fetch_row('SELECT * FROM automation_tree_rules WHERE id=' . $rule_id);
			$_fields_rule_item_edit = $fields_automation_match_rule_item_edit;
			$query_fields  = get_query_fields('host_template', array('id', 'hash'));
			$query_fields += get_query_fields('host', array('id', 'host_template_id'));

			if ($automation_rule['leaf_type'] == TREE_ITEM_TYPE_HOST) {
				$title = 'Device Match Rule';
				$tables = array ('host', 'host_templates');
				#print '<pre>'; print_r($query_fields); print '</pre>';
			} elseif ($automation_rule['leaf_type'] == TREE_ITEM_TYPE_GRAPH) {
				$title = 'Graph Match Rule';
				$tables = array ('host', 'host_templates');
				# add some more filter columns for a GRAPH match
				$query_fields += get_query_fields('graph_templates', array('id', 'hash'));
				$query_fields += array('graph_templates_graph.title' => 'GRAPH_TEMPLATES_GRAPH: title - varchar(255)');
				$query_fields += array('graph_templates_graph.title_cache' => 'GRAPH_TEMPLATES_GRAPH: title_cache - varchar(255)');
				#print '<pre>'; print_r($query_fields); print '</pre>';
			}
			$_fields_rule_item_edit['field']['array'] = $query_fields;
			$module = 'automation_tree_rules.php';
			break;

		case AUTOMATION_RULE_TYPE_TREE_ACTION:
			$item_table = 'automation_tree_rule_items';
			$sql_and = '';
			$automation_rule = db_fetch_row('SELECT * FROM automation_tree_rules WHERE id=' . $rule_id);

			$_fields_rule_item_edit = $fields_automation_tree_rule_item_edit;
			$query_fields  = get_query_fields('host_template', array('id', 'hash'));
			$query_fields += get_query_fields('host', array('id', 'host_template_id'));

			/* list of allowed header types depends on rule leaf_type
			 * e.g. for a Device Rule, only Device-related header types make sense
			 */
			if ($automation_rule['leaf_type'] == TREE_ITEM_TYPE_HOST) {
				$title = 'Create Tree Rule (Device)';
				$tables = array ('host', 'host_templates');
				#print '<pre>'; print_r($query_fields); print '</pre>';
			} elseif ($automation_rule['leaf_type'] == TREE_ITEM_TYPE_GRAPH) {
				$title = 'Create Tree Rule (Graph)';
				$tables = array ('host', 'host_templates');
				# add some more filter columns for a GRAPH match
				$query_fields += get_query_fields('graph_templates', array('id', 'hash'));
				$query_fields += array('graph_templates_graph.title' => 'GRAPH_TEMPLATES_GRAPH: title - varchar(255)');
				$query_fields += array('graph_templates_graph.title_cache' => 'GRAPH_TEMPLATES_GRAPH: title_cache - varchar(255)');
				#print '<pre>'; print_r($query_fields); print '</pre>';
			}
			$_fields_rule_item_edit['field']['array'] = $query_fields;
			$module = 'automation_tree_rules.php';
			break;

	}

	if (!empty($rule_item_id)) {
		$automation_item = db_fetch_row("SELECT * FROM $item_table WHERE id=$rule_item_id $sql_and");

		$header_label = "[edit rule item for $title: " . $automation_rule['name'] . ']';
	}else{
		$header_label = "[new rule item for $title: " . $automation_rule['name'] . ']';
		$automation_item = array();
		$automation_item['sequence'] = get_sequence('', 'sequence', $item_table, 'rule_id=' . $rule_id . $sql_and);
	}

	print "<form method='post' action='" . $module . "' name='form_automation_global_item_edit'>";
	html_start_box("<strong>Rule Item</strong> $header_label", '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => inject_form_variables($_fields_rule_item_edit, (isset($automation_item) ? $automation_item : array()), (isset($automation_rule) ? $automation_rule : array()))
	));

	html_end_box();
}

/**
 * hook executed for a graph template
 * @param $host_id - the host to perform automation on
 * @param $graph_template_id - the graph_template_id to perform automation on
 */
function automation_hook_graph_template($host_id, $graph_template_id) {
	global $config;

	include_once($config['base_path'] . '/lib/api_automation.php');

	if (read_config_option('automation_graphs_enabled') == '') {
		cacti_log(__FUNCTION__ . ' Host[' . $host_id . '] - skipped: Graph Creation Switch is: ' . (read_config_option('automation_graphs_enabled') == '' ? 'off' : 'on') . ' graph template: ' . $graph_template_id, false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
		return;
	}

	execute_graph_template($host_id, $graph_template_id);
}


/**
 * hook executed for a new device on a tree
 * @param $data - data passed from hook
 */
function automation_hook_device_create_tree($data) {
	global $config;

	include_once($config['base_path'] . '/lib/api_automation.php');

	cacti_log(__FUNCTION__ . ' called: ' . serialize($data), false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
	
	if (read_config_option('automation_tree_enabled') == '') {
		cacti_log(__FUNCTION__ . ' Host[' . $data['id'] . '] - skipped: Tree Creation Switch is: ' . (read_config_option('automation_tree_enabled') == '' ? 'off' : 'on'), false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
		return;
	}

	execute_device_create_tree($data);

	/* make sure, the next plugin gets required $data */
	return($data);
}


/**
 * hook executed for a new graph on a tree
 * @param $data - data passed from hook
 */
function automation_hook_graph_create_tree($data) {
	global $config;

	include_once($config['base_path'] . '/lib/api_automation.php');

	cacti_log(__FUNCTION__ . ' called: ' . serialize($data), false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
	
	if (read_config_option('automation_tree_enabled') == '') {
		cacti_log(__FUNCTION__ . ' Graph[' . $data['id'] . '] - skipped: Tree Creation Switch is: ' . (read_config_option('automation_tree_enabled') == '' ? 'off' : 'on'), false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
		return;
	}

	execute_graph_create_tree($data);

	/* make sure, the next plugin gets required $data */
	return($data);
}

/**
 * run rules for a data query
 * @param $data - data passed from hook
 */
function execute_data_query($host_id, $snmp_query_id) {
	global $config;

	include_once($config['base_path'] . '/lib/api_automation.php');

	cacti_log(__FUNCTION__ . ' Host[' . $host_id . "] - start - data query: $snmp_query_id", false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

	# get all related rules for that data query that are enabled
	$sql = "SELECT agr.id, agr.name,
		agr.snmp_query_id, agr.graph_type_id
		FROM automation_graph_rules AS agr
		WHERE snmp_query_id=$snmp_query_id
		AND enabled='on'";

	$rules = db_fetch_assoc($sql);

	cacti_log(__FUNCTION__ . ' Host[' . $host_id . "] - sql: $sql - found: " . sizeof($rules), false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
	
	if (!sizeof($rules)) return;

	# now walk all rules and create graphs
	if (sizeof($rules)) {
		foreach ($rules as $rule) {
			cacti_log(__FUNCTION__ . ' Host[' . $host_id . '] - rule=' . $rule['id'] . ' name: ' . $rule['name'], false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

			/* build magic query, for matching hosts JOIN tables host and host_template */
			$sql_query = 'SELECT h.id AS host_id, h.hostname,
				h.description, ht.name AS host_template_name 
				FROM host AS h 
				LEFT JOIN host_template AS ht
				ON h.host_template_id=ht.id';

			/* get the WHERE clause for matching hosts */
			$sql_filter = build_matching_objects_filter($rule['id'], AUTOMATION_RULE_TYPE_GRAPH_MATCH);

			/* now we build up a new query for counting the rows */
			$rows_query = $sql_query . ' WHERE (' . $sql_filter . ') AND h.id=' . $host_id;

			$hosts = db_fetch_assoc($rows_query);

			cacti_log(__FUNCTION__ . ' Host[' . $data['host_id'] . "] - create sql: $rows_query matches:" . sizeof($hosts), false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

			if (!sizeof($hosts)) { continue; }

			create_dq_graphs($host_id, $snmp_query_id, $rule);
		}
	}
}


/**
 * run rules for a graph template
 * @param $data - data passed from hook
 */
function execute_graph_template($host_id, $graph_template_id) {
	global $config;

	include_once($config['base_path'] . '/lib/api_automation.php');
	include_once($config['base_path'] . '/lib/template.php');
	include_once($config['base_path'] . '/lib/api_automation_tools.php');
	include_once($config['base_path'] . '/lib/utility.php');

	$dataSourceId = '';

	cacti_log(__FUNCTION__ . ' called: Host[' . $host_id . '] - graph template: ' . $graph_template_id, false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

	# are there any input fields?
	if ($graph_template_id > 0) {
		$input_fields = getInputFields($graph_template_id);
		if (sizeof($input_fields)) {
			# do nothing for such graph templates
			return;
		}
	}

	# graph already present?
	$existsAlready = db_fetch_cell("SELECT id FROM graph_local WHERE graph_template_id=$graph_template_id AND host_id=$host_id");

	if ((isset($existsAlready)) && ($existsAlready > 0)) {
		$dataSourceId  = db_fetch_cell('SELECT
			data_template_rrd.local_data_id
			FROM graph_templates_item, data_template_rrd
			WHERE graph_templates_item.local_graph_id=' . $existsAlready . '
			AND graph_templates_item.task_item_id = data_template_rrd.id
			LIMIT 1');

		cacti_log(__FUNCTION__ . ' Host[' . $host_id . "] Not Adding Graph - this graph already exists - graph-id: ($existsAlready) - data-source-id: ($dataSourceId)", false, 'AUTOMATION');
		return;
	}else{
		# input fields are not supported
		$suggested_values = array();
		$returnArray = create_complete_graph_from_template($graph_template_id, $host_id, '', $suggested_values);

		$dataSourceId = '';
		if (sizeof($returnArray)) {
			if (isset($returnArray['local_data_id'])) {
				foreach($returnArray['local_data_id'] as $item) {
					push_out_host($host_id, $item);

					if (strlen($dataSourceId)) {
						$dataSourceId .= ', ' . $item;
					}else{
						$dataSourceId = $item;
					}
				}

				cacti_log('Host[' . $host_id . '] Graph Added - graph-id: (' . $returnArray['local_graph_id'] . ") - data-source-ids: ($dataSourceId)", false, 'AUTOMATION');
			}
		} else {
			cacti_log('Host[' . $host_id . '] ERROR: graph-id: (' . $returnArray['local_graph_id'] . ') without data sources', false, 'AUTOMATION');
		}

	}
}

/**
 * run rules for a new device in a tree
 * @param $data - data passed from hook
 */
function execute_device_create_tree($host_id) {
	global $config;

	include_once($config['base_path'] . '/lib/api_automation.php');

	/* the $data array holds all information about the host we're just working on
	 * even if we selected multiple hosts, the calling code will scan through the list
	 * so we only have a single host here
	 */
	
	cacti_log(__FUNCTION__ . " Host[$host_id] called", false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

	/* 
	 * find all active Tree Rules
	 * checking whether a specific rule matches the selected host
	 * has to be done later
	 */
	$sql = "SELECT atr.id, atr.name, atr.tree_id, atr.tree_item_id, 
		atr.leaf_type, atr.host_grouping_type, atr.rra_id 
		FROM automation_tree_rules AS atr
		WHERE enabled='on'
		AND leaf_type=" . TREE_ITEM_TYPE_HOST;

	$rules = db_fetch_assoc($sql);

	cacti_log(__FUNCTION__ . " Host[$host_id], matching rule sql: $sql matches: " . sizeof($rules), false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
	
	/* now walk all rules 
	 */
	if (sizeof($rules)) {
		foreach ($rules as $rule) {
			cacti_log(__FUNCTION__ . " Host[$host_id], active rule: " . $rule['id'] . ' name: ' . $rule['name'] . ' type: ' . $rule['leaf_type'], false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
			
			/* does the rule apply to the current host?
			 * test 'eligible objects' rule items */
			$matches = get_matching_hosts($rule, AUTOMATION_RULE_TYPE_TREE_MATCH, 'h.id=' . $host_id);

			cacti_log(__FUNCTION__ . " Host[$host_id], matching hosts: " . serialize($matches), false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
			
			/* if the rule produces a match, we will have to create all required tree nodes */
			if (sizeof($matches)) {
				/* create the bunch of header nodes */
				$parent = create_all_header_nodes($host_id, $rule);
				cacti_log(__FUNCTION__ . " Host[$host_id], parent: " . $parent, false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

				/* now that all rule items have been executed, add the item itself */
				$node = create_device_node($host_id, $parent, $rule);
				cacti_log(__FUNCTION__ . " Host[$host_id], node: " . $node, false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
			}
		}
	}
}


/**
 * run rules for a new graph on a tree
 * @param $data - data passed from hook
 */
function execute_graph_create_tree($graph_id) {
	global $config;

	include_once($config['base_path'] . '/lib/api_automation.php');

	/* the $data array holds all information about the graph we're just working on
	 * even if we selected multiple graphs, the calling code will scan through the list
	 * so we only have a single graph here
	 */
	
	cacti_log(__FUNCTION__ . ' Graph[' . $graph_id . '] called, data: ' . serialize($data), false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

	/*
	 * find all active Tree Rules
	 * checking whether a specific rule matches the selected graph
	 * has to be done later
	 */
	$sql = "SELECT atg.id, atg.name, atg.tree_id, atg.tree_item_id,
		atg.leaf_type, atg.host_grouping_type, atg.rra_id
		FROM automation_tree_rules AS atr
		WHERE enabled='on' 
		AND leaf_type=" . TREE_ITEM_TYPE_GRAPH;

	$rules = db_fetch_assoc($sql);

	cacti_log(__FUNCTION__ . ' Graph[' . $graph_id . "], Matching rule sql: $sql matches: " . sizeof($rules), false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
	
	/* now walk all rules
	 */
	if (sizeof($rules)) {			
		foreach ($rules as $rule) {
			cacti_log(__FUNCTION__ . ' Graph[' . $graph_id . '], active rule: ' . $rule['id'] . ' name: ' . $rule['name'] . ' type: ' . $rule['leaf_type'], false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
			
			/* does this rule apply to the current graph?
			 * test 'eligible objects' rule items */
			$matches = get_matching_graphs($rule, AUTOMATION_RULE_TYPE_TREE_MATCH, 'graph_local.id=' . $graph_id);

			cacti_log(__FUNCTION__ . ' Graph[' . $graph_id . '], Matching graphs: ' . serialize($matches), false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
			
			/* if the rule produces a match, we will have to create all required tree nodes */
			if (sizeof($matches)) {
				/* create the bunch of header nodes */
				$parent = create_all_header_nodes($graph_id, $rule);
				cacti_log(__FUNCTION__ . ' Graph[' . $graph_id . '], Parent: ' . $parent, false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

				/* now that all rule items have been executed, add the item itself */
				$node = create_graph_node($graph_id, $parent, $rule);
				cacti_log(__FUNCTION__ . ' Graph[' . $graph_id . '], Node: ' . $node, false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
			}
		}
	}
}

/**
 * create all graphs for a data query
 * @param int $host_id			- host id
 * @param int $snmp_query_id	- snmp query id
 * @param array $rule			- matching rule
 */
function create_dq_graphs($host_id, $snmp_query_id, $rule) {
	global $config, $automation_op_array, $automation_oper;

	include_once($config['base_path'] . '/lib/api_automation.php');
	include_once($config['base_path'] . '/lib/template.php');

	cacti_log(__FUNCTION__ . ' Host[' . $host_id . "] - snmp query: $snmp_query_id - rule: " . $rule['name'], false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

	$snmp_query_array = array();
	$snmp_query_array['snmp_query_id']       = $rule['snmp_query_id'];
	$snmp_query_array['snmp_index_on']       = get_best_data_query_index_type($host_id, $rule['snmp_query_id']);
	$snmp_query_array['snmp_query_graph_id'] = $rule['graph_type_id'];

	# get all rule items
	$automation_rule_items = db_fetch_assoc('SELECT * 
		FROM automation_graph_rule_items AS agri
		WHERE rule_id=' . $rule['id'] . '
		ORDER BY sequence');

	# and all matching snmp_indices from snmp_cache

	/* get the unique field values from the database */
	$sql = 'SELECT DISTINCT 
		field_name 
		FROM host_snmp_cache AS hsc
		WHERE snmp_query_id=' . $snmp_query_id;

	$field_names = db_fetch_assoc($sql);
	#print '<pre>Field Names: $sql<br>'; print_r($field_names); print '</pre>';

	/* build magic query */
	$sql_query  = 'SELECT host_id, snmp_query_id, snmp_index';

	$num_visible_fields = sizeof($field_names);
	$i = 0;
	if (sizeof($field_names) > 0) {
		foreach($field_names as $column) {
			$field_name = $column['field_name'];
			$sql_query .= ", MAX(CASE WHEN field_name='$field_name' THEN field_value ELSE NULL END) AS '$field_name'";
			$i++;
		}
	}

	$sql_query .= ' FROM host_snmp_cache AS hsc
		WHERE snmp_query_id=' . $snmp_query_id . ' 
		AND host_id=' . $host_id . ' 
		GROUP BY snmp_query_id, snmp_index';

#	$sql_filter	= '';
#	if(sizeof($automation_rule_items)) {
#		$sql_filter = ' WHERE';
#		foreach($automation_rule_items as $automation_rule_item) {
#			# AND|OR
#			if ($automation_rule_item['operation'] != AUTOMATION_OPER_NULL) {
#				$sql_filter .= ' ' . $automation_oper[$automation_rule_item['operation']];
#			}
#			# right bracket ')' does not come with a field
#			if ($automation_rule_item['operation'] == AUTOMATION_OPER_RIGHT_BRACKET) {
#				continue;
#			}
#			# field name
#			if ($automation_rule_item['field'] != '') {
#				$sql_filter .= (' a.' . $automation_rule_item['field']);
#				#
#				$sql_filter .= ' ' . $automation_op_array['op'][$automation_rule_item['operator']] . ' ';
#				if ($automation_op_array['binary'][$automation_rule_item['operator']]) {
#					$sql_filter .= (''' . $automation_op_array['pre'][$automation_rule_item['operator']]  . mysql_real_escape_string($automation_rule_item['pattern']) . $automation_op_array['post'][$automation_rule_item['operator']] . ''');
#				}
#			}
#		}
#	}
	$sql_filter = build_rule_item_filter($automation_rule_items, ' a.');

	if (sizeof($sql_filter)) $sql_filter = ' WHERE' . $sql_filter;

	/* add the additional filter settings to the original data query.
	 IMO it's better for the MySQL server to use the original one
	 as an subquery which requires MySQL v4.1(?) or higher */
	$sql_query = 'SELECT * FROM (' . $sql_query	. ") as a $sql_filter";

	/* fetch snmp indices */
	#	print $sql_query . '\n';
	$snmp_query_indexes = db_fetch_assoc($sql_query);

	# now create the graphs
	if (sizeof($snmp_query_indexes)) {
		$graph_template_id = db_fetch_cell('SELECT graph_template_id 
			FROM snmp_query_graph 
			WHERE id=' . $rule['graph_type_id']);

		cacti_log(__FUNCTION__ . ' Host[' . $host_id . "] - graph template: $graph_template_id", false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

		foreach ($snmp_query_indexes as $snmp_index) {
			$snmp_query_array['snmp_index'] = $snmp_index['snmp_index'];

			cacti_log(__FUNCTION__ . ' Host[' . $host_id . '] - checking index: ' . $snmp_index['snmp_index'], false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

			$sql = "SELECT DISTINCT dl.id 
				FROM data_local AS dl
				LEFT JOIN data_template_data AS dtd
				ON dl.id=dtd.local_data_id
				LEFT JOIN data_input_data AS did
				ON dtd.id=did.data_template_data_id
				LEFT JOIN data_input_fields AS dif
				ON did.data_input_field_id=dif.id
				LEFT JOIN snmp_query_graph AS sqg
				ON did.value=sqg.id
				WHERE dif.type_code='output_type'
				AND sqg.id=" . $rule['graph_type_id'] . '
				AND dl.host_id=' . $host_id . '
				AND dl.snmp_query_id=' . $rule['snmp_query_id'] . "
				AND dl.snmp_index='" . $snmp_query_array['snmp_index'] . "'";

			$existsAlready = db_fetch_cell($sql);
			if (isset($existsAlready) && $existsAlready > 0) {
				cacti_log(__FUNCTION__ . ' Host[' . $host_id . "] Not Adding Graph - this graph already exists - DS[$existsAlready]", false, 'AUTOMATION');
				continue;
			}

			$empty = array(); /* Suggested Values are not been implemented */

			$return_array = create_complete_graph_from_template($graph_template_id, $host_id, $snmp_query_array, $empty);

			if (sizeof($return_array) && array_key_exists('local_graph_id', $return_array) && array_key_exists('local_data_id', $return_array)) {
				$data_source_id = db_fetch_cell('SELECT
					data_template_rrd.local_data_id
					FROM graph_templates_item, data_template_rrd
					WHERE graph_templates_item.local_graph_id = ' . $return_array['local_graph_id'] . '
					AND graph_templates_item.task_item_id = data_template_rrd.id
					LIMIT 1');

				foreach($return_array['local_data_id'] as $item) {
					push_out_host($host_id, $item);

					if (strlen($data_source_id)) {
						$data_source_id .= ', ' . $item;
					}else{
						$data_source_id = $item;
					}
				}

				cacti_log(__FUNCTION__ . ' Host[' . $host_id . '] Graph Added - graph-id: (' . $return_array['local_graph_id'] . ") - data-source-ids: ($data_source_id)", false, 'AUTOMATION');
			} else {
				cacti_log(__FUNCTION__ . ' Host[' . $host_id . '] WARNING: Graph Not Added', false, 'AUTOMATION');
			}
		}

	}
}


/* create_all_header_nodes - walk across all tree rule items
 * 					- get all related rule items
 * 					- take header type into account
 * 					- create (multiple) header nodes
 *
 * @arg $item_id	id of the host/graph we're working on
 * @arg $rule		the rule we're working on
 * returns			the last tree item that was hooked into the tree
 */
function create_all_header_nodes ($item_id, $rule) {
	global $config, $automation_tree_header_types;

	include_once($config['base_path'] . '/lib/api_automation.php');

	# get all related rules that are enabled
	$sql = 'SELECT *
		FROM automation_tree_rule_items AS atri
		WHERE atri.rule_id=' . $rule['id'] . '
		ORDER BY sequence ';

	$tree_items = db_fetch_assoc($sql);

	cacti_log(__FUNCTION__ . " called: Item $item_id sql: $sql matches: " . sizeof($tree_items) . ' items', false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

	/* start at the given tree item
	 * it may be worth verifying existance of this entry
	 * in case it was selected once but then deleted
	 */
	$parent_tree_item_id = $rule['tree_item_id'];

	# now walk all rules and create tree nodes
	if (sizeof($tree_items)) {
		/* build magic query, 
		 * for matching hosts JOIN tables host and host_template */
		if ($rule['leaf_type'] == TREE_ITEM_TYPE_HOST) {
			$sql_tables = 'FROM host AS h
				LEFT JOIN host_template AS ht
				ON h.host_template_id=ht.id) ';

			$sql_where = 'WHERE h.id='. $item_id . ' ';
		} elseif ($rule['leaf_type'] == TREE_ITEM_TYPE_GRAPH) {
			/* graphs require a different set of tables to be joined */
			$sql_tables = 'FROM host AS h
				LEFT JOIN host_template AS ht
				ON h.host_template_id=ht.id 
				LEFT JOIN graph_local AS gl
				ON h.id=gl.host_id 
				LEFT JOIN graph_templates AS gt
				ON gl.graph_template_id=gt.id
				LEFT JOIN graph_templates_graph AS gtg
				ON gl.id=gtg.local_graph_id ';

			$sql_where = 'WHERE gl.id=' . $item_id . ' ';
		}

		/* get the WHERE clause for matching hosts */
		$sql_filter = build_matching_objects_filter($rule['id'], AUTOMATION_RULE_TYPE_TREE_MATCH);
		
		foreach ($tree_items as $tree_item) {
			if ($tree_item['field'] === AUTOMATION_TREE_ITEM_TYPE_STRING) {
				# for a fixed string, use the given text
				$sql = '';
				$target = $automation_tree_header_types[AUTOMATION_TREE_ITEM_TYPE_STRING];
			} else {
				$sql_field = $tree_item['field'] . ' AS source ';

				/* now we build up a new query for counting the rows */
				$sql = 'SELECT ' .
				$sql_field .
				$sql_tables .
				$sql_where . ' AND (' . $sql_filter . ')';

				$target = db_fetch_cell($sql);
			}

			cacti_log(__FUNCTION__ . " Item $item_id - sql: $sql matches: " . $target, false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

			$parent_tree_item_id = create_multi_header_node($target, $rule, $tree_item, $parent_tree_item_id);
		}
	}

	return $parent_tree_item_id;
}

/* create_multi_header_node - work on a single header item
 * 							- evaluate replacement rule
 * 							- this may return an array of new header items
 * 							- walk that array to create all header items for this single rule item
 * @arg $target		string (name) of the object; e.g. ht.name
 * @arg $rule		rule
 * @arg $tree_item	rule item; replacement_pattern may result in multi-line replacement
 * @arg $parent_tree_item_id	parent tree item id
 * returns 			id of the header that was hooked in
 */
function create_multi_header_node($object, $rule, $tree_item, $parent_tree_item_id){
	global $config;

	include_once($config['base_path'] . '/lib/api_automation.php');

	cacti_log(__FUNCTION__ . " - object: '" . $object . "', Header: '" . $tree_item['search_pattern'] . "', parent: " . $parent_tree_item_id, false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
	
	if ($tree_item['field'] === AUTOMATION_TREE_ITEM_TYPE_STRING) {
		$parent_tree_item_id = create_header_node($tree_item['search_pattern'], $rule, $tree_item, $parent_tree_item_id);
		cacti_log(__FUNCTION__ . " called - object: '" . $object . "', Header: '" . $tree_item['search_pattern'] . "', hooked at: " . $parent_tree_item_id, false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
	} else {
		$replacement = automation_string_replace($tree_item['search_pattern'], $tree_item['replace_pattern'], $object);
		/* build multiline <td> entry */
		#print '<pre>'; print_r($replacement); print '</pre>';

		for ($j=0; sizeof($replacement); $j++) {
			$title = array_shift($replacement);
			$parent_tree_item_id = create_header_node($title, $rule, $tree_item, $parent_tree_item_id);
			cacti_log(__FUNCTION__ . " - object: '" . $object . "', Header: '" . $title . "', hooked at: " . $parent_tree_item_id, false, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);
		}
	}

	return $parent_tree_item_id;
}


/**
 * create a single tree header node
 * @param string $title				- graph title
 * @param array $rule				- rule
 * @param array $item				- item
 * @param int $parent_tree_item_id	- parent item id
 * @return int						- id of new item
 */
function create_header_node($title, $rule, $item, $parent_tree_item_id) {
	global $config;

	include_once($config['base_path'] . '/lib/api_automation.php');
	include_once($config['base_path'] . '/lib/api_tree.php');

	$id = 0;				# create a new entry
	$local_graph_id = 0;	# headers don't need no graph_id
	$rra_id = 0;			# nor an rra_id
	$host_id = 0;			# or a host_id
	$propagate = ($item['propagate_changes'] != '');

	$new_item = api_tree_item_save($id, $rule['tree_id'], TREE_ITEM_TYPE_HEADER, $parent_tree_item_id,
		$title, $local_graph_id, $rra_id, $host_id, $rule['host_grouping_type'], $item['sort_type'], $propagate);

	if (isset($new_item) && $new_item > 0) {
		cacti_log(__FUNCTION__ . ' Parent[' . $parent_tree_item_id . '] Tree Item added - id: (' . $new_item . ') Title: (' .$title . ')', false, 'AUTOMATION');
	} else {
		cacti_log(__FUNCTION__ . ' WARNING: Parent[' . $parent_tree_item_id . '] Tree Item not added', false, 'AUTOMATION');
	}

	return $new_item;
}


/**
 * add a device to the tree
 * @param int $host_id	- host id
 * @param int $parent	- parent id
 * @param array $rule 	- rule
 * @return int			- id of new item
 */
function create_device_node($host_id, $parent, $rule) {
	global $config;

	include_once($config['base_path'] . '/lib/api_automation.php');
	include_once($config['base_path'] . '/lib/api_tree.php');

	$id = 0;				# create a new entry
	$local_graph_id = 0;	# hosts don't need no graph_id
	$title = '';			# nor a title
	$sort_type = 0;			# nor a sort type
	$propagate = false;		# nor a propagation flag

	$new_item = api_tree_item_save($id, $rule['tree_id'], TREE_ITEM_TYPE_HOST, $parent, $title, 
		$local_graph_id, $rule['rra_id'], $host_id, $rule['host_grouping_type'], $sort_type, $propagate);

	if (isset($new_item) && $new_item > 0) {
		cacti_log(__FUNCTION__ . ' Host[' . $host_id . '] Tree Item added - id: (' . $new_item . ')', false, 'AUTOMATION');
	} else {
		cacti_log(__FUNCTION__ . ' WARNING: Host[' . $host_id . '] Tree Item not added', false, 'AUTOMATION');
	}

	return $new_item;
}


/**
 * add a device to the tree
 * @param int $graph_id	- graph id
 * @param int $parent	- parent id
 * @param array $rule	- rule
 * @return int			- id of new item
 */
function create_graph_node($graph_id, $parent, $rule) {
	global $config;

	include_once($config['base_path'] . '/lib/api_automation.php');
	include_once($config['base_path'] . '/lib/api_tree.php');

	$id = 0;				# create a new entry
	$host_id = 0;			# graphs don't need no host_id
	$title = '';			# nor a title
	$sort_type = 0;			# nor a sort type
	$propagate = false;		# nor a propagation flag

	$new_item = api_tree_item_save($id, $rule['tree_id'], TREE_ITEM_TYPE_GRAPH, $parent, $title, 
		$graph_id, $rule['rra_id'], $host_id, $rule['host_grouping_type'], $sort_type, $propagate);

	if (isset($new_item) && $new_item > 0) {
		cacti_log(__FUNCTION__ . ' Graph[' . $graph_id . '] Tree Item added - id: (' . $new_item . ')', false, 'AUTOMATION');
	} else {
		cacti_log(__FUNCTION__ . ' Graph[' . $graph_id . '] WARNING: Tree Item not added', false, 'AUTOMATION');
	}

	return $new_item;
}
