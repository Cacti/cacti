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

function display_matching_hosts($rule, $rule_type, $url) {
	global $device_actions, $item_rows;

	$details = automation_get_matching_device_sql($rule, $rule_type);

	html_start_box(__('Matching Devices'), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
			<form id='form_automation_host' action='<?php print html_escape($url);?>'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search');?>
						</td>
						<td>
							<input type='text' class='ui-state-default ui-corner-all' id='filterd' size='25' value='<?php print html_escape_request_var('filterd');?>'>
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

								if (cacti_sizeof($host_templates)) {
									foreach ($host_templates as $host_template) {
										print "<option value='" . $host_template['id'] . "'";

										if (get_request_var('host_template_id') == $host_template['id']) {
											print ' selected';
										} print '>' . html_escape($host_template['name']) . '</option>';
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
								if (cacti_sizeof($item_rows)) {
									foreach ($item_rows as $key => $value) {
										print "<option value='". $key . "'";

										if (get_request_var('rowsd') == $key) {
											print ' selected';
										} print '>' . $value . '</option>';
									}
								}
								?>
							</select>
						</td>
						<td>
							<span>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>'>
							</span>
						</td>
					</tr>
				</table>
			</form>
			<script type='text/javascript'>
			function applyDeviceFilter() {
				strURL  = '<?php print $url;?>' + '&host_status=' + $('#host_status').val();
				strURL += '&host_template_id=' + $('#host_template_id').val();
				strURL += '&rowsd=' + $('#rowsd').val();
				strURL += '&filterd=' + $('#filterd').val();
				loadUrl({url:strURL})
			}

			function clearDeviceFilter() {
				strURL = '<?php print $url;?>&cleard=true';
				loadUrl({url:strURL})
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
		</td>
	</tr>
	<?php

	html_end_box();

	$host_graphs = array_rekey(
		db_fetch_assoc('SELECT host_id, COUNT(*) AS graphs
			FROM graph_local
			GROUP BY host_id'),
		'host_id', 'graphs'
	);

	$host_data_sources = array_rekey(
		db_fetch_assoc('SELECT host_id, COUNT(*) AS data_sources
			FROM data_local
			GROUP BY host_id'),
		'host_id', 'data_sources'
	);

	$total_rows = cacti_sizeof(db_fetch_assoc($details['rows_query'], false));
	$sortby     = $details['sortby'];
	$sql_query  = $details['rows_query'] .
		' ORDER BY ' . $sortby . ' ' . get_request_var('sort_direction') .
		' LIMIT ' . ($details['rows'] * (get_request_var('paged') - 1)) . ',' . $details['rows'];

	$hosts = db_fetch_assoc($sql_query, false);

	$nav = html_nav_bar($url, MAX_DISPLAY_PAGES, get_request_var('paged'), $details['rows'], $total_rows, 7, __('Devices'), 'paged', 'main');

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

	if (cacti_sizeof($hosts)) {
		foreach ($hosts as $host) {
			form_alternate_row('line' . $host['host_id'], true);

			form_selectable_cell(filter_value($host['description'], get_request_var('filterd'), 'host.php?action=edit&id=' . $host['host_id']), $host['host_id']);
			form_selectable_cell(filter_value($host['hostname'], get_request_var('filterd')), $host['host_id']);
			form_selectable_cell(get_colored_device_status((($host['disabled'] == 'on' || $host['site_disabled'] == 'on') ? true : false), $host['status']), $host['host_id']);
			form_selectable_cell(filter_value($host['host_template_name'], get_request_var('filterd')), $host['host_id']);
			form_selectable_cell(round(($host['host_id']), 2), $host['host_id']);
			form_selectable_cell((isset($host_graphs[$host['host_id']]) ? $host_graphs[$host['host_id']] : 0), $host['host_id']);
			form_selectable_cell((isset($host_data_sources[$host['host_id']]) ? $host_data_sources[$host['host_id']] : 0), $host['host_id']);

			form_end_row();
		}
	} else {
		print "<tr><td colspan='8'><em>" . __('No Matching Devices') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($hosts)) {
		print $nav;
	}
}

function automation_get_matching_device_sql(&$rule, $rule_type) {
	if (isset_request_var('cleard')) {
		set_request_var('clear', 'true');
	}

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rowsd' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'paged' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'host_status' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'host_template_id' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'filterd' => array(
			'filter'  => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'sort_column' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'description',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'has_graphs' => array(
			'filter'  => FILTER_VALIDATE_REGEXP,
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

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filterd') != '') {
		$sql_where = 'WHERE h.deleted = ""
			AND (h.hostname LIKE '  . db_qstr('%' . get_request_var('filterd') . '%') . '
			OR h.description LIKE ' . db_qstr('%' . get_request_var('filterd') . '%') . '
			OR ht.name LIKE '	    . db_qstr('%' . get_request_var('filterd') . '%') . ')';
	} else {
		$sql_where = "WHERE h.deleted = ''";
	}

	if (db_column_exists('sites', 'disabled')) {
		$host_where_disabled = "(IFNULL(TRIM(s.disabled),'') == 'on' OR IFNULL(TRIM(h.disabled),'') == 'on')";
	} else {
		$host_where_disabled = "(IFNULL(TRIM(h.disabled),'') == 'on')";
	}

	$host_where_status = get_request_var('host_status');

	if ($host_where_status == '-1') {
		/* Show all items */
	} elseif ($host_where_status == '-2') {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE ') . "($host_where_disabled)";
	} elseif ($host_where_status == '-3') {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE ') . "NOT ($host_where_disabled)";
	} elseif ($host_where_status == '-4') {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE ') . "(h.status!='3' OR $host_where_disabled)";
	} else {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE ') . "(h.status=$host_where_status AND NOT ($host_where_disabled))";
	}

	if (get_request_var('host_template_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('host_template_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE ') . 'h.host_template_id=0';
	} elseif (!isempty_request_var('host_template_id')) {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE ') . 'h.host_template_id=' . get_request_var('host_template_id');
	}

	/* build magic query, for matching hosts JOIN tables host and host_template */
	if (db_column_exists('sites', 'disabled')) {
		$sdisabled = 's.disabled AS site_disabled,';
	} else {
		$sdisabled = "'' AS site_disabled,";
	}

	$sql_query = "SELECT h.id AS host_id, h.hostname, h.description,
		h.disabled AS disabled, $sdisabled
		h.status, ht.name AS host_template_name
		FROM host AS h
		LEFT JOIN graph_local AS gl
		ON h.id = gl.host_id
		LEFT JOIN graph_templates AS gt
		ON gl.graph_template_id = gt.id
		LEFT JOIN graph_templates_graph AS gtg
		ON gl.id = gtg.local_graph_id
		LEFT JOIN sites AS s
		ON s.id = h.site_id
		LEFT JOIN host_template AS ht
		ON h.host_template_id = ht.id ";

	/* get the WHERE clause for matching hosts */
	$sql_filter = ($sql_where != '' ? ' AND (' : ' WHERE (') . build_matching_objects_filter($rule['id'], $rule_type) . ')';

	/* now we build up a new query for counting the rows */
	$rows_query = $sql_query . $sql_where . $sql_filter;
	$total_rows = cacti_sizeof(db_fetch_assoc($rows_query, false));

	$sortby = get_request_var('sort_column');

	if ($sortby == 'hostname') {
		$sortby = 'INET_ATON(hostname)';
	}

	return array(
		'rows_query'  => $rows_query,
		'sortby'      => $sortby,
		'rows'        => $rows
	);
}

function automation_get_matching_graphs_sql($rule, $rule_type) {
	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'page' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
		),
		'host_id' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'template_id' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'filter' => array(
			'filter'  => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
		),
		'sort_column' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'title_cache',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_direction' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
		),
		'has_graphs' => array(
			'filter'  => FILTER_VALIDATE_REGEXP,
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

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = 'WHERE (
			gtg.title_cache LIKE '  . db_qstr('%' . get_request_var('filter') . '%') . '
			OR gt.name LIKE '	    . db_qstr('%' . get_request_var('filter') . '%') . '
			OR h.description LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . '
			OR h.hostname LIKE '	. db_qstr('%' . get_request_var('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

	if (get_request_var('host_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('host_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND ':' WHERE ') . ' gl.host_id=0';
	} elseif (!isempty_request_var('host_id')) {
		$sql_where .= ($sql_where != '' ? ' AND ':' WHERE ') . ' gl.host_id=' . get_request_var('host_id');
	}

	if (get_request_var('template_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('template_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND ':' WHERE ') . ' gtg.graph_template_id=0';
	} elseif (!isempty_request_var('template_id')) {
		$sql_where .= ($sql_where != '' ? ' AND ':' WHERE ') .' gtg.graph_template_id=' . get_request_var('template_id');
	}

	/* get the WHERE clause for matching graphs */
	$sql_where .= ($sql_where != '' ? ' AND ':' WHERE ') . build_matching_objects_filter($rule['id'], $rule_type);

	$total_rows_query = "SELECT COUNT(gtg.id)
		FROM host AS h
		INNER JOIN graph_local AS gl
		ON h.id = gl.host_id
		LEFT JOIN sites AS s
		ON h.site_id = s.id
		LEFT JOIN graph_templates AS gt
		ON gl.graph_template_id = gt.id
		LEFT JOIN graph_templates_graph AS gtg
		ON gl.id = gtg.local_graph_id
		LEFT JOIN host_template AS ht
		ON h.host_template_id = ht.id
		$sql_where";

	if (db_column_exists('sites', 'disabled')) {
		$sdisabled = 's.disabled AS site_disabled,';
	} else {
		$sdisabled = "'' AS site_disabled,";
	}

	$rows_query = "SELECT h.id AS host_id, h.hostname, h.description,
		h.disabled AS disabled, $sdisabled
		h.status, ht.name AS host_template_name,
		gtg.id, gtg.local_graph_id, gtg.height, gtg.width,
		gtg.title_cache, gt.name
		FROM host AS h
		INNER JOIN graph_local AS gl
		ON h.id = gl.host_id
		LEFT JOIN sites AS s
		ON h.site_id = s.id
		LEFT JOIN graph_templates AS gt
		ON gl.graph_template_id = gt.id
		LEFT JOIN graph_templates_graph AS gtg
		ON gl.id = gtg.local_graph_id
		LEFT JOIN host_template AS ht
		ON h.host_template_id = ht.id
		$sql_where
		ORDER BY " . get_request_var('sort_column') . ' ' . get_request_var('sort_direction') . '
		LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	return array(
		'rows_query' => $rows_query,
		'total_rows' => $total_rows_query,
		'rows'       => $rows
	);
}

function display_matching_graphs($rule, $rule_type, $url) {
	global $graph_actions, $item_rows;

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

								if (cacti_sizeof($hosts)) {
									foreach ($hosts as $host) {
										print "<option value='" . $host['id'] . "'";

										if (get_request_var('host_id') == $host['id']) {
											print ' selected';
										} print '>' . html_escape($host['description']) . '</option>';
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
								// suppress total rows collection
								$total_rows = -1;

								$templates = get_allowed_graph_templates('', 'name', '', $total_rows);

								if (cacti_sizeof($templates) > 0) {
									foreach ($templates as $template) {
										print "<option value=' " . $template['id'] . "'";

										if (get_request_var('template_id') == $template['id']) {
											print ' selected';
										} print '>' . html_escape($template['name']) . '</option>';
									}
								}
								?>
							</select>
						</td>
						<td>
							<span>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>'>
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
							<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
						</td>
						<td>
							<?php print __('Devices');?>
						</td>
						<td>
							<select id='rows'>
								<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default');?></option>
								<?php
								if (cacti_sizeof($item_rows)) {
									foreach ($item_rows as $key => $value) {
										print "<option value='" . $key . "'";

										if (get_request_var('rows') == $key) {
											print ' selected';
										} print '>' . $value . '</option>';
									}
								}
								?>
							</select>
						</td>
					</tr>
				</table>
			</form>
			<script type='text/javascript'>
			function applyFilter() {
				strURL  = '<?php print $url;?>' + '&host_id=' + $('#host_id').val();
				strURL += '&rows=' + $('#rows').val();
				strURL += '&filter=' + $('#filter').val();
				strURL += '&template_id=' + $('#template_id').val();
				loadUrl({url:strURL})
			}

			function clearFilter() {
				strURL = '<?php print $url;?>&clear=true';
				loadUrl({url:strURL})
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
		</td>
	</tr>
	<?php

	html_end_box(false);

	$details = automation_get_matching_graphs_sql($rule, $rule_type);
	$rows    = $details['rows'];

	if (isset($details['total_rows'])) {
		$total_rows = db_fetch_cell($details['total_rows'], false);
		$graph_list = db_fetch_assoc($details['rows_query'], false);
	} else {
		$total_rows = 0;
		$graph_list = array();
	}

	$nav = html_nav_bar($url, MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 8, __('Devices'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'title_cache'        => array(__('Graph Title'), 'ASC'),
		'local_graph_id'     => array(__('Graph ID'), 'ASC'),
		'name'               => array(__('Graph Template Name'), 'ASC'),
		'description'        => array(__('Device Description'), 'ASC'),
		'hostname'           => array(__('Hostname'), 'ASC'),
		'host_template_name' => array(__('Device Template Name'), 'ASC'),
		'status'             => array(__('Status'), 'ASC'),
	);

	html_header_sort(
		$display_text,
		get_request_var('sort_column'),
		get_request_var('sort_direction'),
		'1',
		$url . '?action=edit&id=' . get_request_var('id') . '&page=' . get_request_var('page')
	);

	if (cacti_sizeof($graph_list)) {
		foreach ($graph_list as $graph) {
			$template_name = ((empty($graph['name'])) ? '<em>' . __('None') . '</em>' : html_escape($graph['name']));

			form_alternate_row('line' . $graph['local_graph_id'], true);

			form_selectable_cell(filter_value(title_trim($graph['title_cache'], read_config_option('max_title_length')), get_request_var('filter'), 'graphs.php?action=graph_edit&id=' . $graph['local_graph_id']), $graph['local_graph_id']);
			form_selectable_cell($graph['local_graph_id'], $graph['local_graph_id']);
			form_selectable_cell(filter_value($template_name, get_request_var('filter')), $graph['local_graph_id']);
			form_selectable_cell(filter_value($graph['description'], get_request_var('filter'), 'host.php?action=edit&id=' . $graph['host_id']), $graph['local_graph_id']);
			form_selectable_cell(filter_value($graph['hostname'], get_request_var('filter')), $graph['local_graph_id']);
			form_selectable_cell(filter_value($graph['host_template_name'], get_request_var('filter')), $graph['local_graph_id']);
			form_selectable_cell(get_colored_device_status((($graph['disabled'] == 'on' || $graph['site_disabled'] == 'on') ? true : false), $graph['status']), $graph['local_graph_id']);

			form_end_row();
		}
	} else {
		print '<tr><td><em>' . __('No Graphs Found') . '</em></td></tr>';
	}

	html_end_box(true);

	if (cacti_sizeof($graph_list)) {
		print $nav;
	}
}

function automation_get_new_graphs_sql($rule) {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('snmp_query_id');
	/* ==================================================== */

	if (isset_request_var('oclear')) {
		set_request_var('clear', 'true');
	}

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'page' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
		),
		'filter' => array(
			'filter'  => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
		),
		'sort_column' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'description',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_direction' => array(
			'filter'  => FILTER_CALLBACK,
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

	if (!cacti_sizeof($snmp_query)) {
		$name = __('Not Found');
	} else {
		$name = $snmp_query['name'];
	}

	/**
	 * determine number of input fields, if any
	 * for a dropdown selection
	 */
	$xml_array = get_data_query_array($rule['snmp_query_id']);

	if (cacti_sizeof($xml_array)) {
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

	if (cacti_sizeof($xml_array)) {
		$dq_header  = '';
		$sql_filter = '';
		$sql_having = '';
		$dq_indexes = array();

		$rule_items = db_fetch_assoc_prepared('SELECT *
			FROM automation_graph_rule_items
			WHERE rule_id = ?
			ORDER BY sequence',
			array($rule['id']));

		$automation_rule_fields = array_rekey(
			db_fetch_assoc_prepared('SELECT DISTINCT field
				FROM automation_graph_rule_items AS agri
				WHERE field != ""
				AND rule_id = ?',
				array($rule['id'])),
			'field', 'field'
		);

		$rule_name = db_fetch_cell_prepared('SELECT name
			FROM automation_graph_rules
			WHERE id = ?',
			array($rule['id']));

		/* get the unique field values from the database */
		$field_names = array_rekey(
			db_fetch_assoc_prepared('SELECT DISTINCT field_name
				FROM host_snmp_cache AS hsc
				WHERE snmp_query_id = ?',
				array($rule['snmp_query_id'])),
			'field_name', 'field_name'
		);

		$run_query = true;

		/* check for possible SQL errors */
		foreach ($automation_rule_fields as $column) {
			if (array_search($column, $field_names, true) === false) {
				$run_query = false;
			}
		}

		if ($run_query) {
			/* main sql */
			if (isset($xml_array['index_order_type'])) {
				$sql_order = build_sort_order($xml_array['index_order_type'], 'automation_host');
				$sql_query = build_data_query_sql($rule) . ' ' . $sql_order;
			} else {
				$sql_query = build_data_query_sql($rule);
			}

			$results = db_fetch_cell("SELECT COUNT(*) FROM ($sql_query) AS `a`", '', false);
		} else {
			$results = array();
		}

		if ($results) {
			/* rule item filter first */
			$sql_filter	= build_rule_item_filter($rule_items, '`a`.');

			/* filter on on the display filter next */
			$sql_having = build_graph_object_sql_having($rule, get_request_var('filter'));

			/* now we build up a new query for counting the rows */
			$rows_query    = "SELECT * \nFROM (\n\t" . trim($sql_query) . "\n) AS `a` " . ($sql_filter != '' ? "\nWHERE (\n\t" . trim($sql_filter) . "\n)":'') . $sql_having;

			/* construct the indexes query */
			$indexes_query = $rows_query . "\nLIMIT " . ($rows * (get_request_var('page') - 1)) . ',' . $rows;
		} else {
			$rows_query    = '';
			$indexes_query = '';
		}

		return array(
			'rows_query'    => $rows_query,
			'indexes_query' => $indexes_query,
			'xml_array'     => $xml_array,
			'dq_header'     => $dq_header,
			'rows'          => $rows,
			'name'          => $name
		);
	} else {
		return false;
	}
}

function display_new_graphs($rule, $url) {
	global $config, $item_rows;

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
							<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
						</td>
						<td>
							<?php print __('Objects');?>
						</td>
						<td>
							<select id='orows'>
								<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default');?></option>
								<?php
								if (cacti_sizeof($item_rows)) {
									foreach ($item_rows as $key => $value) {
										print "<option value='". $key . "'" . (get_request_var('rows') == $key ? ' selected':'') . '>' . $value . '</option>';
									}
								}
								?>
							</select>
						</td>
						<td>
							<span>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='orefresh' value='<?php print __esc('Go');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='oclear' value='<?php print __esc('Clear');?>'>
							</span>
						</td>
					</tr>
				</table>
			</form>
			<script type='text/javascript'>
			function applyObjectFilter() {
				strURL  = '<?php print $url;?>';
				strURL += '&rows=' + $('#orows').val();
				strURL += '&filter=' + $('#filter').val();
				loadUrl({url:strURL})
			}

			function clearObjectFilter() {
				strURL = '<?php print $url;?>&oclear=true';
				loadUrl({url:strURL})
			}

			$(function() {
				$('#orefresh').click(function() {
					applyObjectFilter();
				});

				$('#oclear').click(function() {
					clearObjectFilter();
				});

				$('#orows').change(function() {
					applyObjectFilter();
				});

				$('#form_automation_objects').submit(function(event) {
					event.preventDefault();
					applyObjectFilter();
				});
			});
			</script>
		</td>
	</tr>
	<?php

	html_end_box();

	$details = automation_get_new_graphs_sql($rule);

	if (isset($details['rows_query']) && $details['rows_query'] != '') {
		$total_rows = cacti_sizeof(db_fetch_assoc($details['rows_query'], false));

		if ($total_rows < (get_request_var('rows') * (get_request_var('page') - 1)) + 1) {
			set_request_var('page', '1');
		}

		$dq_indexes = db_fetch_assoc($details['indexes_query'], false);
		$xml_array  = $details['xml_array'];

		$rows = $details['rows'];
		$name = $details['name'];

		$nav = html_nav_bar('automation_graph_rules.php?action=edit&id=' . $rule['id'], MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 30, __('Matching Objects'), 'page', 'main');

		print $nav;

		html_start_box(__('Matching Objects [ %s ]&nbsp;', html_escape($name)) . display_tooltip(__('A blue font color indicates that the rule will be applied to the objects in question.  Other objects will not be subject to the rule.')), '100%', '', '3', 'center', '');

		/**
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
				foreach ($field_names as $row) {
					if ($row['field_name'] == $field_name) {
						$display_text[] = $field_array['name'];

						break;
					}
				}
			}
		}

		html_header($display_text);

		if (!cacti_sizeof($dq_indexes)) {
			print "<tr colspan='6'><td>" . __('There are no Objects that match this rule.') . '</td></tr>';
		} else {
			print "<tr colspan='6'>" . $details['dq_header'] . '</tr>';
		}

		/* list of all entries */
		$row_counter = 0;
		$fields      = array_rekey($field_names, 'field_name', 'field_name');

		if (cacti_sizeof($dq_indexes)) {
			foreach ($dq_indexes as $row) {
				form_alternate_row("line$row_counter", true);

				if (isset($created_graphs[$row['host_id']][$row['snmp_index']])) {
					$style = ' ';
				} else {
					$style = ' style="color: blue"';
				}

				$column_counter = 0;

				foreach ($xml_array['fields'] as $field_name => $field_array) {
					if ($field_array['direction'] == 'input' || $field_array['direction'] == 'input-output') {
						if (in_array($field_name, $fields, true)) {
							if (isset($row[$field_name])) {
								if ($field_name == 'status') {
									form_selectable_cell(get_colored_device_status(($row['disabled'] == 'on' ? true : false), $row['status']), 'status');
								} else {
									print "<td><span id='text$row_counter" . '_' . $column_counter . "' $style>" . filter_value($row[$field_name], get_request_var('filter')) . '</span></td>';
								}
							} else {
								print "<td><span id='text$row_counter" . '_' . $column_counter . "' $style></span></td>";
							}

							$column_counter++;
						}
					}
				}

				print '</tr>';

				$row_counter++;
			}
		}

		html_end_box();

		if ($total_rows > $rows) {
			print $nav;
		}
	} else {
		print "<tr><td colspan='2' style='color: red;'>" . __('Error in data query') . '</td></tr>';
	}

	print '</table>';
}

function display_matching_trees($rule_id, $rule_type, $item, $url) {
	global $automation_tree_header_types;
	global $device_actions, $item_rows;

	$function = automation_function_with_pid(__FUNCTION__);
	cacti_log($function . " called: $rule_id/$rule_type", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'host_template_id' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'host_status' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'filter' => array(
			'filter'  => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'sort_column' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'description',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter'  => FILTER_CALLBACK,
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

	html_start_box(__('Matching Items'), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
			<form id='form_filter' action='<?php print $url;?>'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search');?>
						</td>
						<td>
							<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
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

								if (cacti_sizeof($host_templates)) {
									foreach ($host_templates as $host_template) {
										print "<option value='" . $host_template['id'] . "'";

										if (get_request_var('host_template_id') == $host_template['id']) {
											print ' selected';
										} print '>' . html_escape($host_template['name']) . '</option>';
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
								if (cacti_sizeof($item_rows)) {
									foreach ($item_rows as $key => $value) {
										print "<option value='" . $key . "'" . (get_request_var('rows') == $key ? ' selected':'') . '>' . $value . '</option>';
									}
								}
								?>
							</select>
						</td>
						<td>
							<span>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>'>
								<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>'>
							</span>
						</td>
					</tr>
				</table>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = '<?php print $url;?>' + '&host_status=' + $('#host_status').val();
				strURL += '&host_template_id=' + $('#host_template_id').val();
				strURL += '&rows=' + $('#rows').val();
				strURL += '&filter=' + $('#filter').val();
				loadUrl({url:strURL})
			}

			function clearFilter() {
				strURL = '<?php print $url;?>&clear=true';
				loadUrl({url:strURL})
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
		</td>
	</tr>
	<?php

	html_end_box(false);
	form_hidden_box('page', '1', '');

	/* build magic query, for matching hosts JOIN tables host and host_template */
	$leaf_type = db_fetch_cell('SELECT leaf_type FROM automation_tree_rules WHERE id=' . $rule_id);

	if ($leaf_type == TREE_ITEM_TYPE_HOST) {
		$sql_tables = 'FROM host AS h
			LEFT JOIN sites s
			ON h.site_id = s.id
			LEFT JOIN host_template AS ht
			ON (h.host_template_id=ht.id)';

		$sql_where = 'WHERE h.deleted = ""';
	} elseif ($leaf_type == TREE_ITEM_TYPE_GRAPH) {
		$sql_tables = 'FROM host AS h
			LEFT JOIN sites s
			ON h.site_id = s.id
			LEFT JOIN host_template AS ht
			ON h.host_template_id=ht.id
			LEFT JOIN graph_local AS gl
			ON h.id=gl.host_id
			LEFT JOIN graph_templates AS gt
			ON (gl.graph_template_id=gt.id)
			LEFT JOIN graph_templates_graph AS gtg
			ON (gl.id=gtg.local_graph_id)';

		$sql_where = 'WHERE gtg.local_graph_id>0 AND h.deleted = "" ';
	}

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where .= ' AND (
			h.hostname LIKE '	   . db_qstr('%' . get_request_var('filter') . '%') . '
			OR h.description LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . '
			OR ht.name LIKE '	   . db_qstr('%' . get_request_var('filter') . '%') . ')';
	}

	if (db_column_exists('sites', 'disabled')) {
		$host_where_disabled = "(IFNULL(TRIM(s.disabled),'') == 'on' || IFNULL(TRIM(h.disabled),'') == 'on')";
	} else {
		$host_where_disabled = "(IFNULL(TRIM(h.disabled),'') == 'on')";
	}

	$host_where_status = get_request_var('host_status');

	if ($host_where_status == '-1') {
		/* Show all items */
	} elseif ($host_where_status == '-2') {
		$sql_where .= " AND $host_where_disabled";
	} elseif ($host_where_status == '-3') {
		$sql_where .= " AND NOT $host_where_disabled";
	} elseif ($host_where_status == '-4') {
		$sql_where .= " AND (h.status!='3' OR $host_where_disabled";
	} else {
		$sql_where .= " AND (h.status=$host_where_status AND NOT $host_where_disabled)";
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
	if (db_column_exists('sites', 'disabled')) {
		$sdisabled = 's.disabled AS site_disabled,';
	} else {
		$sdisabled = "'' AS site_disabled,";
	}

	$rows_query = "SELECT h.id AS host_id, h.hostname, h.description,
		h.disabled AS disabled, $sdisabled
		h.status, ht.name AS host_template_name, $sql_field
		$sql_tables
		$sql_where AND ($sql_filter)";

	$total_rows = cacti_sizeof(db_fetch_assoc($rows_query, false));

	$sortby = get_request_var('sort_column');

	if ($sortby == 'h.hostname') {
		$sortby = 'INET_ATON(h.hostname)';
	}

	$sql_query = "$rows_query ORDER BY $sortby " .
		get_request_var('sort_direction') . ' LIMIT ' .
		($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$templates = db_fetch_assoc($sql_query, false);

	cacti_log($function. ' templates sql: ' . str_replace("\n",' ', $sql_query), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_DEBUG);

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

	if (cacti_sizeof($templates)) {
		foreach ($templates as $template) {
			cacti_log($function . ' template: ' . json_encode($template), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

			$replacement = automation_string_replace($item['search_pattern'], $item['replace_pattern'], $template['source']);

			$repl = '';

			for ($j=0; cacti_sizeof($replacement); $j++) {
				if ($j > 0) {
					$repl .= '<br>';
					$repl .= str_pad('', $j * 3, '-') . '&nbsp;' . array_shift($replacement);
				} else {
					$repl  = array_shift($replacement);
				}
			}

			cacti_log($function . " replacement: $repl", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

			form_alternate_row('line' . $template['host_id'], true);

			form_selectable_cell(filter_value($template['description'], get_request_var('filter'), 'host.php?action=edit&id=' . $template['host_id']), $template['host_id']);
			form_selectable_cell(filter_value($template['hostname'], get_request_var('filter')), $template['host_id']);
			form_selectable_cell(filter_value($template['host_template_name'], get_request_var('filter')), $template['host_id']);
			form_selectable_cell(get_colored_device_status(($template['disabled'] == 'on' ? true : false), $template['status']), $template['host_id']);
			form_selectable_cell($template['source'], $template['host_id']);
			form_selectable_cell($repl, $template['host_id']);

			form_end_row();
		}
	} else {
		print "<tr><td colspan='6'><em>" . __('No Items Found') . '</em></td></tr>';
	}

	html_end_box(true);

	if (cacti_sizeof($templates)) {
		print $nav;
	}
}

function display_match_rule_items($title, &$rule, $rule_type, $module) {
	global $automation_op_array, $automation_oper, $automation_tree_header_types;

	$rule_id = $rule['id'];

	$items = db_fetch_assoc_prepared('SELECT *
		FROM automation_match_rule_items AS mri
		WHERE rule_id = ?
		AND rule_type = ?
		ORDER BY sequence',
		array($rule_id, $rule_type));

	html_start_box($title . '&nbsp;<i id="show_device_sql" title="' . __esc('Show Matching Device SQL Query') . '" class="cactiTooltipHint fas fa-stethoscope" style="cursor:pointer"></i>', '100%', '', '3', 'center', $module . '?action=item_edit&id=' . $rule_id . '&rule_type=' . $rule_type);

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

	if (cacti_sizeof($items)) {
		foreach ($items as $item) {
			$operation = ($item['operation'] != 0) ? $automation_oper[$item['operation']] : '&nbsp;';

			form_alternate_row();

			$url = $module . '?action=item_edit&id=' . $rule_id. '&item_id=' . $item['id'] . '&rule_type=' . $rule_type;

			form_selectable_cell(filter_value(__('Item#%d', $i+1), '', $url), $i);
			form_selectable_cell($item['sequence'], $i);
			form_selectable_cell($operation, $i);
			form_selectable_cell(html_escape($item['field']), $i);

			if (isset($item['operator']) && $item['operator'] > 0) {
				form_selectable_cell($automation_op_array['display'][$item['operator']], $i);
			} else {
				form_selectable_cell('', $i);
			}

			form_selectable_cell(html_escape($item['pattern']), $i);

			$form_data = '';

			if ($i != cacti_sizeof($items)-1) {
				$url = $module . '?action=item_movedown&item_id=' . $item['id'] . '&id=' . $rule_id . '&rule_type=' . $rule_type;

				$form_data .= '<a class="pic fa fa-caret-down moveArrow"
					href="' . html_escape($url) . '" title="' . __esc('Move Down') . '"></a>';
			} else {
				$form_data .= '<span class="moveArrowNone"></span>';
			}

			if ($i > 0) {
				$url = $module . '?action=item_moveup&item_id=' . $item['id'] .   '&id=' . $rule_id . '&rule_type=' . $rule_type;

				$form_data .= '<a class="pic fa fa-caret-up moveArrow"
					href="' . html_escape($url) . '" title="' . __esc('Move Up') . '"></a>';
			} else {
				$form_data .= '<span class="moveArrowNone"></span>';
			}

			form_selectable_cell($form_data, $i, '32px', 'right nowrap');

			$url = $module . '?action=item_remove&item_id=' . $item['id'] .    '&id=' . $rule_id . '&rule_type=' . $rule_type;

			$form_data = '<a class="pid deleteMarker fa fa-times"
				href="' . html_escape($url) . '" title="' . __esc('Delete') . '"></a>';

			form_selectable_cell($form_data, $i, '16px', 'right nowrap');

			form_end_row();

			$i++;
		}
	} else {
		print "<tr><td colspan='8'><em>" . __('No Device Selection Criteria') . '</em></td></tr>';
	}

	$details = automation_get_matching_device_sql($rule, $rule_type);
	$data    = db_fetch_assoc($details['rows_query']);

	html_end_box(true);

	print '<div id="sql_device_query" style="display:none"><div style="white-space:pre">' . str_replace(array("\n", "\t"), array('<br>', ''), $details['rows_query']) . '</div><br><hr><br><div>' . db_error() . '</div></div>';
}

function display_graph_rule_items($title, &$rule, $rule_type, $module) {
	global $automation_op_array, $automation_oper, $automation_tree_header_types;

	$rule_id = $rule['id'];

	$items = db_fetch_assoc_prepared('SELECT *
		FROM automation_graph_rule_items
		WHERE rule_id = ?
		ORDER BY sequence',
		array($rule_id));

	html_start_box($title . '&nbsp;<i id="show_sql" title="' . __esc('Show Matching Objects SQL Query') . '" class="cactiTooltipHint fas fa-stethoscope" style="cursor:pointer"></i>', '100%', '', '3', 'center', $module . '?action=item_edit&id=' . $rule_id . '&rule_type=' . $rule_type);

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

	if (cacti_sizeof($items)) {
		foreach ($items as $item) {
			$operation = ($item['operation'] != 0) ? $automation_oper[$item['operation']] : '&nbsp;';

			form_alternate_row();

			$url = $module . '?action=item_edit&id=' . $rule_id. '&item_id=' . $item['id'] . '&rule_type=' . $rule_type;

			form_selectable_cell(filter_value(__('Item#%d', $i+1), '', $url), $i);
			form_selectable_cell($item['sequence'], $i);
			form_selectable_cell($operation, $i);
			form_selectable_cell(html_escape($item['field']), $i);

			if ($item['operator'] > 0 || $item['operator'] == '') {
				form_selectable_cell($automation_op_array['display'][$item['operator']], $i);
			} else {
				form_selectable_cell('', $i);
			}

			form_selectable_cell(html_escape($item['pattern']), $i);

			$form_data = '';

			if ($i != cacti_sizeof($items)-1) {
				$form_data .= '<a class="pic fa fa-caret-down moveArrow" href="' . html_escape($module . '?action=item_movedown&item_id=' . $item['id'] . '&id=' . $rule_id .	'&rule_type=' . $rule_type) . '" title="' . __esc('Move Down') . '"></a>';
			} else {
				$form_data .= '<span class="moveArrowNone"></span>';
			}

			if ($i > 0) {
				$form_data .= '<a class="pic fa fa-caret-up moveArrow" href="' . html_escape($module . '?action=item_moveup&item_id=' . $item['id'] .	'&id=' . $rule_id .	'&rule_type=' . $rule_type) . '" title="' . __esc('Move Up') . '"></a>';
			} else {
				$form_data .= '<span class="moveArrowNone"></span>';
			}

			form_selectable_cell($form_data, $i, '32px', 'right nowrap');

			$form_data = '<a class="pic deleteMarker fa fa-times"
				href="' . html_escape($module . '?action=item_remove&item_id=' . $item['id'] .	'&id=' . $rule_id .	'&rule_type=' . $rule_type) . '" title="' . __esc('Delete') . '"></a>';

			form_selectable_cell($form_data, $i, '16px', 'right nowrap');

			form_end_row();

			$i++;
		}
	} else {
		print "<tr><td colspan='8'><em>" . __('No Graph Creation Criteria') . '</em></td></tr>';
	}

	html_end_box(true);

	$details = automation_get_new_graphs_sql($rule);

	if (isset($details['indexes_query']) && $details['indexes_query'] != '') {
		$data = db_fetch_assoc(trim($details['indexes_query']));

		print '<div id="sql_query" style="display:none"><div style="white-space:pre">' . str_replace(array("\n"), array('<br>'), $details['indexes_query']) . '</div><br><hr><br><div>' . db_error() . '</div></div>';
	} else {
		print '<div id="sql_query" style="display:none"><div style="white-space:pre">' . __('Warning matching Graph Rule returned no matches') . '</div><br><hr><br><div>' . db_error() . '</div></div>';
	}
}

function display_tree_rule_items($title, $rule, $item_type, $rule_type, $module) {
	global $automation_tree_header_types, $tree_sort_types, $host_group_types;

	$rule_id = $rule['id'];

	$items = db_fetch_assoc_prepared('SELECT *
		FROM automation_tree_rule_items
		WHERE rule_id = ?
		ORDER BY sequence',
		array($rule_id));

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

	if (cacti_sizeof($items)) {
		foreach ($items as $item) {
			if ($item['field'] === AUTOMATION_TREE_ITEM_TYPE_STRING) {
				$field_name = $automation_tree_header_types[AUTOMATION_TREE_ITEM_TYPE_STRING];
			} else {
				$field_name = $item['field'];
			}

			form_alternate_row();

			$url = $module . '?action=item_edit&id=' . $rule_id. '&item_id=' . $item['id'] . '&rule_type=' . $rule_type;

			form_selectable_cell(filter_value(__('Item#%d', $i+1), '', $url), $i);
			form_selectable_cell($item['sequence'], $i);
			form_selectable_cell($field_name, $i);
			form_selectable_cell($tree_sort_types[$item['sort_type']], $i);
			form_selectable_cell(($item['propagate_changes'] ? __('Yes'):__('No')), $i);
			form_selectable_cell(html_escape($item['search_pattern']), $i);
			form_selectable_cell(html_escape($item['replace_pattern']), $i);

			$form_data = '';

			if ($i != cacti_sizeof($items)-1) {
				$url = $module . '?action=item_movedown&item_id=' . $item['id'] . '&id=' . $rule_id . '&rule_type=' . $rule_type;

				$form_data .= '<a class="pic fa fa-caret-down moveArrow" href="' .
					html_escape($url) . '" title="' . __esc('Move Down') . '"></a>';
			} else {
				$form_data .= '<span class="moveArrowNone"></span>';
			}

			if ($i > 0) {
				$url = $module . '?action=item_moveup&item_id=' . $item['id'] . '&id=' . $rule_id . '&rule_type=' . $rule_type;

				$form_data .= '<a class="pic fa fa-caret-up moveArrow" href="' .
					html_escape($url) . '" title="' . __esc('Move Up') . '"></a>';
			} else {
				$form_data .= '<span class="moveArrowNone" style="width:16px"></span>';
			}

			form_selectable_cell($form_data, $i, '32px', 'right nowrap');

			$url = $module . '?action=item_remove&item_id=' . $item['id'] . '&id=' . $rule_id .  '&rule_type=' . $rule_type;

			$form_data = '<a class="pic deleteMarker fa fa-times"
				href="' . html_escape($url) . '" title="' . __esc('Delete') . '"></a>';

			form_selectable_cell($form_data, $i, '16px', 'right nowrap');

			form_end_row();

			$i++;
		}
	} else {
		print '<tr><td><em>' . __('No Tree Creation Criteria') . '</em></td></tr>';
	}

	html_end_box(true);
}

function duplicate_automation_graph_rules($_id, $_title) {
	global $fields_automation_graph_rules_edit1, $fields_automation_graph_rules_edit2, $fields_automation_graph_rules_edit3;

	$rule = db_fetch_row_prepared('SELECT *
		FROM automation_graph_rules
		WHERE id = ?',
		array($_id));

	$match_items = db_fetch_assoc_prepared('SELECT *
		FROM automation_match_rule_items
		WHERE rule_id = ?
		AND rule_type = ?',
		array($_id, AUTOMATION_RULE_TYPE_GRAPH_MATCH));

	$rule_items  = db_fetch_assoc_prepared('SELECT *
		FROM automation_graph_rule_items
		WHERE rule_id = ?',
		array($_id));

	$fields_automation_graph_rules_edit = $fields_automation_graph_rules_edit1 +
		$fields_automation_graph_rules_edit2 + $fields_automation_graph_rules_edit3;

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
	$save['id']      = 0;
	$rule_id         = sql_save($save, 'automation_graph_rules');

	/* create new match items */
	if (cacti_sizeof($match_items) > 0) {
		foreach ($match_items as $match_item) {
			$save            = $match_item;
			$save['id']      = 0;
			$save['rule_id'] = $rule_id;
			$match_item_id   = sql_save($save, 'automation_match_rule_items');
		}
	}

	/* create new rule items */
	if (cacti_sizeof($rule_items) > 0) {
		foreach ($rule_items as $rule_item) {
			$save            = $rule_item;
			$save['id']      = 0;
			$save['rule_id'] = $rule_id;
			$rule_item_id    = sql_save($save, 'automation_graph_rule_items');
		}
	}
}

function duplicate_automation_tree_rules($_id, $_title) {
	global $fields_automation_tree_rules_edit1, $fields_automation_tree_rules_edit2, $fields_automation_tree_rules_edit3;

	$rule = db_fetch_row_prepared('SELECT *
		FROM automation_tree_rules
		WHERE id = ?',
		array($_id));

	$match_items = db_fetch_assoc_prepared('SELECT *
		FROM automation_match_rule_items
		WHERE rule_id = ?
		AND rule_type = ?',
		array($_id, AUTOMATION_RULE_TYPE_TREE_MATCH));

	$rule_items = db_fetch_assoc_prepared('SELECT *
		FROM automation_tree_rule_items
		WHERE rule_id = ?',
		array($_id));

	$fields_automation_tree_rules_edit = $fields_automation_tree_rules_edit1 +
		$fields_automation_tree_rules_edit2 + $fields_automation_tree_rules_edit3;

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
	$save['id']      = 0;
	$rule_id         = sql_save($save, 'automation_tree_rules');

	/* create new match items */
	if (cacti_sizeof($match_items) > 0) {
		foreach ($match_items as $rule_item) {
			$save            = $rule_item;
			$save['id']      = 0;
			$save['rule_id'] = $rule_id;
			$rule_item_id    = sql_save($save, 'automation_match_rule_items');
		}
	}

	/* create new action rule items */
	if (cacti_sizeof($rule_items) > 0) {
		foreach ($rule_items as $rule_item) {
			$save = $rule_item;
			/* make sure, that regexp is correctly masked */
			$save['search_pattern']  = form_input_validate($rule_item['search_pattern'], 'search_pattern', '', false, 3);
			$save['replace_pattern'] = form_input_validate($rule_item['replace_pattern'], 'replace_pattern', '', true, 3);
			$save['id']              = 0;
			$save['rule_id']         = $rule_id;
			$rule_item_id            = sql_save($save, 'automation_tree_rule_items');
		}
	}
}

function build_graph_object_sql_having($rule, $filter) {
	if ($filter != '') {
		$field_names = get_field_names($rule['snmp_query_id']);

		if (cacti_sizeof($field_names)) {
			$sql_having = ' HAVING (';

			$i = 0;

			foreach ($field_names as $column) {
				$sql_having .= ($i == 0 ? '':' OR ') . '`' . implode('`.`', explode('.', $column['field_name'])) . '`' . ' LIKE ' . db_qstr('%' . $filter . '%');
				$i++;
			}

			$sql_having .= ')';
		}

		return $sql_having;
	}
}

function build_data_query_sql($rule) {
	$function = automation_function_with_pid(__FUNCTION__);
	cacti_log($function . ' called: ' . json_encode($rule), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	/* build magic query, for matching hosts JOIN tables host and host_template */
	if (db_column_exists('sites', 'disabled')) {
		$sdisabled = 's.disabled AS site_disabled,';
	} else {
		$sdisabled = "'' AS site_disabled,";
	}

	$field_names = get_field_names($rule['snmp_query_id']);
	$sql_query   = "\n\tSELECT h.hostname AS automation_host, host_id, \n\th.disabled, $sdisabled \n\th.status, snmp_query_id, snmp_index ";

	if (cacti_sizeof($field_names) > 0) {
		foreach ($field_names as $column) {
			$field_name = $column['field_name'];
			$sql_query .= ",\n\tMAX(CASE WHEN field_name='$field_name' THEN field_value ELSE NULL END) AS '$field_name'";
		}
	}

	/* take matching hosts into account */
	$sql_where = build_matching_objects_filter($rule['id'], AUTOMATION_RULE_TYPE_GRAPH_MATCH);

	/* build magic query, for matching hosts JOIN tables host and host_template */
	$sql_query .= "\n\tFROM host_snmp_cache AS hsc";
	$sql_query .= "\n\tLEFT JOIN host AS h";
	$sql_query .= "\n\tON hsc.host_id = h.id";
	$sql_query .= "\n\tLEFT JOIN sites AS s";
	$sql_query .= "\n\tON s.id = h.site_id";
	$sql_query .= "\n\tLEFT JOIN host_template AS ht";
	$sql_query .= "\n\tON h.host_template_id = ht.id";
	$sql_query .= "\n\tWHERE snmp_query_id = " . $rule['snmp_query_id'];
	$sql_query .= "\n\tAND ($sql_where)";
	$sql_query .= "\n\tGROUP BY host_id, snmp_query_id, snmp_index";

	cacti_log($function . ' returns: ' . $sql_query, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	return $sql_query;
}

function build_matching_objects_filter($rule_id, $rule_type) {
	$function = automation_function_with_pid(__FUNCTION__);

	cacti_log($function . " called rule id: $rule_id", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	$sql_filter = '';

	/**
	 * create an SQL which queries all host related tables in a huge join
	 * this way, we may add any where clause that might be added via
	 *  'Matching Device' match
	 */
	$rule_items = db_fetch_assoc_prepared('SELECT *
		FROM automation_match_rule_items
		WHERE rule_id = ?
		AND rule_type = ?
		ORDER BY sequence',
		array($rule_id, $rule_type));

	if (cacti_sizeof($rule_items)) {
		$sql_filter	= build_rule_item_filter($rule_items);
	} else {
		/* force empty result set if no host matching rule item present */
		$sql_filter = ' (1 != 1)';
	}

	cacti_log($function . ' returns: ' . $sql_filter, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	return trim($sql_filter);
}

function build_rule_item_filter($automation_rule_items, $prefix = '') {
	global $automation_op_array, $automation_oper;

	$function = automation_function_with_pid(__FUNCTION__);

	cacti_log($function . ' called: ' . json_encode($automation_rule_items) . ", prefix: $prefix", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	$sql_filter = '';
	$indent     = 1;

	if (cacti_sizeof($automation_rule_items)) {
		$sql_filter = ' ';

		foreach($automation_rule_items as $automation_rule_item) {
			// AND|OR|(|)
			if ($automation_rule_item['operation'] != AUTOMATION_OPER_NULL) {
				if ($automation_rule_item['operation'] == AUTOMATION_OPER_RIGHT_BRACKET) {
					$indent--;
				}

				$sql_filter .= PHP_EOL . str_repeat("\t", $indent) . $automation_oper[$automation_rule_item['operation']];
			}

			// right bracket '(' does not come with a field
			if ($automation_rule_item['operation'] == AUTOMATION_OPER_LEFT_BRACKET) {
				$indent++;
				$sql_filter .= PHP_EOL . str_repeat("\t", $indent);
			}

			# right bracket ')' does not come with a field
			if ($automation_rule_item['operation'] == AUTOMATION_OPER_RIGHT_BRACKET) {
				continue;
			}

			// field name
			if ($automation_rule_item['field'] != '') {
				$sql_filter .= ' ' . $prefix . '`' . implode('`.`', explode('.', $automation_rule_item['field'])) . '`';
				$sql_filter .= ' ' . $automation_op_array['op'][$automation_rule_item['operator']] . ' ';

				if ($automation_op_array['binary'][$automation_rule_item['operator']]) {
					$query_pattern = $automation_op_array['pre'][$automation_rule_item['operator']] .
						$automation_rule_item['pattern'] .
						$automation_op_array['post'][$automation_rule_item['operator']];

					// Don't escape numeric values with numeric comparison operators
					if ($automation_rule_item['operator'] >= AUTOMATION_OP_LT &&
						$automation_rule_item['operator'] <= AUTOMATION_OP_GE &&
						is_numeric($query_pattern)) {
						$sql_filter .= $query_pattern;
					} else {
						$sql_filter .= db_qstr($query_pattern);
					}
				}
			}
		}
	}

	cacti_log($function . ' returns: ' . $sql_filter, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	return $sql_filter;
}

/*
 * build_sort_order
 *
 * @param string $index_order   - sort order given by e.g. xml_array[index_order_type]
 * @param string $default_order	- default order if any
 *
 * @return string - sql sort order string
 */
function build_sort_order($index_order, $default_order = '') {
	$function = automation_function_with_pid(__FUNCTION__);

	cacti_log($function . " called: $index_order/$default_order", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	$sql_order = $default_order;

	/* determine the sort order */
	if (isset($index_order)) {
		if ($index_order == 'numeric') {
			$sql_order .= ', CAST(snmp_index AS unsigned)';
		} elseif ($index_order == 'alphabetic') {
			$sql_order .= ', snmp_index';
		} elseif ($index_order == 'natural') {
			$sql_order .= ', INET_ATON(snmp_index)';
		}
	}

	/* if ANY order is requested */
	if ($sql_order != '') {
		$sql_order = "\n\tORDER BY " . $sql_order;
	}

	cacti_log($function . " returns: $sql_order", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

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
	$function = automation_function_with_pid(__FUNCTION__);

	cacti_log($function . ' called: ' . json_encode($rule) . ' type: ' . $rule_type, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	/* build magic query, for matching hosts JOIN tables host and host_template */
	if (db_column_exists('sites', 'disabled')) {
		$sdisabled = 's.disabled AS site_disabled,';
	} else {
		$sdisabled = "'' AS site_disabled,";
	}

	$sql_query = "SELECT h.id AS host_id, h.hostname, h.description,
		h.disabled AS disabled, $sdisabled
		h.status, ht.name AS host_template_name
		FROM host AS h
		LEFT JOIN sites AS s
		ON h.site_id = s.id
		LEFT JOIN host_template AS ht
		ON h.host_template_id = ht.id ";

	/* get the WHERE clause for matching hosts */
	$sql_filter = ' WHERE h.deleted = "" AND (' . build_matching_objects_filter($rule['id'], $rule_type) .')';

	if ($sql_where != '') {
		$sql_filter .= ' AND ' . $sql_where;
	}

	$results = db_fetch_assoc($sql_query . $sql_filter, false);

	cacti_log($function . ' returning: ' . str_replace("\n", '', $sql_query . $sql_filter) . ' matches: ' . cacti_sizeof($results), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	return $results;
}

/**
 * get an array of graphs matching a graph_match rule
 *
 * @param array $rule		- rule
 * @param int $rule_type	- rule type
 * @param string $sql_where - additional where clause
 *
 * @return array			- matching graphs
 */
function get_matching_graphs($rule, $rule_type, $sql_where = '') {
	$function = automation_function_with_pid(__FUNCTION__);

	cacti_log($function . ' called: ' . json_encode($rule) . ' type: ' . $rule_type, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	if (db_column_exists('sites', 'disabled')) {
		$sdisabled = 's.disabled AS site_disabled,';
	} else {
		$sdisabled = "'' AS site_disabled,";
	}

	$sql_query = "SELECT h.id AS host_id, h.hostname, h.description,
		h.disabled, $sdisabled
		h.status, ht.name AS host_template_name, gtg.id,
		gtg.local_graph_id, gtg.height, gtg.width, gtg.title_cache, gt.name
		FROM graph_local AS gl
		INNER JOIN graph_templates_graph AS gtg
		LEFT JOIN graph_templates AS gt
		ON gl.graph_template_id = gt.id
		LEFT JOIN host AS h
		ON gl.host_id = h.id
		LEFT JOIN sites AS s
		ON h.site_id = s.id
		LEFT JOIN host_template AS ht
		ON h.host_template_id = ht.id";

	/* get the WHERE clause for matching graphs */
	$sql_filter = 'WHERE gl.id=gtg.local_graph_id AND ' . build_matching_objects_filter($rule['id'], $rule_type);

	if ($sql_where != '') {
		$sql_filter .= ' AND ' . $sql_where;
	}

	$results = db_fetch_assoc($sql_query . $sql_filter, false);

	cacti_log($function . ' returning: ' . str_replace("\n", '', $sql_query . $sql_filter) . ' matches: ' . cacti_sizeof($results), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	return $results;
}

/*
 * get_created_graphs
 *
 * @param $rule - provide snmp_query_id, graph_type_id
 *
 * @return all graphs that have already been created for the given selection
 */
function get_created_graphs($rule) {
	$function = automation_function_with_pid(__FUNCTION__);

	cacti_log($function . ' called: ' . json_encode($rule), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	$sql = 'SELECT sqg.id
		FROM snmp_query_graph AS sqg
		WHERE sqg.snmp_query_id=' . $rule['snmp_query_id'] . '
		AND sqg.id=' . $rule['graph_type_id'];

	$snmp_query_graph_id = db_fetch_cell($sql);

	/* take matching hosts into account */
	$sql_where = build_matching_objects_filter($rule['id'], AUTOMATION_RULE_TYPE_GRAPH_MATCH);

	/* build magic query, for matching hosts JOIN tables host and host_template */
	$sql = "SELECT DISTINCT gl.host_id, gl.snmp_index
		FROM graph_local AS gl
		INNER JOIN graph_templates_item AS gti
		ON gl.id = gti.local_graph_id
		INNER JOIN data_template_rrd AS dtr
		ON gti.task_item_id = dtr.id
		INNER JOIN data_local AS dl
		ON dtr.local_data_id = dl.id
		INNER JOIN data_template_data AS dtd
		ON dl.id=dtd.local_data_id
		LEFT JOIN host As h
		ON dl.host_id=h.id
		LEFT JOIN host_template AS ht
		ON h.host_template_id=ht.id
		LEFT JOIN data_input_data AS did
		ON dtd.id=did.data_template_data_id
		LEFT JOIN data_input_fields AS dif
		ON did.data_input_field_id=dif.id
		WHERE dl.id = dtd.local_data_id
		AND dif.type_code = 'output_type'
		AND gl.snmp_query_graph_id = '" . $snmp_query_graph_id . "'
		AND ($sql_where)";

	$graphs = db_fetch_assoc($sql, false);

	cacti_log($function . ' sql: ' . str_replace("\n", ' ', $sql), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_DEBUG);

	# rearrange items to ease indexed access
	$items = array();

	if (cacti_sizeof($graphs)) {
		foreach ($graphs as $graph) {
			$items[$graph['host_id']][$graph['snmp_index']] = $graph['snmp_index'];
		}
	}

	cacti_log($function . ' returns: ' . cacti_sizeof($items), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	return $items;
}

function get_query_fields($table, $excluded_fields) {
	$function = automation_function_with_pid(__FUNCTION__);
	cacti_log($function . ' called', false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	$table = trim($table);

	$sql    = 'SHOW COLUMNS FROM ' . $table;
	$fields = array_rekey(db_fetch_assoc($sql), 'Field', 'Type');
	#print '<pre>'; print_r($fields); print '</pre>';
	# remove unwanted entries
	$fields = array_minus($fields, $excluded_fields);

	# now reformat entries for use with draw_edit_form
	if (cacti_sizeof($fields)) {
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
			# give the user a hint about the data type of the column
			$new_fields[$new_key] = strtoupper($table) . ': ' . $key . ' - ' . $value;
		}
	}

	cacti_log($function . ' returns: ' . cacti_sizeof($new_fields), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	return $new_fields;
}

/*
 * get_field_names
 *
 * @param $snmp_query_id - snmp query id
 *
 * @return all field names for that snmp query, taken from snmp_cache
 */
function get_field_names($snmp_query_id) {
	$function = automation_function_with_pid(__FUNCTION__);
	cacti_log($function . " called: $snmp_query_id", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	/* get the unique field values from the database */
	$sql    = 'SELECT DISTINCT field_name FROM host_snmp_cache WHERE snmp_query_id=' . $snmp_query_id;
	$fields = db_fetch_assoc($sql);

	cacti_log($function . ' returns: ' . cacti_sizeof($fields), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	return $fields;
}

function array_to_list($array, $sql_column) {
	$function = automation_function_with_pid(__FUNCTION__);

	/* if the last item is null; pop it off */
	$counter = cacti_count($array);

	if (empty($array[$counter - 1]) && $counter > 1) {
		array_pop($array);
		$counter = cacti_count($array);
	}

	if ($counter > 0) {
		$sql = '(';

		for ($i=0; $i < $counter; $i++) {
			$sql .= $array[$i][$sql_column];

			if ($i + 1 < $counter) {
				$sql .= ',';
			}
		}

		$sql .= ')';

		cacti_log($function . "() returns: $sql", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

		return $sql;
	}
}

function array_minus($big_array, $small_array) {
	# remove all unwanted fields
	if (cacti_sizeof($small_array)) {
		foreach ($small_array as $exclude) {
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
		case AUTOMATION_RULE_TYPE_GRAPH_MATCH: // Graph Rules - Device Selection Criteria > Edit
			$title      = __('Device Match Rule');
			$item_table = 'automation_match_rule_items';
			$sql_and    = ' AND rule_type=' . $rule_type;
			$tables     = array('host', 'host_templates');

			$automation_rule = db_fetch_row_prepared('SELECT *
				FROM automation_graph_rules
				WHERE id = ?',
				array($rule_id));

			$_fields_rule_item_edit = $fields_automation_match_rule_item_edit;

			$query_fields  = get_query_fields('host_template', array('id', 'hash'));
			$query_fields += get_query_fields('host', array('id', 'host_template_id'));

			$_fields_rule_item_edit['field']['array'] = $query_fields;

			$module = 'automation_graph_rules.php';

			break;
		case AUTOMATION_RULE_TYPE_GRAPH_ACTION: // Graph Rules - Graph Creation Criterial > Edit
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
			$fields    = array();

			if (cacti_sizeof($xml_array) && cacti_sizeof($xml_array['fields'])) {
				foreach ($xml_array['fields'] as $key => $value) {
					# ... work on all input fields
					if (isset($value['direction']) && ($value['direction'] == 'input' || $value['direction'] == 'input-output')) {
						$fields[$key] = $key . ' - ' . $value['name'];
					}
				}

				$_fields_rule_item_edit['field']['array'] = $fields;
			}

			$module = 'automation_graph_rules.php';

			break;
		case AUTOMATION_RULE_TYPE_TREE_MATCH: // Tree Rules - Object Selection > Edit
			$item_table = 'automation_match_rule_items';
			$sql_and    = ' AND rule_type=' . $rule_type;

			$automation_rule = db_fetch_row_prepared('SELECT *
				FROM automation_tree_rules
				WHERE id = ?',
				array($rule_id));

			$_fields_rule_item_edit = $fields_automation_match_rule_item_edit;

			$query_fields  = get_query_fields('host_template', array('id', 'hash'));
			$query_fields += get_query_fields('host', array('id', 'host_template_id'));

			if ($automation_rule['leaf_type'] == TREE_ITEM_TYPE_HOST) {
				$title  = __('Device Match Rule');
				$tables = array('host', 'host_templates');
			} elseif ($automation_rule['leaf_type'] == TREE_ITEM_TYPE_GRAPH) {
				$title  = __('Graph Match Rule');
				$tables = array('host', 'host_templates');

				# add some more filter columns for a GRAPH match
				$query_fields += get_query_fields('graph_templates', array('id', 'hash'));
				$query_fields += array('gtg.title' => 'GTG: title - varchar(255)');
				$query_fields += array('gtg.title_cache' => 'GTG: title_cache - varchar(255)');
			}

			$_fields_rule_item_edit['field']['array'] = $query_fields;

			$module = 'automation_tree_rules.php';

			break;
		case AUTOMATION_RULE_TYPE_TREE_ACTION: // Tree Rules - Tree Creation Criteria > Edit
			$item_table = 'automation_tree_rule_items';
			$sql_and    = '';

			$automation_rule = db_fetch_row_prepared('SELECT *
				FROM automation_tree_rules
				WHERE id = ?',
				array($rule_id));

			$_fields_rule_item_edit = $fields_automation_tree_rule_item_edit;

			$query_fields  = get_query_fields('host_template', array('id', 'hash'));
			$query_fields += get_query_fields('host', array('id', 'host_template_id'));

			/* list of allowed header types depends on rule leaf_type
			 * e.g. for a Device Rule, only Device-related header types make sense
			 */
			if ($automation_rule['leaf_type'] == TREE_ITEM_TYPE_HOST) {
				$title  = __('Create Tree Rule (Device)');
				$tables = array('host', 'host_templates');
			} elseif ($automation_rule['leaf_type'] == TREE_ITEM_TYPE_GRAPH) {
				$title  = __('Create Tree Rule (Graph)');
				$tables = array('host', 'host_templates');

				# add some more filter columns for a GRAPH match
				$query_fields += get_query_fields('graph_templates', array('id', 'hash'));
				$query_fields += array('gtg.title' => 'GTG: title - varchar(255)');
				$query_fields += array('gtg.title_cache' => 'GTG: title_cache - varchar(255)');
			}

			$_fields_rule_item_edit['field']['array'] = $query_fields;

			$module = 'automation_tree_rules.php';

			break;
	}

	if (!empty($rule_item_id)) {
		$automation_item = db_fetch_row_prepared("SELECT *
			FROM $item_table
			WHERE id = ?
			$sql_and",
			array($rule_item_id));

		if (cacti_sizeof($automation_item)) {
			$missing_key = $automation_item['field'];

			if (empty($missing_key)) {
				// Fixed String
			} elseif (!array_key_exists($missing_key, $_fields_rule_item_edit['field']['array'])) {
				$missing_array = explode('.',$missing_key);

				if (cacti_sizeof($missing_array) > 1) {
					$missing_table = strtoupper($missing_array[0]);
					$missing_value = strtolower($missing_array[1]);
				} else {
					$missing_table = '';
					$missing_value = strtolower($missing_array[0]);
				}

				$_fields_rule_item_edit['field']['array'] = array_merge(
					array($automation_item['field'] => 'Unknown: ' . $missing_table . ': ' . $missing_value),
					$_fields_rule_item_edit['field']['array']
				);
			}
		}

		$header_label = __esc('Rule Item [edit rule item for %s: %s]', $title, $automation_rule['name']);
	} else {
		$header_label = __esc('Rule Item [new rule item for %s: %s]', $title, $automation_rule['name']);

		$automation_item             = array();
		$automation_item['sequence'] = get_sequence(0, 'sequence', $item_table, 'rule_id=' . $rule_id . $sql_and);
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
 *
 * @param $host_id - the host to perform automation on
 * @param $graph_template_id - the graph_template_id to perform automation on
 *
 * @return null
 */
function automation_hook_graph_template($host_id, $graph_template_id) {
	global $config;

	$function = automation_function_with_pid(__FUNCTION__);

	cacti_log($function . ' called: Device[' . $host_id . '], GT[' . $graph_template_id . ']', false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	if (read_config_option('automation_graphs_enabled') == '') {
		cacti_log($function . ' Device[' . $host_id . '] - skipped: Graph Creation Switch is: ' . (read_config_option('automation_graphs_enabled') == '' ? 'off' : 'on'), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

		return;
	}

	automation_execute_graph_template($host_id, $graph_template_id);
}

/**
 * hook executed for a new graph on a tree
 *
 * @param $data - data passed from hook
 *
 * @return null|array
 */
function automation_hook_graph_create_tree($data) {
	global $config;

	$function = automation_function_with_pid(__FUNCTION__);

	cacti_log($function . ' called: ' . json_encode($data), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	if (read_config_option('automation_tree_enabled') == '') {
		cacti_log($function. ' skipped: Tree Creation Switch is: ' . (read_config_option('automation_tree_enabled') == '' ? 'off' : 'on'), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

		return;
	}

	automation_execute_graph_create_tree($data['id']);

	/* make sure, the next plugin gets required $data */
	return ($data);
}

/**
 * run rules for a data query
 *
 * @param $data - data passed from hook
 * @param mixed $host_id
 * @param mixed $snmp_query_id
 *
 * @return null
 */
function automation_execute_data_query($host_id, $snmp_query_id) {
	global $config;

	$function = automation_function_with_pid(__FUNCTION__);

	cacti_log($function . ' Device[' . $host_id . "] - start - data query: $snmp_query_id", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	$rules = db_fetch_cell_prepared('SELECT atr.*
		FROM automation_templates_rules AS atr
		INNER JOIN automation_templates AS at
		ON atr.template_id = at.id
		INNER JOIN host AS h
		ON at.host_template = h.host_template_id
		WHERE h.id = ?
		AND rule_type = 1',
		array($host_id));

	/* see if this is a new style automation or legacy */
	if (cacti_sizeof($rules)) {
		$sql = 'SELECT agr.id, agr.name,
			agr.snmp_query_id, agr.graph_type_id
			FROM automation_graph_rules AS agr
			INNER JOIN automation_templates_rules AS atr
			ON agr.id = atr.rule_id
			AND atr.rule_type = 1
			INNER JOIN host_snmp_query AS hsq
			ON agr.snmp_query_id = hsq.snmp_query_id
			WHERE agr.snmp_query_id = ?
			AND hsq.host_id = ?
			AND enabled = "on"';
	} else {
		$sql = 'SELECT agr.id, agr.name,
			agr.snmp_query_id, agr.graph_type_id
			FROM automation_graph_rules AS agr
			INNER JOIN host_snmp_query AS hsq
			ON agr.snmp_query_id = hsq.snmp_query_id
			WHERE agr.snmp_query_id = ?
			AND hsq.host_id = ?
			AND enabled = "on"';
	}

	$rules = db_fetch_assoc_prepared($sql, array($snmp_query_id, $host_id));

	cacti_log($function . ' Device[' . $host_id . '] - sql: ' . str_replace("\t", '', str_replace("\n", ' ', $sql)) . ' - found: ' . cacti_sizeof($rules), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_DEBUG);

	if (!cacti_sizeof($rules)) {
		return;
	}

	# now walk all rules and create graphs
	if (cacti_sizeof($rules)) {
		foreach ($rules as $rule) {
			cacti_log($function . ' Device[' . $host_id . '] - rule=' . $rule['id'] . ' name: ' . $rule['name'], false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

			/* build magic query, for matching hosts JOIN tables host and host_template */
			$sql_query = 'SELECT h.id AS host_id, h.hostname,
				h.description, ht.name AS host_template_name
				FROM host AS h
				LEFT JOIN host_template AS ht
				ON h.host_template_id = ht.id';

			/* get the WHERE clause for matching hosts */
			$sql_filter = build_matching_objects_filter($rule['id'], AUTOMATION_RULE_TYPE_GRAPH_MATCH);

			/* now we build up a new query for counting the rows */
			$rows_query = $sql_query . ' WHERE (' . $sql_filter . ') AND h.id=' . $host_id . ' AND h.deleted = ""';

			$hosts = db_fetch_assoc($rows_query, false);

			cacti_log($function . ' Device[' . $host_id . '] - create sql: ' . str_replace("\n",' ', $rows_query) . ' matches: ' . cacti_sizeof($hosts), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_DEBUG);

			if (!cacti_sizeof($hosts)) {
				continue;
			}

			create_dq_graphs($host_id, $snmp_query_id, $rule);
		}
	}
}

/**
 * automation_graph_automation_eligible
 *
 * This function determines if a Graph Template is eligible for
 * automation.  If there are any of the following that have
 * designated allowing for an over-ride, but to not have a default
 * value, then the Graph Template is not eligible for automatic
 * automation.
 *
 * Data Input Fields
 * Data Template Data Fields
 * Graph Template Fields
 *
 * @param $graph_template_id
 *
 * @return bool eligibility
 */
function automation_graph_automation_eligible($graph_template_id) {
	$graph_template = db_fetch_row_prepared('SELECT *
		FROM graph_templates_graph
		WHERE graph_template_id = ?
		AND local_graph_id = 0',
		array($graph_template_id));

	// Check the Graph Template first for adherence
	if (cacti_sizeof($graph_template)) {
		foreach ($graph_template as $field => $value) {
			if (substr($field, 0, 2) == 't_') {
				$parent = substr($field, 2);

				if (isset($graph_template[$parent])) {
					if ($value == 'on' && $graph_template[$parent] == '') {
						return false;
					}
				}
			}
		}
	}

	// Next let's check it's source Data Templates
	$data_templates = db_fetch_assoc_prepared('SELECT DISTINCT dtd.*
		FROM data_template_data AS dtd
		INNER JOIN data_template_rrd AS dtr
		ON dtd.data_template_id = dtr.data_template_id
		INNER JOIN graph_templates_item AS gti
		ON dtr.id = gti.task_item_id
		WHERE gti.graph_template_id = ?
		AND dtd.local_data_id = 0
		AND dtr.local_data_id = 0
		AND gti.hash != ""',
		array($graph_template_id));

	if (cacti_sizeof($data_templates)) {
		foreach ($data_templates as $dtd) {
			foreach ($dtd as $field => $value) {
				if (substr($field, 0, 2) == 't_') {
					$parent = substr($field, 2);

					if (isset($dtd[$parent])) {
						if ($value == 'on' && $dtd[$parent] == '') {
							return false;
						}
					}
				}
			}

			// Lastly check the data input fields
			$input_fields = db_fetch_assoc_prepared('SELECT dif.data_input_id, did.t_value, did.value, dtd.name
				FROM data_template_data AS dtd
				INNER JOIN data_template AS dt
				ON dt.id = dtd.data_template_id
				INNER JOIN data_input_data AS did
				ON did.data_template_data_id = dtd.id
				INNER JOIN data_input_fields AS dif
				ON dif.id = did.data_input_field_id
				WHERE dt.hash != ""
				AND dtd.id = ?
				AND dtd.local_data_id = 0
				AND dif.input_output = "in"
				AND dif.type_code = ""
				AND dif.allow_nulls = ""
				AND did.t_value = "on"
				AND did.value = ""',
				array($dtd['id']));

			if (cacti_sizeof($input_fields)) {
				return false;
			}
		}
	}

	return true;
}

/**
 * run rules for a graph template
 *
 * @param $data - data passed from hook
 * @param mixed $host_id
 * @param mixed $graph_template_id
 *
 * @return null
 */
function automation_execute_graph_template($host_id, $graph_template_id) {
	global $config;

	include_once(CACTI_PATH_LIBRARY . '/template.php');
	include_once(CACTI_PATH_LIBRARY . '/api_automation_tools.php');
	include_once(CACTI_PATH_LIBRARY . '/utility.php');

	$dataSourceId     = '';
	$returnArray      = array();
	$suggested_values = array();

	$function  = automation_function_with_pid(__FUNCTION__);
	cacti_log($function . ' called: Device[' . $host_id . '] - GT[' . $graph_template_id . ']', false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	# are there any input fields? if so use the default values
	if ($graph_template_id > 0) {
		$input_fields = getInputFields($graph_template_id);

		if (cacti_sizeof($input_fields)) {
			$suggested_vals[$graph_template_id]['custom_data'] = array();

			foreach ($input_fields as $field) {
				$suggested_vals[$graph_template_id]['custom_data'][$field['data_template_id']][$field['data_input_field_id']] = $field['default'];
			}
		}
	}

	# graph already present?
	$existsAlready = db_fetch_cell_prepared('SELECT id
		FROM graph_local
		WHERE graph_template_id = ?
		AND host_id = ?',
		array($graph_template_id, $host_id));

	if ($existsAlready > 0) {
		$dataSourceId  = db_fetch_cell_prepared('SELECT
			data_template_rrd.local_data_id
			FROM graph_templates_item, data_template_rrd
			WHERE graph_templates_item.local_graph_id = ?
			AND graph_templates_item.task_item_id = data_template_rrd.id
			LIMIT 1', array($existsAlready));

		cacti_log('NOTE: ' . $function . ' Device[' . $host_id . "] Graph Creation Skipped - Already Exists - Graph[$existsAlready] - DS[$dataSourceId]", false, 'AUTOM8', POLLER_VERBOSITY_MEDIUM);

		return;
	}

	if (automation_graph_automation_eligible($graph_template_id)) {
		if (test_data_sources($graph_template_id, $host_id)) {
			cacti_log('NOTE: Data Check Succeeded for - Device[' . $host_id . '], GT[' . $graph_template_id . ']', false, 'AUTOM8');

			$returnArray  = create_complete_graph_from_template($graph_template_id, $host_id, array(), $suggested_values);

			$dataSourceId = '';

			if ($returnArray !== false) {
				if (cacti_sizeof($returnArray)) {
					if (isset($returnArray['local_data_id'])) {
						foreach ($returnArray['local_data_id'] as $item) {
							push_out_host($host_id, $item);

							if ($dataSourceId != '') {
								$dataSourceId .= ', ' . $item;
							} else {
								$dataSourceId = $item;
							}
						}

						cacti_log('NOTE: Graph Added - Device[' . $host_id . '], Graph[' . $returnArray['local_graph_id'] . "], DS[$dataSourceId]", false, 'AUTOM8');
					}
				} else {
					cacti_log('ERROR: Device[' . $host_id . '], GT[' . $graph_template_id . '] Graph not added due to missing data sources.', false, 'AUTOM8');
				}
			} else {
				cacti_log('ERROR: Device[' . $host_id . '], GT[' . $graph_template_id . '] Graph not added due to whitelist check failure.', false, 'AUTOM8');
			}
		} else {
			cacti_log('NOTE: Device[' . $host_id . '], GT[' . $graph_template_id . '] Graph not added due to invalid data source output.', false, 'AUTOM8');
		}
	} else {
		cacti_log('NOTE: Device[' . $host_id . '], GT[' . $graph_template_id . '] Graph not added due to no default value for overridable field.', false, 'AUTOM8');
	}
}

/**
 * run rules for a new device in a tree
 *
 * @param $host_id - the host id of the device
 *
 * @return null
 */
function automation_execute_device_create_tree($host_id) {
	global $config;

	/* the $data array holds all information about the host we're just working on
	 * even if we selected multiple hosts, the calling code will scan through the list
	 * so we only have a single host here
	 */

	$function = automation_function_with_pid(__FUNCTION__);

	cacti_log($function . " Device[$host_id] called", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	/**
	 * find all active Tree Rules checking to see if there is a device rule that matches
	 * the Device.  If there is one, limit the Tree Rules to just those that match.
	 */
	$rules = db_fetch_cell_prepared('SELECT atr.*
		FROM automation_templates_rules AS atr
		INNER JOIN automation_templates AS at
		ON atr.template_id = at.id
		INNER JOIN host AS h
		ON at.host_template = h.host_template_id
		WHERE h.id = ?
		AND rule_type = 2',
		array($host_id));

	$host_template_id = db_fetch_cell_prepared('SELECT host_template_id
		FROM host
		WHERE id = ?',
		array($host_id));

	if (cacti_sizeof($rules)) {
		$sql = "SELECT atr.id, atr.name, atr.tree_id, atr.tree_item_id,
			atr.leaf_type, atr.host_grouping_type, aatr.exit_rules, aatr.sequence
			FROM automation_tree_rules AS atr
			INNER JOIN automation_templates_rules AS aatr
			ON atr.id = aatr.rule_id
			AND aatr.rule_type = 2
			WHERE enabled = 'on'
			AND leaf_type = " . TREE_ITEM_TYPE_HOST . "
			AND host_template_id = ?
			ORDER BY aatr.sequence";

		$rules = db_fetch_assoc_prepared($sql, array($host_template_id));
	} else {
		$sql = "SELECT atr.id, atr.name, atr.tree_id, atr.tree_item_id, '0' AS exit_rules, '1' AS sequence,
			atr.leaf_type, atr.host_grouping_type
			FROM automation_tree_rules AS atr
			WHERE enabled='on'
			AND leaf_type=" . TREE_ITEM_TYPE_HOST;

		$rules = db_fetch_assoc($sql);
	}

	cacti_log($function . ' Device[' . $host_id . '], matching rule sql: ' . str_replace("\n", '', $sql) . ' matches: ' . cacti_sizeof($rules), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_DEBUG);

	/* now walk all rules */
	if (cacti_sizeof($rules)) {
		foreach ($rules as $rule) {
			cacti_log($function . " Device[$host_id], rule: " . $rule['id'] . ' name: ' . $rule['name'] . ' type: ' . $rule['leaf_type'], false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

			/**
			 * does the rule apply to the current host?
			 * test 'eligible objects' rule items
			 */
			$matches = get_matching_hosts($rule, AUTOMATION_RULE_TYPE_TREE_MATCH, 'h.id=' . $host_id);

			cacti_log($function . " Device[$host_id], rule: " . $rule['id'] . ', matching hosts: ' . json_encode($matches), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

			/* if the rule produces a match, we will have to create all required tree nodes */
			if (cacti_sizeof($matches)) {
				/* create the bunch of header nodes */
				$parent = create_all_header_nodes($host_id, $rule);

				cacti_log($function . " Device[$host_id], rule: " . $rule['id'] . ', parent: ' . $parent, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

				/* now that all rule items have been executed, add the item itself */
				$node = create_device_node($host_id, $parent, $rule);

				cacti_log($function . " Device[$host_id], rule: " . $rule['id'] . ', node: ' . $node, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

				/* if the rule is setup to exit after the first match, exit */
				if ($rule['exit_rules'] == 1) {
					return;
				}
			}
		}
	}
}

/**
 * run rules for a new graph on a tree
 * @param $data - data passed from hook
 * @param mixed $graph_id
 */
function automation_execute_graph_create_tree($graph_id) {
	global $config;

	/* the $data array holds all information about the graph we're just working on
	 * even if we selected multiple graphs, the calling code will scan through the list
	 * so we only have a single graph here
	 */

	$function = automation_function_with_pid(__FUNCTION__);
	cacti_log($function . ' Graph[' . $graph_id . '] called', false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	$host_id = db_fetch_cell_prepared('SELECT host_id
		FROM graph_local
		WHERE id = ?',
		array($graph_id));

	$host_template_id = db_fetch_cell_prepared('SELECT host_template_id
		FROM host
		WHERE id = ?',
		array($host_id));

	/**
	 * find all active Tree Rules
	 * checking whether a specific rule matches the selected graph
	 * has to be done later
	 */
	$rules = db_fetch_cell_prepared('SELECT atr.*
		FROM automation_templates_rules AS atr
		INNER JOIN automation_templates AS at
		ON atr.template_id = at.id
		INNER JOIN host AS h
		ON at.host_template = h.host_template_id
		WHERE h.id = ?
		AND rule_type = 2',
		array($host_id));

	if (cacti_sizeof($rules)) {
		$sql = "SELECT atr.id, atr.name, atr.tree_id, atr.tree_item_id, aatr.exit_rules, aatr.sequence,
			atr.leaf_type, atr.host_grouping_type
			FROM automation_tree_rules AS atr
			INNER JOIN automation_templates_rules AS aatr
			ON atr.id = aatr.rule_id
			AND aatr.rule_type = 2
			WHERE enabled = 'on'
			AND leaf_type = " . TREE_ITEM_TYPE_GRAPH . "
			AND host_template_id = ?
			ORDER BY aatr.sequence";

		$rules = db_fetch_assoc_prepared($sql, array($host_template_id));
	} else {
		$sql = "SELECT atr.id, atr.name, atr.tree_id, atr.tree_item_id, '0' AS exit_rules, '0' AS sequence,
			atr.leaf_type, atr.host_grouping_type
			FROM automation_tree_rules AS atr
			WHERE enabled='on'
			AND leaf_type=" . TREE_ITEM_TYPE_GRAPH;

		$rules = db_fetch_assoc($sql);
	}


	cacti_log($function . ' Graph[' . $graph_id . '], Matching rule sql: ' . str_replace("\n",' ', $sql) . ' matches: ' . cacti_sizeof($rules), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_DEBUG);

	/* now walk all rules */
	if (cacti_sizeof($rules)) {
		foreach ($rules as $rule) {
			cacti_log($function  . ' Graph[' . $graph_id . '], rule: ' . $rule['id'] . ', name: ' . $rule['name'] . ', type: ' . $rule['leaf_type'], false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

			/* does this rule apply to the current graph?
			 * test 'eligible objects' rule items */
			$matches = get_matching_graphs($rule, AUTOMATION_RULE_TYPE_TREE_MATCH, 'gl.id=' . $graph_id);

			cacti_log($function . ' Graph[' . $graph_id . '], rule: ' . $rule['id'] . ', matching graphs: ' . json_encode($matches), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

			/* if the rule produces a match, we will have to create all required tree nodes */
			if (cacti_sizeof($matches)) {
				/* create the bunch of header nodes */
				$parent = create_all_header_nodes($graph_id, $rule);
				cacti_log($function . ' Graph[' . $graph_id . '], Rule: ' . $rule['id'] . ', Parent: ' . $parent, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

				/* now that all rule items have been executed, add the item itself */
				$node = create_graph_node($graph_id, $parent, $rule);
				cacti_log($function . ' Graph[' . $graph_id . '], Rule: ' . $rule['id'] . ', Node: ' . $node, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

				/* if the rule is setup to exit after the first match, exit */
				if ($rule['exit_rules'] == 1) {
					return;
				}
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

	$function = automation_function_with_pid(__FUNCTION__);
	cacti_log($function . ' Device[' . $host_id . "] - snmp query: $snmp_query_id - rule: " . $rule['name'], false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	$snmp_query_array                        = array();
	$snmp_query_array['snmp_query_id']       = $rule['snmp_query_id'];
	$snmp_query_array['snmp_index_on']       = get_best_data_query_index_type($host_id, $rule['snmp_query_id']);
	$snmp_query_array['snmp_query_graph_id'] = $rule['graph_type_id'];

	# get all rule items
	$automation_rule_items = db_fetch_assoc_prepared('SELECT *
		FROM automation_graph_rule_items AS agri
		WHERE rule_id = ?
		ORDER BY sequence',
		array($rule['id']));

	$automation_rule_fields = array_rekey(
		db_fetch_assoc_prepared('SELECT field
			FROM automation_graph_rule_items AS agri
			WHERE field != ""
			AND rule_id = ?',
			array($rule['id'])),
		'field', 'field'
	);

	# and all matching snmp_indices from snmp_cache
	$rule_name = db_fetch_cell_prepared('SELECT name
		FROM automation_graph_rules
		WHERE id = ?',
		array($rule['id']));

	/* get the unique field values from the database */
	$field_names = array_rekey(
		db_fetch_assoc_prepared('SELECT DISTINCT field_name
			FROM host_snmp_cache AS hsc
			WHERE snmp_query_id= ?
			AND host_id = ?',
			array($snmp_query_id, $host_id)),
		'field_name', 'field_name'
	);

	/* build magic query */
	$sql_query  = 'SELECT host_id, snmp_query_id, snmp_index';

	/* check for possible SQL errors */
	foreach ($automation_rule_fields as $column) {
		if (array_search($column, $field_names, true) === false) {
			cacti_log('WARNING: Automation Rule[' . $rule_name . '] for Device[' . $host_id . '] - DQ[' . $snmp_query_id . '] includes a SQL column ' . $column . ' that is not found for the Device.  Can not continue.', false, 'AUTOM8');

			return false;
		}
	}

	$num_visible_fields = cacti_sizeof($field_names);

	$i = 0;

	if (cacti_sizeof($field_names) > 0) {
		foreach ($field_names as $column) {
			$sql_query .= ", MAX(CASE WHEN field_name ='$column' THEN field_value ELSE NULL END) AS '$column'";
			$i++;
		}
	}

	$sql_query .= ' FROM host_snmp_cache AS hsc
		WHERE snmp_query_id = ' . $snmp_query_id . '
		AND host_id = ' . $host_id . '
		GROUP BY snmp_query_id, snmp_index';

	$sql_filter = build_rule_item_filter($automation_rule_items, '`a`.');

	if (strlen($sql_filter)) {
		$sql_filter = "\nWHERE" . $sql_filter;
	}

	/**
	 * add the additional filter settings to the original data query.
	 * IMO it's better for the MySQL server to use the original one
	 * as an subquery which requires MySQL v4.1(?) or higher
	 */
	$sql_query = "SELECT *\nFROM (\n" . $sql_query . ") AS `a`\n$sql_filter";

	/* fetch snmp indices */
	$dq_indexes = db_fetch_assoc($sql_query);

	# now create the graphs
	if (cacti_sizeof($dq_indexes)) {
		$graph_template_id = db_fetch_cell_prepared('SELECT graph_template_id
			FROM snmp_query_graph
			WHERE id = ?',
			array($rule['graph_type_id']));

		cacti_log($function . ' Found Template for Device[' . $host_id . '] - GT[' . $graph_template_id . ']', false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

		foreach ($dq_indexes as $snmp_index) {
			$snmp_query_array['snmp_index'] = $snmp_index['snmp_index'];

			cacti_log($function . ' Device[' . $host_id . '] - checking index: ' . $snmp_index['snmp_index'], false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

			$existsAlready = db_fetch_cell_prepared('SELECT DISTINCT gl.id
				FROM graph_local AS gl
				WHERE gl.snmp_query_graph_id = ?
				AND gl.host_id = ?
				AND gl.snmp_query_id = ?
				AND gl.snmp_index = ?',
				array($rule['graph_type_id'], $host_id, $rule['snmp_query_id'], $snmp_query_array['snmp_index']));

			if (isset($existsAlready) && $existsAlready > 0) {
				cacti_log('NOTE: ' . $function . ' Device[' . $host_id . "] Graph Creation Skipped - Already Exists - Graph[$existsAlready]", false, 'AUTOM8', POLLER_VERBOSITY_HIGH);
				continue;
			}

			$suggested_values = array();

			if (test_data_sources($graph_template_id, $host_id, $rule['snmp_query_id'], $snmp_query_array['snmp_index'])) {
				$return_array = create_complete_graph_from_template($graph_template_id, $host_id, $snmp_query_array, $suggested_values);

				if ($return_array !== false) {
					if (cacti_sizeof($return_array) &&
						array_key_exists('local_graph_id', $return_array) &&
						array_key_exists('local_data_id', $return_array)) {

						$data_source_id = db_fetch_cell_prepared('SELECT
							data_template_rrd.local_data_id
							FROM graph_templates_item, data_template_rrd
							WHERE graph_templates_item.local_graph_id = ?
							AND graph_templates_item.task_item_id = data_template_rrd.id
							LIMIT 1',
							array($return_array['local_graph_id']));

						foreach ($return_array['local_data_id'] as $item) {
							push_out_host($host_id, $item);

							if ($data_source_id != '') {
								$data_source_id .= ', ' . $item;
							} else {
								$data_source_id = $item;
							}
						}

						cacti_log('NOTE: Graph Added - Device[' . $host_id . '], Graph[' . $return_array['local_graph_id'] . "], DS[$data_source_id], Rule[" . $rule['id'] . ']', false, 'AUTOM8');
					} else {
						cacti_log('ERROR: Device[' . $host_id . '], GT[' . $graph_template_id . '], DQ[' . $rule['snmp_query_id'] . '], Index[' . $snmp_query_array['snmp_index'] . '], Rule[' . $rule['id'] . '] Graph not added due to missing data sources.', false, 'AUTOM8');
					}
				} else {
					cacti_log('ERROR: Device[' . $host_id . '], GT[' . $graph_template_id . '], DQ[' . $rule['snmp_query_id'] . '], Index[' . $snmp_query_array['snmp_index'] . '], Rule[' . $rule['id'] . '] Graph not added due to whitelist failure.', false, 'AUTOM8');
				}
			} else {
				cacti_log('NOTE: Device[' . $host_id . '], GT[' . $graph_template_id . '], DQ[' . $rule['snmp_query_id'] . '], Index[' . $snmp_query_array['snmp_index'] . '], Rule[' . $rule['id'] . '] Graph not added due to invalid data returned.', false, 'AUTOM8');
			}
		}
	}
}

/**
 * create_all_header_nodes - walk across all tree rule items
 *   - get all related rule items
 *   - take header type into account
 *   - create (multiple) header nodes
 *
 * @param mixed $item_id - id of the host/graph we're working on
 * @param mixed $rule    - the rule we're working on
 *
 * @return int - the last tree item that was hooked into the tree
 */
function create_all_header_nodes($item_id, $rule) {
	global $config, $automation_tree_header_types;

	# get all related rules that are enabled
	$tree_items = db_fetch_assoc_prepared('SELECT *
        FROM automation_tree_rule_items AS atri
        WHERE atri.rule_id = ?
        ORDER BY sequence',
		array($rule['id']));

	$function = automation_function_with_pid(__FUNCTION__);
	cacti_log($function . " called: Item $item_id matches: " . cacti_sizeof($tree_items) . ' items', false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	/* start at the given tree item
	 * it may be worth verifying existence of this entry
	 * in case it was selected once but then deleted
	 */
	$parent_tree_item_id = $rule['tree_item_id'];

	# now walk all rules and create tree nodes
	if (cacti_sizeof($tree_items)) {
		/* build magic query,
		 * for matching hosts JOIN tables host and host_template */
		if ($rule['leaf_type'] == TREE_ITEM_TYPE_HOST) {
			$sql_tables = 'FROM host AS h
				LEFT JOIN host_template AS ht
				ON h.host_template_id=ht.id ';

			$sql_where = 'WHERE h.id='. $item_id . ' AND h.deleted = "" ';
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

			$sql_where = 'WHERE gl.id=' . $item_id . ' AND h.deleted = "" ';
		}

		/* get the WHERE clause for matching hosts */
		$sql_filter = build_matching_objects_filter($rule['id'], AUTOMATION_RULE_TYPE_TREE_MATCH);

		foreach ($tree_items as $tree_item) {
			if ($tree_item['field'] === AUTOMATION_TREE_ITEM_TYPE_STRING) {
				# for a fixed string, use the given text
				$sql    = '';
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

			cacti_log($function . ' Item ' . $item_id . ' - sql: ' . str_replace("\m",'',$sql) . ' matches: ' . $target, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_DEBUG);

			$parent_tree_item_id = create_multi_header_node($target, $rule, $tree_item, $parent_tree_item_id);
		}
	}

	return $parent_tree_item_id;
}

/**
 * create_multi_header_node - work on a single header item
 *   - evaluate replacement rule
 *   - this may return an array of new header items
 *   - walk that array to create all header items for this single rule item
 *
 * @param mixed $object
 * @param mixed $rule
 * @param mixed $tree_item
 * @param mixed $parent_tree_item_id
 *
 * @return int id of the header that was hooked in
 */
function create_multi_header_node($object, $rule, $tree_item, $parent_tree_item_id) {
	global $config;

	$function = automation_function_with_pid(__FUNCTION__);
	cacti_log($function . " - object: '" . $object . "', Header: '" . $tree_item['search_pattern'] . "', parent: " . $parent_tree_item_id, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	if ($tree_item['field'] === AUTOMATION_TREE_ITEM_TYPE_STRING) {
		$parent_tree_item_id = create_header_node($tree_item['search_pattern'], $rule, $tree_item, $parent_tree_item_id);
		cacti_log($function . " called - object: '" . $object . "', Header: '" . $tree_item['search_pattern'] . "', hooked at: " . $parent_tree_item_id, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);
	} else {
		$replacement = automation_string_replace($tree_item['search_pattern'], $tree_item['replace_pattern'], $object);
		/* build multiline <td> entry */
		#print '<pre>'; print_r($replacement); print '</pre>';

		for ($j=0; cacti_sizeof($replacement); $j++) {
			$title               = array_shift($replacement);
			$parent_tree_item_id = create_header_node($title, $rule, $tree_item, $parent_tree_item_id);
			cacti_log($function . " - object: '" . $object . "', Header: '" . $title . "', hooked at: " . $parent_tree_item_id, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);
		}
	}

	return $parent_tree_item_id;
}

/**
 * create a single tree header node
 *
 * @param string $title				- graph title
 * @param array $rule				- rule
 * @param array $item				- item
 * @param int $parent_tree_item_id	- parent item id
 *
 * @return int						- id of new item
 */
function create_header_node($title, $rule, $item, $parent_tree_item_id) {
	global $config;

	$id             = 0;  # create a new entry
	$local_graph_id = 0;  # headers don't need no graph_id
	$host_id        = 0;  # or a host_id
	$site_id        = 0;  # or a site_id
	$propagate      = ($item['propagate_changes'] != '');
	$function       = automation_function_with_pid(__FUNCTION__);

	if (api_tree_branch_exists($rule['tree_id'], $parent_tree_item_id, $title)) {
		$new_item = api_tree_get_branch_id($rule['tree_id'], $parent_tree_item_id, $title);
		cacti_log('NOTE: ' . $function . ' Parent[' . $parent_tree_item_id . '] Tree Item - Already Exists', false, 'AUTOM8', POLLER_VERBOSITY_MEDIUM);
	} else {
		$new_item = api_tree_item_save($id, $rule['tree_id'], TREE_ITEM_TYPE_HEADER, $parent_tree_item_id,
			$title, $local_graph_id, $host_id, $site_id, $rule['host_grouping_type'], $item['sort_type'], $propagate);

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

	$id             = 0;      # create a new entry
	$local_graph_id = 0;      # hosts don't need no graph_id
	$site_id        = 0;      # hosts don't need no site_id
	$title          = '';     # nor a title
	$sort_type      = 0;      # nor a sort type
	$propagate      = false;  # nor a propagation flag
	$function       = automation_function_with_pid(__FUNCTION__);

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
			$local_graph_id, $host_id, $site_id, $rule['host_grouping_type'], $sort_type, $propagate);

		if (isset($new_item) && $new_item > 0) {
			cacti_log('NOTE: ' . $function . ' Device[' . $host_id . '] Tree Item - Added - Parent[' . $parent . '] Id[' . $new_item . ']', false, 'AUTOM8');
		} else {
			cacti_log('WARNING: ' . $function . ' Device[' . $host_id . '] Tree Item - Not Added', false, 'AUTOM8');
		}
	}

	return $new_item;
}

/**
 * add a site to the tree
 * @param int $site_id	- site id
 * @param int $parent	- parent id
 * @param array $rule 	- rule
 * @return int			- id of new item
 */
function create_site_node($site_id, $parent, $rule) {
	global $config;

	$id             = 0;      # create a new entry
	$local_graph_id = 0;      # hosts don't need no graph_id
	$host_id        = 0;      # hosts don't need no host_id
	$title          = '';     # nor a title
	$sort_type      = 0;      # nor a sort type
	$propagate      = false;  # nor a propagation flag
	$function       = 'Function[' . __FUNCTION__ . ']';

	if (api_tree_site_exists($rule['tree_id'], $parent, $site_id)) {
		$new_item = db_fetch_cell_prepared('SELECT id
			FROM graph_tree_items
			WHERE site_id = ?
			AND parent = ?
			AND graph_tree_id = ?',
			array($site_id, $parent, $rule['tree_id']));

		cacti_log('NOTE: ' . $function . ' Site[' . $host_id . '] Tree Item - Already Exists', false, 'AUTOM8', POLLER_VERBOSITY_MEDIUM);
	} else {
		$new_item = api_tree_item_save($id, $rule['tree_id'], TREE_ITEM_TYPE_HOST, $parent, $title,
			$local_graph_id, $host_id, $site_id, $rule['host_grouping_type'], $sort_type, $propagate);

		if (isset($new_item) && $new_item > 0) {
			cacti_log('NOTE: ' . $function . ' Site[' . $site_id . '] Tree Item - Added - id: (' . $new_item . ')', false, 'AUTOM8');
		} else {
			cacti_log('WARNING: ' . $function . ' Site[' . $site_id . '] Tree Item - Not Added', false, 'AUTOM8');
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

	$id        = 0;      # create a new entry
	$host_id   = 0;      # graphs don't need no host_id
	$site_id   = 0;      # graphs don't need no site_id
	$title     = '';     # nor a title
	$sort_type = 0;      # nor a sort type
	$propagate = false;  # nor a propagation flag
	$function  = automation_function_with_pid(__FUNCTION__);

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
			$graph_id, $host_id, $site_id, $rule['host_grouping_type'], $sort_type, $propagate);

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

	$command_string = cacti_escapeshellcmd(read_config_option('path_php_binary'));

	// If its not set, just assume its in the path
	if (trim($command_string) == '') {
		$command_string = 'php';
	}

	$extra_args = ' -q ' . cacti_escapeshellarg(CACTI_PATH_BASE . '/poller_automation.php') . ' -M';

	exec_background($command_string, $extra_args);
}

function automation_add_device($device, $web = false) {
	global $plugins, $config;

	$template_id          = $device['host_template'];
	$snmp_sysName         = $device['snmp_sysName'];

	$description          = isset($device['description']) ?
		$device['description'] : ($snmp_sysName != '' ? $snmp_sysName : ($device['hostname'] == '' ? $device['ip'] : $device['hostname']));

	$poller_id            = isset($device['poller_id']) ? $device['poller_id'] : read_config_option('default_poller');
	$site_id              = isset($device['site_id']) ? $device['site_id'] : read_config_option('default_site');
	$ip                   = isset($device['ip']) ? $device['ip']:$device['ip_address'];
	$snmp_community       = $device['snmp_community'];
	$snmp_ver             = $device['snmp_version'];
	$snmp_username	       = $device['snmp_username'];
	$snmp_password	       = $device['snmp_password'];
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
	$snmp_context	        = $device['snmp_context'];
	$snmp_engine_id       = $device['snmp_engine_id'];
	$max_oids             = isset($device['max_oids']) ? $device['max_oids']:10;
	$device_threads       = isset($device['device_threads']) ? $device['device_threads']:1;
	$external_id          = isset($device['external_id']) ? $device['external_id']:'';
	$location             = isset($device['location']) ? $device['location']:'';
	$bulk_walk_size       = isset($device['bulk_walk_size']) ? $device['bulk_walk_size']:-1;

	automation_debug(' - Adding Device');

	$host_id = api_device_save('0', $template_id, $description, $ip,
		$snmp_community, $snmp_ver, $snmp_username, $snmp_password,
		$snmp_port, $snmp_timeout, $disable, $availability_method,
		$ping_method, $ping_port, $ping_timeout, $ping_retries,
		$notes, $snmp_auth_protocol, $snmp_priv_passphrase,
		$snmp_priv_protocol, $snmp_context, $snmp_engine_id, $max_oids,
		$device_threads, $poller_id, $site_id, $external_id, $location, $bulk_walk_size);

	if ($host_id) {
		automation_debug(" - Success\n");

		/* Use the thold plugin if it exists */
		api_plugin_hook_function('device_threshold_autocreate', $host_id);

		db_execute_prepared('DELETE FROM automation_devices WHERE ip = ? LIMIT 1', array($ip));
	} else {
		automation_debug(" - Failed\n");
	}

	return $host_id;
}

function automation_add_tree($host_id, $tree) {
	automation_debug("     Adding to tree\n");

	if ($tree > 1000000) {
		$tree_id = $tree - 1000000;
		$parent  = 0;
	} else {
		$tree_item = db_fetch_row_prepared('SELECT * FROM graph_tree_items WHERE id = ?', array($tree));

		if (!isset($tree_item['graph_tree_id'])) {
			return;
		}
		$tree_id = $tree_item['graph_tree_id'];
		$parent  = $tree;
	}

	$nodeId = api_tree_item_save(0, $tree_id, 3, $parent, '', 0, $host_id, 0, 1, 1, false);
}

function automation_find_os($sysDescr, $sysObject, $sysName) {
	$sql_where  = '';

	$sql_where .= "WHERE (? REGEXP sysDescr OR ? LIKE CONCAT('%', sysDescr, '%'))";
	$sql_where .= " AND (? REGEXP sysOid OR ? LIKE CONCAT('%', sysOid, '%'))";
	$sql_where .= " AND (? REGEXP sysName OR ? LIKE CONCAT('%', sysName, '%'))";

	$params = array($sysDescr, $sysDescr, $sysObject, $sysObject, $sysName, $sysName);

	$result = db_fetch_row_prepared("SELECT at.*, ht.name
		FROM automation_templates AS at
		INNER JOIN host_template AS ht
		ON ht.id = at.host_template
		$sql_where
		ORDER BY sequence LIMIT 1", $params);

	if (cacti_sizeof($result)) {
		return $result;
	} else {
		return false;
	}
}

function automation_debug($text) {
	global $debug, $config;
	static $message = '';

	if (strstr($text, "\n") !== false) {
		$logLevel = POLLER_VERBOSITY_MEDIUM;

		if ($debug) {
			$logLevel = POLLER_VERBOSITY_NONE;
		}
		$full_message = trim($message . $text);
		$messages     = explode("\n",$full_message);

		foreach ($messages as $line) {
			$line = trim($line);

			if (strlen($line) > 0) {
				cacti_log(automation_get_pid() . ' ' . $line, false, 'AUTOM8', $logLevel);
			}
		}
		$message = '';
	} else {
		if (!$config['is_web']) {
			print $text;
		}
		$message .= $text;
	}
}

function automation_masktocidr($mask) {
	$cidr = false;
	$long = ip2long($mask);

	if ($long !== false) {
		$base = ip2long('255.255.255.255');
		$cidr = 32 - log(($long ^ $base) + 1, 2);
	}

	return $cidr;
}

function automation_get_valid_ip($range) {
	$long = ip2long($range);

	return $long === false ? false : long2ip($long);
}

function automation_get_valid_subnet_cidr($range) {
	$long = ip2long($range);

	if ($long !== false) {
		$bin = decbin($long);

		if (strlen($bin) == 32) {
			$zero = false;
			$cidr = 0;

			foreach (str_split($bin) as $char) {
				if ($char === '0') {
					$zero = true;
				} elseif ($zero) {
					$long = false;

					break;
				} else {
					$cidr++;
				}
			}
		} else {
			$long = false;
		}
	}

	return $long === false ? false : array('cidr' => $cidr, 'subnet' => long2ip($long));
}

function automation_get_valid_mask($range) {
	$cidr = false;

	if (is_numeric($range)) {
		if ($range > 0 && $range < 33) {
			$cidr = $range;
			$mask = array(
				'cidr'   => $cidr,
				'subnet' => long2ip((pow(2, $range) - 1) << (32 - $range)),
			);
		} else {
			$mask = false;
		}
	} else {
		$mask = automation_get_valid_subnet_cidr($range);
	}

	if ($mask !== false) {
		$mask['count'] = bindec(str_repeat('0',$mask['cidr']) . str_repeat('1',32 - $mask['cidr']));

		if ($mask['count'] == 0) {
			$mask['count'] = 1;
		}
	}

	return $mask;
}

function automation_get_network_info($range) {
	$network   = false;
	$broadcast = false;
	$mask      = array();
	$detail    = array();

	$range = trim($range);

	if (strpos($range, '/') !== false) {
		// 10.1.0.0/24 or 10.1.0.0/255.255.255.0
		$range_parts = explode('/', $range);

		if (!filter_var($range_parts[0], FILTER_VALIDATE_IP)) {
			return false;
		}

		$mask = automation_get_valid_mask($range_parts[1]);

		if (cacti_sizeof($mask)) {
			$network = automation_get_valid_ip($range_parts[0]);

			if ($mask['cidr'] != 0) {
				$dec       = ip2long($network) & ip2long($mask['subnet']);
				$count     = $mask['cidr'] == 32 ? 0 : $mask['count'];
				$network   = long2ip($dec);
				$broadcast = long2ip($dec + $count);
			}
		}
	} elseif (strpos($range, '*') !== false && strpos($range, '-') === false) {
		$test = str_replace('*', 0, $range);

		if (!filter_var($test, FILTER_VALIDATE_IP)) {
			return false;
		}

		$range_parts = explode('.', $range);
		$network     = '';
		$broadcast   = '';
		$part_count  = 0;

		foreach ($range_parts as $part) {
			if ($part != '*') {
				$part_count++;

				if (is_numeric($part)) {
					if ($part >= 0 && $part <= 255) {
						$network .= $part . '.';
						$broadcast .= '255.';
					} else {
						$network = false;

						break;
					}
				} else {
					$network = false;

					break;
				}
			} else {
				break;
			}
		}

		if ($part_count == 0 || $part_count > 3) {
			$network   = false;
			$broadcast = false;
		} else {
			while ($part_count < 4) {
				$part_count += 1;
				$broadcast .= '0.';
				$network .= '0.';
			}

			return automation_get_network_info(rtrim($network,'.').'/'.rtrim($broadcast,'.'));
		}
	} elseif (strpos($range, '-') !== false) {
		raise_message('automation_iprange', __('ERROR: IP ranges in the form of range1-range2 are no longer supported.'), MESSAGE_LEVEL_ERROR);

		return false;
	} else {
		$network   = automation_get_valid_ip($range);
		$broadcast = automation_get_valid_ip($range);
	}

	if ($network !== false && $broadcast !== false) {
		if (ip2long($network) <= ip2long($broadcast)) {
			$detail['network']   = $network;
			$detail['broadcast'] = $broadcast;
			$detail['cidr']      = isset($mask['cidr']) ? $mask['cidr'] : false;

			if ($network == $broadcast) {
				$detail['type']  = 'single';
				$detail['count'] = 1;
				$detail['cidr']  = 32;
				$detail['start'] = $network;
				$detail['end']   = $network;
			} else {
				$detail['type']  = isset($mask['cidr']) ? 'subnet' : 'range';
				$detail['count'] = ip2long($broadcast) - ip2long($network) - 1;
				$detail['start'] = long2ip(ip2long($network) + 1);
				$detail['end']   = long2ip(ip2long($broadcast) - 1);
			}
		}
	} else {
		return false;
	}

	return $detail;
}

function automation_calculate_start($range) {
	$detail = automation_get_network_info($range);

	if ($detail) {
		return $detail['start'];
	}

	automation_debug('  Could not calculate starting IP!');

	return false;
}

function automation_calculate_total_ips($range) {
	$detail = automation_get_network_info($range);

	if ($detail) {
		return $detail['count'];
	}

	automation_debug('  Could not calculate total IPs!');

	return false;
}

function automation_get_next_host($start, $total, $count, $range) {
	if ($count == $total || $total < 1) {
		return false;
	}

	if (preg_match('/^([0-9]{1,3}\.[0-9]{1,3}\.)\*(\.[0-9]{1,3})$/', $range, $matches)) {
		// 10.1.*.1
		return $matches[1] . ++$count . $matches[2];
	} else {
		// other cases
		$ip = explode('.', $start);
		$y  = 16777216;

		for ($x = 0; $x < 4; $x++) {
			$ip[$x] += intval($count / $y);
			$count -= ((intval($count / $y)) * 256);
			$y = $y / 256;

			if ($ip[$x] == 256 && $x > 0) {
				$ip[$x] = 0;
				$ip[$x - 1] += 1;
			}
		}

		return implode('.', $ip);
	}
}

function automation_primeIPAddressTable($network_id) {
	$subNets = db_fetch_cell_prepared('SELECT subnet_range
		FROM automation_networks
		WHERE id = ?',
		array($network_id));

	$ignore_ips = db_fetch_cell_prepared('SELECT ignore_ips
		FROM automation_networks
		WHERE id = ?',
		array($network_id));

	$subNets    = explode(',', trim($subNets));
	$total      = 0;

	if ($ignore_ips != '') {
		$ignore_ips = explode(',', $ignore_ips);

		foreach($ignore_ips as $index => $ip) {
			$ignore_ips[$index] = trim($ip);
		}
	} else {
		$ignore_ips = array();
	}

	if (cacti_sizeof($subNets)) {
		foreach ($subNets as $position => $subNet) {
			$count       = 1;
			$sql         = array();
			$subNetTotal = automation_calculate_total_ips($subNet);
			$total += $subNetTotal;

			$start = automation_calculate_start($subNet);

			if ($start != '' && !in_array($start, $ignore_ips)) {
				$sql[] = "('$start', '', $network_id, '0', '0', '0')";
			}

			while ($count < $subNetTotal) {
				$ip = automation_get_next_host($start, $subNetTotal, $count, $subNet);

				if ($ip != '' && !in_array($ip, $ignore_ips)) {
					$sql[] = "('$ip', '', $network_id, '0', '0', '0')";

					$count++;
				}

				if ($count % 1000 == 0) {
					db_execute('INSERT INTO automation_ips
						(ip_address, hostname, network_id, pid, status, thread)
						VALUES ' . implode(',', $sql));
					$sql = array();
				}
			}

			if (cacti_sizeof($sql)) {
				db_execute('INSERT INTO automation_ips
					(ip_address, hostname, network_id, pid, status, thread)
					VALUES ' . implode(',', $sql));
			}
		}
	}

	automation_debug("A Total of $total IP Addresses Primed\n");
}

function automation_valid_snmp_device(&$device) {
	global $snmp_logging;

	/* initialize variable */
	$host_up               = false;
	$snmp_logging          = false;
	$device['snmp_status'] = HOST_DOWN;
	$device['ping_status'] = 0;

	/* force php to return numeric oid's */
	cacti_oid_numeric_format();

	$snmp_items = db_fetch_assoc_prepared('SELECT *
		FROM automation_snmp_items
		WHERE snmp_id = ?
		ORDER BY sequence ASC',
		array($device['snmp_id']));

	if (cacti_sizeof($snmp_items)) {
		automation_debug(', SNMP: ');

		foreach ($snmp_items as $item) {
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
			$device['bulk_walk_size']       = $item['bulk_walk_size'];

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
						$snmp_sysObjectID      = trim(str_replace('"', '', $snmp_sysObjectID));
						$device['snmp_status'] = HOST_UP;
						$host_up               = true;

						break;
					}
				}
			}

			if ($host_up == true) {
				break;
			}
		}

		if ($host_up) {
			$device['snmp_sysObjectID'] = $snmp_sysObjectID;

			/* get system name */
			$snmp_sysName = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.5.0');

			if ($snmp_sysName != '') {
				$snmp_sysName           = trim(strtr($snmp_sysName,'"',' '));
				$device['snmp_sysName'] = $snmp_sysName;
				automation_debug($snmp_sysName);
			} else {
				automation_debug('Unknown System');
			}

			/* get system location */
			$snmp_sysLocation = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.6.0');

			if ($snmp_sysLocation != '') {
				$snmp_sysLocation           = trim(strtr($snmp_sysLocation,'"',' '));
				$device['snmp_sysLocation'] = $snmp_sysLocation;
			}

			/* get system contact */
			$snmp_sysContact = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.4.0');

			if ($snmp_sysContact != '') {
				$snmp_sysContact           = trim(strtr($snmp_sysContact,'"',' '));
				$device['snmp_sysContact'] = $snmp_sysContact;
			}

			/* get system description */
			$snmp_sysDescr = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.1.0');

			if ($snmp_sysDescr != '') {
				$snmp_sysDescr           = trim(strtr($snmp_sysDescr,'"',' '));
				$device['snmp_sysDescr'] = $snmp_sysDescr;
			}

			/* get system uptime */
			$snmp_sysUptime = cacti_snmp_session_get($session, '.1.3.6.1.6.3.10.2.1.3.0');

			if (!empty($snmp_sysUptime)) {
				$snmp_sysUptime *= 100;
			} else {
				$snmp_sysUptime = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.3.0');
			}

			if ($snmp_sysUptime != '') {
				$snmp_sysUptime           = trim(strtr($snmp_sysUptime,'"',' '));
				$device['snmp_sysUptime'] = $snmp_sysUptime;
			}

			$session->close();
		} else {
			automation_debug('No response');
		}
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
	if (cacti_count($octets) != 4) {
		return 'ERROR';
	}

	/* needs a byte to indicate the length of each segment of the request */
	for ($x=3; $x >= 0; $x--) {
		switch (strlen($octets[$x])) {
			case 1: // 1 byte long segment
				$data .= "\1";

				break;
			case 2: // 2 byte long segment
				$data .= "\2";

				break;
			case 3: // 3 byte long segment
				$data .= "\3";

				break;

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

	@stream_set_timeout($handle, floor($timeout / 1000), ($timeout * 1000) % 1000000);
	@stream_set_blocking($handle, 1);

	/* send our request (and store request size so we can cheat later) */
	$requestsize = @fwrite($handle, $data);

	/* get the response */
	$response = @fread($handle, 1000);

	/* check to see if it timed out */
	$info = @stream_get_meta_data($handle);

	/* close the socket */
	@fclose($handle);

	if (isset($info['timed_out'])) {
		return 'timed_out';
	}

	/* more error handling */
	if ($response == '' || $requestsize == false || strlen($response) <= $requestsize) {
		return $ip;
	}

	/* parse the response and find the response type */
	$type = @unpack('s', substr($response, $requestsize + 2));

	if (isset($type[1]) && $type[1] == 0x0C00) {
		/* set up our variables */
		$host = '';
		$len  = 0;

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
				return strtoupper(substr($host, 0, strlen($host) - 1));
			}

			/* add the next segment to our host */
			$host .= substr($response, $position + 1, $len[1]) . '.';

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
	$net = db_fetch_row_prepared('SELECT *
		FROM automation_networks
		WHERE id = ?',
		array($network_id));

	$now   = time();

	switch($net['sched_type']) {
		case '1':
			return false;

			break;
		case '2':
			$recur = $net['recur_every'] * 86400; // days
			$start = strtotime($net['start_at']);
			$next  = strtotime($net['next_start']);

			if ($net['next_start'] == '0000-00-00 00:00:00') {
				$target = $start;
			} else {
				$target = $next;
			}

			if ($now > $target) {
				while ($now > $target) {
					$target += $recur;
				}

				db_execute_prepared('UPDATE automation_networks
				SET next_start = ?
				WHERE id = ?',
					array(date('Y-m-d H:i', $target), $network_id));

				return true;

				break;
			}

			return false;

			break;
		case '3':
			$recur = $net['recur_every'] * 86400 * 7; // weeks
			$start = strtotime($net['start_at']);
			$next  = strtotime($net['next_start']);
			$days  = explode(',', $net['day_of_week']);
			$day   = 86400;
			$week  = 86400 * 7;

			if ($net['next_start'] == '0000-00-00 00:00:00') {
				$target = $start;
			} else {
				$target = $next;
			}

			if ($now > $target) {
				while (true) {
					$target += $day;
					$cur_day = date('w', $target) + 1;

					$key = array_search($cur_day, $days, false);

					if ($key !== false && $key >= 0) {
						if ($key == 0) {
							$target += $recur - $week;
						}

						break;
					}
				}

				db_execute_prepared('UPDATE automation_networks
				SET next_start = ?
				WHERE id = ?',
					array(date('Y-m-d H:i', $target), $network_id));

				return true;
			}

			return false;

			break;
		case '4':
		case '5':
			$next = calculateNextStart($net, $now);

			db_execute_prepared('UPDATE automation_networks
			SET next_start = ?
			WHERE id = ?',
				array(date('Y-m-d H:i', $next), $network_id));

			if ($net['next_start'] == '0000-00-00 00:00:00') {
				if ($now > strtotime($net['start_at'])) {
					return true;
				} else {
					return false;
				}
			} elseif ($now > strtotime($net['next_start'])) {
				return true;
			}

			return false;

			break;
	}
}

function calculateNextStart($net) {
	$now    = time();
	$dates  = array();

	switch($net['sched_type']) {
		case '4':
			$months = explode(',', $net['month']);
			$days   = explode(',', $net['day_of_month']);

			foreach ($months as $month) {
				foreach ($days as $day) {
					switch($month) {
						case '1':
							$smonth = 'January';

							break;
						case '2':
							$smonth = 'February';

							break;
						case '3':
							$smonth = 'March';

							break;
						case '4':
							$smonth = 'April';

							break;
						case '5':
							$smonth = 'May';

							break;
						case '6':
							$smonth = 'June';

							break;
						case '7':
							$smonth = 'July';

							break;
						case '8':
							$smonth = 'August';

							break;
						case '9':
							$smonth = 'September';

							break;
						case '10':
							$smonth = 'October';

							break;
						case '11':
							$smonth = 'November';

							break;
						case '12':
							$smonth = 'December';

							break;
					}

					if ($day == '32') {
						$dates[] = strtotime('last day of ' . $smonth);
					} else {
						$dates[] = strtotime("$smonth $day");
					}
				}
			}

			break;
		case '5':
			$months = explode(',', $net['month']);
			$weeks  = explode(',', $net['monthly_week']);
			$days   = explode(',', $net['monthly_day']);
			$now    = time();
			$dates  = array();

			foreach ($months as $month) {
				foreach ($weeks as $week) {
					foreach ($days as $day) {
						switch($month) {
							case '1':
								$smonth = 'January';

								break;
							case '2':
								$smonth = 'February';

								break;
							case '3':
								$smonth = 'March';

								break;
							case '4':
								$smonth = 'April';

								break;
							case '5':
								$smonth = 'May';

								break;
							case '6':
								$smonth = 'June';

								break;
							case '7':
								$smonth = 'July';

								break;
							case '8':
								$smonth = 'August';

								break;
							case '9':
								$smonth = 'September';

								break;
							case '10':
								$smonth = 'October';

								break;
							case '11':
								$smonth = 'November';

								break;
							case '12':
								$smonth = 'December';

								break;
						}

						switch($week) {
							case '1':
								$sweek = 'first';

								break;
							case '2':
								$sweek = 'second';

								break;
							case '3':
								$sweek = 'third';

								break;
							case '4':
								$sweek = 'forth';

								break;
							case '32':
								$sweek = 'last';

								break;
						}

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

						$dates[] = strtotime("$sweek $sday of $smonth", strtotime($net['start_at']));
					}
				}
			}

			break;
	}

	asort($dates);

	$newdates = array();

	foreach ($dates as $date) {
		$ndate = date('Y-m-d', $date) . ' ' . date('H:i:s', strtotime($net['start_at']));
		$ntime = strtotime($ndate);

		automation_debug('Start At: ' . $net['start_at'] . ', Possible Next Start: ' . $ndate . ' with Timestamp: ' . $ntime);

		if ($ntime > $now) {
			return $ntime;
		}
	}

	return false;
}

function ping_netbios_name($ip, $timeout_ms = 1000) {
	$handle = @fsockopen("udp://$ip", 137);

	if (is_resource($handle)) {
		stream_set_timeout($handle, floor($timeout_ms / 1000), ($timeout_ms * 1000) % 1000000);
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

			for ($i=57;$i < strlen($response);$i += 1) {
				if (hexdec(ord($response[$i])) == 0) {
					break;
				}
				$host .= $response[$i];
			}

			return trim(strtolower($host));
		} else {
			return false;
		}
	}
}

function automation_update_device($host_id) {
	$function = automation_function_with_pid(__FUNCTION__);
	cacti_log($function . ' Device[' . $host_id . ']', true, 'AUTOM8 TRACE', POLLER_VERBOSITY_MEDIUM);

	/* select all graph templates associated with this host, but exclude those where
	 * a graph already exists (table graph_local has a known entry for this host/template) */
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

	cacti_log($function . ' Device[' . $host_id . '], sql: ' . str_replace("\n",' ', $sql), true, 'AUTOM8 TRACE', POLLER_VERBOSITY_DEBUG);

	if ($host_id > 0) {
		object_cache_get_totals('device_state', $host_id);
	}

	/* create all graph template graphs */
	if (cacti_sizeof($graph_templates)) {
		foreach ($graph_templates as $graph_template) {
			cacti_log($function . ' Found GT[' . $graph_template['id'] . '] for Device[' . $host_id . ']', true, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

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
	if (cacti_sizeof($data_queries)) {
		foreach ($data_queries as $data_query) {
			cacti_log($function . ' Found DQ[' . $data_query['id'] . '] for Device[' . $host_id . ']', true, 'AUTOM8 TRACE', POLLER_VERBOSITY_MEDIUM);

			automation_execute_data_query($host_id, $data_query['id']);
		}
	}

	if ($host_id > 0) {
		object_cache_get_totals('device_state', $host_id, true);
		object_cache_update_totals('diff');
	}

	/* now handle tree rules for that host */
	cacti_log($function . ' Create Tree for Device[' . $host_id . ']', true, 'AUTOM8 TRACE', POLLER_VERBOSITY_MEDIUM);
	automation_execute_device_create_tree($host_id);
}

function automation_function_with_pid($functionName) {
	return automation_get_pid() . ' ' . $functionName . '()';
}

function automation_get_pid() {
	static $pid;

	if (!isset($pid)) {
		$pid = getmypid();
	}

	return "[PID: $pid]";
}

function automation_change_tree_rule_leaf_type($leaf_type, $rule_id) {
	$function = automation_function_with_pid(__FUNCTION__);

	$leaf_old = db_fetch_cell_prepared('SELECT leaf_type
		FROM automation_tree_rules
		WHERE id = ?',
		array($rule_id));

	if ($leaf_old != $leaf_type) {
		cacti_log($function . ' Found leaf change from Leaf[' . $leaf_old . '] to Leaf[' . $leaf_type . '] for TreeRule[' . $rule_id . ']', true, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

		if ($leaf_type == 3) {
			cacti_log($function . ' Found leaf changed to \'Device\' for TreeRule[' . $rule_id . ']', true, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

			$rule_items = db_fetch_assoc_prepared('SELECT *
				FROM automation_tree_rule_items
				WHERE rule_id = ?
				AND (field like \'gtg.%\' or field like \'gt.%\')',
				array($rule_id));

			if (cacti_sizeof($rule_items)) {
				cacti_log($function . ' ' . cacti_sizeof($rule_items) . ' invalid Tree Creation rule items found for TreeRule[' . $rule_id . ']', true, 'AUTOM8 TRACE', POLLER_VERBOSITY_DEBUG);

				foreach ($rule_items as $rule_item) {
					cacti_log($function . ' Removing invalid Tree Creation rule item TreeRule[' . $rule_id . '] TreeRuleItem[' . $rule_item['id'] . '] Field[' . html_escape($rule_item['field']) . '] with Search[' . html_escape($rule_item['search_pattern']) . '] Replace[' . html_escape($rule_item['replace_pattern']) . ']', true, 'AUTOM8 TRACE', POLLER_VERBOSITY_DEBUG);

					db_execute_prepared('DELETE
						FROM automation_tree_rule_items
						WHERE id = ?',
						array($rule_item['id']));
				}
			} else {
				cacti_log($function . ' No invalid Tree Creation rule items found for TreeRule[' . $rule_id . ']', true, 'AUTOM8 TRACE', POLLER_VERBOSITY_DEBUG);
			}

			$match_items = db_fetch_assoc_prepared('SELECT *
				FROM automation_match_rule_items
				WHERE rule_id = ?
				AND (field like \'gtg.%\' or field like \'gt.%\')',
				array($rule_id));

			if (cacti_sizeof($match_items)) {
				cacti_log($function . ' ' . cacti_sizeof($match_items) . ' invalid Object Selection rule items found for TreeRule[' . $rule_id . ']', true, 'AUTOM8 TRACE', POLLER_VERBOSITY_DEBUG);

				foreach ($match_items as $match_item) {
					cacti_log($function . ' Removing invalid Object Selection rule item TreeRule[' . $rule_id . '] TreeMatchItem[' . $match_item['id'] . '] Field[' . html_escape($match_item['field']) . '] with Pattern[' . html_escape($match_item['pattern']) . ']', true, 'AUTOM8 TRACE', POLLER_VERBOSITY_DEBUG);

					db_execute_prepared('DELETE
						FROM automation_match_rule_items
						WHERE id = ?',
						array($match_item['id']));
				}
			} else {
				cacti_log($function . ' No invalid Object Selection rule items found for TreeRule[' . $rule_id . ']', true, 'AUTOM8 TRACE', POLLER_VERBOSITY_DEBUG);
			}
		}

		db_execute_prepared('UPDATE automation_tree_rules
			SET leaf_type = ?
			WHERE id = ?',
			array($leaf_type, $rule_id));
	}
}

function automation_type_to_table($type) {
	$table = '';

	switch($type) {
		case 'network':
			$table = 'automation_networks';
			break;
		case 'device':
			$table = 'automation_templates';
			break;
		case 'device_rules':
			$table = 'automation_templates_rules';
			break;
		case 'graph':
			$table = 'automation_graph_rules';
			break;
		case 'graph_items':
			$table = 'automation_graph_rule_items';
			break;
		case 'tree':
			$table = 'automation_tree_rules';
			break;
		case 'tree_items':
			$table = 'automation_tree_rule_items';
			break;
		case 'snmp':
			$table = 'automation_snmp';
			break;
		case 'snmp_items':
			$table = 'automation_snmp_items';
			break;
	}

	return $table;
}

function automation_id_to_hash($type, $id) {
	$table = automation_type_to_table($table);

	if ($table != '') {
		return db_fetch_cell_prepared("SELECT hash FROM $table WHERE id = ?", array($id));
	}

	return false;
}

function automation_hash_to_id($type, $hash) {
	$table = automation_type_to_table($type);

	if ($table != '') {
		return db_fetch_cell_prepared("SELECT id
			FROM $table
			WHERE hash = ?",
			array($hash));
	}

	return false;
}

function automation_update_hashes() {
	$tables = array(
		'automation_templates',
		'automation_templates_rules',
		'automation_graph_rules',
		'automation_graph_rule_items',
		'automation_tree_rules',
		'automation_tree_rule_items',
		'automation_match_rule_items',
		'automation_snmp',
		'automation_snmp_items',
		'automation_networks'
	);

	foreach($tables as $table) {
		$items = db_fetch_assoc("SELECT id FROM $table WHERE hash = ''");

		if (cacti_sizeof($items)) {
			foreach($items as $item) {
				$hash = get_hash_automation(0, $table);

				db_execute_prepared("UPDATE $table
					SET hash = ?
					WHERE id = ?",
					array($hash, $item['id']));
			}
		}
	}
}

function automation_network_export($network_ids) {
	if (!is_array($network_ids)) {
		$export_name = db_fetch_cell_prepared("SELECT CONCAT('automation_network_', name)
			FROM automation_networks
			WHERE id = ?",
			array($network_ids));

		$network_ids = array($network_ids);
	} else {
		$export_name = 'automation_network_multiple';
	}

	$json_array = array();

	$json_array['name'] = clean_up_name(strtolower($export_name));
	$json_array['export_name'] = $json_array['name'] . '.json';

	if (cacti_sizeof($network_ids)) {
		$networks = array();

		foreach($network_ids as $id) {
			/* get the row of data */
			$network = db_fetch_row_prepared('SELECT *
				FROM automation_networks
				WHERE id = ?',
				array($id));

			$snmp_id = $network['snmp_id'];

			$network['snmp_id'] = db_fetch_cell_prepared('SELECT hash
				FROM automation_snmp
				WHERE id = ?', array($network['snmp_id']));

			/* set some safe defaults */
			$network['poller_id'] = 1;

			/* remove objects that have a hash */
			unset($network['id']);
			unset($network['up_hosts']);
			unset($network['snmp_hosts']);
			unset($network['last_runtime']);
			unset($network['last_started']);
			unset($network['last_status']);

			/* get the snmp options data */
			$snmp = db_fetch_row_prepared('SELECT *
				FROM automation_snmp
				WHERE id = ?',
				array($snmp_id));

			/* remove objects that have a hash */
			unset($snmp['id']);

			/* get the snmp options item data */
			$snmp_items = db_fetch_assoc_prepared('SELECT *
				FROM automation_snmp_items
				WHERE snmp_id = ?',
				array($snmp_id));

			/* remove objects that have a hash */
			foreach($snmp_items as $index => $item) {
				unset($snmp_items[$index]['id']);
				unset($snmp_items[$index]['snmp_id']);

				if ($item['snmp_version'] == 3) {
					unset($snmp_items[$index]['snmp_community']);
				} else {
					unset($snmp_items[$index]['snmp_username']);
					unset($snmp_items[$index]['snmp_password']);
					unset($snmp_items[$index]['snmp_auth_protocol']);
					unset($snmp_items[$index]['snmp_priv_protocol']);
					unset($snmp_items[$index]['snmp_priv_passphrase']);
					unset($snmp_items[$index]['snmp_context']);
					unset($snmp_items[$index]['snmp_engine_id']);
				}
			}

			/* collapse the snmp items into snmp */
			$snmp['snmp_items'] = $snmp_items;

			/* collapse the snmp object into the network */
			$network['snmp'] = $snmp;

			/* collapse the data object into the json object */
			$networks[] = $network;
		}

		$json_array['network'] = $networks;
	}

	return $json_array;
}

function automation_device_rule_export($template_ids) {
	if (!is_array($template_ids)) {
		$export_name = db_fetch_cell_prepared("SELECT CONCAT('automation_device_rule_', name)
			FROM automation_templates AS at
			INNER JOIN host_template AS ht
			ON at.host_template = ht.id
			WHERE at.id = ?",
			array($template_ids));

		$template_ids = array($template_ids);
	} else {
		$export_name = 'automation_device_rules_multiple';
	}

	$json_array = array();

	$json_array['name'] = clean_up_name(strtolower($export_name));
	$json_array['export_name'] = $json_array['name'] . '.json';

	if (cacti_sizeof($template_ids)) {
		$devices = array();

		foreach($template_ids as $id) {
			/* get the row of data */
			$device = db_fetch_row_prepared('SELECT *
				FROM automation_templates
				WHERE id = ?',
				array($id));

			$device['host_template'] = db_fetch_cell_prepared('SELECT hash
				FROM host_template
				WHERE id = ?',
				array($device['host_template']));

			/* remove objects that have a hash */
			unset($device['id']);

			/* get the snmp options data */
			$device_rule_items = db_fetch_assoc_prepared('SELECT *
				FROM automation_templates_rules
				WHERE template_id = ?',
				array($id));

			foreach($device_rule_items as $index => $rule) {
				if ($rule['rule_type'] == 1) {
					$device_rule_items[$index]['rule_id'] = db_fetch_cell_prepared('SELECT hash
						FROM automation_graph_rules
						WHERE id = ?',
						array($rule['rule_id']));
				} else {
					$device_rule_items[$index]['rule_id'] = db_fetch_cell_prepared('SELECT hash
						FROM automation_tree_rules
						WHERE id = ?',
						array($rule['rule_id']));
				}

				unset($device_rule_items[$index]['id']);
				unset($device_rule_items[$index]['template_id']);
			}

			$device['device_rules'] = $device_rule_items;

			/* get the snmp options item data */
			$graph_rules = db_fetch_assoc_prepared('SELECT *
				FROM automation_graph_rules
				WHERE id IN (SELECT rule_id FROM automation_templates_rules WHERE rule_type = 1 AND template_id = ?)',
				array($id));

			/* remove objects that have a hash */
			foreach($graph_rules as $index => $rule) {
				$rule_id = $rule['id'];

				$graph_rules[$index]['snmp_query_id'] = db_fetch_cell_prepared('SELECT hash
					FROM snmp_query
					WHERE id = ?',
					array($rule['snmp_query_id']));

				$graph_rules[$index]['graph_type_id'] = db_fetch_cell_prepared('SELECT hash
					FROM snmp_query_graph
					WHERE id = ?',
					array($rule['graph_type_id']));

				unset($graph_rules[$index]['id']);

				/* get the snmp options item data */
				$graph_rule_items = db_fetch_assoc_prepared('SELECT gri.*
					FROM automation_graph_rule_items AS gri
					WHERE gri.rule_id = ?',
					array($rule_id));

				/* remove objects that have a hash */
				foreach($graph_rule_items as $grindex => $rule_item) {
					unset($graph_rule_items[$grindex]['id']);
					unset($graph_rule_items[$grindex]['rule_id']);
				}

				/* collapse the graph rule items */
				$graph_rules[$index]['graph_rule_items'] = $graph_rule_items;

				/* match items */
				$graph_match_items = db_fetch_assoc_prepared('SELECT mri.*
					FROM automation_match_rule_items AS mri
					WHERE mri.rule_id = ?
					AND mri.rule_type IN (1,2)',
					array($rule_id));

				/* remove objects that have a hash */
				foreach($graph_match_items as $gmindex => $rule_item) {
					unset($graph_match_items[$gmindex]['id']);
					unset($graph_match_items[$gmindex]['rule_id']);
				}

				$graph_rules[$index]['graph_match_items'] = $graph_match_items;
			}

			$device['graph_rules'] = $graph_rules;

			/* get the snmp options item data */
			$tree_rules = db_fetch_assoc_prepared('SELECT *
				FROM automation_tree_rules
				WHERE id IN (SELECT rule_id FROM automation_templates_rules WHERE rule_type = 2 AND template_id = ?)',
				array($id));

			/* remove objects that have a hash */
			foreach($tree_rules as $index => $rule) {
				$rule_id = $rule['id'];

				/* get the snmp options item data */
				$tree_rule_items = db_fetch_assoc_prepared('SELECT gri.*
					FROM automation_tree_rule_items AS gri
					WHERE gri.rule_id = ?',
					array($rule_id));

				/* remove objects that have a hash */
				foreach($tree_rule_items as $trindex => $rule_item) {
					unset($tree_rule_items[$trindex]['id']);
				}

				/* unset the rule id */
				unset($tree_rules[$index]['id']);

				/* collapse the tree rule items */
				$tree_rules[$index]['tree_rule_items'] = $tree_rule_items;

				/* match items */
				$tree_match_items = db_fetch_assoc_prepared('SELECT mri.*
					FROM automation_match_rule_items AS mri
					WHERE mri.rule_id = ?
					AND mri.rule_type IN (3,4)',
					array($rule_id));

				/* remove objects that have a hash */
				foreach($tree_match_items as $tmindex => $rule_item) {
					unset($tree_match_items[$tmindex]['id']);
					unset($tree_match_items[$tmindex]['rule_id']);
				}

				$tree_rules[$index]['tree_match_items'] = $tree_match_items;
			}

			$device['tree_rules'] = $tree_rules;

			/* collapse the data object into the json object */
			$devices[] = $device;
		}

		$json_array['device'] = $devices;
	}

	return $json_array;
}

function automation_graph_rule_export($graph_rule_ids) {
	if (!is_array($graph_rule_ids)) {
		$export_name = db_fetch_cell_prepared("SELECT CONCAT('automation_graphs_rule_', name)
			FROM automation_graph_rules
			WHERE id = ?",
			array($graph_rule_ids));

		$graph_rule_ids = array($graph_rule_ids);
	} else {
		$export_name = 'automation_graph_rules_multiple';
	}

	$json_array = array();

	$json_array['name'] = clean_up_name(strtolower($export_name));
	$json_array['export_name'] = $json_array['name'] . '.json';

	if (cacti_sizeof($graph_rule_ids)) {
		$graph_rules = array();

		foreach($graph_rule_ids as $rule_id) {
			/* get the snmp options item data */
			$graph_rule = db_fetch_row_prepared('SELECT *
				FROM automation_graph_rules
				WHERE id = ?',
				array($rule_id));

			$graph_rule['snmp_query_id'] = db_fetch_cell_prepared('SELECT hash
				FROM snmp_query
				WHERE id = ?',
				array($graph_rule['snmp_query_id']));

			$graph_rule['graph_type_id'] = db_fetch_cell_prepared('SELECT hash
				FROM snmp_query_graph
				WHERE id = ?',
				array($graph_rule['graph_type_id']));

			/* get the snmp options item data */
			$graph_rule_items = db_fetch_assoc_prepared('SELECT gri.*
				FROM automation_graph_rule_items AS gri
				WHERE gri.rule_id = ?',
				array($rule_id));

			/* remove objects that have a hash */
			foreach($graph_rule_items as $grindex => $rule_item) {
				unset($graph_rule_items[$grindex]['id']);
				unset($graph_rule_items[$grindex]['rule_id']);
			}

			/* unset the rule id */
			unset($graph_rule['id']);

			/* collapse the graph rule items */
			$graph_rule['graph_rule_items'] = $graph_rule_items;

			/* match items */
			$graph_match_items = db_fetch_assoc_prepared('SELECT mri.*
				FROM automation_match_rule_items AS mri
				WHERE mri.rule_id = ?
				AND mri.rule_type IN (1,2)',
				array($rule_id));

			/* remove objects that have a hash */
			foreach($graph_match_items as $gmindex => $rule_item) {
				unset($graph_match_items[$gmindex]['id']);
				unset($graph_match_items[$gmindex]['rule_id']);
			}

			$graph_rule['graph_match_items'] = $graph_match_items;

			$graph_rules[] = $graph_rule;
		}

		$json_array['graph_rules'] = $graph_rules;
	}

	return $json_array;
}

function automation_tree_rule_export($tree_rule_ids) {
	if (!is_array($tree_rule_ids)) {
		$export_name = db_fetch_cell_prepared("SELECT CONCAT('automation_tree_rule_', name)
			FROM automation_tree_rules
			WHERE id = ?",
			array($tree_rule_ids));

		$tree_rule_ids = array($tree_rule_ids);
	} else {
		$export_name = 'automation_tree_rules_multiple';
	}

	$json_array = array();

	$json_array['name'] = clean_up_name(strtolower($export_name));
	$json_array['export_name'] = $json_array['name'] . '.json';

	if (cacti_sizeof($tree_rule_ids)) {
		$tree_rules = array();

		foreach($tree_rule_ids as $rule_id) {
			/* get the snmp options item data */
			$tree_rule = db_fetch_row_prepared('SELECT *
				FROM automation_tree_rules
				WHERE id = ?',
				array($rule_id));

			/* get the snmp options item data */
			$tree_rule_items = db_fetch_assoc_prepared('SELECT gri.*
				FROM automation_tree_rule_items AS gri
				WHERE gri.rule_id = ?',
				array($rule_id));

			/* remove objects that have a hash */
			foreach($tree_rule_items as $trindex => $rule_item) {
				unset($tree_rule_items[$trindex]['id']);
				unset($tree_rule_items[$trindex]['rule_id']);
			}

			/* unset the rule id */
			unset($tree_rule['id']);

			/* collapse the graph rule items */
			$tree_rule['tree_rule_items'] = $tree_rule_items;

			/* match items */
			$tree_match_items = db_fetch_assoc_prepared('SELECT mri.*
				FROM automation_match_rule_items AS mri
				WHERE mri.rule_id = ?
				AND mri.rule_type IN (3,4)',
				array($rule_id));

			/* remove objects that have a hash */
			foreach($tree_match_items as $tmindex => $rule_item) {
				unset($tree_match_items[$tmindex]['id']);
				unset($tree_match_items[$tmindex]['rule_id']);
			}

			$tree_rule['tree_match_items'] = $tree_match_items;

			$tree_rules[] = $tree_rule;
		}

		$json_array['tree_rules'] = $tree_rules;
	}

	return $json_array;
}

function automation_snmp_option_export($snmp_option_ids) {
	if (!is_array($snmp_option_ids)) {
		$export_name = db_fetch_cell_prepared("SELECT CONCAT('automation_snmp_option_', name)
			FROM automation_snmp
			WHERE id = ?",
			array($snmp_option_ids));

		$snmp_option_ids = array($snmp_option_ids);
	} else {
		$export_name = 'automation_snmp_options_multiple';
	}

	$json_array = array();

	$json_array['name'] = clean_up_name(strtolower($export_name));
	$json_array['export_name'] = $json_array['name'] . '.json';

	if (cacti_sizeof($snmp_option_ids)) {
		$options = array();

		foreach($snmp_option_ids as $option) {
			/* get the snmp options data */
			$snmp_option = db_fetch_row_prepared('SELECT *
				FROM automation_snmp
				WHERE id = ?',
				array($option));

			/* remove objects that have a hash */
			unset($snmp_option['id']);

			/* get the snmp options item data */
			$snmp_items = db_fetch_assoc_prepared('SELECT *
				FROM automation_snmp_items
				WHERE snmp_id = ?',
				array($option));

			/* remove objects that have a hash */
			foreach($snmp_items as $index => $item) {
				unset($snmp_items[$index]['id']);
				unset($snmp_items[$index]['snmp_id']);

				if ($item['snmp_version'] == 3) {
					unset($snmp_items[$index]['snmp_community']);
				} else {
					unset($snmp_items[$index]['snmp_username']);
					unset($snmp_items[$index]['snmp_password']);
					unset($snmp_items[$index]['snmp_auth_protocol']);
					unset($snmp_items[$index]['snmp_priv_protocol']);
					unset($snmp_items[$index]['snmp_priv_passphrase']);
					unset($snmp_items[$index]['snmp_context']);
					unset($snmp_items[$index]['snmp_engine_id']);
				}
			}

			/* collapse the snmp items into snmp */
			$snmp_option['snmp_items'] = $snmp_items;

			$options[] = $snmp_option;
		}

		$json_array['snmp'] = $options;
	}

	return $json_array;
}

function automation_validate_upload() {
	/* check file tranfer if used */
	if (isset($_FILES['import_file'])) {
		/* check for errors first */
		if ($_FILES['import_file']['error'] != 0) {
			switch ($_FILES['import_file']['error']) {
				case 1:
					raise_message('ftb', __('The file is too big.'), MESSAGE_LEVEL_ERROR);
					break;
				case 2:
					raise_message('ftb2', __('The file is too big.'), MESSAGE_LEVEL_ERROR);
					break;
				case 3:
					raise_message('ift', __('Incomplete file transfer.'), MESSAGE_LEVEL_ERROR);
					break;
				case 4:
					raise_message('nfu', __('No file uploaded.'), MESSAGE_LEVEL_ERROR);
					break;
				case 6:
					raise_message('tfm', __('Temporary folder missing.'), MESSAGE_LEVEL_ERROR);
					break;
				case 7:
					raise_message('ftwf', __('Failed to write file to disk'), MESSAGE_LEVEL_ERROR);
					break;
				case 8:
					raise_message('fusbe', __('File upload stopped by extension'), MESSAGE_LEVEL_ERROR);
					break;
			}

			if (is_error_message()) {
				return false;
			}
		}

		/* check mine type of the uploaded file */
		if ($_FILES['import_file']['type'] != 'application/json') {
			raise_message('ife', __('Invalid file extension.'), MESSAGE_LEVEL_ERROR);

			return false;
		}

		return json_decode(file_get_contents($_FILES['import_file']['tmp_name']), true);
	}

	raise_message('nfu2', __('No file uploaded.'), MESSAGE_LEVEL_ERROR);

	return false;
}

function automation_snmp_option_import($snmp) {
	global $config;

	$debug_data = array();

	foreach($snmp as $column => $coldata) {
		switch($column) {
			case 'hash':
				$save['id']   = automation_hash_to_id('snmp', $coldata);
				$save['hash'] = $coldata;

				break;
			case 'name':
				$save['name'] = $coldata;
				$snmp_id = sql_save($save, 'automation_snmp');

				if ($snmp_id) {
					if ($config['is_web']) {
						$debug_data['success'][] = __esc('Automation Network SNMP Rule \'%s\' %s!', $save['name'], ($save['id'] > 0 ? __('Updated'):__('Imported')));
					} else {
						$debug_data['success'][] = __('Automation Network SNMP Rule \'%s\' %s!', $save['name'], ($save['id'] > 0 ? __('Updated'):__('Imported')));
					}
				} else {
					if ($config['is_web']) {
						$debug_data['failure'][] = __esc('Automation Network SNMP Rule \'%s\' %s Failed!', $save['name'], ($save['id'] > 0 ? __('Update'):__('Import')));
					} else {
						$debug_data['failure'][] = __('Automation Network SNMP Rule \'%s\' %s Failed!', $save['name'], ($save['id'] > 0 ? __('Update'):__('Import')));
					}
				}

				break;
			case 'snmp_items':
				foreach($coldata as $snmp_options) {
					$save = array();

					$save['snmp_id'] = $snmp_id;

					foreach($snmp_options as $option => $optdata) {
						switch($option) {
							case 'hash':
								$save['id']   = automation_hash_to_id('snmp_items', $optdata);
								$save['hash'] = $optdata;

								break;
							default:
								$save[$option] = $optdata;
								break;
						}
					}

					$opt_id = sql_save($save, 'automation_snmp_items');

					if ($opt_id) {
						if ($config['is_web']) {
							$debug_data['success'][] = __esc('Automation Network SNMP Option %s!', ($save['id'] > 0 ? __('Updated'):__('Imported')));
						} else {
							$debug_data['success'][] = __('Automation Network SNMP Option %s!', ($save['id'] > 0 ? __('Updated'):__('Imported')));
						}
					} else {
						if ($config['is_web']) {
							$debug_data['failure'][] = __esc('Automation Network SNMP Option %s Failed!', ($save['id'] > 0 ? __('Update'):__('Import')));
						} else {
							$debug_data['failure'][] = __('Automation Network SNMP Option %s Failed!', ($save['id'] > 0 ? __('Update'):__('Import')));
						}
					}
				}

				break;
		}
	}

	return $debug_data;
}

function automation_network_import($json_data) {
	global $config;

	$debug_data = array();

	/**
	 * This routine will work from the bottom up so that we can maintain hash to id rules
	 * all the way up the rules.  So, in this case we will import the snmp_options first
	 * followed by the network's.
	 */
	if (is_array($json_data) && cacti_sizeof($json_data) && isset($json_data['network'])) {
		$error = false;
		$save  = array();

		foreach ($json_data['network'] as $netowrk => $data) {
			if (isset($data['snmp'])) {
				$debug_data += automation_snmp_option_import($data['snmp']);
				unset($data['snmp']);

				$save  = array();
			} else {
				$error = true;
				$debug_data['errors'][] = __('The Automation Network Rule does not include any SNMP Options!');
			}

			if (!automation_validate_import_columns('automation_networks', $data, $debug_data)) {
				$debug_data['errors'][] = __('The Automation Network Rule import columns do not match the database schema');
				$error = true;
			}

			if (!$error) {
				foreach($data as $column => $coldata) {
					switch($column) {
						case 'hash':
							$save['id']   = automation_hash_to_id('network', $coldata);
							$save['hash'] = $coldata;

							break;
						case 'snmp_id':
							$save['snmp_id'] = automation_hash_to_id('snmp', $coldata);

							break;
						case 'host_template':
							$template = db_fetch_row_prepared('SELECT id, name
								FROM host_templates
								WHERE hash = ?',
								array($coldata));

							if (!cacti_sizeof($save['host_template'])) {
								$debug_data['errors'][] = __('The Device Template related to the Network Rule is not loaded in this Cacti System.  Please edit this Network and set the Device Template, or Import the Device Template and then re-import this Automation Rule.');

								$error = true;

								$save['host_template'] = 0;

								$tname = __('Unknown Device Template');
							} else {
								$save['host_template'] = $template['id'];

								$name = $template['name'];
							}

							break;
						default:
							$save[$column] = $coldata;

							break;
					}
				}

				/* save the automation network first */
				$id = sql_save($save, 'automation_networks');

				if ($id) {
					if ($config['is_web']) {
						$debug_data['success'][] = __esc('Automation Network Rule \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated'):__('Imported')));
					} else {
						$debug_data['success'][] = __('Automation Network Rule \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated'):__('Imported')));
					}
				} else {
					if ($config['is_web']) {
						$debug_data['failure'][] = __esc('Automation Network Rule \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update'):__('Import')));
					} else {
						$debug_data['failure'][] = __('Automation Network Rule \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update'):__('Import')));
					}
				}
			}
		}
	} else {
		$debug_data['failure'][] = __('Automation Network Rule Import data is either for another object type or not JSON formatted.');
	}

	return $debug_data;
}

function automation_validate_import_columns($table, &$data, &$debug_data) {
	if (cacti_sizeof($data)) {
		foreach($data as $column => $data) {
			if (!db_column_exists($table, $column)) {
				$debug_data['errors'][] = __('Template column \'' . $column . '\' is not valid column.');

				cacti_log('Template column \'' . $column . '\' is not valid column.', false, 'AUTOM8');

				return false;
			}
		}
	} else {
		return false;
	}

	return true;
}
