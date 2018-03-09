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

function display_matching_hosts($rule, $rule_type, $url) {
	global $device_actions, $item_rows;

	if (isset_request_var('cleard')) {
		set_request_var('clear', 'true');
	}

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rowsd' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'paged' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'host_status' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'host_template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'filterd' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'description',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'has_graphs' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => 'true'
			)
	);

	validate_store_request_vars($filters, 'sess_auto');
	/* ================= input validation ================= */

	if (isset_request_var('cleard')) {
		unset_request_var('clear');
	}

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rowsd') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rowsd');
	}

	if ((!empty($_SESSION['sess_automation_host_status'])) && (!isempty_request_var('host_status'))) {
		if ($_SESSION['sess_automation_host_status'] != get_request_var('host_status')) {
			set_request_var('paged', '1');
		}
	}

	?>
	<script type='text/javascript'>
	function applyDeviceFilter() {
		strURL  = '<?php print $url;?>' + '&host_status=' + $('#host_status').val();
		strURL += '&host_template_id=' + $('#host_template_id').val();
		strURL += '&rowsd=' + $('#rowsd').val();
		strURL += '&filterd=' + $('#filterd').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearDeviceFilter() {
		strURL = '<?php print $url;?>' + '&cleard=true&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh').click(function() {
			applyDeviceFilter();
		});

		$('#clear').click(function() {
			clearDeviceFilter();
		});

		$('#form_automation_host').submit(function(event) {
			event.preventDefault();
			applyDeviceFilter();
		});

		setupSpecialKeys('filterd');
	});
	</script>
	<?php

	html_start_box(__('Matching Devices'), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
			<form method='post' id='form_automation_host' action='<?php print html_escape($url);?>'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search');?>
						</td>
						<td>
							<input type='text' id='filterd' size='25' value='<?php print html_escape_request_var('filterd');?>'>
						</td>
						<td>
							<?php print __('Type');?>
						</td>
						<td>
							<select id='host_template_id' onChange='applyDeviceFilter()'>
								<option value='-1'<?php if (get_request_var('host_template_id') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
								<option value='0'<?php if (get_request_var('host_template_id') == '0') {?> selected<?php }?>><?php print __('None');?></option>
								<?php
								$host_templates = db_fetch_assoc('SELECT id,name FROM host_template ORDER BY name');

								if (sizeof($host_templates)) {
									foreach ($host_templates as $host_template) {
										print "<option value='" . $host_template['id'] . "'"; if (get_request_var('host_template_id') == $host_template['id']) { print ' selected'; } print '>' . $host_template['name'] . "</option>\n";
									}
								}
								?>
							</select>
						</td>
						<td>
							<?php print __('Status');?>
						</td>
						<td>
							<select id='host_status' onChange='applyDeviceFilter()'>
								<option value='-1'<?php if (get_request_var('host_status') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
								<option value='-3'<?php if (get_request_var('host_status') == '-3') {?> selected<?php }?>><?php print __('Enabled');?></option>
								<option value='-2'<?php if (get_request_var('host_status') == '-2') {?> selected<?php }?>><?php print __('Disabled');?></option>
								<option value='-4'<?php if (get_request_var('host_status') == '-4') {?> selected<?php }?>><?php print __('Not Up');?></option>
								<option value='3'<?php if (get_request_var('host_status') == '3') {?> selected<?php }?>><?php print __('Up');?></option>
								<option value='1'<?php if (get_request_var('host_status') == '1') {?> selected<?php }?>><?php print __('Down');?></option>
								<option value='2'<?php if (get_request_var('host_status') == '2') {?> selected<?php }?>><?php print __('Recovering');?></option>
								<option value='0'<?php if (get_request_var('host_status') == '0') {?> selected<?php }?>><?php print __('Unknown');?></option>
							</select>
						</td>
						<td>
							<?php print __('Devices');?>
						</td>
						<td>
							<select id='rowsd' onChange='applyDeviceFilter()'>
								<option value='-1'<?php if (get_request_var('rowsd') == '-1') {?> selected<?php }?>><?php print __('Default');?></option>
								<?php
								if (sizeof($item_rows)) {
									foreach ($item_rows as $key => $value) {
										print "<option value='". $key . "'"; if (get_request_var('rowsd') == $key) { print ' selected'; } print '>' . $value . '</option>\n';
									}
								}
								?>
							</select>
						</td>
						<td>
							<span>
								<input id='refresh' type='button' value='<?php print __esc('Go');?>'>
								<input id='clear' type='button' value='<?php print __esc('Clear');?>'>
							</span>
						</td>
					</tr>
				</table>
			</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filterd') != '') {
		$sql_where = "WHERE (h.hostname LIKE '%" . get_request_var('filterd') . "%' OR h.description LIKE '%" . get_request_var('filterd') . "%' OR ht.name LIKE '%" . get_request_var('filterd') . "%')";
	} else {
		$sql_where = '';
	}

	if (get_request_var('host_status') == '-1') {
		/* Show all items */
	} elseif (get_request_var('host_status') == '-2') {
		$sql_where .= ($sql_where != '' ? " AND h.disabled='on'" : "WHERE h.disabled='on'");
	} elseif (get_request_var('host_status') == '-3') {
		$sql_where .= ($sql_where != '' ? " AND h.disabled=''" : "WHERE h.disabled=''");
	} elseif (get_request_var('host_status') == '-4') {
		$sql_where .= ($sql_where != '' ? " AND (h.status!='3' or h.disabled='on')" : "WHERE (h.status!='3' or h.disabled='on')");
	}else {
		$sql_where .= ($sql_where != '' ? ' AND (h.status=' . get_request_var('host_status') . " AND h.disabled = '')" : "WHERE (h.status=" . get_request_var('host_status') . " AND h.disabled = '')");
	}

	if (get_request_var('host_template_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('host_template_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND h.host_template_id=0' : 'WHERE h.host_template_id=0');
	} elseif (!isempty_request_var('host_template_id')) {
		$sql_where .= ($sql_where != '' ? ' AND h.host_template_id=' . get_request_var('host_template_id') : 'WHERE h.host_template_id=' . get_request_var('host_template_id'));
	}

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
	if ($sql_where != '') {
		$sql_filter = ' AND (' . build_matching_objects_filter($rule['id'], $rule_type) . ')';
	} else {
		$sql_filter = ' WHERE (' . build_matching_objects_filter($rule['id'], $rule_type) .')';
	}

	/* now we build up a new query for counting the rows */
	$rows_query = $sql_query . $sql_where . $sql_filter;
	$total_rows = sizeof(db_fetch_assoc($rows_query, false));

	$sortby = get_request_var('sort_column');
	if ($sortby=='hostname') {
		$sortby = 'INET_ATON(hostname)';
	}

	$sql_query = $rows_query .
		' ORDER BY ' . $sortby . ' ' . get_request_var('sort_direction') .
		' LIMIT ' . ($rows*(get_request_var('paged')-1)) . ',' . $rows;
	$hosts = db_fetch_assoc($sql_query, false);

	$nav = html_nav_bar($url, MAX_DISPLAY_PAGES, get_request_var('paged'), $rows, $total_rows, 7, __('Devices'), 'paged', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'description'        => array(__('Description'), 'ASC'),
		'hostname'           => array(__('Hostname'), 'ASC'),
		'status'             => array(__('Status'), 'ASC'),
		'host_template_name' => array(__('Device Template Name'), 'ASC'),
		'id'                 => array(__('ID'), 'ASC'),
		'nosort1'            => array(__('Graphs'), 'ASC'),
		'nosort2'            => array(__('Data Sources'), 'ASC'),
	);

	html_header_sort(
		$display_text,
		get_request_var('sort_column'),
		get_request_var('sort_direction'),
		'1',
		$url . '?action=edit&id=' . get_request_var('id') . '&paged=' . get_request_var('paged')
	);

	if (sizeof($hosts)) {
		foreach ($hosts as $host) {
			form_alternate_row('line' . $host['host_id'], true);
			form_selectable_cell(filter_value($host['description'], get_request_var('filterd'), 'host.php?action=edit&id=' . $host['host_id']), $host['host_id']);
			form_selectable_cell(filter_value($host['hostname'], get_request_var('filterd')), $host['host_id']);
			form_selectable_cell(get_colored_device_status(($host['disabled'] == 'on' ? true : false), $host['status']), $host['host_id']);
			form_selectable_cell(filter_value($host['host_template_name'], get_request_var('filterd')), $host['host_id']);
			form_selectable_cell(round(($host['host_id']), 2), $host['host_id']);
			form_selectable_cell((isset($host_graphs[$host['host_id']]) ? $host_graphs[$host['host_id']] : 0), $host['host_id']);
			form_selectable_cell((isset($host_data_sources[$host['host_id']]) ? $host_data_sources[$host['host_id']] : 0), $host['host_id']);
			form_end_row();
		}
	} else {
		print "<tr><td colspan='8'><em>" . __('No Matching Devices') . "</em></td></tr>";
	}

	html_end_box(false);

	if (sizeof($hosts)) {
		print $nav;
	}

	form_end();
}

function display_matching_graphs($rule, $rule_type, $url) {
	global $graph_actions, $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'host_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'title_cache',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'has_graphs' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => 'true'
			)
	);

	validate_store_request_vars($filters, 'sess_autog');
	/* ================= input validation ================= */

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL  = '<?php print $url;?>' + '&host_id=' + $('#host_id').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&template_id=' + $('#template_id').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = '<?php print $url;?>' + '&clear=true&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#host_id, #template_id, #rows, #filter').change(function() {
			applyFilter();
		});

		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_graphs').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php

	html_start_box(__('Matching Objects'), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
			<form id='form_graphs' action='<?php print html_escape($url);?>'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Device');?>
						</td>
						<td>
							<select id='host_id'>
								<option value='-1'<?php if (get_request_var('host_id') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
								<option value='0'<?php if (get_request_var('host_id') == '0') {?> selected<?php }?>><?php print __('None');?></option>
								<?php
								$hosts = get_allowed_devices();
								if (sizeof($hosts)) {
									foreach ($hosts as $host) {
										print "<option value='" . $host['id'] . "'"; if (get_request_var('host_id') == $host['id']) { print ' selected'; } print '>' . html_escape($host['description']) . "</option>\n";
									}
								}
								?>
							</select>
						</td>
						<td>
							<?php print __('Template');?>
						</td>
						<td>
							<select id='template_id'>
								<option value='-1'<?php if (get_request_var('template_id') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
								<option value='0'<?php if (get_request_var('template_id') == '0') {?> selected<?php }?>><?php print __('None');?></option>
								<?php
								$templates = get_allowed_graph_templates();

								if (sizeof($templates) > 0) {
								foreach ($templates as $template) {
									print "<option value=' " . $template['id'] . "'"; if (get_request_var('template_id') == $template['id']) { print ' selected'; } print '>' . title_trim($template['name'], 40) . "</option>\n";
								}
								}
								?>
							</select>
						</td>
						<td>
							<span>
								<input id='refresh' type='button' value='<?php print __esc('Go');?>'>
								<input id='clear' type='button' value='<?php print __esc('Clear');?>'>
							</span>
						</td>
					</tr>
				</table>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search');?>
						</td>
						<td>
							<input id='filter' type='text' size='25' value='<?php print html_escape_request_var('filter');?>'>
						</td>
						<td>
							<?php print __('Devices');?>
						</td>
						<td>
							<select id='rows'>
								<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default');?></option>
								<?php
								if (sizeof($item_rows)) {
									foreach ($item_rows as $key => $value) {
										print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . "</option>\n";
									}
								}
								?>
							</select>
						</td>
					</tr>
				</table>
			</form>
		</td>
	</tr>
	<?php

	html_end_box(false);

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = "WHERE (gtg.title_cache LIKE '%" . get_request_var('filter') . "%'" .
			" OR gt.name LIKE '%" . get_request_var('filter') . "%'" .
			" OR h.description LIKE '%" . get_request_var('filter') . "%'" .
			" OR h.hostname LIKE '%" . get_request_var('filter') . "%')";
	} else {
		$sql_where = '';
	}

	if (get_request_var('host_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('host_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' gl.host_id=0';
	} elseif (!isempty_request_var('host_id')) {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' gl.host_id=' . get_request_var('host_id');
	}

	if (get_request_var('template_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('template_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' gtg.graph_template_id=0';
	} elseif (!isempty_request_var('template_id')) {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') .' gtg.graph_template_id=' . get_request_var('template_id');
	}

	/* get the WHERE clause for matching graphs */
	$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . build_matching_objects_filter($rule['id'], $rule_type);

	$total_rows = db_fetch_cell("SELECT COUNT(gtg.id)
		FROM graph_local AS gl
		INNER JOIN graph_templates_graph AS gtg
		ON gl.id=gtg.local_graph_id
		LEFT JOIN graph_templates AS gt
		ON gl.graph_template_id=gt.id
		LEFT JOIN host AS h
		ON gl.host_id=h.id
		LEFT JOIN host_template AS ht
		ON h.host_template_id=ht.id
		$sql_where", '', false);

	$sql = "SELECT h.id AS host_id, h.hostname, h.description,
		h.disabled, h.status, ht.name AS host_template_name,
		gtg.id, gtg.local_graph_id, gtg.height, gtg.width,
		gtg.title_cache, gt.name
		FROM graph_local AS gl
		INNER JOIN graph_templates_graph AS gtg
		ON gl.id=gtg.local_graph_id
		LEFT JOIN graph_templates AS gt
		ON gl.graph_template_id=gt.id
		LEFT JOIN host AS h
		ON gl.host_id=h.id
		LEFT JOIN host_template AS ht
		ON h.host_template_id=ht.id
		$sql_where
		ORDER BY " . get_request_var('sort_column') . ' ' . get_request_var('sort_direction') . '
		LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$graph_list = db_fetch_assoc($sql, false);

	$nav = html_nav_bar($url, MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 8, __('Devices'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'description'        => array(__('Device Description'), 'ASC'),
		'hostname'           => array(__('Hostname'), 'ASC'),
		'host_template_name' => array(__('Device Template Name'), 'ASC'),
		'status'             => array(__('Status'), 'ASC'),
		'title_cache'        => array(__('Graph Title'), 'ASC'),
		'local_graph_id'     => array(__('Graph ID'), 'ASC'),
		'name'               => array(__('Graph Template Name'), 'ASC'),
	);

	html_header_sort(
		$display_text,
		get_request_var('sort_column'),
		get_request_var('sort_direction'),
		'1',
		$url . '?action=edit&id=' . get_request_var('id') . '&page=' . get_request_var('page')
	);

	if (sizeof($graph_list)) {
		foreach ($graph_list as $graph) {
			$template_name = ((empty($graph['name'])) ? '<em>' . __('None') . '</em>' : html_escape($graph['name']));
			form_alternate_row('line' . $graph['local_graph_id'], true);
			form_selectable_cell(filter_value($graph['description'], get_request_var('filter'), 'host.php?action=edit&id=' . $graph['host_id']), $graph['local_graph_id']);
			form_selectable_cell(filter_value($graph['hostname'], get_request_var('filter')), $graph['local_graph_id']);
			form_selectable_cell(filter_value($graph['host_template_name'], get_request_var('filter')), $graph['local_graph_id']);
			form_selectable_cell(get_colored_device_status(($graph['disabled'] == 'on' ? true : false), $graph['status']), $graph['local_graph_id']);
			form_selectable_cell(filter_value(title_trim($graph['title_cache'], read_config_option('max_title_length')), get_request_var('filter'), 'graphs.php?action=graph_edit&id=' . $graph['local_graph_id']), $graph['local_graph_id']);
			form_selectable_cell($graph['local_graph_id'], $graph['local_graph_id']);
			form_selectable_cell(filter_value($template_name, get_request_var('filter')), $graph['local_graph_id']);
			form_end_row();
		}
	} else {
		print '<tr><td><em>' . __('No Graphs Found') . '</em></td></tr>';
	}

	html_end_box(true);

	if (sizeof($graph_list)) {
		print $nav;
	}

	form_end();
}

function display_new_graphs($rule, $url) {
	global $config, $item_rows;

	if (isset_request_var('oclear')) {
		set_request_var('clear', 'true');
	}

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
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
			'default' => 'description',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_autog');
	/* ================= input validation ================= */

	if (isset_request_var('oclear')) {
		unset_request_var('clear');
	}

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	?>
	<script type='text/javascript'>
	function applyObjectFilter() {
		strURL  = '<?php print $url;?>';
		strURL += '&rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearObjectFilter() {
		strURL = '<?php print $url;?>' + '&oclear=true&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#orefresh').click(function() {
			applyObjectFilter();
		});

		$('#oclear').click(function() {
			clearObjectFilter();
		});

		$('#form_automation_objects').submit(function(event) {
			event.preventDefault();
			applyObjectFilter();
		});
	});
	</script>
	<?php

	html_start_box(__('Matching Objects'), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
			<form id='form_automation_objects' action='<?php print html_escape($url);?>'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search');?>
						</td>
						<td>
							<input type='text' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
						</td>
						<td>
							<?php print __('Objects');?>
						</td>
						<td>
							<select id='rows' onChange='applyFilter()'>
								<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default');?></option>
								<?php
								if (sizeof($item_rows)) {
									foreach ($item_rows as $key => $value) {
										print "<option value='". $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . '</option>\n';
									}
								}
								?>
							</select>
						</td>
						<td>
							<span>
								<input id='orefresh' type='button' value='<?php print __esc('Go');?>'>
								<input id='oclear' type='button' value='<?php print __esc('Clear');?>'>
							</span>
						</td>
					</tr>
				</table>
			</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('snmp_query_id');
	/* ==================================================== */

	$rule_items     = array();
	$created_graphs = array();
	$created_graphs = get_created_graphs($rule);

	$total_rows         = 0;
	$num_input_fields   = 0;
	$num_visible_fields = 0;

	$snmp_query = db_fetch_row_prepared('SELECT snmp_query.id, snmp_query.name, snmp_query.xml_path
		FROM snmp_query
		WHERE snmp_query.id = ?',
		array($rule['snmp_query_id']));

	if (!sizeof($snmp_query)) {
		$name = __('Not Found');
	} else {
		$name = $snmp_query['name'];
	}

	/*
	 * determine number of input fields, if any
	 * for a dropdown selection
	 */
	$xml_array = get_data_query_array($rule['snmp_query_id']);
	if (sizeof($xml_array)) {
		/* loop through once so we can find out how many input fields there are */
		foreach ($xml_array['fields'] as $field_name => $field_array) {
			if ($field_array['direction'] == 'input' || $field_array['direction'] == 'input-output') {
				$num_input_fields++;

				if (!isset($total_rows)) {
					$total_rows = db_fetch_cell_prepared('SELECT count(*)
						FROM host_snmp_cache
						WHERE snmp_query_id = ?
						AND field_name = ?',
						array($rule['snmp_query_id'], $field_name));
				}
			}
		}
	}

	if (!isset($total_rows)) {
		$total_rows = 0;
	}

	html_start_box(__('Matching Objects [ %s ]', html_escape($name)) . display_tooltip(__('A blue font color indicates that the rule will be applied to the objects in question.  Other objects will not be subject to the rule.')), '100%', '', '3', 'center', '');

	if ($xml_array != false) {
		$html_dq_header     = '';
		$sql_filter         = '';
		$sql_having         = '';
		$snmp_query_indexes = array();

		$rule_items         = db_fetch_assoc_prepared('SELECT *
			FROM automation_graph_rule_items
			WHERE rule_id = ?
			ORDER BY sequence', array($rule['id']));

		/* main sql */
		if (isset($xml_array['index_order_type'])) {
			$sql_order = build_sort_order($xml_array['index_order_type'], 'automation_host');
			$sql_query = build_data_query_sql($rule) . ' ' . $sql_order;
		} else {
			$sql_query = build_data_query_sql($rule);
		}

		$results = db_fetch_cell("SELECT COUNT(*) FROM ($sql_query) AS a", '', false);

		if ($results > 0) {
			/* rule item filter first */
			$sql_filter	= build_rule_item_filter($rule_items, ' a.');

			/* filter on on the display filter next */
			$sql_having = build_graph_object_sql_having($rule, get_request_var('filter'));

			/* now we build up a new query for counting the rows */
			$rows_query = "SELECT * FROM ($sql_query) AS a " . ($sql_filter != '' ? "WHERE ($sql_filter)":'') . $sql_having;
			$total_rows = sizeof(db_fetch_assoc($rows_query));

			if ($total_rows < (get_request_var('rows')*(get_request_var('page')-1))+1) {
				set_request_var('page', '1');
			}

			$sql_query = $rows_query . ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

			$snmp_query_indexes = db_fetch_assoc($sql_query);
		} else {
			$total_rows = 0;
			$snmp_query_indexes = array();
		}

		$nav = html_nav_bar('automation_graph_rules.php?action=edit&id=' . $rule['id'], MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 30, __('Matching Objects'), 'page', 'main');

		print $nav;

		/*
		 * print the Data Query table's header
		 * number of fields has to be dynamically determined
		 * from the Data Query used
		 */
		# we want to print the host name as the first column
		$new_fields['automation_host'] = array('name' => __('Hostname'), 'direction' => 'input');
		$new_fields['status']          = array('name' => __('Device Status'), 'direction' => 'input');
		$xml_array['fields']           = $new_fields + $xml_array['fields'];

		$field_names = get_field_names($rule['snmp_query_id']);
		array_unshift($field_names, array('field_name' => 'status'));
		array_unshift($field_names, array('field_name' => 'automation_host'));

		$display_text = array();
		foreach ($xml_array['fields'] as $field_name => $field_array) {
			if ($field_array['direction'] == 'input' || $field_array['direction'] == 'input-output') {
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
			print "<tr colspan='6'><td>" . __('There are no Objects that match this rule.') . "</td></tr>\n";
		} else {
			print "<tr colspan='6'>" . $html_dq_header . "</tr>\n";
		}

		/* list of all entries */
		$row_counter    = 0;
		$fields         = array_rekey($field_names, 'field_name', 'field_name');
		if (sizeof($snmp_query_indexes)) {
			foreach($snmp_query_indexes as $row) {
				form_alternate_row("line$row_counter", true);

				if (isset($created_graphs[$row['host_id']][$row['snmp_index']])) {
					$style = ' ';
				} else {
					$style = ' style="color: blue"';
				}
				$column_counter = 0;
				foreach ($xml_array['fields'] as $field_name => $field_array) {
					if ($field_array['direction'] == 'input' || $field_array['direction'] == 'input-output') {
						if (in_array($field_name, $fields)) {
							if (isset($row[$field_name])) {
								if ($field_name == 'status') {
									form_selectable_cell(get_colored_device_status(($row['disabled'] == 'on' ? true : false), $row['status']), 'status');
								} else {
									print "<td><span id='text$row_counter" . '_' . $column_counter . "' $style>" . filter_value($row[$field_name], get_request_var('filter')) . "</span></td>";
								}
							} else {
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

		if ($total_rows > $rows) {
			print $nav;
		}
	} else {
		print "<tr><td colspan='2' style='color: red;'>" . __('Error in data query') . "</td></tr>\n";
	}

	print '</table>';
	print '<br>';
}

function display_matching_trees ($rule_id, $rule_type, $item, $url) {
	global $automation_tree_header_types;
	global $device_actions, $item_rows;

	cacti_log(__FUNCTION__ . " called: $rule_id/$rule_type", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'host_template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'host_status' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'description',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_autot');
	/* ================= input validation ================= */

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	if ((!empty($_SESSION['sess_automation_host_status'])) && (!isempty_request_var('host_status'))) {
		if ($_SESSION['sess_automation_host_status'] != get_request_var('host_status')) {
			set_request_var('page', '1');
		}
	}

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL  = '<?php print $url;?>' + '&host_status=' + $('#host_status').val();
		strURL += '&host_template_id=' + $('#host_template_id').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = '<?php print $url;?>' + '&clear=true&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_automation_tree').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php

	print "<form method='post' id='form_automation_tree' action='" . html_escape($url) . "'>";

	html_start_box(__('Matching Items'), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Type');?>
					</td>
					<td>
						<select id='host_template_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('host_template_id') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<option value='0'<?php if (get_request_var('host_template_id') == '0') {?> selected<?php }?>><?php print __('None');?></option>
							<?php
							$host_templates = db_fetch_assoc('select id,name from host_template order by name');

							if (sizeof($host_templates) > 0) {
							foreach ($host_templates as $host_template) {
								print "<option value='" . $host_template['id'] . "'"; if (get_request_var('host_template_id') == $host_template['id']) { print ' selected'; } print '>' . $host_template['name'] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Status');?>
					</td>
					<td>
						<select id='host_status' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('host_status') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<option value='-3'<?php if (get_request_var('host_status') == '-3') {?> selected<?php }?>><?php print __('Enabled');?></option>
							<option value='-2'<?php if (get_request_var('host_status') == '-2') {?> selected<?php }?>><?php print __('Disabled');?></option>
							<option value='-4'<?php if (get_request_var('host_status') == '-4') {?> selected<?php }?>><?php print __('Not Up');?></option>
							<option value='3'<?php if (get_request_var('host_status') == '3') {?> selected<?php }?>><?php print __('Up');?></option>
							<option value='1'<?php if (get_request_var('host_status') == '1') {?> selected<?php }?>><?php print __('Down');?></option>
							<option value='2'<?php if (get_request_var('host_status') == '2') {?> selected<?php }?>><?php print __('Recovering');?></option>
							<option value='0'<?php if (get_request_var('host_status') == '0') {?> selected<?php }?>><?php print __('Unknown');?></option>
						</select>
					</td>
					<td>
						<?php print __('Data Queries');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default');?></option>
							<?php
							if (sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input id='refresh' type='button' value='<?php print __esc('Go');?>'>
							<input id='clear' type='button' value='<?php print __esc('Clear');?>'>
						</span>
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
	if (get_request_var('filter') != '') {
		$sql_where .= " AND (h.hostname LIKE '%" . get_request_var('filter') . "%' OR h.description LIKE '%" . get_request_var('filter') . "%' OR ht.name LIKE '%" . get_request_var('filter') . "%')";
	}

	if (get_request_var('host_status') == '-1') {
		/* Show all items */
	} elseif (get_request_var('host_status') == '-2') {
		$sql_where .= " AND h.disabled='on'";
	} elseif (get_request_var('host_status') == '-3') {
		$sql_where .= " AND h.disabled=''";
	} elseif (get_request_var('host_status') == '-4') {
		$sql_where .= " AND (h.status!='3' or h.disabled='on')";
	}else {
		$sql_where .= ' AND (h.status=' . get_request_var('host_status') . " AND h.disabled = '')";
	}

	if (get_request_var('host_template_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('host_template_id') == '0') {
		$sql_where .= ' AND h.host_template_id=0';
	} elseif (!isempty_request_var('host_template_id')) {
		$sql_where .= ' AND h.host_template_id=' . get_request_var('host_template_id');
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

	$total_rows = sizeof(db_fetch_assoc($rows_query, false));

	$sortby = get_request_var('sort_column');
	if ($sortby=='h.hostname') {
		$sortby = 'INET_ATON(h.hostname)';
	}

	$sql_query = "$rows_query ORDER BY $sortby " .
		get_request_var('sort_direction') . ' LIMIT ' .
		($rows*(get_request_var('page')-1)) . ',' . $rows;

	$templates = db_fetch_assoc($sql_query, false);

	cacti_log(__FUNCTION__ . " templates sql: $sql_query", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	$nav = html_nav_bar($url, MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 8, __('Devices'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'description'        => array(__('Description'), 'ASC'),
		'hostname'           => array(__('Hostname'), 'ASC'),
		'host_template_name' => array(__('Device Template Name'), 'ASC'),
		'status'             => array(__('Status'), 'ASC'),
		'source'             => array($item['field'], 'ASC'),
		'result'             => array(__('Resulting Branch'), 'ASC'),
	);

	html_header_sort(
		$display_text,
		get_request_var('sort_column'),
		get_request_var('sort_direction'),
		'1',
		$url . '?action=edit&id=' . get_request_var('id') . '&page=' . get_request_var('page')
	);

	$i = 0;
	if (sizeof($templates)) {
		foreach ($templates as 	$template) {
			cacti_log(__FUNCTION__ . ' template: ' . serialize($template), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);
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
			cacti_log(__FUNCTION__ . " replacement: $repl", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

			form_alternate_row('line' . $template['host_id'], true);
			form_selectable_cell(filter_value($template['description'], get_request_var('filter'), "host.php?action=edit&id=" . $template['host_id']), $template['host_id']);
			form_selectable_cell(filter_value($template['hostname'], get_request_var('filter')), $template['host_id']);
			form_selectable_cell(filter_value($template['host_template_name'], get_request_var('filter')), $template['host_id']);
			form_selectable_cell(get_colored_device_status(($template['disabled'] == 'on' ? true : false), $template['status']), $template['host_id']);
			form_selectable_cell($template['source'], $template['host_id']);
			form_selectable_cell($repl, $template['host_id']);
			form_end_row();
		}
	} else {
		print "<tr><td colspan='6'><em>" . __('No Items Found') . "</em></td></tr>";
	}

	html_end_box(true);

	if (sizeof($templates)) {
		print $nav;
	}

	print "</form>\n";
}

function display_match_rule_items($title, $rule_id, $rule_type, $module) {
	global $automation_op_array, $automation_oper, $automation_tree_header_types;

	$items = db_fetch_assoc_prepared('SELECT *
		FROM automation_match_rule_items
		WHERE rule_id = ?
		AND rule_type = ?
		ORDER BY sequence',
		array($rule_id, $rule_type));

	html_start_box($title, '100%', '', '3', 'center', $module . '?action=item_edit&id=' . $rule_id . '&rule_type=' . $rule_type);

	$display_text = array(
		array('display' => __('Item'),      'align' => 'left'),
		array('display' => __('Sequence'),  'align' => 'left'),
		array('display' => __('Operation'), 'align' => 'left'),
		array('display' => __('Field'),     'align' => 'left'),
		array('display' => __('Operator'),  'align' => 'left'),
		array('display' => __('Pattern'),   'align' => 'left'),
		array('display' => __('Actions'),   'align' => 'right')
	);

	html_header($display_text, 2);

	$i = 0;
	if (sizeof($items)) {
		foreach ($items as $item) {
			$operation = ($item['operation'] != 0) ? $automation_oper[$item['operation']] : '&nbsp;';

			form_alternate_row();
			$form_data = '<td><a class="linkEditMain" href="' . html_escape($module . '?action=item_edit&id=' . $rule_id. '&item_id=' . $item['id'] . '&rule_type=' . $rule_type) . '">' . __('Item#%d', $i+1) . '</a></td>';
			$form_data .= '<td>' . 	$item['sequence'] . '</td>';
			$form_data .= '<td>' . 	$operation . '</td>';
			$form_data .= '<td>' . 	$item['field'] . '</td>';
			$form_data .= '<td>' . 	((isset($item['operator']) && $item['operator'] > 0) ? $automation_op_array['display'][$item['operator']] : '') . '</td>';
			$form_data .= '<td>' . 	$item['pattern'] . '</td>';

			$form_data .= '<td class="right nowrap">';

			if ($i != sizeof($items)-1) {
				$form_data .= '<a class="pic fa fa-caret-down moveArrow" href="' . html_escape($module . '?action=item_movedown&item_id=' . $item['id'] . '&id=' . $rule_id . '&rule_type=' . $rule_type) . '" title="' . __esc('Move Down') . '"></a>';
			} else {
				$form_data .= '<span class="moveArrowNone"></span>';
			}

			if ($i > 0) {
				$form_data .= '<a class="pic fa fa-caret-up moveArrow" href="' . html_escape($module . '?action=item_moveup&item_id=' . $item['id'] .	'&id=' . $rule_id .	'&rule_type=' . $rule_type) . '" title="' . __esc('Move Up') . '"></a>';
			} else {
				$form_data .= '<span class="moveArrowNone"></span>';
			}
			$form_data .= '</td>';

			$form_data .= '<td style="width:1%;">
				<a class="pid deleteMarker fa fa-remove" href="' . html_escape($module . '?action=item_remove&item_id=' . $item['id'] .	'&id=' . $rule_id .	'&rule_type=' . $rule_type) . '" title="' . __esc('Delete') . '"></a></td>
			</tr>';

			print $form_data;

			$i++;
		}
	} else {
		print "<tr><td colspan='8'><em>" . __('No Device Selection Criteria') . "</em></td></tr>\n";
	}

	html_end_box(true);
}

function display_graph_rule_items($title, $rule_id, $rule_type, $module) {
	global $automation_op_array, $automation_oper, $automation_tree_header_types;

	$items = db_fetch_assoc_prepared('SELECT * FROM automation_graph_rule_items WHERE rule_id = ? ORDER BY sequence', array($rule_id));

	html_start_box($title, '100%', '', '3', 'center', $module . '?action=item_edit&id=' . $rule_id . '&rule_type=' . $rule_type);

	$display_text = array(
		array('display' => __('Item'),      'align' => 'left'),
		array('display' => __('Sequence'),  'align' => 'left'),
		array('display' => __('Operation'), 'align' => 'left'),
		array('display' => __('Field'),     'align' => 'left'),
		array('display' => __('Operator'),  'align' => 'left'),
		array('display' => __('Pattern'),   'align' => 'left'),
		array('display' => __('Actions'),   'align' => 'right')
	);

	html_header($display_text, 2);

	$i = 0;
	if (sizeof($items)) {
		foreach ($items as $item) {
			$operation = ($item['operation'] != 0) ? $automation_oper[$item['operation']] : '&nbsp;';

			form_alternate_row();
			$form_data = '<td><a class="linkEditMain" href="' . html_escape($module . '?action=item_edit&id=' . $rule_id. '&item_id=' . $item['id'] . '&rule_type=' . $rule_type) . '">' . __('Item#%d', $i+1) . '</a></td>';
			$form_data .= '<td>' . 	$item['sequence'] . '</td>';
			$form_data .= '<td>' . 	$operation . '</td>';
			$form_data .= '<td>' . 	$item['field'] . '</td>';
			$form_data .= '<td>' . 	(($item['operator'] > 0 || $item['operator'] == '') ? $automation_op_array['display'][$item['operator']] : '') . '</td>';
			$form_data .= '<td>' . 	$item['pattern'] . '</td>';

			$form_data .= '<td class="right nowrap">';

			if ($i != sizeof($items)-1) {
				$form_data .= '<a class="pic fa fa-awwow-down moveArrow" href="' . html_escape($module . '?action=item_movedown&item_id=' . $item['id'] . '&id=' . $rule_id .	'&rule_type=' . $rule_type) . '" title="' . __esc('Move Down') . '"></a>';
			} else {
				$form_data .= '<span class="moveArrowNone"></span>';
			}

			if ($i > 0) {
				$form_data .= '<a class="pic fa fa-caret-up moveArrow" href="' . html_escape($module . '?action=item_moveup&item_id=' . $item['id'] .	'&id=' . $rule_id .	'&rule_type=' . $rule_type) . '" title="' . __esc('Move Up') . '"></a>';
			} else {
				$form_data .= '<span class="moveArrowNone"></span>';
			}
			$form_data .= '</td>';

			$form_data .= '<td class="right nowrap">
				<a class="pic deleteMarker fa fa-remove" href="' . html_escape($module . '?action=item_remove&item_id=' . $item['id'] .	'&id=' . $rule_id .	'&rule_type=' . $rule_type) . '" title="' . __esc('Delete') . '"></a></td>
			</tr>';

			print $form_data;
			$i++;
		}
	} else {
		print "<tr><td colspan='8'><em>" . __('No Graph Creation Criteria') . "</em></td></tr>\n";
	}

	html_end_box(true);

}

function display_tree_rule_items($title, $rule_id, $item_type, $rule_type, $module) {
	global $automation_tree_header_types, $tree_sort_types, $host_group_types;

	$items = db_fetch_assoc_prepared('SELECT * FROM automation_tree_rule_items WHERE rule_id = ? ORDER BY sequence', array($rule_id));

	html_start_box($title, '100%', '', '3', 'center', $module . '?action=item_edit&id=' . $rule_id . '&rule_type=' . $rule_type);

	$display_text = array(
		array('display' => __('Item'),             'align' => 'left'),
		array('display' => __('Sequence'),         'align' => 'left'),
		array('display' => __('Field Name'),       'align' => 'left'),
		array('display' => __('Sorting Type'),     'align' => 'left'),
		array('display' => __('Propagate Change'), 'align' => 'left'),
		array('display' => __('Search Pattern'),   'align' => 'left'),
		array('display' => __('Replace Pattern'),  'align' => 'left'),
		array('display' => __('Actions'),          'align' => 'right')
	);

	html_header($display_text, 2);

	$i = 0;
	if (sizeof($items)) {
		foreach ($items as $item) {
			#print '<pre>'; print_r($item); print '</pre>';
			$field_name = ($item['field'] === AUTOMATION_TREE_ITEM_TYPE_STRING) ? $automation_tree_header_types[AUTOMATION_TREE_ITEM_TYPE_STRING] : $item['field'];

			form_alternate_row();
			$form_data = '<td><a class="linkEditMain" href="' . html_escape($module . '?action=item_edit&id=' . $rule_id. '&item_id=' . $item['id'] . '&rule_type=' . $rule_type) . '">' . __('Item#%d', $i+1) . '</a></td>';
			$form_data .= '<td>' . 	$item['sequence'] . '</td>';
			$form_data .= '<td>' . 	$field_name . '</td>';
			$form_data .= '<td>' . 	$tree_sort_types[$item['sort_type']] . '</td>';
			$form_data .= '<td>' . 	($item['propagate_changes'] ? 'Yes' : 'No') . '</td>';
			$form_data .= '<td>' . 	$item['search_pattern'] . '</td>';
			$form_data .= '<td>' . 	$item['replace_pattern'] . '</td>';

			$form_data .= '<td class="right">';
			if ($i != sizeof($items)-1) {
				$form_data .= '<a class="pic fa fa-caret-down moveArrow" href="' . html_escape($module . '?action=item_movedown&item_id=' . $item['id'] . '&id=' . $rule_id .	'&rule_type=' . $rule_type) . '" title="' . __esc('Move Down') . '"></a>';
			} else {
				$form_data .= '<span class="moveArrowNone"></span>';
			}

			if ($i > 0) {
				$form_data .= '<a class="pic fa fa-caret-up moveArrow" href="' . html_escape($module . '?action=item_moveup&item_id=' . $item['id'] .	'&id=' . $rule_id .	'&rule_type=' . $rule_type) . '" title="' . __esc('Move Up') . '"></a>';
			} else {
				$form_data .= '<span class="moveArrowNone"></span>';
			}
			$form_data .= '</td>';

			$form_data .= '<td class="nowrap" style="width:1%;">
				<a class="pic deleteMarker fa fa-remove" href="' . html_escape($module . '?action=item_remove&item_id=' . $item['id'] . '&id=' . $rule_id .	'&rule_type=' . $rule_type) . '" title="' . __esc('Delete') . '"></a></td>
			</tr>';

			print $form_data;

			$i++;
		}
	} else {
		print "<tr><td><em>" . __('No Tree Creation Criteria') . "</em></td></tr>\n";
	}

	html_end_box(true);
}

function duplicate_automation_graph_rules($_id, $_title) {
	global $fields_automation_graph_rules_edit1, $fields_automation_graph_rules_edit2, $fields_automation_graph_rules_edit3;

	$rule        = db_fetch_row_prepared('SELECT * FROM automation_graph_rules WHERE id = ?', array($_id));
	$match_items = db_fetch_assoc_prepared('SELECT * FROM automation_match_rule_items WHERE rule_id = ? AND rule_type = ?', array($_id, AUTOMATION_RULE_TYPE_GRAPH_MATCH));
	$rule_items  = db_fetch_assoc_prepared('SELECT * FROM automation_graph_rule_items WHERE rule_id = ?', array($_id));

	$fields_automation_graph_rules_edit = $fields_automation_graph_rules_edit1 + $fields_automation_graph_rules_edit2 + $fields_automation_graph_rules_edit3;
	$save = array();
	foreach ($fields_automation_graph_rules_edit as $field => $array) {
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

	$rule        = db_fetch_row_prepared('SELECT * FROM automation_tree_rules WHERE id = ?', array($_id));
	$match_items = db_fetch_assoc_prepared('SELECT * FROM automation_match_rule_items WHERE rule_id = ? AND rule_type = ?', array($_id, AUTOMATION_RULE_TYPE_TREE_MATCH));
	$rule_items  = db_fetch_assoc_prepared('SELECT * FROM automation_tree_rule_items WHERE rule_id = ?', array($_id));

	$fields_automation_tree_rules_edit = $fields_automation_tree_rules_edit1 + $fields_automation_tree_rules_edit2 + $fields_automation_tree_rules_edit3;
	$save = array();
	foreach ($fields_automation_tree_rules_edit as $field => $array) {
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
			$save['search_pattern'] = form_input_validate($rule_item['search_pattern'], 'search_pattern', '', false, 3);
			$save['replace_pattern'] = form_input_validate($rule_item['replace_pattern'], 'replace_pattern', '', true, 3);
			$save['id'] = 0;
			$save['rule_id'] = $rule_id;
			$rule_item_id = sql_save($save, 'automation_tree_rule_items');
		}
	}
}

function build_graph_object_sql_having($rule, $filter) {
	if ($filter != '') {
		$field_names = get_field_names($rule['snmp_query_id']);

		if (sizeof($field_names)) {
			$sql_having = ' HAVING (';
			$i = 0;

			foreach($field_names as $column) {
				$sql_having .= ($i == 0 ? '':' OR ') . '`' . implode('`.`', explode('.', $column['field_name'])) . '`' . ' LIKE "%' . $filter . '%"';
				$i++;
			}

			$sql_having .= ')';
		}

		return $sql_having;
	}
}

function build_data_query_sql($rule) {
	cacti_log(__FUNCTION__ . ' called: ' . serialize($rule), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	$field_names = get_field_names($rule['snmp_query_id']);
	$sql_query = 'SELECT h.hostname AS automation_host, host_id, h.disabled, h.status, snmp_query_id, snmp_index ';
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
	cacti_log(__FUNCTION__ . ' returns: ' . $sql_query, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	return $sql_query;
}

function build_matching_objects_filter($rule_id, $rule_type) {
	cacti_log(__FUNCTION__ . " called rule id: $rule_id", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	$sql_filter = '';

	/* create an SQL which queries all host related tables in a huge join
	 * this way, we may add any where clause that might be added via
	 *  'Matching Device' match
	 */
	$rule_items = db_fetch_assoc_prepared('SELECT *
		FROM automation_match_rule_items
		WHERE rule_id = ?
		AND rule_type = ?
		ORDER BY sequence',
		array($rule_id, $rule_type));

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

	cacti_log(__FUNCTION__ . ' returns: ' . $sql_filter, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	return $sql_filter;
}

function build_rule_item_filter($automation_rule_items, $prefix = '') {
	global $automation_op_array, $automation_oper;

	cacti_log(__FUNCTION__ . ' called: ' . serialize($automation_rule_items) . ", prefix: $prefix", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	$sql_filter = '';
	if (sizeof($automation_rule_items)) {
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
				$sql_filter .= (' ' . $prefix . '`' . implode('`.`', explode('.', $automation_rule_item['field'])) . '`');
				#
				$sql_filter .= ' ' . $automation_op_array['op'][$automation_rule_item['operator']] . ' ';
				if ($automation_op_array['binary'][$automation_rule_item['operator']]) {
					$sql_filter .= (db_qstr($automation_op_array['pre'][$automation_rule_item['operator']] . $automation_rule_item['pattern'] . $automation_op_array['post'][$automation_rule_item['operator']]));
				}
			}
		}
	}

	cacti_log(__FUNCTION__ . ' returns: ' . $sql_filter, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	return $sql_filter;
}

/*
 * build_sort_order
 * @arg $index_order	sort order given by e.g. xml_array[index_order_type]
 * @arg $default_order	default order if any
 * return				sql sort order string
 */
function build_sort_order($index_order, $default_order = '') {
	cacti_log(__FUNCTION__ . " called: $index_order/$default_order", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

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
	if ($sql_order != '') {
		$sql_order = 'ORDER BY ' . $sql_order;
	}

	cacti_log(__FUNCTION__ . " returns: $sql_order", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

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
	cacti_log(__FUNCTION__ . ' called: ' . serialize($rule) . ' type: ' . $rule_type, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	/* build magic query, for matching hosts JOIN tables host and host_template */
	$sql_query = 'SELECT h.id AS host_id, h.hostname, h.description,
		h.disabled, h.status, ht.name AS host_template_name
		FROM host AS h
		LEFT JOIN host_template AS ht
		ON (h.host_template_id=ht.id) ';

	/* get the WHERE clause for matching hosts */
	$sql_filter = ' WHERE (' . build_matching_objects_filter($rule['id'], $rule_type) .')';
	if ($sql_where != '') {
		$sql_filter .= ' AND ' . $sql_where;
	}

	return db_fetch_assoc($sql_query . $sql_filter, false);
}

/**
 * get an array of graphs matching a graph_match rule
 * @param array $rule		- rule
 * @param int $rule_type	- rule type
 * @param string $sql_where - additional where clause
 * @return array			- matching graphs
 */
function get_matching_graphs($rule, $rule_type, $sql_where = '') {
	cacti_log(__FUNCTION__ . ' called: ' . serialize($rule) . ' type: ' . $rule_type, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

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

	if ($sql_where != '') {
		$sql_filter .= ' AND ' . $sql_where;
	}

	return db_fetch_assoc($sql_query . $sql_filter, false);
}

/*
 * get_created_graphs
 * @arg $rule		provide snmp_query_id, graph_type_id
 * return			all graphs that have already been created for the given selection
 */
function get_created_graphs($rule) {
	cacti_log(__FUNCTION__ . ' called: ' . serialize($rule), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

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

	$graphs = db_fetch_assoc($sql, false);

	# rearrange items to ease indexed access
	$items = array();
	if (sizeof($graphs)) {
		foreach ($graphs as $graph) {
			$items[$graph['host_id']][$graph['snmp_index']] = $graph['snmp_index'];
		}
	}

	return $items;

}

function get_query_fields($table, $excluded_fields) {
	cacti_log(__FUNCTION__ . ' called', false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	$table = trim($table);

	$sql = 'SHOW COLUMNS FROM ' . $table;
	$fields = array_rekey(db_fetch_assoc($sql), 'Field', 'Type');
	#print '<pre>'; print_r($fields); print '</pre>';
	# remove unwanted entries
	$fields = array_minus($fields, $excluded_fields);

	# now reformat entries for use with draw_edit_form
	if (sizeof($fields)) {
		foreach ($fields as $key => $value) {
			switch($table) {
			case 'graph_templates_graph':
				$table = 'gtg';
				break;
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
	cacti_log(__FUNCTION__ . " called: $snmp_query_id", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	/* get the unique field values from the database */
	$sql = 'SELECT DISTINCT field_name FROM host_snmp_cache WHERE snmp_query_id=' . $snmp_query_id;
	return db_fetch_assoc($sql);
}

function array_to_list($array, $sql_column) {
	/* if the last item is null; pop it off */
	$counter = count($array);
	if (empty($array[$counter-1]) && $counter > 1) {
		array_pop($array);
		$counter = count($array);
	}

	if ($counter > 0) {
		$sql = '(';

		for ($i=0; $i<$counter; $i++) {
			$sql .= $array[$i][$sql_column];

			if ($i+1 < $counter) {
				$sql .= ',';
			}
		}

		$sql .= ')';

		cacti_log(__FUNCTION__ . " returns: $sql", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);
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
		$title = __('Device Match Rule');
		$item_table = 'automation_match_rule_items';
		$sql_and = ' AND rule_type=' . $rule_type;
		$tables = array ('host', 'host_templates');
		$automation_rule = db_fetch_row_prepared('SELECT * FROM automation_graph_rules WHERE id = ?', array($rule_id));

		$_fields_rule_item_edit = $fields_automation_match_rule_item_edit;
		$query_fields  = get_query_fields('host_template', array('id', 'hash'));
		$query_fields += get_query_fields('host', array('id', 'host_template_id'));

		$_fields_rule_item_edit['field']['array'] = $query_fields;
		$module = 'automation_graph_rules.php';

		break;
	case AUTOMATION_RULE_TYPE_GRAPH_ACTION:
		$title      = __('Create Graph Rule');
		$tables     = array(AUTOMATION_RULE_TABLE_XML);
		$item_table = 'automation_graph_rule_items';
		$sql_and    = '';

		$automation_rule = db_fetch_row_prepared('SELECT *
			FROM automation_graph_rules
			WHERE id = ?',
			array($rule_id));

		$_fields_rule_item_edit = $fields_automation_graph_rule_item_edit;
		$xml_array = get_data_query_array($automation_rule['snmp_query_id']);
		$fields = array();

		if (sizeof($xml_array) && sizeof($xml_array['fields'])) {
			foreach($xml_array['fields'] as $key => $value) {
				# ... work on all input fields
				if (isset($value['direction']) && ($value['direction'] == 'input' || $value['direction'] == 'input-output')) {
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
		$automation_rule = db_fetch_row_prepared('SELECT * FROM automation_tree_rules WHERE id = ?', array($rule_id));
		$_fields_rule_item_edit = $fields_automation_match_rule_item_edit;
		$query_fields  = get_query_fields('host_template', array('id', 'hash'));
		$query_fields += get_query_fields('host', array('id', 'host_template_id'));

		if ($automation_rule['leaf_type'] == TREE_ITEM_TYPE_HOST) {
			$title = __('Device Match Rule');
			$tables = array ('host', 'host_templates');
			#print '<pre>'; print_r($query_fields); print '</pre>';
		} elseif ($automation_rule['leaf_type'] == TREE_ITEM_TYPE_GRAPH) {
			$title = __('Graph Match Rule');
			$tables = array ('host', 'host_templates');
			# add some more filter columns for a GRAPH match
			$query_fields += get_query_fields('graph_templates', array('id', 'hash'));
			$query_fields += array('gtg.title' => 'GTG: title - varchar(255)');
			$query_fields += array('gtg.title_cache' => 'GTG: title_cache - varchar(255)');
			#print '<pre>'; print_r($query_fields); print '</pre>';
		}
		$_fields_rule_item_edit['field']['array'] = $query_fields;
		$module = 'automation_tree_rules.php';

		break;
	case AUTOMATION_RULE_TYPE_TREE_ACTION:
		$item_table = 'automation_tree_rule_items';
		$sql_and = '';
		$automation_rule = db_fetch_row_prepared('SELECT * FROM automation_tree_rules WHERE id = ?', array($rule_id));

		$_fields_rule_item_edit = $fields_automation_tree_rule_item_edit;
		$query_fields  = get_query_fields('host_template', array('id', 'hash'));
		$query_fields += get_query_fields('host', array('id', 'host_template_id'));

		/* list of allowed header types depends on rule leaf_type
		 * e.g. for a Device Rule, only Device-related header types make sense
		 */
		if ($automation_rule['leaf_type'] == TREE_ITEM_TYPE_HOST) {
			$title = __('Create Tree Rule (Device)');
			$tables = array ('host', 'host_templates');
			#print '<pre>'; print_r($query_fields); print '</pre>';
		} elseif ($automation_rule['leaf_type'] == TREE_ITEM_TYPE_GRAPH) {
			$title = __('Create Tree Rule (Graph)');
			$tables = array ('host', 'host_templates');
			# add some more filter columns for a GRAPH match
			$query_fields += get_query_fields('graph_templates', array('id', 'hash'));
			$query_fields += array('gtg.title' => 'GTG: title - varchar(255)');
			$query_fields += array('gtg.title_cache' => 'GTG: title_cache - varchar(255)');
			#print '<pre>'; print_r($query_fields); print '</pre>';
		}
		$_fields_rule_item_edit['field']['array'] = $query_fields;
		$module = 'automation_tree_rules.php';

		break;
	}

	if (!empty($rule_item_id)) {
		$automation_item = db_fetch_row("SELECT *
			FROM $item_table
			WHERE id=$rule_item_id
			$sql_and");

		$header_label = __('Rule Item [edit rule item for %s: %s]', $title, $automation_rule['name']);
	} else {
		$header_label = __('Rule Item [new rule item for %s: %s]', $title, $automation_rule['name']);
		$automation_item = array();
		$automation_item['sequence'] = get_sequence('', 'sequence', $item_table, 'rule_id=' . $rule_id . $sql_and);
	}

	form_start($module, 'form_automation_global_item_edit');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($_fields_rule_item_edit, (isset($automation_item) ? $automation_item : array()), (isset($automation_rule) ? $automation_rule : array()))
		)
	);

	html_end_box(true, true);
}

/**
 * hook executed for a graph template
 * @param $host_id - the host to perform automation on
 * @param $graph_template_id - the graph_template_id to perform automation on
 */
function automation_hook_graph_template($host_id, $graph_template_id) {
	global $config;

	if (read_config_option('automation_graphs_enabled') == '') {
		cacti_log(__FUNCTION__ . ' Device[' . $host_id . '] - skipped: Graph Creation Switch is: ' . (read_config_option('automation_graphs_enabled') == '' ? 'off' : 'on') . ' graph template: ' . $graph_template_id, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);
		return;
	}

	automation_execute_graph_template($host_id, $graph_template_id);
}

/**
 * hook executed for a new graph on a tree
 * @param $data - data passed from hook
 */
function automation_hook_graph_create_tree($data) {
	global $config;

	cacti_log(__FUNCTION__ . ' called: ' . serialize($data), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	if (read_config_option('automation_tree_enabled') == '') {
		cacti_log(__FUNCTION__ . ' Graph[' . $data['id'] . '] - skipped: Tree Creation Switch is: ' . (read_config_option('automation_tree_enabled') == '' ? 'off' : 'on'), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);
		return;
	}

	automation_execute_graph_create_tree($data['id']);

	/* make sure, the next plugin gets required $data */
	return($data);
}

/**
 * run rules for a data query
 * @param $data - data passed from hook
 */
function automation_execute_data_query($host_id, $snmp_query_id) {
	global $config;

	cacti_log(__FUNCTION__ . ' Device[' . $host_id . "] - start - data query: $snmp_query_id", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	# get all related rules for that data query that are enabled
	$sql = "SELECT agr.id, agr.name,
		agr.snmp_query_id, agr.graph_type_id
		FROM automation_graph_rules AS agr
		WHERE snmp_query_id=$snmp_query_id
		AND enabled='on'";

	$rules = db_fetch_assoc($sql);

	cacti_log(__FUNCTION__ . ' Device[' . $host_id . "] - sql: $sql - found: " . sizeof($rules), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	if (!sizeof($rules)) return;

	# now walk all rules and create graphs
	if (sizeof($rules)) {
		foreach ($rules as $rule) {
			cacti_log(__FUNCTION__ . ' Device[' . $host_id . '] - rule=' . $rule['id'] . ' name: ' . $rule['name'], false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

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

			$hosts = db_fetch_assoc($rows_query, false);

			cacti_log(__FUNCTION__ . ' Device[' . $host_id . "] - create sql: $rows_query matches:" . sizeof($hosts), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

			if (!sizeof($hosts)) { continue; }

			create_dq_graphs($host_id, $snmp_query_id, $rule);
		}
	}
}

/**
 * run rules for a graph template
 * @param $data - data passed from hook
 */
function automation_execute_graph_template($host_id, $graph_template_id) {
	global $config;

	include_once($config['base_path'] . '/lib/template.php');
	include_once($config['base_path'] . '/lib/api_automation_tools.php');
	include_once($config['base_path'] . '/lib/utility.php');

	$dataSourceId = '';
	$function  = 'Function[' . __FUNCTION__ . ']';

	cacti_log(__FUNCTION__ . ' called: Device[' . $host_id . '] - graph template: ' . $graph_template_id, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	# are there any input fields?
	if ($graph_template_id > 0) {
		$input_fields = getInputFields($graph_template_id);
		if (sizeof($input_fields)) {
			# do nothing for such graph templates
			return;
		}
	}

	# graph already present?
	$existsAlready = db_fetch_cell_prepared('SELECT id
		FROM graph_local
		WHERE graph_template_id = ?
		AND host_id = ?',
		array($graph_template_id, $host_id));

	if ((isset($existsAlready)) && ($existsAlready > 0)) {
		$dataSourceId  = db_fetch_cell_prepared('SELECT
			data_template_rrd.local_data_id
			FROM graph_templates_item, data_template_rrd
			WHERE graph_templates_item.local_graph_id = ?
			AND graph_templates_item.task_item_id = data_template_rrd.id
			LIMIT 1', array($existsAlready));

		cacti_log('NOTE: ' . $function . ' Device[' . $host_id . "] Graph - Already Exists - graph-id: ($existsAlready) - data-source-id: ($dataSourceId)", false, 'AUTOM8', POLLER_VERBOSITY_MEDIUM);
		return;
	} else {
		# input fields are not supported
		$suggested_values = array();
		$returnArray = create_complete_graph_from_template($graph_template_id, $host_id, array(), $suggested_values);

		$dataSourceId = '';
		if (sizeof($returnArray)) {
			if (isset($returnArray['local_data_id'])) {
				foreach($returnArray['local_data_id'] as $item) {
					push_out_host($host_id, $item);

					if ($dataSourceId != '') {
						$dataSourceId .= ', ' . $item;
					} else {
						$dataSourceId = $item;
					}
				}

				cacti_log('Device[' . $host_id . '] Graph Added - graph-id: (' . $returnArray['local_graph_id'] . ") - data-source-ids: ($dataSourceId)", false, 'AUTOM8');
			}
		} else {
			cacti_log('Device[' . $host_id . '] ERROR: graph-id: (' . $returnArray['local_graph_id'] . ') without data sources', false, 'AUTOM8');
		}

	}
}

/**
 * run rules for a new device in a tree
 * @param $host_id - the host id of the device
 */
function automation_execute_device_create_tree($host_id) {
	global $config;

	/* the $data array holds all information about the host we're just working on
	 * even if we selected multiple hosts, the calling code will scan through the list
	 * so we only have a single host here
	 */

	cacti_log(__FUNCTION__ . " Device[$host_id] called", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	/*
	 * find all active Tree Rules
	 * checking whether a specific rule matches the selected host
	 * has to be done later
	 */
	$sql = "SELECT atr.id, atr.name, atr.tree_id, atr.tree_item_id,
		atr.leaf_type, atr.host_grouping_type
		FROM automation_tree_rules AS atr
		WHERE enabled='on'
		AND leaf_type=" . TREE_ITEM_TYPE_HOST;

	$rules = db_fetch_assoc($sql);

	cacti_log(__FUNCTION__ . " Device[$host_id], matching rule sql: $sql matches: " . sizeof($rules), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	/* now walk all rules
	 */
	if (sizeof($rules)) {
		foreach ($rules as $rule) {
			cacti_log(__FUNCTION__ . " Device[$host_id], active rule: " . $rule['id'] . ' name: ' . $rule['name'] . ' type: ' . $rule['leaf_type'], false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

			/* does the rule apply to the current host?
			 * test 'eligible objects' rule items */
			$matches = get_matching_hosts($rule, AUTOMATION_RULE_TYPE_TREE_MATCH, 'h.id=' . $host_id);

			cacti_log(__FUNCTION__ . " Device[$host_id], matching hosts: " . serialize($matches), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

			/* if the rule produces a match, we will have to create all required tree nodes */
			if (sizeof($matches)) {
				/* create the bunch of header nodes */
				$parent = create_all_header_nodes($host_id, $rule);
				cacti_log(__FUNCTION__ . " Device[$host_id], parent: " . $parent, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

				/* now that all rule items have been executed, add the item itself */
				$node = create_device_node($host_id, $parent, $rule);
				cacti_log(__FUNCTION__ . " Device[$host_id], node: " . $node, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);
			}
		}
	}
}

/**
 * run rules for a new graph on a tree
 * @param $data - data passed from hook
 */
function automation_execute_graph_create_tree($graph_id) {
	global $config;

	/* the $data array holds all information about the graph we're just working on
	 * even if we selected multiple graphs, the calling code will scan through the list
	 * so we only have a single graph here
	 */

	cacti_log(__FUNCTION__ . ' Graph[' . $graph_id . '] called', false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	/*
	 * find all active Tree Rules
	 * checking whether a specific rule matches the selected graph
	 * has to be done later
	 */
	$sql = "SELECT atr.id, atr.name, atr.tree_id, atr.tree_item_id,
		atr.leaf_type, atr.host_grouping_type
		FROM automation_tree_rules AS atr
		WHERE enabled='on'
		AND leaf_type=" . TREE_ITEM_TYPE_GRAPH;

	$rules = db_fetch_assoc($sql);

	cacti_log(__FUNCTION__ . ' Graph[' . $graph_id . "], Matching rule sql: $sql matches: " . sizeof($rules), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	/* now walk all rules
	 */
	if (sizeof($rules)) {
		foreach ($rules as $rule) {
			cacti_log(__FUNCTION__ . ' Graph[' . $graph_id . '], active rule: ' . $rule['id'] . ' name: ' . $rule['name'] . ' type: ' . $rule['leaf_type'], false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

			/* does this rule apply to the current graph?
			 * test 'eligible objects' rule items */
			$matches = get_matching_graphs($rule, AUTOMATION_RULE_TYPE_TREE_MATCH, 'gl.id=' . $graph_id);

			cacti_log(__FUNCTION__ . ' Graph[' . $graph_id . '], Matching graphs: ' . serialize($matches), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

			/* if the rule produces a match, we will have to create all required tree nodes */
			if (sizeof($matches)) {
				/* create the bunch of header nodes */
				$parent = create_all_header_nodes($graph_id, $rule);
				cacti_log(__FUNCTION__ . ' Graph[' . $graph_id . '], Parent: ' . $parent, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

				/* now that all rule items have been executed, add the item itself */
				$node = create_graph_node($graph_id, $parent, $rule);
				cacti_log(__FUNCTION__ . ' Graph[' . $graph_id . '], Node: ' . $node, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);
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

	include_once($config['base_path'] . '/lib/template.php');

	cacti_log(__FUNCTION__ . ' Device[' . $host_id . "] - snmp query: $snmp_query_id - rule: " . $rule['name'], false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	$function  = 'Function[' . __FUNCTION__ . ']';

	$snmp_query_array = array();
	$snmp_query_array['snmp_query_id']       = $rule['snmp_query_id'];
	$snmp_query_array['snmp_index_on']       = get_best_data_query_index_type($host_id, $rule['snmp_query_id']);
	$snmp_query_array['snmp_query_graph_id'] = $rule['graph_type_id'];

	# get all rule items
	$automation_rule_items = db_fetch_assoc_prepared('SELECT *
		FROM automation_graph_rule_items AS agri
		WHERE rule_id = ?
		ORDER BY sequence',
		array($rule['id']));

	# and all matching snmp_indices from snmp_cache

	/* get the unique field values from the database */
	$field_names = db_fetch_assoc_prepared('SELECT DISTINCT
		field_name
		FROM host_snmp_cache AS hsc
		WHERE snmp_query_id= ?',
		array($snmp_query_id));

	#print '<pre>Field Names: $sql<br>'; print_r($field_names); print '</pre>';

	/* build magic query */
	$sql_query  = 'SELECT host_id, snmp_query_id, snmp_index';

	$num_visible_fields = sizeof($field_names);
	$i = 0;
	if (sizeof($field_names) > 0) {
		foreach($field_names as $column) {
			$field_name = $column['field_name'];
			$sql_query .= ", MAX(CASE WHEN field_name ='$field_name' THEN field_value ELSE NULL END) AS '$field_name'";
			$i++;
		}
	}

	$sql_query .= ' FROM host_snmp_cache AS hsc
		WHERE snmp_query_id=' . $snmp_query_id . '
		AND host_id=' . $host_id . '
		GROUP BY snmp_query_id, snmp_index';

	$sql_filter = build_rule_item_filter($automation_rule_items, ' a.');

	if (strlen($sql_filter)) $sql_filter = ' WHERE' . $sql_filter;

	/* add the additional filter settings to the original data query.
	 IMO it's better for the MySQL server to use the original one
	 as an subquery which requires MySQL v4.1(?) or higher */
	$sql_query = 'SELECT * FROM (' . $sql_query	. ") as a $sql_filter";

	/* fetch snmp indices */
	#	print $sql_query . '\n';
	$snmp_query_indexes = db_fetch_assoc($sql_query);

	# now create the graphs
	if (sizeof($snmp_query_indexes)) {
		$graph_template_id = db_fetch_cell_prepared('SELECT graph_template_id
			FROM snmp_query_graph
			WHERE id = ?',
			array($rule['graph_type_id']));

		cacti_log(__FUNCTION__ . ' Device[' . $host_id . "] - graph template: $graph_template_id", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

		foreach ($snmp_query_indexes as $snmp_index) {
			$snmp_query_array['snmp_index'] = $snmp_index['snmp_index'];

			cacti_log(__FUNCTION__ . ' Device[' . $host_id . '] - checking index: ' . $snmp_index['snmp_index'], false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

			$existsAlready = db_fetch_cell_prepared('SELECT DISTINCT dl.id
				FROM data_local AS dl
				LEFT JOIN data_template_data AS dtd
				ON dl.id=dtd.local_data_id
				LEFT JOIN data_input_data AS did
				ON dtd.id=did.data_template_data_id
				LEFT JOIN data_input_fields AS dif
				ON did.data_input_field_id=dif.id
				LEFT JOIN snmp_query_graph AS sqg
				ON did.value=sqg.id
				WHERE dif.type_code="output_type"
				AND sqg.id = ?
				AND dl.host_id = ?
				AND dl.snmp_query_id = ?
				AND dl.snmp_index = ?',
				array($rule['graph_type_id'], $host_id, $rule['snmp_query_id'], $snmp_query_array['snmp_index']));

			if (isset($existsAlready) && $existsAlready > 0) {
				cacti_log('NOTE: ' . $function . ' Device[' . $host_id . "] Graph - Already Exists - DS[$existsAlready]", false, 'AUTOM8', POLLER_VERBOSITY_MEDIUM);
				continue;
			}

			$myempty = array(); /* Suggested Values are not been implemented */

			$return_array = create_complete_graph_from_template($graph_template_id, $host_id, $snmp_query_array, $myempty);

			if (sizeof($return_array) && array_key_exists('local_graph_id', $return_array) && array_key_exists('local_data_id', $return_array)) {
				$data_source_id = db_fetch_cell_prepared('SELECT
					data_template_rrd.local_data_id
					FROM graph_templates_item, data_template_rrd
					WHERE graph_templates_item.local_graph_id = ?
					AND graph_templates_item.task_item_id = data_template_rrd.id
					LIMIT 1',
					array($return_array['local_graph_id']));

				foreach($return_array['local_data_id'] as $item) {
					push_out_host($host_id, $item);

					if ($data_source_id != '') {
						$data_source_id .= ', ' . $item;
					} else {
						$data_source_id = $item;
					}
				}

				cacti_log('NOTE: ' . $function . ' Device[' . $host_id . '] Graph Added - graph-id: (' . $return_array['local_graph_id'] . ") - data-source-ids: ($data_source_id)", false, 'AUTOM8');
			} else {
				cacti_log('WARNING: ' . $function . ' Device[' . $host_id . '] Graph Not Added', false, 'AUTOM8');
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

	# get all related rules that are enabled
	$tree_items = db_fetch_assoc_prepared('SELECT *
        FROM automation_tree_rule_items AS atri
        WHERE atri.rule_id = ?
        ORDER BY sequence', array($rule['id']));

	cacti_log(__FUNCTION__ . " called: Item $item_id matches: " . sizeof($tree_items) . ' items', false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

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
				ON h.host_template_id=ht.id ';

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

				$target = db_fetch_cell($sql, '', false);
			}

			cacti_log(__FUNCTION__ . " Item $item_id - sql: $sql matches: " . $target, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

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

	cacti_log(__FUNCTION__ . " - object: '" . $object . "', Header: '" . $tree_item['search_pattern'] . "', parent: " . $parent_tree_item_id, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	if ($tree_item['field'] === AUTOMATION_TREE_ITEM_TYPE_STRING) {
		$parent_tree_item_id = create_header_node($tree_item['search_pattern'], $rule, $tree_item, $parent_tree_item_id);
		cacti_log(__FUNCTION__ . " called - object: '" . $object . "', Header: '" . $tree_item['search_pattern'] . "', hooked at: " . $parent_tree_item_id, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);
	} else {
		$replacement = automation_string_replace($tree_item['search_pattern'], $tree_item['replace_pattern'], $object);
		/* build multiline <td> entry */
		#print '<pre>'; print_r($replacement); print '</pre>';

		for ($j=0; sizeof($replacement); $j++) {
			$title = array_shift($replacement);
			$parent_tree_item_id = create_header_node($title, $rule, $tree_item, $parent_tree_item_id);
			cacti_log(__FUNCTION__ . " - object: '" . $object . "', Header: '" . $title . "', hooked at: " . $parent_tree_item_id, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);
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

	include_once($config['base_path'] . '/lib/api_tree.php');

	$id             = 0;  # create a new entry
	$local_graph_id = 0;  # headers don't need no graph_id
	$host_id        = 0;  # or a host_id
	$propagate      = ($item['propagate_changes'] != '');
	$function  = 'Function[' . __FUNCTION__ . ']';

	if (api_tree_branch_exists($rule['tree_id'], $parent_tree_item_id, $title)) {
		$new_item = api_tree_get_branch_id($rule['tree_id'], $parent_tree_item_id, $title);
		cacti_log('NOTE: ' . $function . ' Parent[' . $parent_tree_item_id . '] Tree Item - Already Exists', false, 'AUTOM8', POLLER_VERBOSITY_MEDIUM);
	} else {
		$new_item = api_tree_item_save($id, $rule['tree_id'], TREE_ITEM_TYPE_HEADER, $parent_tree_item_id,
			$title, $local_graph_id, $host_id, $rule['host_grouping_type'], $item['sort_type'], $propagate);

		if (isset($new_item) && $new_item > 0) {
			cacti_log('NOTE: ' . $function . ' Parent[' . $parent_tree_item_id . '] Tree Item - Added - id: (' . $new_item . ') Title: (' .$title . ')', false, 'AUTOM8');
		} else {
			cacti_log('WARNING: ' . $function . ' Parent[' . $parent_tree_item_id . '] Tree Item - Not Added', false, 'AUTOM8');
		}
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

	include_once($config['base_path'] . '/lib/api_tree.php');

	$id             = 0;      # create a new entry
	$local_graph_id = 0;      # hosts don't need no graph_id
	$title          = '';     # nor a title
	$sort_type      = 0;      # nor a sort type
	$propagate      = false;  # nor a propagation flag
	$function       = 'Function[' . __FUNCTION__ . ']';

	if (api_tree_host_exists($rule['tree_id'], $parent, $host_id)) {
		$new_item = db_fetch_cell_prepared('SELECT id
			FROM graph_tree_items
			WHERE host_id = ?
			AND parent = ?
			AND graph_tree_id = ?',
			array($host_id, $parent, $rule['tree_id']));

		cacti_log('NOTE: ' . $function . ' Device[' . $host_id . '] Tree Item - Already Exists', false, 'AUTOM8', POLLER_VERBOSITY_MEDIUM);
	} else {
		$new_item = api_tree_item_save($id, $rule['tree_id'], TREE_ITEM_TYPE_HOST, $parent, $title,
			$local_graph_id, $host_id, $rule['host_grouping_type'], $sort_type, $propagate);

		if (isset($new_item) && $new_item > 0) {
			cacti_log('NOTE: ' . $function . ' Device[' . $host_id . '] Tree Item - Added - id: (' . $new_item . ')', false, 'AUTOM8');
		} else {
			cacti_log('WARNING: ' . $function . ' Device[' . $host_id . '] Tree Item - Not Added', false, 'AUTOM8');
		}
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

	include_once($config['base_path'] . '/lib/api_tree.php');

	$id        = 0;      # create a new entry
	$host_id   = 0;      # graphs don't need no host_id
	$title     = '';     # nor a title
	$sort_type = 0;      # nor a sort type
	$propagate = false;  # nor a propagation flag
	$function  = 'Function[' . __FUNCTION__ . ']';

	if (api_tree_graph_exists($rule['tree_id'], $parent, $graph_id)) {
		$new_item = db_fetch_cell_prepared('SELECT id
			FROM graph_tree_items
			WHERE local_graph_id = ?
			AND parent = ?
			AND graph_tree_id = ?',
			array($graph_id, $parent, $rule['tree_id']));

		cacti_log('NOTE: ' . $function . ' Graph[' . $graph_id . '] Tree Item - Already Exists', false, 'AUTOM8', POLLER_VERBOSITY_MEDIUM);
	} else {
		$new_item = api_tree_item_save($id, $rule['tree_id'], TREE_ITEM_TYPE_GRAPH, $parent, $title,
			$graph_id, $host_id, $rule['host_grouping_type'], $sort_type, $propagate);

		if (isset($new_item) && $new_item > 0) {
			cacti_log('NOTE: ' . $function . ' Graph[' . $graph_id . '] Tree Item - Added - id: (' . $new_item . ')', false, 'AUTOM8');
		} else {
			cacti_log('WARNING: ' . $function . ' Graph[' . $graph_id . '] Tree Item - Not Added', false, 'AUTOM8');
		}
	}

	return $new_item;
}

function automation_poller_bottom() {
	global $config;

	$command_string = trim(read_config_option('path_php_binary'));

	// If its not set, just assume its in the path
	if (trim($command_string) == '') {
		$command_string = 'php';
	}

	$extra_args = ' -q ' . $config['base_path'] . '/poller_automation.php -M';

	exec_background($command_string, $extra_args);
}

function automation_add_device($device, $web = false) {
	global $plugins, $config;

	$template_id          = $device['host_template'];
	$snmp_sysName         = $device['snmp_sysName'];
	$description          = ($snmp_sysName != '' ? $snmp_sysName : ($device['hostname'] == '' ? $device['ip'] : $device['hostname']));
	$poller_id            = isset($device['poller_id']) ? $device['poller_id'] : read_config_option('default_poller');
	$site_id              = isset($device['site_id']) ? $device['site_id'] : read_config_option('default_site');
	$ip                   = isset($device['ip']) ? $device['ip']:$device['ip_address'];
	$snmp_community       = $device['snmp_community'];
	$snmp_ver             = $device['snmp_version'];
	$snmp_username	      = $device['snmp_username'];
	$snmp_password	      = $device['snmp_password'];
	$snmp_port            = $device['snmp_port'];
	$snmp_timeout         = isset($device['snmp_timeout']) ? $device['snmp_timeout']:read_config_option('snmp_timeout');
	$disable              = '';
	$availability_method  = isset($device['availability_method']) ? $device['availability_method']:read_config_option('availability_method');
	$ping_method          = isset($device['ping_method']) ? $device['ping_method'] : read_config_option('ping_method');
	$ping_port            = isset($device['ping_port']) ? $device['ping_port'] : read_config_option('ping_port');
	$ping_timeout         = isset($device['ping_timeout']) ? $device['ping_timeout'] : read_config_option('ping_timeout');
	$ping_retries         = isset($device['ping_retries']) ? $device['ping_retries'] : read_config_option('ping_retries');
	$notes                = isset($device['notes']) ? $device['notes'] : __('Added by Cacti Automation');
	$snmp_auth_protocol   = $device['snmp_auth_protocol'];
	$snmp_priv_passphrase = $device['snmp_priv_passphrase'];
	$snmp_priv_protocol   = $device['snmp_priv_protocol'];
	$snmp_context	      = $device['snmp_context'];
	$snmp_engine_id       = $device['snmp_engine_id'];
	$device_threads       = isset($device['device_threads']) ? $device['device_threads']:1;
	$max_oids             = isset($device['max_oids']) ? $device['max_oids']:10;

	automation_debug(' - Adding Device');

	$host_id = api_device_save('0', $template_id, $description, $ip,
		$snmp_community, $snmp_ver, $snmp_username, $snmp_password,
		$snmp_port, $snmp_timeout, $disable, $availability_method,
		$ping_method, $ping_port, $ping_timeout, $ping_retries,
		$notes, $snmp_auth_protocol, $snmp_priv_passphrase,
		$snmp_priv_protocol, $snmp_context, $snmp_engine_id, $max_oids, $device_threads, $poller_id, $site_id);

	if ($host_id) {
		if (!$web) {
			automation_debug(' - Success');
		}
		/* Use the thold plugin if it exists */
		if (api_plugin_is_enabled('thold')) {
			if (!$web) {
				automation_debug("     Creating Thresholds\n");
			}

			if (file_exists($config['base_path'] . '/plugins/thold/thold-functions.php')) {
				include_once($config['base_path'] . '/plugins/thold/thold-functions.php');
				autocreate($host_id);
			} else if (file_exists($config['base_path'] . '/plugins/thold/thold_functions.php')) {
				include_once($config['base_path'] . '/plugins/thold/thold_functions.php');
				autocreate($host_id);
			}
		}

		db_execute_prepared('DELETE FROM automation_devices WHERE ip = ? LIMIT 1', array($ip));
	} else {
		if (!$web) {
			automation_debug(' - Failed');
		}
	}

	return $host_id;
}

function automation_add_tree ($host_id, $tree) {
	automation_debug("     Adding to tree\n");
	if ($tree > 1000000) {
		$tree_id = $tree - 1000000;
		$parent = 0;
	} else {
		$tree_item = db_fetch_row_prepared('SELECT * FROM graph_tree_items WHERE id = ?', array($tree));

		if (!isset($tree_item['graph_tree_id']))
			return;
		$tree_id = $tree_item['graph_tree_id'];
		$parent = $tree;
	}

	$nodeId = api_tree_item_save(0, $tree_id, 3, $parent, '', 0, $host_id, 1, 1, false);
}

function automation_find_os($sysDescr, $sysObject, $sysName) {
	$sql_where  = '';
	$sql_where .= trim($sysDescr)  != '' ? 'WHERE (' . db_qstr($sysDescr) . ' RLIKE sysDescr OR ' . db_qstr($sysDescr) . ' LIKE CONCAT("%", sysDescr, "%"))':'';
	$sql_where .= trim($sysObject) != '' ? ($sql_where != '' ? ' AND':'WHERE') . ' (' . db_qstr($sysObject) . ' RLIKE sysOid OR ' . db_qstr($sysObject) . ' LIKE CONCAT("%", sysOid, "%"))':'';
	$sql_where .= trim($sysName)   != '' ? ($sql_where != '' ? ' AND':'WHERE') . ' (' . db_qstr($sysName) . ' RLIKE sysName OR ' . db_qstr($sysName) . ' LIKE CONCAT("%", sysName, "%"))':'';

	$result = db_fetch_row("SELECT at.*,ht.name
		FROM automation_templates AS at
		INNER JOIN host_template AS ht
		ON ht.id=at.host_template
		$sql_where
		ORDER BY sequence LIMIT 1");

	if (sizeof($result)) {
		return $result;
	} else {
		return false;
	}
}

function automation_debug($text) {
	global $debug;
	static $message = '';

	if ($debug)	{
		print $text;

		if (strstr($text, "\n") !== false) {
			cacti_log(trim($message . $text), false, "AUTOM8");
			$message = '';
		} else {
			$message .= $text;
		}
	}
}

function automation_calculate_start($networks, $position) {
	if (!isset($networks[$position])) return false;
	$h = trim($networks[$position]);

	// 10.1.0.1
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/", $h)) {
		return $h;
	}

	// 10.1.0.*
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.\*$/", $h)) {
		return substr($h,0,-1) . "1";
	}
	// 10.1.*.1
	if (preg_match("/^([0-9]{1,3}\.[0-9]{1,3}\.)\*(\.[0-9]{1,3})$/", $h, $matches)) {
		return $matches[1] . "1" . $matches[2];
	}

	// 10.1.0.0/24
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.0\/[0-9]{1,2}$/", $h)) {
		preg_match_all("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\./", $h, $matches);
		return $matches[0][0] . "1";
	}

	// 10.1.0./24
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.\/[0-9]{1,2}$/", $h)) {
		preg_match_all("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\./", $h, $matches);
		return $matches[0][0] . "1";
	}

	// 10.1.0/24
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\/[0-9]{1,2}$/", $h)) {
		preg_match_all("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}/", $h, $matches);
		return $matches[0][0] . ".1";
	}

	// 10.1.0.172/24
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\/[0-9]{1,2}$/", $h)) {
		preg_match_all("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}/", $h, $matches);
		return $matches[0][0];
	}

	// 10.1.0.0/255.255.255.0
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.0\/255\.255\.[0-9]{1,3}\.0$/", $h)) {
		preg_match_all("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\./", $h, $matches);
		return $matches[0][0] . "1";
	}

	// 10.1.0.19/255.255.255.240
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\/255\.255\.255\.[0-9]{1,3}\$/", $h)) {
		preg_match_all("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}/", $h, $matches);
		return $matches[0][0];
	}

	// 10.1.0.19-10.1.0.27
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}-[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\$/", $h)) {
		preg_match_all("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}-/", $h, $matches);
		return substr($matches[0][0], 0, -1);
	}

	automation_debug("  Could not calculate starting address!\n");
	return false;
}

function automation_calculate_total_ips($networks, $position) {
	if (!isset($networks[$position])) return false;
	$h = trim($networks[$position]);

	// 10.1.0.1
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/", $h)) {
		return 1;
	}

	// 10.1.0.*
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.\*$/", $h)) {
		return "254";
	}

	// 10.1.*.1
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.\*\.[0-9]{1,3}$/", $h)) {
		return "254";
	}

	// 10.1.0.0/24
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.0\/[0-9]{1,2}$/", $h)) {
		preg_match_all("/\/[0-9]{1,2}$/", $h, $matches);
		$subnet = substr($matches[0][0], 1);
		$total = 1;
		for ($x = 0; $x < (32-$subnet); $x++) {
			$total = $total * 2;
		}
		return $total-2;
	}

	// 10.1.0./24
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.\/[0-9]{1,2}$/", $h)) {
		preg_match_all("/\/[0-9]{1,2}$/", $h, $matches);
		$subnet = substr($matches[0][0], 1);
		$total = 1;
		for ($x = 0; $x < (32-$subnet); $x++) {
			$total = $total * 2;
		}
		return $total-2;
	}

	// 10.1.0/24
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\/[0-9]{1,2}$/", $h)) {
		preg_match_all("/\/[0-9]{1,2}$/", $h, $matches);
		$subnet = substr($matches[0][0], 1);
		$total = 1;
		for ($x = 0; $x < (32-$subnet); $x++) {
			$total = $total * 2;
		}
		return $total-2;
	}

	// 10.1.0.172/24
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\/[0-9]{1,2}$/", $h)) {
		preg_match_all("/\/[0-9]{1,2}$/", $h, $matches);
		$subnet = substr($matches[0][0], 1);
		$total = 1;
		for ($x = 0; $x < (32-$subnet); $x++) {
			$total = $total * 2;
		}
		return $total-2;
	}

	// 10.1.0.0/255.255.255.0
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.0\/255\.255\.[0-9]{1,3}\.0$/", $h)) {
		preg_match_all("/[0-9]{1,3}.0$/", $h, $matches);
		$subnet = substr($matches[0][0], 0, -2);
		return ((256 - $subnet)*256) - 2;
	}

	// 10.1.0.19/255.255.255.240
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\/255\.255\.255\.[0-9]{1,3}\$/", $h)) {
		preg_match_all("/\.[0-9]{1,3}$/", $h, $matches);
		$subnet = substr($matches[0][0], 1);
		return (256 - $subnet)-1;
	}

	// 10.1.0.19-10.1.0.27
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}-[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\$/", $h)) {
		preg_match_all("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}-/", $h, $matches);
		preg_match_all("/-[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/", $h, $matches2);
		$start = substr($matches[0][0], 0, -1);
		$end = substr($matches2[0][0], 1);
		$starta = explode('.', $start);
		$enda = explode('.', $end);
		$start = ($starta[0] * 16777216) + ($starta[1] * 65536) + ($starta[2] * 256) + $starta[3];
		$end = ($enda[0] * 16777216) + ($enda[1] * 65536) + ($enda[2] * 256) + $enda[3];
		return $end - $start + 1;
	}

	automation_debug("  Could not calculate total IPs!\n");

	return false;
}

function automation_get_next_host ($start, $total, $count, $networks, $position) {
	if (!isset($networks[$position])) return false;
	$h = trim($networks[$position]);

	if ($count == $total || $total < 1)
		return false;

	if (preg_match("/^([0-9]{1,3}\.[0-9]{1,3}\.)\*(\.[0-9]{1,3})$/", $h, $matches)) {
		// 10.1.*.1
		return $matches[1] . ++$count . $matches[2];
	} else {
		// other cases
		$ip = explode('.', $start);
		$y = 16777216;
		for ($x = 0; $x < 4; $x++) {
			$ip[$x] += intval($count/$y);
			$count -= ((intval($count/$y))*256);
			$y = $y / 256;
			if ($ip[$x] == 256 && $x > 0) {
				$ip[$x] = 0;
				$ip[$x-1] += 1;
			}
		}
		return implode('.',$ip);
	}
}

function automation_primeIPAddressTable($network_id) {
	$subNets    = db_fetch_cell_prepared('SELECT subnet_range FROM automation_networks WHERE id = ?', array($network_id));
	$subNets    = explode(',', trim($subNets));
	$total      = 0;

	if (sizeof($subNets)) {
		foreach($subNets as $position => $subNet) {
			$count = 1;
			$sql   = array();
			$subNetTotal = automation_calculate_total_ips($subNets, $position);
			$total += $subNetTotal;

			$start = automation_calculate_start($subNets, $position);

			if ($start != '') {
				$sql[] = "('$start', '', $network_id, '0', '0', '0')";
			}

			while ($count < $subNetTotal) {
				$ip = automation_get_next_host($start, $subNetTotal, $count, $subNets, $position);

				$count++;

				if ($ip != '') {
					$sql[] = "('$ip', '', $network_id, '0', '0', '0')";
				}

				if ($count % 1000 == 0) {
					db_execute("INSERT INTO automation_ips (ip_address, hostname, network_id, pid, status, thread) VALUES " . implode(',', $sql));
					$sql = array();
				}
			}

			if (sizeof($sql)) {
				db_execute("INSERT INTO automation_ips (ip_address, hostname, network_id, pid, status, thread) VALUES " . implode(',', $sql));
			}
		}
	}

	automation_debug("A Total of $total IP Addresses Primed\n");
}

function automation_valid_snmp_device(&$device) {
	global $snmp_logging;

	/* initialize variable */
	$host_up = false;
	$snmp_logging = false;
	$device['snmp_status'] = HOST_DOWN;
	$device['ping_status'] = 0;

	/* force php to return numeric oid's */
	cacti_oid_numeric_format();

	$snmp_items = db_fetch_assoc_prepared('SELECT *
		FROM automation_snmp_items
		WHERE snmp_id = ?
		ORDER BY sequence ASC',
		array($device['snmp_id']));

	if (sizeof($snmp_items)) {
		automation_debug(', SNMP:');
		foreach($snmp_items as $item) {
			// general options
			$device['snmp_id']              = $item['snmp_id'];
			$device['snmp_version']         = $item['snmp_version'];
			$device['snmp_port']            = $item['snmp_port'];
			$device['snmp_timeout']         = $item['snmp_timeout'];
			$device['snmp_retries']         = $item['snmp_retries'];

			// snmp v1/v2 options
			$device['snmp_community']       = $item['snmp_community'];

			// snmp v3 options
			$device['snmp_username']        = $item['snmp_username'];
			$device['snmp_password']        = $item['snmp_password'];
			$device['snmp_auth_protocol']   = $item['snmp_auth_protocol'];
			$device['snmp_priv_passphrase'] = $item['snmp_priv_passphrase'];
			$device['snmp_priv_protocol']   = $item['snmp_priv_protocol'];
			$device['snmp_context']         = $item['snmp_context'];
			$device['snmp_engine_id']       = $item['snmp_engine_id'];
			$device['max_oids']             = $item['max_oids'];

            $session = cacti_snmp_session($device['ip_address'], $device['snmp_community'], $device['snmp_version'],
                $device['snmp_username'], $device['snmp_password'], $device['snmp_auth_protocol'], $device['snmp_priv_passphrase'],
                $device['snmp_priv_protocol'], $device['snmp_context'], $device['snmp_engine_id'], $device['snmp_port'],
                $device['snmp_timeout'], $device['snmp_retries'], $device['max_oids']);

            if ($session !== false) {
				/* Community string is not used for v3 */
				$snmp_sysObjectID = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.2.0');

				if ($snmp_sysObjectID != 'U') {
					$snmp_sysObjectID = str_replace('enterprises', '.1.3.6.1.4.1', $snmp_sysObjectID);
					$snmp_sysObjectID = str_replace('OID: ', '', $snmp_sysObjectID);
					$snmp_sysObjectID = str_replace('.iso', '.1', $snmp_sysObjectID);

					if ((strlen($snmp_sysObjectID)) &&
						(!substr_count($snmp_sysObjectID, 'No Such Object')) &&
						(!substr_count($snmp_sysObjectID, 'Error In'))) {
						$snmp_sysObjectID = trim(str_replace('"', '', $snmp_sysObjectID));
						$device['snmp_status'] = HOST_UP;
						$host_up = true;
						break;
					}
				}
			}

			if ($host_up == true) {
				break;
			}

		}
	}

	if ($host_up) {
		$device['snmp_sysObjectID'] = $snmp_sysObjectID;

		/* get system name */
		$snmp_sysName = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.5.0');

		if ($snmp_sysName != '') {
			$snmp_sysName = trim(strtr($snmp_sysName,'"',' '));
			$device['snmp_sysName'] = $snmp_sysName;
		}

		/* get system location */
		$snmp_sysLocation = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.6.0');

		if ($snmp_sysLocation != '') {
			$snmp_sysLocation = trim(strtr($snmp_sysLocation,'"',' '));
			$device['snmp_sysLocation'] = $snmp_sysLocation;
		}

		/* get system contact */
		$snmp_sysContact = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.4.0');

		if ($snmp_sysContact != '') {
			$snmp_sysContact = trim(strtr($snmp_sysContact,'"',' '));
			$device['snmp_sysContact'] = $snmp_sysContact;
		}

		/* get system description */
		$snmp_sysDescr = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.1.0');

		if ($snmp_sysDescr != '') {
			$snmp_sysDescr = trim(strtr($snmp_sysDescr,'"',' '));
			$device['snmp_sysDescr'] = $snmp_sysDescr;
		}

		/* get system uptime */
		$snmp_sysUptime = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.3.0');

		if ($snmp_sysUptime != '') {
			$snmp_sysUptime = trim(strtr($snmp_sysUptime,'"',' '));
			$device['snmp_sysUptime'] = $snmp_sysUptime;
		}

		$session->close();
	} else {
		automation_debug(' No response');
	}

	return $host_up;
}

/*	gethostbyaddr_wtimeout - This function provides a good method of performing
  a rapid lookup of a DNS entry for a host so long as you don't have to look far.
*/
function automation_get_dns_from_ip($ip, $dns, $timeout = 1000) {
	/* random transaction number (for routers etc to get the reply back) */
	$data = rand(10, 99);

	/* trim it to 2 bytes */
	$data = substr($data, 0, 2);

	/* create request header */
	$data .= "\1\0\0\1\0\0\0\0\0\0";

	/* split IP into octets */
	$octets = explode('.', $ip);

	/* perform a quick error check */
	if (count($octets) != 4) return 'ERROR';

	/* needs a byte to indicate the length of each segment of the request */
	for ($x=3; $x>=0; $x--) {
		switch (strlen($octets[$x])) {
		case 1: // 1 byte long segment
			$data .= "\1"; break;
		case 2: // 2 byte long segment
			$data .= "\2"; break;
		case 3: // 3 byte long segment
			$data .= "\3"; break;
		default: // segment is too big, invalid IP
			return 'ERROR';
		}

		/* and the segment itself */
		$data .= $octets[$x];
	}

	/* and the final bit of the request */
	$data .= "\7in-addr\4arpa\0\0\x0C\0\1";

	/* create UDP socket */
	$handle = @fsockopen("udp://$dns", 53);

	@stream_set_timeout($handle, floor($timeout/1000), ($timeout*1000)%1000000);
	@stream_set_blocking($handle, 1);

	/* send our request (and store request size so we can cheat later) */
	$requestsize = @fwrite($handle, $data);

	/* get the response */
	$response = @fread($handle, 1000);

	/* check to see if it timed out */
	$info = @stream_get_meta_data($handle);

	/* close the socket */
	@fclose($handle);

	if ($info['timed_out']) {
		return 'timed_out';
	}

	/* more error handling */
	if ($response == '' || $requestsize == false || strlen($response) <= $requestsize) { return $ip; }

	/* parse the response and find the response type */
	$type = @unpack('s', substr($response, $requestsize+2));

	if (isset($type[1]) && $type[1] == 0x0C00) {
		/* set up our variables */
		$host = '';
		$len = 0;

		/* set our pointer at the beginning of the hostname uses the request
		   size from earlier rather than work it out.
		*/
		$position = $requestsize + 12;

		/* reconstruct the hostname */
		do {
			/* get segment size */
			$len = unpack('c', substr($response, $position));

			/* null terminated string, so length 0 = finished */
			if ($len[1] == 0) {
				/* return the hostname, without the trailing '.' */
				return strtoupper(substr($host, 0, strlen($host) -1));
			}

			/* add the next segment to our host */
			$host .= substr($response, $position+1, $len[1]) . '.';

			/* move pointer on to the next segment */
			$position += $len[1] + 1;
		} while ($len != 0);

		/* error - return the hostname we constructed (without the . on the end) */
		return strtoupper($ip);
	}

	/* error - return the hostname */
	return strtoupper($ip);
}

function api_automation_is_time_to_start($network_id) {
	$net = db_fetch_row_prepared('SELECT * FROM automation_networks WHERE id = ?', array($network_id));

	switch($net['sched_type']) {
	case '1':
		return false;

		break;
	case '2':
		$recur = $net['recur_every'] * 86400; // days
		$start = strtotime($net['start_at']);
		$next  = strtotime($net['next_start']);
		$now   = time();

		if ($net['next_start'] == '0000-00-00 00:00:00') {
			if ($now > $start) {
				while($now > $start) {
					$start += $recur;
				}

				db_execute_prepared('UPDATE automation_networks SET next_start = ? WHERE id = ?', array(date('Y-m-d H:i', $start), $network_id));

				return true;

				break;
			}
		} else {
			if ($now > $next) {
				while($now > $next) {
					$next += $recur;
				}

				db_execute_prepared('UPDATE automation_networks SET next_start = ? WHERE id = ?', array(date('Y-m-d H:i', $next), $network_id));

				return true;
			}
		}

		return false;

		break;
	case '3':
		$recur = $net['recur_every'] * 86400 * 7; // weeks
		$start = strtotime($net['start_at']);
		$next  = strtotime($net['next_start']);
		$now   = time();
		$days  = explode(',', $net['day_of_week']);
		$day   = 86400;
		$week  = 86400 * 7;

		if ($net['next_start'] == '0000-00-00 00:00:00') {
			if ($now > $start) {
				while(true) {
					$start += $day;
					$cur_day = date('w', $start) + 1;

					$key = array_search($cur_day, $days, false);
					if ($key !== false && $key >= 0) {
						break;
					}
				}

				db_execute_prepared('UPDATE automation_networks SET next_start = ? WHERE id = ?', array(date('Y-m-d H:i', $start), $network_id));

				return true;
			}
		} else {
			if ($now > $next) {
				while(true) {
					$next += $day;
					$cur_day = date('w', $next) + 1;

					$key = array_search($cur_day, $days, false);
					if ($key !== false && $key >= 0) {
						if ($key == 0) {
							$next += $recur - $week;
						}
						break;
					}
				}

				db_execute_prepared('UPDATE automation_networks SET next_start = ? WHERE id = ?', array(date('Y-m-d H:i', $next), $network_id));

				return true;
			}
		}

		return false;

		break;
	case '4':
		$start  = strtotime($net['start_at']);
		$next   = strtotime($net['next_start']);
		$now    = time();
		$months = explode(',', $net['month']);
		$days   = explode(',', $net['day_of_month']);
		$day    = 86400;

		// See if the last day of the month is selected
		$last   = array_search('32', $days);
		if ($last !== false && $last > 0) {
			$last = true;
		} else {
			$last = false;
		}

		if ($net['next_start'] == '0000-00-00 00:00:00') {
			if ($now > $start) {
				while(true) {
					$start += $day;
					$month_of_year = date('n', $start);
					$day_of_month = date('j', $start);
					$chdays = $days;
					if ($last) {
						$chdays[] = date('j', strtotime('last day', $start));
					}

					$key = array_search($month_of_year, $months);
					if ($key !== false && $key >= 0) {
						$key = array_search($day_of_month, $chdays);
						if ($key !== false && $key >= 0) {
							break;
						}
					}
				}

				db_execute_prepared('UPDATE automation_networks SET next_start = ? WHERE id = ?', array(date('Y-m-d H:i', $start), $network_id));

				return true;
			}
		} else {
			if ($now > $next) {
				while(true) {
					$next += $day;
					$month_of_year = date('n', $next);
					$day_of_month = date('j', $next);
					$chdays = $days;
					if ($last) {
						$chdays[] = date('j', strtotime('last day', $next));
					}

					$key = array_search($month_of_year, $months);
					if ($key !== false && $key >= 0) {
						$key = array_search($day_of_month, $chdays);
						if ($key !== false && $key >= 0) {
							break;
						}
					}
				}

				db_execute_prepared('UPDATE automation_networks SET next_start = ? WHERE id = ?', array(date('Y-m-d H:i', $next), $network_id));

				return true;
			}
		}

		return false;

		break;
	case '5':
		$start  = strtotime($net['start_at']);
		$next   = strtotime($net['next_start']);
		$now    = time();
		$months = explode(',', $net['month']);
		$weeks  = explode(',', $net['monthly_week']);
		$days   = explode(',', $net['monthly_day']);
		$day    = 86400;

		if ($net['next_start'] == '0000-00-00 00:00:00') {
			if ($now > $start) {
				while(true) {
					$start += $day;
					$month_of_year = date('n', $start);
					$day_of_month  = date('j', $start);
					$times         = array();

					$key = array_search($month_of_year, $months);
					if ($key !== false && $key >= 0) {
						foreach($weeks as $week) {
							switch($week) {
							case '1':
								$sweek = '1st';
								break;
							case '2':
								$sweek = '2nd';
								break;
							case '3':
								$sweek = '3rd';
								break;
							case '4':
								$sweek = '4th';
								break;
							}

							foreach($days as $day) {
								switch($day) {
								case '1':
									$sday = 'Sunday';
									break;
								case '2':
									$sday = 'Monday';
									break;
								case '3':
									$sday = 'Tuesday';
									break;
								case '4':
									$sday = 'Wednesday';
									break;
								case '5':
									$sday = 'Thursday';
									break;
								case '6':
									$sday = 'Friday';
									break;
								case '7':
									$sday = 'Saturday';
									break;
								}

								$time = strtotime("$sweek $sday", $start);

								$cur_month = date('n', $time);
								if ($cur_month != $month_of_year) {
									break 2;
								}

								if ($time !== false && $time > 0) {
									$times[$time] = $time;
								}
							}
						}

						asort($times);

						foreach($times as $time) {
							if ($time > $now) {
								break;
							}
						}
					}
				}

				db_execute_prepared('UPDATE automation_networks SET next_start = ? WHERE id = ?', array(date('Y-m-d H:i', $time), $network_id));

				return true;
			}
		} else {
			if ($now > $next) {
				while(true) {
					$next += $day;
					$month_of_year = date('n', $next);
					$day_of_month  = date('j', $next);
					$times         = array();

					$key = array_search($month_of_year, $months);
					if ($key !== false && $key >= 0) {
						foreach($weeks as $week) {
							switch($week) {
							case '1':
								$sweek = '1st';
								break;
							case '2':
								$sweek = '2nd';
								break;
							case '3':
								$sweek = '3rd';
								break;
							case '4':
								$sweek = '4th';
								break;
							}

							foreach($days as $day) {
								switch($day) {
								case '1':
									$sday = 'Sunday';
									break;
								case '2':
									$sday = 'Monday';
									break;
								case '3':
									$sday = 'Tuesday';
									break;
								case '4':
									$sday = 'Wednesday';
									break;
								case '5':
									$sday = 'Thursday';
									break;
								case '6':
									$sday = 'Friday';
									break;
								case '7':
									$sday = 'Saturday';
									break;
								}

								$time = strtotime("$sweek $sday", $next);

								$cur_month = date('n', $time);
								if ($cur_month != $month_of_year) {
									break 2;
								}

								if ($time !== false && $time > 0) {
									$times[$time] = $time;
								}
							}
						}

						asort($times);

						foreach($times as $time) {
							if ($time > $now) {
								break;
							}
						}
					}
				}

				db_execute_prepared('UPDATE automation_networks SET next_start = ? WHERE id = ?', array(date('Y-m-d H:i', $time), $network_id));

				return true;
			}
		}

		return false;

		break;
	}
}

function ping_netbios_name($ip, $timeout_ms = 1000) {
	$handle = @fsockopen("udp://$ip", 137);

	if (is_resource($handle)) {
		stream_set_timeout($handle, floor($timeout_ms/1000), ($timeout_ms*1000)%1000000);
		stream_set_blocking($handle, 1);

		$packet = "\x99\x99\x00\x00\x00\x01\x00\x00\x00\x00\x00\x00\x20\x43\x4b" . str_repeat("\x41", 30) . "\x00\x00\x21\x00\x01";

		/* send our request (and store request size so we can cheat later) */
		$requestsize = @fwrite($handle, $packet);

		/* get the response */
		$response = @fread($handle, 2048);

		/* check to see if it timed out */
		$info = @stream_get_meta_data($handle);

		/* close the socket */
		fclose($handle);

		if ($info['timed_out']) {
			return false;
		}

		if (!isset($response[56])) {
			return false;
		}

		/* parse the response and find the response type */
		$names = hexdec(ord($response[56]));

		if ($names > 0) {
			$host = '';

			for($i=57;$i<strlen($response);$i += 1) {
				if (hexdec(ord($response[$i])) == 0) break;
				$host .= $response[$i];
			}

			return trim(strtolower($host));
		} else {
			return false;
		}
	}
}

function automation_update_device($host_id) {
	cacti_log(__FUNCTION__ . ' Host[' . $host_id . ']', true, 'AUTOM8 TRACE', POLLER_VERBOSITY_MEDIUM);

	/* select all graph templates associated with this host, but exclude those where
	*  a graph already exists (table graph_local has a known entry for this host/template) */
	$sql = 'SELECT gt.*
		FROM graph_templates AS gt
		INNER JOIN host_graph AS hg
		ON gt.id=hg.graph_template_id
		WHERE hg.host_id=' . $host_id . '
		AND gt.id NOT IN (
			SELECT gl.graph_template_id
			FROM graph_local AS gl
			WHERE host_id=' . $host_id . '
		)';

	$graph_templates = db_fetch_assoc($sql);

	cacti_log(__FUNCTION__ . ' Host[' . $host_id . '], sql: ' . $sql, true, 'AUTOM8 TRACE', POLLER_VERBOSITY_MEDIUM);

	/* create all graph template graphs */
	if (sizeof($graph_templates)) {
		foreach ($graph_templates as $graph_template) {
			cacti_log(__FUNCTION__ . ' Host[' . $host_id . '], graph: ' . $graph_template['id'], true, 'AUTOM8 TRACE', POLLER_VERBOSITY_MEDIUM);

			automation_execute_graph_template($host_id, $graph_template['id']);
		}
	}

	/* all associated data queries */
	$data_queries = db_fetch_assoc_prepared('SELECT sq.*,
		hsq.reindex_method
		FROM snmp_query AS sq
		INNER JOIN host_snmp_query AS hsq
		ON sq.id=hsq.snmp_query_id
		WHERE hsq.host_id = ?', array($host_id));

	/* create all data query graphs */
	if (sizeof($data_queries)) {
		foreach ($data_queries as $data_query) {
			cacti_log(__FUNCTION__ . ' Host[' . $host_id . '], dq[' . $data_query['id'] . ']', true, 'AUTOM8 TRACE', POLLER_VERBOSITY_MEDIUM);

			automation_execute_data_query($host_id, $data_query['id']);
		}
	}

	/* now handle tree rules for that host */
	cacti_log(__FUNCTION__ . ' Host[' . $host_id . '], create_tree for host: ' . $host_id, true, 'AUTOM8 TRACE', POLLER_VERBOSITY_MEDIUM);

	automation_execute_device_create_tree($host_id);
}

