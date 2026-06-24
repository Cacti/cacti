<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

/**
 * Creates a filter for tree devices.
 *
 * @param string $rows_label Optional. The label for the rows. Default is an empty string.
 *
 * @return array The filter for tree devices.
 */
function create_tree_devices_filter(string $rows_label = '') : array {
	global $item_rows;

	if ($rows_label == '') {
		$rows_label = __('Devices');
	}

	$any  = ['-1' => __('Any')];
	$none = ['0'  => __('None')];

	$templates = array_rekey(
		db_fetch_assoc('SELECT DISTINCT ht.id, ht.name
			FROM host_template AS ht
			ORDER BY name'),
		'id', 'name'
	);

	$templates = $any + $none + $templates;

	$status = [
		'-1' => __('Any'),
		'-3' => __('Enabled'),
		'-2' => __('Disabled'),
		'-4' => __('Not Up'),
		'3'  => __('Up'),
		'1'  => __('Down'),
		'2'  => __('Recovering'),
		'0'  => __('Unknown')
	];

	return [
		'rows' => [
			[
				'filter' => [
					'method'         => 'textbox',
					'friendly_name'  => __('Search'),
					'filter'         => FILTER_DEFAULT,
					'placeholder'    => __('Enter a search term'),
					'size'           => '30',
					'default'        => '',
					'pageset'        => true,
					'max_length'     => '120',
					'value'          => ''
				],
				'template_id' => [
					'method'         => 'drop_array',
					'friendly_name'  => __('Template'),
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'pageset'        => true,
					'array'          => $templates,
					'value'          => '-1'
				],
				'status' => [
					'method'         => 'drop_array',
					'friendly_name'  => __('Status'),
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'pageset'        => true,
					'array'          => $status,
					'value'          => '-1'
				],
				'rows' => [
					'method'         => 'drop_array',
					'friendly_name'  => $rows_label,
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'pageset'        => true,
					'array'          => $item_rows,
					'value'          => '-1'
				]
			]
		],
		'buttons' => [
			'go' => [
				'method'  => 'submit',
				'display' => __('Go'),
				'title'   => __('Apply Filter to Table'),
			],
			'clear' => [
				'method'  => 'button',
				'display' => __('Clear'),
				'title'   => __('Reset Filter to Default Values'),
			]
		],
		'sort' => [
			'sort_column'    => 'description',
			'sort_direction' => 'ASC'
		]
	];
}

/**
 * Processes and sanitizes the filter for drawing tree devices.
 *
 * @param bool   $render Whether to render the output. Default is false.
 * @param string $url    The URL to be used in the process. Default is an empty string.
 *
 * @return void
 */
function draw_tree_devices_filter(bool $render = false, string $url = '') : void {
	$filters = create_tree_devices_filter();

	$header = __('Matching Devices');

	// create the page filter
	$pageFilter = new CactiTableFilter($header, $url, 'forms', 'sess_auto_tdm');

	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}

/**
 * Displays the hosts that match the given rule.
 *
 * @param array  $rule      The rule to match hosts against. The type of this parameter depends on the rule_type.
 * @param int    $rule_type The type of rule being applied. This determines how the rule is interpreted.
 * @param string $url       The URL to be used for displaying the matching hosts.
 *
 * @return void
 */
function display_matching_hosts(array $rule, int $rule_type, string $url) : void {
	global $device_actions, $item_rows;

	draw_tree_devices_filter(true, $url);

	$details = automation_get_matching_device_sql($rule, $rule_type);

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

	$total_rows     = cacti_sizeof(db_fetch_assoc($details['rows_query'], false));
	$sort_column    = api_automation_column_exists(grv('sort_column'), ['host', 'graph_local', 'sites', 'graph_templates', 'graph_templates_graph', 'host_template']) ? grv('sort_column') : 'description';
	$sort_direction = in_array(strtoupper((string) grv('sort_direction')), ['ASC', 'DESC'], true) ? strtoupper((string) grv('sort_direction')) : 'ASC';
	$sortby         = str_ends_with($sort_column, 'hostname') ? 'INET_ATON(' . $sort_column . ')' : $sort_column;
	$sql_query      = $details['rows_query'] .
		' ORDER BY ' . $sortby . ' ' . $sort_direction .
		' LIMIT ' . ($details['rows'] * (grv('page') - 1)) . ',' . $details['rows'];

	$hosts = db_fetch_assoc($sql_query, false);

	$nav = html_nav_bar($url, MAX_DISPLAY_PAGES, grv('page'), $details['rows'], $total_rows, 7, __('Devices'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	$display_text = [
		'description' => [
			'display' => __('Description'),
			'sort'    => 'ASC'
		],
		'hostname' => [
			'display' => __('Hostname'),
			'sort'    => 'ASC'
		],
		'site_name' => [
			'display' => __('Site'),
			'sort'    => 'ASC'
		],
		'location' => [
			'display' => __('Location'),
			'sort'    => 'ASC'
		],
		'status' => [
			'display' => __('Status'),
			'sort'    => 'ASC',
			'align'   => 'center'
		],
		'host_template_name' => [
			'display' => __('Device Template Name'),
			'sort'    => 'ASC'
		],
		'id' => [
			'display' => __('ID'),
			'sort'    => 'ASC',
			'align'   => 'right'
		],
		'nosort1' => [
			'display' => __('Graphs'),
			'align'   => 'right'
		],
		'nosort2' => [
			'display' => __('Data Sources'),
			'align'   => 'right'
		],
	];

	html_header_sort($display_text, grv('sort_column'), grv('sort_direction'), 1, $url . '?action=edit&id=' . grv('id') . '&page=' . grv('page'));

	if (cacti_sizeof($hosts)) {
		foreach ($hosts as $host) {
			form_alternate_row('line' . $host['host_id'], true);

			form_selectable_cell(filter_value($host['description'], grv('filter'), 'host.php?action=edit&id=' . $host['host_id']), $host['host_id']);
			form_selectable_cell(filter_value($host['hostname'], grv('filter')), $host['host_id']);
			form_selectable_cell(filter_value($host['site_name'], grv('filter')), $host['host_id']);
			form_selectable_cell(filter_value($host['location'], grv('filter')), $host['host_id']);
			form_selectable_cell(get_colored_device_status((($host['disabled'] == 'on' || $host['site_disabled'] == 'on') ? true : false), $host['status']), $host['host_id'], '', 'center');
			form_selectable_cell(filter_value($host['host_template_name'], grv('filter')), $host['host_id']);
			form_selectable_cell(round(($host['host_id']), 2), $host['host_id'], '', 'right');
			form_selectable_cell(($host_graphs[$host['host_id']] ?? 0), $host['host_id'], '', 'right');
			form_selectable_cell(($host_data_sources[$host['host_id']] ?? 0), $host['host_id'], '', 'right');

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="8"><em>' . __('No Matching Devices') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($hosts)) {
		print $nav;
	}
}

/**
 * Generates SQL query to find devices matching the given automation rule.
 *
 * @param array $rule      The automation rule to match devices against.
 * @param int   $rule_type The type of rule being processed.
 *
 * @return array - The SQL query and parameters to find matching devices.
 */
function automation_get_matching_device_sql(array &$rule, int $rule_type) : array {
	// if the number of rows is -1, set it to the default
	if (grv('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	// form the 'where' clause for our main sql query
	if (grv('filter') != '') {
		$sql_where = 'WHERE h.deleted = ""
			AND (h.hostname LIKE ' . db_qstr('%' . grv('filter') . '%') . '
			OR h.description LIKE ' . db_qstr('%' . grv('filter') . '%') . '
			OR h.location LIKE ' . db_qstr('%' . grv('filter') . '%') . '
			OR h.external_id LIKE ' . db_qstr('%' . grv('filter') . '%') . '
			OR s.site LIKE ' . db_qstr('%' . grv('filter') . '%') . '
			OR ht.name LIKE ' . db_qstr('%' . grv('filter') . '%') . ')';
	} else {
		$sql_where = "WHERE h.deleted = ''";
	}

	if (db_column_exists('sites', 'disabled')) {
		$host_where_disabled = "(IFNULL(TRIM(s.disabled),'') == 'on' OR IFNULL(TRIM(h.disabled),'') == 'on')";
	} else {
		$host_where_disabled = "(IFNULL(TRIM(h.disabled),'') == 'on')";
	}

	$host_where_status = grv('status');

	if ($host_where_status == '-1') {
		// Show all items
	} elseif ($host_where_status == '-2') {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE ') . "($host_where_disabled)";
	} elseif ($host_where_status == '-3') {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE ') . "NOT ($host_where_disabled)";
	} elseif ($host_where_status == '-4') {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE ') . "(h.status!='3' OR $host_where_disabled)";
	} else {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE ') . "(h.status=$host_where_status AND NOT ($host_where_disabled))";
	}

	if (grv('host_template_id') == '-1') {
		// Show all items
	} elseif (grv('host_template_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE ') . 'h.host_template_id=0';
	} elseif (!ierv('host_template_id')) {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE ') . 'h.host_template_id=' . (int) grv('host_template_id');
	}

	// build magic query, for matching hosts JOIN tables host and host_template
	if (db_column_exists('sites', 'disabled')) {
		$sdisabled = 's.disabled AS site_disabled,';
	} else {
		$sdisabled = "'' AS site_disabled,";
	}

	// data query objects sql
	$hsc_sql = make_host_snnp_cache_sql();

	if ($hsc_sql !== false) {
		$hsc_join = "LEFT JOIN (\n$hsc_sql\n\t\t) AS hsc\n\t\tON hsc.host_id = h.id";
	} else {
		$hsc_join = '';
	}

	$sql_query = "SELECT DISTINCT h.id AS host_id, h.hostname, h.description,
		h.disabled AS disabled, $sdisabled
		h.status, ht.name AS host_template_name, s.name AS site_name, h.location
		FROM host AS h
		LEFT JOIN graph_local AS gl
		ON h.id = gl.host_id
		LEFT JOIN graph_templates AS gt
		ON gl.graph_template_id = gt.id
		LEFT JOIN graph_templates_graph AS gtg
		ON gl.id = gtg.local_graph_id
		LEFT JOIN sites AS s
		ON s.id = h.site_id
		$hsc_join
		LEFT JOIN host_template AS ht
		ON h.host_template_id = ht.id ";

	// get the WHERE clause for matching hosts
	$sql_filter = ($sql_where != '' ? ' AND (' : ' WHERE (') . build_matching_objects_filter($rule['id'], $rule_type) . ')';

	// now we build up a new query for counting the rows
	$rows_query = $sql_query . $sql_where . $sql_filter;
	$total_rows = cacti_sizeof(db_fetch_assoc($rows_query, false));

	$sortby = sanitize_sql_column(grv('sort_column'));

	if ($sortby == 'hostname') {
		$sortby = 'INET_ATON(hostname)';
	}

	return [
		'rows_query'  => $rows_query,
		'sortby'      => $sortby,
		'rows'        => $rows
	];
}

/**
 * Generates SQL query to get matching graphs based on the provided rule and rule type.
 *
 * @param array $rule      The rule to match graphs against.
 * @param int   $rule_type The type of rule to apply.
 *
 * @return array - The SQL query to fetch matching graphs.
 */
function automation_get_matching_graphs_sql(array $rule, int $rule_type) : array {
	// if the number of rows is -1, set it to the default
	if (grv('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	// form the 'where' clause for our main sql query
	if (grv('filter') != '') {
		$sql_where = 'WHERE (
			gtg.title_cache LIKE ' . db_qstr('%' . grv('filter') . '%') . '
			OR gt.name LIKE ' . db_qstr('%' . grv('filter') . '%') . '
			OR s.name LIKE ' . db_qstr('%' . grv('filter') . '%') . '
			OR h.description LIKE ' . db_qstr('%' . grv('filter') . '%') . '
			OR h.location LIKE ' . db_qstr('%' . grv('filter') . '%') . '
			OR h.hostname LIKE ' . db_qstr('%' . grv('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

	if (grv('host_id') == '-1') {
		// Show all items
	} elseif (grv('host_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE ') . ' gl.host_id = 0';
	} elseif (!ierv('host_id')) {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE ') . ' gl.host_id = ' . grv('host_id');
	}

	if (grv('template_id') == '-1') {
		// Show all items
	} elseif (grv('template_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE ') . ' gtg.graph_template_id = 0';
	} elseif (!ierv('template_id')) {
		$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE ') . ' gtg.graph_template_id = ' . grv('template_id');
	}

	// get the WHERE clause for matching graphs
	$sql_where .= ($sql_where != '' ? ' AND ' : ' WHERE ') . build_matching_objects_filter($rule['id'], $rule_type);

	// data query objects sql
	$hsc_sql = make_host_snnp_cache_sql();

	if ($hsc_sql !== false) {
		$hsc_join = "LEFT JOIN ($hsc_sql) AS hsc
			ON hsc.host_id = h.id";
	} else {
		$hsc_join = '';
	}

	$total_rows_query = "SELECT COUNT(gtg.id)
		FROM host AS h
		INNER JOIN graph_local AS gl
		ON h.id = gl.host_id
		LEFT JOIN sites AS s
		ON h.site_id = s.id
		$hsc_join
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

	$sort_column    = api_automation_column_exists(grv('sort_column'), ['host', 'graph_local', 'sites', 'graph_templates', 'graph_templates_graph', 'host_template']) ? grv('sort_column') : 'title_cache';
	$sort_direction = in_array(strtoupper((string) grv('sort_direction')), ['ASC', 'DESC'], true) ? strtoupper((string) grv('sort_direction')) : 'ASC';

	$rows_query = "SELECT h.id AS host_id, h.hostname, h.description,
		h.disabled AS disabled, $sdisabled
		h.status, ht.name AS host_template_name,
		gtg.id, gtg.local_graph_id, gtg.height, gtg.width,
		gtg.title_cache, gt.name, s.name AS site_name, h.location
		FROM host AS h
		INNER JOIN graph_local AS gl
		ON h.id = gl.host_id
		LEFT JOIN sites AS s
		ON h.site_id = s.id
		$hsc_join
		LEFT JOIN graph_templates AS gt
		ON gl.graph_template_id = gt.id
		LEFT JOIN graph_templates_graph AS gtg
		ON gl.id = gtg.local_graph_id
		LEFT JOIN host_template AS ht
		ON h.host_template_id = ht.id
		$sql_where
		ORDER BY " . $sort_column . ' ' . $sort_direction . '
		LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;

	return [
		'rows_query' => $rows_query,
		'total_rows' => $total_rows_query,
		'rows'       => $rows
	];
}

/**
 * Creates and returns an array of tree graphs based on a filter.
 *
 * @return array An array of tree graphs that match the filter criteria.
 */
function create_tree_graphs_filter() : array {
	global $item_rows;

	$any  = ['-1' => __('Any')];
	$none = ['0'  => __('None')];

	$templates = array_rekey(
		db_fetch_assoc('SELECT DISTINCT ht.id, ht.name
			FROM host_template AS ht
			ORDER BY name'),
		'id', 'name'
	);

	$rtemplates = [];
	$rhosts     = [];

	$templates = get_allowed_graph_templates('', 'name', '', $total_rows);

	if (cacti_sizeof($templates)) {
		foreach ($templates as $t) {
			$rtemplates[$t['id']] = $t['name'];
		}
	}

	$rtemplates = $any + $none + $rtemplates;

	$hosts = get_allowed_devices();

	if (cacti_sizeof($hosts)) {
		foreach ($hosts as $h) {
			$rhosts[$h['id']] = $h['description'];
		}
	}

	$rhosts = $any + $rhosts;

	$status = [
		'-1' => __('Any'),
		'-3' => __('Enabled'),
		'-2' => __('Disabled'),
		'-4' => __('Not Up'),
		'3'  => __('Up'),
		'1'  => __('Down'),
		'2'  => __('Recovering'),
		'0'  => __('Unknown')
	];

	return [
		'rows' => [
			[
				'filter' => [
					'method'         => 'textbox',
					'friendly_name'  => __('Search'),
					'filter'         => FILTER_DEFAULT,
					'placeholder'    => __('Enter a search term'),
					'size'           => '30',
					'default'        => '',
					'pageset'        => true,
					'max_length'     => '120',
					'value'          => ''
				],
				'host_id' => [
					'method'         => 'drop_array',
					'friendly_name'  => __('Device'),
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'pageset'        => true,
					'array'          => $rhosts,
					'value'          => '-1'
				],
				'template_id' => [
					'method'         => 'drop_array',
					'friendly_name'  => __('Template'),
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'pageset'        => true,
					'array'          => $rtemplates,
					'value'          => '-1'
				],
				'rows' => [
					'method'         => 'drop_array',
					'friendly_name'  => __('Graphs'),
					'filter'         => FILTER_VALIDATE_INT,
					'default'        => '-1',
					'pageset'        => true,
					'array'          => $item_rows,
					'value'          => '-1'
				]
			]
		],
		'buttons' => [
			'go' => [
				'method'  => 'submit',
				'display' => __('Go'),
				'title'   => __('Apply Filter to Table'),
			],
			'clear' => [
				'method'  => 'button',
				'display' => __('Clear'),
				'title'   => __('Reset Filter to Default Values'),
			]
		],
		'sort' => [
			'sort_column'    => 'title_cache',
			'sort_direction' => 'ASC'
		]
	];
}

/**
 * Processes and sanitizes the filter for drawing tree graphs.
 *
 * @param bool   $render Indicates whether to render the output.
 * @param string $url    The URL to be processed.
 *
 * @return void
 */
function draw_tree_graphs_filter(bool $render = false, string $url = '') : void {
	$filters = create_tree_graphs_filter();

	$header = __('Matching Graphs');

	// create the page filter
	$pageFilter = new CactiTableFilter($header, $url, 'forms', 'sess_auto_tgm');

	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}

/**
 * Displays matching graphs based on the provided rule and rule type.
 *
 * @param array  $rule      The rule to match graphs against.
 * @param int    $rule_type The type of the rule.
 * @param string $url       The URL to be used for displaying the graphs.
 *
 * @return void
 */
function display_matching_graphs(array $rule, int $rule_type, string $url) : void {
	global $graph_actions, $item_rows;

	draw_tree_graphs_filter(true, $url);

	$details = automation_get_matching_graphs_sql($rule, $rule_type);
	$rows    = $details['rows'];

	if (isset($details['total_rows'])) {
		$total_rows = db_fetch_cell($details['total_rows'], '', false);
		$graph_list = db_fetch_assoc($details['rows_query'], false);
	} else {
		$total_rows = 0;
		$graph_list = [];
	}

	$nav = html_nav_bar($url, MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 9, __('Graphs'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	$display_text = [
		'title_cache' => [
			'display' => __('Graph Title'),
			'sort'    => 'ASC'
		],
		'local_graph_id' => [
			'display' => __('Graph ID'),
			'sort'    => 'ASC'
		],
		'name' => [
			'display' => __('Graph Template Name'),
			'sort'    => 'ASC'
		],
		'description' => [
			'display' => __('Device Description'),
			'sort'    => 'ASC'
		],
		'hostname' => [
			'display' => __('Hostname'),
			'sort'    => 'ASC'
		],
		's.name' => [
			'display' => __('Site'),
			'sort'    => 'ASC'
		],
		'location' => [
			'display' => __('Location'),
			'sort'    => 'ASC'
		],
		'host_template_name' => [
			'display' => __('Device Template Name'),
			'sort'    => 'ASC'
		],
		'status' => [
			'display' => __('Status'),
			'sort'    => 'ASC'
		],
	];

	html_header_sort($display_text, grv('sort_column'), grv('sort_direction'), 1, $url . '?action=edit&id=' . grv('id') . '&page=' . grv('page'));

	if (cacti_sizeof($graph_list)) {
		foreach ($graph_list as $graph) {
			$template_name = ((empty($graph['name'])) ? '<em>' . __('None') . '</em>' : htmle($graph['name']));

			form_alternate_row('line' . $graph['local_graph_id'], true);

			form_selectable_cell(filter_value($graph['title_cache'], grv('filter'), 'graphs.php?action=graph_edit&id=' . $graph['local_graph_id']), $graph['local_graph_id']);
			form_selectable_cell($graph['local_graph_id'], $graph['local_graph_id']);
			form_selectable_cell(filter_value($template_name, grv('filter')), $graph['local_graph_id']);
			form_selectable_cell(filter_value($graph['description'], grv('filter'), 'host.php?action=edit&id=' . $graph['host_id']), $graph['local_graph_id']);
			form_selectable_cell(filter_value($graph['hostname'], grv('filter')), $graph['local_graph_id']);
			form_selectable_cell(filter_value($graph['site_name'], grv('filter')), $graph['local_graph_id']);
			form_selectable_cell(filter_value($graph['location'], grv('filter')), $graph['local_graph_id']);
			form_selectable_cell(filter_value($graph['host_template_name'], grv('filter')), $graph['local_graph_id']);
			form_selectable_cell(get_colored_device_status((($graph['disabled'] == 'on' || $graph['site_disabled'] == 'on') ? true : false), $graph['status']), $graph['local_graph_id']);

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="8"><em>' . __('No Graphs Found') . '</em></td></tr>';
	}

	html_end_box(true);

	if (cacti_sizeof($graph_list)) {
		print $nav;
	}
}

/**
 * Generates the SQL query to retrieve new graphs based on the provided rule.
 *
 * @param array $rule An associative array containing the rule parameters for the SQL query.
 *
 * @return mixed - Returns an array of new graphs if successful, or false on failure.
 */
function automation_get_new_graphs_sql(array $rule) : mixed {
	// ================= input validation =================
	gfrv('id');
	gfrv('snmp_query_id');
	// ====================================================

	if (isrv('oclear')) {
		srv('clear', 'true');
	}

	// ================= input validation and session storage =================
	$filters = [
		'rows' => [
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		],
		'page' => [
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
		],
		'filter' => [
			'filter'  => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
		],
		'sort_column' => [
			'filter'  => FILTER_CALLBACK,
			'default' => 'description',
			'options' => ['options' => 'sanitize_search_string']
		],
		'sort_direction' => [
			'filter'  => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => ['options' => 'sanitize_search_string']
		]
	];

	validate_store_request_vars($filters, 'sess_autog');
	// ================= input validation =================

	if (isrv('oclear')) {
		unsrv('clear');
	}

	// if the number of rows is -1, set it to the default
	if (grv('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	$rule_items     = [];
	$created_graphs = get_created_graphs($rule);

	$total_rows         = 0;
	$num_input_fields   = 0;
	$num_visible_fields = 0;

	$snmp_query = db_fetch_row_prepared('SELECT snmp_query.id, snmp_query.name, snmp_query.xml_path
		FROM snmp_query
		WHERE snmp_query.id = ?',
		[$rule['snmp_query_id']]);

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
		// loop through once so we can find out how many input fields there are
		foreach ($xml_array['fields'] as $field_name => $field_array) {
			if ($field_array['direction'] == 'input' || $field_array['direction'] == 'input-output') {
				$num_input_fields++;

				if ($total_rows == 0) {
					$total_rows = db_fetch_cell_prepared('SELECT COUNT(*)
						FROM host_snmp_cache
						WHERE snmp_query_id = ?
						AND field_name = ?',
						[$rule['snmp_query_id'], $field_name]);
				}
			}
		}
	}

	if (cacti_sizeof($xml_array)) {
		$dq_header  = '';
		$sql_filter = '';
		$sql_query  = '';
		$sql_having = '';
		$dq_indexes = [];

		$rule_items = db_fetch_assoc_prepared('SELECT *
			FROM automation_graph_rule_items
			WHERE rule_id = ?
			ORDER BY sequence',
			[$rule['id']]);

		$automation_rule_fields = array_rekey(
			db_fetch_assoc_prepared('SELECT DISTINCT field
				FROM automation_graph_rule_items AS agri
				WHERE field != ""
				AND rule_id = ?',
				[$rule['id']]),
			'field', 'field'
		);

		$rule_name = db_fetch_cell_prepared('SELECT name
			FROM automation_graph_rules
			WHERE id = ?',
			[$rule['id']]);

		// get the unique field values from the database
		$field_names = array_rekey(
			db_fetch_assoc_prepared('SELECT DISTINCT field_name
				FROM host_snmp_cache AS hsc
				WHERE snmp_query_id = ?',
				[$rule['snmp_query_id']]),
			'field_name', 'field_name'
		);

		$run_query = true;

		// check for possible SQL errors
		foreach ($automation_rule_fields as $column) {
			if (array_search($column, $field_names, true) === false) {
				$run_query = false;
			}
		}

		if ($run_query) {
			// main sql
			if (isset($xml_array['index_order_type'])) {
				$sql_order = build_sort_order($xml_array['index_order_type'], 'automation_host');
				$sql_query = build_data_query_sql($rule) . ' ' . $sql_order;
			} else {
				$sql_query = build_data_query_sql($rule);
			}

			$results = db_fetch_cell("SELECT COUNT(*) FROM ($sql_query) AS `a`", '', false);
		} else {
			$results = [];
		}

		if ($results) {
			// rule item filter first
			$sql_filter	 = build_rule_item_filter($rule_items, '`a`.');

			// filter on on the display filter next
			$sql_having = build_graph_object_sql_having($rule, grv('filter'));

			// now we build up a new query for counting the rows
			$rows_query    = "SELECT * \nFROM (\n" . trim($sql_query) . "\n) AS `a` " . ($sql_filter != '' ? "\nWHERE (\n" . trim($sql_filter) . "\n)" : '') . $sql_having;

			// construct the indexes query
			$indexes_query = $rows_query . "\nLIMIT " . ($rows * (grv('page') - 1)) . ',' . $rows;
		} else {
			$rows_query    = '';
			$indexes_query = '';
		}

		return [
			'rows_query'    => $rows_query,
			'indexes_query' => $indexes_query,
			'xml_array'     => $xml_array,
			'dq_header'     => $dq_header,
			'rows'          => $rows,
			'name'          => $name
		];
	} else {
		return false;
	}
}

/**
 * Displays new graphs based on the provided rule and URL.
 *
 * @param array  $rule An array containing the rules for displaying new graphs.
 * @param string $url  The URL to be used for displaying the new graphs.
 *
 * @return void
 */
function display_new_graphs(array $rule, string $url) : void {
	global $item_rows;

	// create the page filter
	$pageFilter             = new CactiTableFilter(__('Matching Indexes'), $url, 'form', 'sess_auto_mo', '', false, false);
	$pageFilter->rows_label = __('Objects');
	$pageFilter->render();

	$details        = automation_get_new_graphs_sql($rule);
	$created_graphs = get_created_graphs($rule);

	if (isset($details['rows_query']) && $details['rows_query'] != '') {
		$total_rows = cacti_sizeof(db_fetch_assoc($details['rows_query'], false));

		if ($total_rows < (grv('rows') * (grv('page') - 1)) + 1) {
			srv('page', '1');
		}

		$dq_indexes = db_fetch_assoc($details['indexes_query'], false);
		$xml_array  = $details['xml_array'];

		$rows = $details['rows'];
		$name = $details['name'];

		$nav = html_nav_bar('automation_graph_rules.php?action=edit&id=' . $rule['id'], MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 30, __('Matching Indexes'), 'page', 'main');

		print $nav;

		html_start_box(__('Matching Indexes [ %s ]&nbsp;', htmle($name)) . display_tooltip(__('A blue font color indicates that the rule will be applied to the objects in question.  Other objects will not be subject to the rule.')), '100%', false, 3, 'center', '');

		/**
		 * print the Data Query table's header
		 * number of fields has to be dynamically determined
		 * from the Data Query used
		 */
		// we want to print the host name as the first column
		$new_fields['automation_host'] = ['name' => __('Hostname'), 'direction' => 'input'];
		$new_fields['status']          = ['name' => __('Device Status'), 'direction' => 'input'];
		$xml_array['fields']           = $new_fields + $xml_array['fields'];

		$field_names = get_field_names($rule['snmp_query_id']);

		if (is_array($field_names)) {
			array_unshift($field_names, ['field_name' => 'status']);
			array_unshift($field_names, ['field_name' => 'automation_host']);
		} else {
			$field_names = [['field_name' => 'status'], ['field_name' => 'automation_host']];
		}

		$display_text = [];

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

		// list of all entries
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
									print "<td><span id='text$row_counter" . '_' . $column_counter . "' $style>" . filter_value($row[$field_name], grv('filter')) . '</span></td>';
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
		$display_text = [__('Error Message')];

		html_start_box(__('Index Errors [ %s ]', htmle($details['xml_array']['name'])), '100%', false, 3, 'center', '');
		html_header($display_text);
		print "<tr class='tableRow odd'><td class='deviceDown'>" . __('Error in data query') . '</td></tr>';
		html_end_box();
	}

	print '</table>';
}

/**
 * Processes and sanitizes the filter for drawing tree items.
 *
 * @param bool   $render Indicates whether to render the tree items.
 * @param string $url    The URL to be used for processing.
 *
 * @return void
 */
function draw_tree_items_filter(bool $render = false, string $url = '') : void {
	$filters = create_tree_devices_filter(__('Data Queries'));

	$header = __('Matching Items');

	// create the page filter
	$pageFilter             = new CactiTableFilter($header, $url, 'forms', 'sess_auto_tim');
	$pageFilter->rows_label = __('Data Queries');

	$pageFilter->set_filter_array($filters);

	if ($render) {
		$pageFilter->render();
	} else {
		$pageFilter->sanitize();
	}
}

/**
 * Displays matching trees based on the provided rule ID and type.
 *
 * @param int    $rule_id   - The ID of the rule to match.
 * @param int    $rule_type - The type of the rule to match.
 * @param array  $item      - The item array to be processed.
 * @param string $url       - The URL associated with the item.
 *
 * @return void
 */
function display_matching_trees(int $rule_id, int $rule_type, array $item, string $url) : void {
	global $automation_tree_header_types;
	global $device_actions, $item_rows;

	$function = automation_function_with_pid(__FUNCTION__);
	cacti_log($function . " called: $rule_id/$rule_type", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	draw_tree_items_filter(true, $url);

	if (grv('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	form_hidden_box('page', '1', '');

	// build magic query, for matching hosts JOIN tables host and host_template
	$leaf_type = db_fetch_cell('SELECT leaf_type FROM automation_tree_rules WHERE id = ' . $rule_id);

	// data query objects sql
	$hsc_sql = make_host_snnp_cache_sql();

	// initialize variables for PHPStan
	$sql_where  = 'WHERE 1 = 1';
	$sql_tables = '';

	if ($hsc_sql !== false) {
		$hsc_join = "LEFT JOIN ($hsc_sql) AS hsc
			ON hsc.host_id = h.id";
	} else {
		$hsc_join = '';
	}

	if ($leaf_type == TREE_ITEM_TYPE_HOST) {
		$sql_tables = "FROM host AS h
			LEFT JOIN sites AS s
			ON h.site_id = s.id
			$hsc_join
			LEFT JOIN host_template AS ht
			ON h.host_template_id = ht.id";

		$sql_where = 'WHERE h.deleted = ""';
	} elseif ($leaf_type == TREE_ITEM_TYPE_GRAPH) {
		$sql_tables = "FROM host AS h
			LEFT JOIN sites AS s
			ON h.site_id = s.id
			$hsc_join
			LEFT JOIN host_template AS ht
			ON h.host_template_id = ht.id
			LEFT JOIN graph_local AS gl
			ON h.id = gl.host_id
			LEFT JOIN graph_templates AS gt
			ON gl.graph_template_id = gt.id
			LEFT JOIN graph_templates_graph AS gtg
			ON gl.id = gtg.local_graph_id";

		$sql_where = 'WHERE gtg.local_graph_id > 0 AND h.deleted = "" ';
	}

	// form the 'where' clause for our main sql query
	if (grv('filter') != '') {
		$sql_where .= ' AND (
			h.hostname LIKE ' . db_qstr('%' . grv('filter') . '%') . '
			OR h.description LIKE ' . db_qstr('%' . grv('filter') . '%') . '
			OR h.location LIKE ' . db_qstr('%' . grv('filter') . '%') . '
			OR s.name LIKE ' . db_qstr('%' . grv('filter') . '%') . '
			OR ht.name LIKE ' . db_qstr('%' . grv('filter') . '%') . ')';
	}

	if (db_column_exists('sites', 'disabled')) {
		$host_where_disabled = "(IFNULL(TRIM(s.disabled),'') = 'on' || IFNULL(TRIM(h.disabled),'') = 'on')";
	} else {
		$host_where_disabled = "(IFNULL(TRIM(h.disabled),'') = 'on')";
	}

	$host_where_status = grv('status');

	if ($host_where_status == '-1') {
		// Show all items
	} elseif ($host_where_status == '-2') {
		$sql_where .= " AND $host_where_disabled";
	} elseif ($host_where_status == '-3') {
		$sql_where .= " AND NOT $host_where_disabled";
	} elseif ($host_where_status == '-4') {
		$sql_where .= " AND (h.status!='3' OR $host_where_disabled";
	} else {
		$sql_where .= " AND (h.status=$host_where_status AND NOT $host_where_disabled)";
	}

	if (grv('host_template_id') == '-1') {
		// Show all items
	} elseif (grv('host_template_id') == '0') {
		$sql_where .= ' AND h.host_template_id=0';
	} elseif (!ierv('host_template_id')) {
		$sql_where .= ' AND h.host_template_id=' . (int) grv('host_template_id');
	}

	// get the WHERE clause for matching hosts
	$sql_filter = build_matching_objects_filter($rule_id, AUTOMATION_RULE_TYPE_TREE_MATCH);

	$templates = [];

	if (api_automation_column_exists($item['field'], ['host', 'host_template', 'graph_local', 'graph_templates_graph', 'graph_templates'])) {
		$sql_field = $item['field'] . ' AS source ';
	} else {
		$sql_field = '"SQL Injection" AS source ';
		cacti_log("Attempted SQL Injection found in Tree Automation for the field variable {$item['field']}.", false, 'AUTOM8');
		raise_message('sql_injection', __("Attempted SQL Injection found in Tree Automation for the field variable {$item['field']}."), MESSAGE_LEVEL_ERROR);
	}

	// now we build up a new query for counting the rows
	if (db_column_exists('sites', 'disabled')) {
		$sdisabled = 's.disabled AS site_disabled,';
	} else {
		$sdisabled = "'' AS site_disabled,";
	}

	$rows_query = "SELECT DISTINCT h.id AS host_id, h.hostname, h.description,
		h.disabled AS disabled, $sdisabled
		h.status, ht.name AS host_template_name, $sql_field
		$sql_tables
		$sql_where AND ($sql_filter)";

	$total_rows = cacti_sizeof(db_fetch_assoc($rows_query, false));

	$sort_column    = api_automation_column_exists(grv('sort_column'), ['host', 'graph_local', 'sites', 'graph_templates', 'graph_templates_graph', 'host_template']) ? grv('sort_column') : 'description';
	$sort_direction = in_array(strtoupper((string) grv('sort_direction')), ['ASC', 'DESC'], true) ? strtoupper((string) grv('sort_direction')) : 'ASC';
	$sortby         = str_ends_with($sort_column, 'hostname') ? 'INET_ATON(' . $sort_column . ')' : $sort_column;

	$sql_query = "$rows_query ORDER BY $sortby " .
		$sort_direction . ' LIMIT ' .
		($rows * (grv('page') - 1)) . ',' . $rows;

	$templates = db_fetch_assoc($sql_query, false);

	cacti_log($function . ' templates sql: ' . str_replace("\n",' ', $sql_query), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_DEBUG);

	$nav = html_nav_bar($url, MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 8, __('Devices'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	$display_text = [
		'description' => [
			'display' => __('Description'),
			'sort'    => 'ASC'
		],
		'hostname' => [
			'display' => __('Hostname'),
			'sort'    => 'ASC'
		],
		'host_template_name' => [
			'display' => __('Device Template Name'),
			'sort'    => 'ASC'
		],
		'status' => [
			'display' => __('Status'),
			'sort'    => 'ASC'
		],
		'source' => [
			'display' => $item['field'],
			'sort'    => 'ASC'
		],
		'result' => [
			'display' => __('Resulting Branch'),
			'sort'    => 'ASC'
		],
	];

	html_header_sort($display_text, grv('sort_column'), grv('sort_direction'), 1, $url);

	if (cacti_sizeof($templates)) {
		foreach ($templates as $template) {
			cacti_log($function . ' template: ' . json_encode($template), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

			$replacement = automation_string_replace($item['search_pattern'], $item['replace_pattern'], $template['source']);

			$repl = '';

			for ($j = 0; cacti_sizeof($replacement); $j++) {
				if ($j > 0) {
					$repl .= '<br>';
					$repl .= str_pad('', $j * 3, '-') . '&nbsp;' . array_shift($replacement);
				} else {
					$repl  = array_shift($replacement);
				}
			}

			cacti_log($function . " replacement: $repl", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

			form_alternate_row('line' . $template['host_id'], true);

			form_selectable_cell(filter_value($template['description'], grv('filter'), 'host.php?action=edit&id=' . $template['host_id']), $template['host_id']);
			form_selectable_cell(filter_value($template['hostname'], grv('filter')), $template['host_id']);
			form_selectable_cell(filter_value($template['host_template_name'], grv('filter')), $template['host_id']);
			form_selectable_cell(get_colored_device_status(($template['disabled'] == 'on' ? true : false), $template['status']), $template['host_id']);
			form_selectable_ecell($template['source'], $template['host_id']);
			form_selectable_cell($repl, $template['host_id']);

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="' . (cacti_sizeof($display_text)) . '"><em>' . __('No Items Found') . '</em></td></tr>';
	}

	html_end_box(true);

	if (cacti_sizeof($templates)) {
		print $nav;
	}
}

/**
 * Checks if a specified column exists in the given tables.
 *
 * @param  string $column The name of the column to check for.
 * @param  array  $tables An array of table names to search for the column.
 * @return bool   Returns true if the column exists in any of the tables, false otherwise.
 */
function api_automation_column_exists(string $column, array $tables) : bool {
	$column = str_replace(['h.', 'ht.', 'gt.', 'gl.', 'gtg.'], ['', '', '', '', ''], $column);

	if (cacti_sizeof($tables)) {
		foreach ($tables as $table) {
			if (db_column_exists($table, $column)) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Displays match rule items.
 *
 * @param string $title     The title to display.
 * @param array  $rule      The rule to be matched.
 * @param int    $rule_type The type of the rule.
 * @param string $module    The module associated with the rule.
 *
 * @return void
 */
function display_match_rule_items(string $title, array $rule, int $rule_type, string $module) : void {
	global $automation_op_array, $automation_oper, $automation_tree_header_types;

	$rule_id = $rule['id'];

	$items = db_fetch_assoc_prepared('SELECT *
		FROM automation_match_rule_items AS mri
		WHERE rule_id = ?
		AND rule_type = ?
		ORDER BY sequence',
		[$rule_id, $rule_type]);

	html_start_box($title . '&nbsp;<i id="show_device_sql" title="' . __esc('Show Matching Device SQL Query') . '" class="cactiTooltipHint ti ti-stethoscope" style="cursor:pointer"></i>', '100%', false, 3, 'center', $module . '?action=item_edit&id=' . $rule_id . '&rule_type=' . $rule_type);

	$display_text = [
		[
			'display' => __('Item'),
			'align'   => 'left'
		],
		[
			'display' => __('Sequence'),
			'align'   => 'left'
		],
		[
			'display' => __('Operation'),
			'align'   => 'left'
		],
		[
			'display' => __('Field Name'),
			'align'   => 'left'
		],
		[
			'display' => __('Operator'),
			'align'   => 'left'
		],
		[
			'display' => __('Matching Pattern'),
			'align'   => 'left'
		],
		[
			'display' => __('Actions'),
			'align'   => 'right'
		]
	];

	html_header($display_text, 2);

	$i = 0;

	if (cacti_sizeof($items)) {
		foreach ($items as $item) {
			$operation = ($item['operation'] != 0) ? $automation_oper[$item['operation']] : '&nbsp;';

			form_alternate_row();

			$url = $module . '?action=item_edit&id=' . $rule_id . '&item_id=' . $item['id'] . '&rule_type=' . $rule_type;

			form_selectable_cell(filter_value(__('Item # %d', $i + 1), '', $url), $i);
			form_selectable_cell($item['sequence'], $i);
			form_selectable_cell($operation, $i);
			form_selectable_ecell($item['field'], $i);

			if (isset($item['operator']) && $item['operator'] > 0) {
				form_selectable_cell($automation_op_array['display'][$item['operator']], $i);
			} else {
				form_selectable_cell('', $i);
			}

			form_selectable_ecell($item['pattern'], $i);

			$form_data = '';

			if ($i != cacti_sizeof($items) - 1) {
				$url = $module . '?action=item_movedown&item_id=' . $item['id'] . '&id=' . $rule_id . '&rule_type=' . $rule_type;

				$form_data .= '<a class="pic ti ti-caret-down-filled moveArrow"
					href="' . htmle($url) . '" title="' . __esc('Move Down') . '"></a>';
			} else {
				$form_data .= '<span class="moveArrowNone"></span>';
			}

			if ($i > 0) {
				$url = $module . '?action=item_moveup&item_id=' . $item['id'] . '&id=' . $rule_id . '&rule_type=' . $rule_type;

				$form_data .= '<a class="pic ti ti-caret-up-filled moveArrow"
					href="' . htmle($url) . '" title="' . __esc('Move Up') . '"></a>';
			} else {
				$form_data .= '<span class="moveArrowNone"></span>';
			}

			form_selectable_cell($form_data, $i, '32px', 'right nowrap');

			$url = $module . '?action=item_remove&item_id=' . $item['id'] . '&id=' . $rule_id . '&rule_type=' . $rule_type;

			$form_data = '<a class="pid deleteMarker ti ti-x"
				href="' . htmle($url) . '" title="' . __esc('Delete') . '"></a>';

			form_selectable_cell($form_data, $i, '16px', 'right nowrap');

			form_end_row();

			$i++;
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="8"><em>' . __('No Device Selection Criteria') . '</em></td></tr>';
	}

	// sanitize the variables
	draw_tree_devices_filter();

	$details = automation_get_matching_device_sql($rule, $rule_type);
	$data    = db_fetch_assoc($details['rows_query']);

	html_end_box(true);

	print '<div id="sql_device_query" style="display:none"><div style="white-space:pre">' . str_replace(["\n", "\t"], ['<br>', '&nbsp;&nbsp;&nbsp;&nbsp;'], $details['rows_query']) . '</div><br><hr><br><div>' . db_error() . '</div></div>';
}

/**
 * Displays the graph rule items.
 *
 * @param string $title     The title of the graph rule.
 * @param array  $rule      The rule array to be displayed.
 * @param int    $rule_type The type of the rule.
 * @param string $module    The module associated with the rule.
 *
 * @return void
 */
function display_graph_rule_items(string $title, array &$rule, int $rule_type, string $module) : void {
	global $automation_op_array, $automation_oper, $automation_tree_header_types;

	$rule_id = $rule['id'];

	$items = db_fetch_assoc_prepared('SELECT *
		FROM automation_graph_rule_items
		WHERE rule_id = ?
		ORDER BY sequence',
		[$rule_id]);

	html_start_box($title . '&nbsp;<i id="show_sql" title="' . __esc('Show Matching Indexes SQL Query') . '" class="cactiTooltipHint ti ti-stethoscope" style="cursor:pointer"></i>', '100%', false, 3, 'center', $module . '?action=item_edit&id=' . $rule_id . '&rule_type=' . $rule_type);

	$display_text = [
		['display' => __('Item'),      'align' => 'left'],
		['display' => __('Sequence'),  'align' => 'left'],
		['display' => __('Operation'), 'align' => 'left'],
		['display' => __('Field Name'),     'align' => 'left'],
		['display' => __('Operator'),  'align' => 'left'],
		['display' => __('Matching Pattern'),   'align' => 'left'],
		['display' => __('Actions'),   'align' => 'right']
	];

	html_header($display_text, 2);

	$i = 0;

	if (cacti_sizeof($items)) {
		foreach ($items as $item) {
			$operation = ($item['operation'] != 0) ? $automation_oper[$item['operation']] : '&nbsp;';

			form_alternate_row();

			$url = $module . '?action=item_edit&id=' . $rule_id . '&item_id=' . $item['id'] . '&rule_type=' . $rule_type;

			form_selectable_cell(filter_value(__('Item # %d', $i + 1), '', $url), $i);
			form_selectable_cell($item['sequence'], $i);
			form_selectable_cell($operation, $i);
			form_selectable_cell(htmle($item['field']), $i);

			if ($item['operator'] > 0 || $item['operator'] == '') {
				form_selectable_cell($automation_op_array['display'][$item['operator']], $i);
			} else {
				form_selectable_cell('', $i);
			}

			form_selectable_cell(htmle($item['pattern']), $i);

			$form_data = '';

			if ($i != cacti_sizeof($items) - 1) {
				$form_data .= '<a class="pic ti ti-caret-down-filled moveArrow" href="' . htmle($module . '?action=item_movedown&item_id=' . $item['id'] . '&id=' . $rule_id . '&rule_type=' . $rule_type) . '" title="' . __esc('Move Down') . '"></a>';
			} else {
				$form_data .= '<span class="moveArrowNone"></span>';
			}

			if ($i > 0) {
				$form_data .= '<a class="pic ti ti-caret-up-filled moveArrow" href="' . htmle($module . '?action=item_moveup&item_id=' . $item['id'] . '&id=' . $rule_id . '&rule_type=' . $rule_type) . '" title="' . __esc('Move Up') . '"></a>';
			} else {
				$form_data .= '<span class="moveArrowNone"></span>';
			}

			form_selectable_cell($form_data, $i, '32px', 'right nowrap');

			$form_data = '<a class="pic deleteMarker ti ti-x"
				href="' . htmle($module . '?action=item_remove&item_id=' . $item['id'] . '&id=' . $rule_id . '&rule_type=' . $rule_type) . '" title="' . __esc('Delete') . '"></a>';

			form_selectable_cell($form_data, $i, '16px', 'right nowrap');

			form_end_row();

			$i++;
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="8"><em>' . __('No Graph Creation Criteria') . '</em></td></tr>';
	}

	html_end_box(true);

	$details = automation_get_new_graphs_sql($rule);

	if (isset($details['indexes_query']) && $details['indexes_query'] != '') {
		$data = db_fetch_assoc(trim($details['indexes_query']));

		print '<div id="sql_query" style="display:none"><div style="white-space:pre">' . str_replace(["\n"], ['<br>'], $details['indexes_query']) . '</div><br><hr><br><div>' . db_error() . '</div></div>';
	} else {
		print '<div id="sql_query" style="display:none"><div style="white-space:pre">' . __('Warning matching Graph Rule returned no matches') . '</div><br><hr><br><div>' . db_error() . '</div></div>';
	}
}

/**
 * Displays the tree rule items.
 *
 * @param string $title     The title of the tree rule items.
 * @param array  $rule      The rule array containing the tree rule items.
 * @param string $item_type The type of the item.
 * @param int    $rule_type The type of the rule.
 * @param string $module    The module associated with the rule.
 *
 * @return void
 */
function display_tree_rule_items(string $title, array $rule, string $item_type, int $rule_type, string $module) : void {
	global $automation_tree_header_types, $tree_sort_types, $host_group_types;

	$rule_id = $rule['id'];

	$items = db_fetch_assoc_prepared('SELECT *
		FROM automation_tree_rule_items
		WHERE rule_id = ?
		ORDER BY sequence',
		[$rule_id]);

	html_start_box($title, '100%', false, 3, 'center', $module . '?action=item_edit&id=' . $rule_id . '&rule_type=' . $rule_type);

	$display_text = [
		['display' => __('Item'),             'align' => 'left'],
		['display' => __('Sequence'),         'align' => 'left'],
		['display' => __('Field Name'),       'align' => 'left'],
		['display' => __('Sorting Type'),     'align' => 'left'],
		['display' => __('Propagate Change'), 'align' => 'left'],
		['display' => __('Matching Pattern'),   'align' => 'left'],
		['display' => __('Replacement Pattern'),  'align' => 'left'],
		['display' => __('Actions'),          'align' => 'right']
	];

	html_header($display_text, 2);

	$i = 0;

	if (cacti_sizeof($items)) {
		foreach ($items as $item) {
			if ($item['field'] === AUTOMATION_TREE_ITEM_TYPE_STRING) {
				$field_name = $automation_tree_header_types[AUTOMATION_TREE_ITEM_TYPE_STRING];
			} else {
				$field_name = htmle($item['field']);
			}

			form_alternate_row();

			$url = $module . '?action=item_edit&tab=rule&id=' . $rule_id . '&item_id=' . $item['id'] . '&rule_type=' . $rule_type;

			form_selectable_cell(filter_value(__('Item # %d', $i + 1), '', $url), $i);
			form_selectable_cell($item['sequence'], $i);
			form_selectable_cell($field_name, $i);
			form_selectable_cell($tree_sort_types[$item['sort_type']], $i);
			form_selectable_cell(($item['propagate_changes'] ? __('Yes') : __('No')), $i);
			form_selectable_cell(htmle($item['search_pattern']), $i);
			form_selectable_cell(htmle($item['replace_pattern']), $i);

			$form_data = '';

			if ($i != cacti_sizeof($items) - 1) {
				$url = $module . '?action=item_movedown&item_id=' . $item['id'] . '&id=' . $rule_id . '&rule_type=' . $rule_type;

				$form_data .= '<a class="pic ti ti-caret-down-filled moveArrow" href="' .
					htmle($url) . '" title="' . __esc('Move Down') . '"></a>';
			} else {
				$form_data .= '<span class="moveArrowNone"></span>';
			}

			if ($i > 0) {
				$url = $module . '?action=item_moveup&item_id=' . $item['id'] . '&id=' . $rule_id . '&rule_type=' . $rule_type;

				$form_data .= '<a class="pic ti ti-caret-up-filled moveArrow" href="' .
					htmle($url) . '" title="' . __esc('Move Up') . '"></a>';
			} else {
				$form_data .= '<span class="moveArrowNone" style="width:16px"></span>';
			}

			form_selectable_cell($form_data, $i, '32px', 'right nowrap');

			$url = $module . '?action=item_remove&item_id=' . $item['id'] . '&id=' . $rule_id . '&rule_type=' . $rule_type;

			$form_data = '<a class="pic deleteMarker ti ti-x"
				href="' . htmle($url) . '" title="' . __esc('Delete') . '"></a>';

			form_selectable_cell($form_data, $i, '16px', 'right nowrap');

			form_end_row();

			$i++;
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="8"><em>' . __('No Tree Creation Criteria') . '</em></td></tr>';
	}

	html_end_box(true);
}

/**
 * Duplicates automation graph rules based on the given ID and title.
 *
 * @param int    $_id    The ID of the automation graph rule to duplicate.
 * @param string $_title The title for the duplicated automation graph rule.
 *
 * @return void
 */
function duplicate_automation_graph_rules(int $_id, string $_title) : void {
	global $fields_automation_graph_rules_edit1, $fields_automation_graph_rules_edit2, $fields_automation_graph_rules_edit3;

	$rule = db_fetch_row_prepared('SELECT *
		FROM automation_graph_rules
		WHERE id = ?',
		[$_id]);

	$match_items = db_fetch_assoc_prepared('SELECT *
		FROM automation_match_rule_items
		WHERE rule_id = ?
		AND rule_type = ?',
		[$_id, AUTOMATION_RULE_TYPE_GRAPH_MATCH]);

	$rule_items  = db_fetch_assoc_prepared('SELECT *
		FROM automation_graph_rule_items
		WHERE rule_id = ?',
		[$_id]);

	$fields_automation_graph_rules_edit = $fields_automation_graph_rules_edit1 +
		$fields_automation_graph_rules_edit2 + $fields_automation_graph_rules_edit3;

	$save = [];

	if (cacti_sizeof($rule)) {
		foreach ($fields_automation_graph_rules_edit as $field => $array) {
			if (!preg_match('/^hidden/', $array['method'])) {
				$save[$field] = $rule[$field];
			}
		}
	}

	// substitute the title variable
	$save['name'] = str_replace('<rule_name>', $rule['name'], $_title);

	// create new rule
	$save['enabled'] = '';	// no new rule accidentally taking action immediately
	$save['id']      = 0;
	$rule_id         = sql_save($save, 'automation_graph_rules');

	// create new match items
	if (cacti_sizeof($match_items) > 0) {
		foreach ($match_items as $match_item) {
			$save            = $match_item;
			$save['id']      = 0;
			$save['rule_id'] = $rule_id;
			$match_item_id   = sql_save($save, 'automation_match_rule_items');
		}
	}

	// create new rule items
	if (cacti_sizeof($rule_items) > 0) {
		foreach ($rule_items as $rule_item) {
			$save            = $rule_item;
			$save['id']      = 0;
			$save['rule_id'] = $rule_id;
			$rule_item_id    = sql_save($save, 'automation_graph_rule_items');
		}
	}
}

/**
 * Duplicates an automation tree rule and its associated match and action rule items.
 *
 * @param int    $_id    The ID of the existing automation tree rule to duplicate.
 * @param string $_title The title for the new duplicated rule, with '<rule_name>' placeholder replaced by the original rule's name.
 *
 * @return void
 */
function duplicate_automation_tree_rules(int $_id, string $_title) : void {
	global $fields_automation_tree_rules_edit1, $fields_automation_tree_rules_edit2, $fields_automation_tree_rules_edit3;

	$rule = db_fetch_row_prepared('SELECT *
		FROM automation_tree_rules
		WHERE id = ?',
		[$_id]);

	$match_items = db_fetch_assoc_prepared('SELECT *
		FROM automation_match_rule_items
		WHERE rule_id = ?
		AND rule_type = ?',
		[$_id, AUTOMATION_RULE_TYPE_TREE_MATCH]);

	$rule_items = db_fetch_assoc_prepared('SELECT *
		FROM automation_tree_rule_items
		WHERE rule_id = ?',
		[$_id]);

	$fields_automation_tree_rules_edit = $fields_automation_tree_rules_edit1 +
		$fields_automation_tree_rules_edit2 + $fields_automation_tree_rules_edit3;

	$save = [];

	if (cacti_sizeof($rule)) {
		foreach ($fields_automation_tree_rules_edit as $field => $array) {
			if (!preg_match('/^hidden/', $array['method'])) {
				$save[$field] = $rule[$field];
			}
		}
	}

	// substitute the title variable
	$save['name'] = str_replace('<rule_name>', $rule['name'], $_title);

	// create new rule
	$save['enabled'] = '';	// no new rule accidentally taking action immediately
	$save['id']      = 0;
	$rule_id         = sql_save($save, 'automation_tree_rules');

	// create new match items
	if (cacti_sizeof($match_items) > 0) {
		foreach ($match_items as $rule_item) {
			$save            = $rule_item;
			$save['id']      = 0;
			$save['rule_id'] = $rule_id;
			$rule_item_id    = sql_save($save, 'automation_match_rule_items');
		}
	}

	// create new action rule items
	if (cacti_sizeof($rule_items) > 0) {
		foreach ($rule_items as $rule_item) {
			$save = $rule_item;
			// make sure, that regexp is correctly masked
			$save['search_pattern']  = form_input_validate($rule_item['search_pattern'], 'search_pattern', '', false, 3);
			$save['replace_pattern'] = form_input_validate($rule_item['replace_pattern'], 'replace_pattern', '', true, 3);
			$save['id']              = 0;
			$save['rule_id']         = $rule_id;
			$rule_item_id            = sql_save($save, 'automation_tree_rule_items');
		}
	}
}

/**
 * Builds the SQL HAVING clause for a graph object based on the provided rule and filter.
 *
 * @param array  $rule   An associative array containing the rule details, including 'snmp_query_id'.
 * @param string $filter The filter string to be used in the LIKE clause. If null or empty, no HAVING clause is generated.
 *
 * @return string The generated SQL HAVING clause if the filter is not empty, otherwise null.
 */
function build_graph_object_sql_having(array $rule, string $filter) : string {
	if ($filter != '') {
		$field_names = get_field_names($rule['snmp_query_id']);
		$sql_having  = '';

		if (cacti_sizeof($field_names)) {
			$sql_having = ' HAVING (';

			$i = 0;

			foreach ($field_names as $column) {
				$sql_having .= ($i == 0 ? '' : ' OR ') . '`' . implode('`.`', explode('.', $column['field_name'])) . '`' . ' LIKE ' . db_qstr('%' . $filter . '%');
				$i++;
			}

			$sql_having .= ')';
		}

		return $sql_having;
	}

	return '';
}

/**
 * Builds a SQL query string for retrieving data based on the provided rule.
 *
 * @param array $rule An associative array containing the rule parameters.
 *
 * @return string The constructed SQL query string.
 */
function build_data_query_sql(array $rule) : string {
	$function = automation_function_with_pid(__FUNCTION__);
	cacti_log($function . ' called: ' . json_encode($rule), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	// build magic query, for matching hosts JOIN tables host and host_template
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

	// take matching hosts into account
	$sql_where = build_matching_objects_filter($rule['id'], AUTOMATION_RULE_TYPE_GRAPH_MATCH);

	// build magic query, for matching hosts JOIN tables host and host_template
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

/**
 * Builds a SQL filter string based on the provided rule ID and rule type.
 *
 * @param int $rule_id   The ID of the rule to build the filter for.
 * @param int $rule_type The type of the rule to build the filter for.
 *
 * @return string - The constructed SQL filter string.
 */
function build_matching_objects_filter(int $rule_id, int $rule_type) : string {
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
		[$rule_id, $rule_type]);

	if (cacti_sizeof($rule_items)) {
		$sql_filter	 = build_rule_item_filter($rule_items);
	} else {
		// force empty result set if no host matching rule item present
		$sql_filter = ' (1 != 1)';
	}

	cacti_log($function . ' returns: ' . $sql_filter, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	return trim($sql_filter);
}

/**
 * Builds a filter string for automation rule items.
 *
 * @param array  $automation_rule_items An array of automation rule items to be filtered.
 * @param string $prefix                An optional prefix to be added to the filter string.
 *
 * @return string The constructed filter string.
 */
function build_rule_item_filter(array $automation_rule_items, string $prefix = '') : string {
	global $automation_op_array, $automation_oper;

	$function = automation_function_with_pid(__FUNCTION__);

	cacti_log($function . ' called: ' . json_encode($automation_rule_items) . ", prefix: $prefix", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	$sql_filter = '';
	$indent     = 1;

	if (cacti_sizeof($automation_rule_items)) {
		$sql_filter = ' ';

		foreach ($automation_rule_items as $automation_rule_item) {
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

			// right bracket ')' does not come with a field
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

/**
 * Builds and returns a sort order based on the provided index order.
 *
 * @param string $index_order   The index order to build the sort order from.
 * @param string $default_order The default order to use if no index order is provided.
 *
 * @return string - The built sort order
 */
function build_sort_order(string $index_order, string $default_order = '') : string {
	$function = automation_function_with_pid(__FUNCTION__);

	cacti_log($function . " called: $index_order/$default_order", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	$sql_order = $default_order;

	// determine the sort order
	if ($index_order == 'numeric') {
		$sql_order .= ', CAST(snmp_index AS unsigned)';
	} elseif ($index_order == 'alphabetic') {
		$sql_order .= ', snmp_index';
	} elseif ($index_order == 'natural') {
		$sql_order .= ', INET_ATON(snmp_index)';
	}

	// if ANY order is requested
	if ($sql_order != '') {
		$sql_order = "\n\tORDER BY " . $sql_order;
	}

	cacti_log($function . " returns: $sql_order", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	return $sql_order;
}

/**
 * Retrieves hosts that match the given rule and rule type.
 *
 * @param array  $rule      The rule to match hosts against.
 * @param int    $rule_type The type of rule to apply.
 * @param string $sql_where Optional SQL WHERE clause to filter the results.
 *
 * @return array|bool - The list of matching hosts.
 */
function get_matching_hosts(array $rule, int $rule_type, string $sql_where = '') : array|bool {
	$function = automation_function_with_pid(__FUNCTION__);

	cacti_log($function . ' called: ' . json_encode($rule) . ' type: ' . $rule_type, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	// build magic query, for matching hosts JOIN tables host and host_template
	if (db_column_exists('sites', 'disabled')) {
		$sdisabled = 's.disabled AS site_disabled,';
	} else {
		$sdisabled = "'' AS site_disabled,";
	}

	// data query objects sql
	$hsc_sql = make_host_snnp_cache_sql();

	if ($hsc_sql !== false) {
		$hsc_join = "LEFT JOIN ($hsc_sql) AS hsc
			ON hsc.host_id = h.id";
	} else {
		$hsc_join = '';
	}

	$sql_query = "SELECT h.id AS host_id, h.hostname, h.description,
		h.disabled AS disabled, $sdisabled
		h.status, ht.name AS host_template_name
		FROM host AS h
		LEFT JOIN sites AS s
		ON h.site_id = s.id
		$hsc_join
		LEFT JOIN host_template AS ht
		ON h.host_template_id = ht.id ";

	// get the WHERE clause for matching hosts
	$sql_filter = ' WHERE h.deleted = "" AND (' . build_matching_objects_filter($rule['id'], $rule_type) . ')';

	if ($sql_where != '') {
		$sql_filter .= ' AND ' . $sql_where;
	}

	$results = db_fetch_assoc($sql_query . $sql_filter, false);

	cacti_log($function . ' returning: ' . str_replace("\n", '', $sql_query . $sql_filter) . ' matches: ' . cacti_sizeof($results), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	return $results;
}

/**
 * Retrieves graphs that match the specified rule and rule type.
 *
 * @param array  $rule      The rule to match graphs against.
 * @param int    $rule_type The type of rule to apply.
 * @param string $sql_where Optional SQL WHERE clause to further filter the results.
 *
 * @return array|bool The list of matching graphs.
 */
function get_matching_graphs(array $rule, int $rule_type, string $sql_where = '') : array|bool {
	$function = automation_function_with_pid(__FUNCTION__);

	cacti_log($function . ' called: ' . json_encode($rule) . ' type: ' . $rule_type, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	if (db_column_exists('sites', 'disabled')) {
		$sdisabled = 's.disabled AS site_disabled,';
	} else {
		$sdisabled = "'' AS site_disabled,";
	}

	// data query objects sql
	$hsc_sql = make_host_snnp_cache_sql();

	if ($hsc_sql !== false) {
		$hsc_join = "LEFT JOIN ($hsc_sql) AS hsc
			ON hsc.host_id = h.id";
	} else {
		$hsc_join = '';
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
		$hsc_join
		LEFT JOIN host_template AS ht
		ON h.host_template_id = ht.id";

	// get the WHERE clause for matching graphs
	$sql_filter = 'WHERE gl.id=gtg.local_graph_id AND ' . build_matching_objects_filter($rule['id'], $rule_type);

	if ($sql_where != '') {
		$sql_filter .= ' AND ' . $sql_where;
	}

	$results = db_fetch_assoc($sql_query . $sql_filter, false);

	cacti_log($function . ' returning: ' . str_replace("\n", '', $sql_query . $sql_filter) . ' matches: ' . cacti_sizeof($results), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	return $results;
}

/**
 * Retrieves the graphs created based on the specified rule.
 *
 * @param array $rule An associative array containing the criteria for selecting the created graphs.
 *
 * @return array An array of graphs that match the specified rule.
 */
function get_created_graphs(array $rule) : array {
	$function = automation_function_with_pid(__FUNCTION__);

	cacti_log($function . ' called: ' . json_encode($rule), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	$sql = 'SELECT sqg.id
		FROM snmp_query_graph AS sqg
		WHERE sqg.snmp_query_id=' . $rule['snmp_query_id'] . '
		AND sqg.id=' . $rule['graph_type_id'];

	$snmp_query_graph_id = db_fetch_cell($sql);

	// take matching hosts into account
	$sql_where = build_matching_objects_filter($rule['id'], AUTOMATION_RULE_TYPE_GRAPH_MATCH);

	// build magic query, for matching hosts JOIN tables host and host_template
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

	// rearrange items to ease indexed access
	$items = [];

	if (cacti_sizeof($graphs)) {
		foreach ($graphs as $graph) {
			$items[$graph['host_id']][$graph['snmp_index']] = $graph['snmp_index'];
		}
	}

	cacti_log($function . ' returns: ' . cacti_sizeof($items), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	return $items;
}

/**
 * Generates the SQL query for creating the SNMP cache for a host.
 *
 * @return string|false The SQL query string if successful, or false on failure.
 */
function make_host_snnp_cache_sql() : string|false {
	$fields = db_fetch_assoc('SELECT DISTINCT field_name FROM host_snmp_cache ORDER BY field_name');

	if (cacti_sizeof($fields)) {
		$sql = "\t\tSELECT host_id ";

		foreach ($fields as $field) {
			$sql .= ",\n\t\t\tMAX(CASE WHEN field_name = '{$field['field_name']}' THEN field_value ELSE NULL END) AS `{$field['field_name']}`";
		}

		$sql .= "\n\t\t\tFROM host_snmp_cache AS hsc GROUP BY host_id";

		return $sql;
	} else {
		return false;
	}
}

/**
 * Retrieves the fields of a given table, excluding specified fields.
 *
 * @param string $table           The name of the table to retrieve fields from.
 * @param array  $excluded_fields An array of field names to exclude from the result.
 *
 * @return array An array of field names from the table, excluding the specified fields.
 */
function get_query_fields(string $table, array $excluded_fields) : array {
	$function = automation_function_with_pid(__FUNCTION__);
	cacti_log($function . ' called', false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	$table = trim($table);

	$new_fields = [];

	if ($table != 'host_snmp_cache') {
		$sql    = 'SHOW COLUMNS FROM ' . $table;
		$fields = array_rekey(db_fetch_assoc($sql), 'Field', 'Type');

		// remove unwanted entries
		$fields = array_minus($fields, $excluded_fields);
	} else {
		$fields = array_rekey(
			db_fetch_assoc('SELECT DISTINCT field_name FROM host_snmp_cache ORDER BY field_name'),
			'field_name', 'field_name'
		);
	}

	// now reformat entries for use with draw_edit_form
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
				case 'sites':
					$table = 's';

					break;
				case 'host_snmp_cache':
					$table = 'hsc';

					break;
			}

			// we want to know later which table was selected
			$new_key = $table . '.' . $key;
			// give the user a hint about the data type of the column
			$new_fields[$new_key] = cacti_strtoupper($table) . ': ' . $key . ' - ' . $value;
		}
	}

	cacti_log($function . ' returns: ' . cacti_sizeof($new_fields), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	return $new_fields;
}

/**
 * Retrieves the field names associated with a given SNMP query ID.
 *
 * @param string $snmp_query_id The ID of the SNMP query for which to retrieve field names.
 *
 * @return array|bool An array of field names associated with the specified SNMP query ID.
 */
function get_field_names(string $snmp_query_id) : array|bool {
	$function = automation_function_with_pid(__FUNCTION__);
	cacti_log($function . " called: $snmp_query_id", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	// get the unique field values from the database
	$sql    = 'SELECT DISTINCT field_name FROM host_snmp_cache WHERE snmp_query_id=' . $snmp_query_id;
	$fields = db_fetch_assoc($sql);

	cacti_log($function . ' returns: ' . cacti_sizeof($fields), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	return $fields;
}

function array_to_list(array $array, string $sql_column) : string {
	$function = automation_function_with_pid(__FUNCTION__);

	// if the last item is null; pop it off
	$counter = cacti_count($array);

	if (empty($array[$counter - 1]) && $counter > 1) {
		array_pop($array);
		$counter = cacti_count($array);
	}

	if ($counter > 0) {
		$sql = '(';

		for ($i = 0; $i < $counter; $i++) {
			$sql .= $array[$i][$sql_column];

			if ($i + 1 < $counter) {
				$sql .= ',';
			}
		}

		$sql .= ')';

		cacti_log($function . "() returns: $sql", false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

		return $sql;
	}

	return '';
}

/**
 * Subtracts the elements of one array from another.
 *
 * @param array $big_array   The array from which elements will be subtracted.
 * @param array $small_array The array containing elements to subtract from the first array.
 *
 * @return array The resulting array after subtraction.
 */
function array_minus(array $big_array, array $small_array) : array {
	// remove all unwanted fields
	if (cacti_sizeof($small_array)) {
		foreach ($small_array as $exclude) {
			if (array_key_exists($exclude, $big_array)) {
				unset($big_array[$exclude]);
			}
		}
	}

	return $big_array;
}

/**
 * Replaces all occurrences of the search string with the replacement string in the target string.
 *
 * @param string       $search  The value being searched for.
 * @param array|string $replace The replacement value that replaces found search values.
 * @param string       $target  The string being searched and replaced on.
 *
 * @return list<string> The resulting string after the replacements have been made.
 */
function automation_string_replace(string $search, array|string $replace, string $target) : array {
	$repl = preg_replace('/' . $search . '/i', $replace, $target);

	return preg_split('/\\\\n/', $repl ?? '', -1, PREG_SPLIT_NO_EMPTY) ?: [];
}

/**
 * Edits a global item based on the provided rule ID, rule item ID, and rule type.
 *
 * @param int $rule_id      The ID of the rule to be edited.
 * @param int $rule_item_id The ID of the rule item to be edited.
 * @param int $rule_type    The type of the rule to be edited.
 *
 * @return void
 */
function global_item_edit(int $rule_id, int $rule_item_id, int $rule_type) : void {
	global $fields_automation_match_rule_item_edit, $fields_automation_graph_rule_item_edit;
	global $fields_automation_tree_rule_item_edit, $automation_tree_header_types;
	global $automation_op_array;

	$automation_item = [];
	$automation_rule = [];
	$module          = '';
	$sql_and         = '';
	$item_table      = '';
	$title           = '';

	switch ($rule_type) {
		case AUTOMATION_RULE_TYPE_GRAPH_MATCH: // Graph Rules - Device Selection Criteria > Edit
			$title      = __('Device Match Rule');
			$item_table = 'automation_match_rule_items';
			$sql_and    = ' AND rule_type=' . $rule_type;
			$tables     = ['host', 'host_templates'];

			$automation_rule = db_fetch_row_prepared('SELECT *
				FROM automation_graph_rules
				WHERE id = ?',
				[$rule_id]);

			$_fields_rule_item_edit = $fields_automation_match_rule_item_edit;

			$query_fields  = get_query_fields('host_template', ['id', 'hash']);
			$query_fields += get_query_fields('host', ['id', 'host_template_id']);
			$query_fields += get_query_fields('sites', ['id']);
			$query_fields += get_query_fields('host_snmp_cache', ['host_id', 'snmp_query_id', 'oid', 'present', 'last_updated', 'snmp_index']);

			$_fields_rule_item_edit['field']['array'] = $query_fields;

			$module = 'automation_graph_rules.php';

			break;
		case AUTOMATION_RULE_TYPE_GRAPH_ACTION: // Graph Rules - Graph Creation Criterial > Edit
			$title      = __('Create Graph Rule');
			$tables     = [AUTOMATION_RULE_TABLE_XML];
			$item_table = 'automation_graph_rule_items';
			$sql_and    = '';

			$automation_rule = db_fetch_row_prepared('SELECT *
				FROM automation_graph_rules
				WHERE id = ?',
				[$rule_id]);

			$_fields_rule_item_edit = $fields_automation_graph_rule_item_edit;

			$xml_array = is_array($automation_rule) ? get_data_query_array($automation_rule['snmp_query_id']) : [];
			$fields    = [];

			if (cacti_sizeof($xml_array) && cacti_sizeof($xml_array['fields'])) {
				foreach ($xml_array['fields'] as $key => $value) {
					// ... work on all input fields
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
				[$rule_id]);

			$_fields_rule_item_edit = $fields_automation_match_rule_item_edit;

			$query_fields  = get_query_fields('host_template', ['id', 'hash']);
			$query_fields += get_query_fields('host', ['id', 'host_template_id']);
			$query_fields += get_query_fields('sites', ['id']);
			$query_fields += get_query_fields('host_snmp_cache', ['host_id', 'snmp_query_id', 'oid', 'present', 'last_updated', 'snmp_index']);

			if (is_array($automation_rule) && $automation_rule['leaf_type'] == TREE_ITEM_TYPE_HOST) {
				$title  = __('Device Match Rule');
				$tables = ['host', 'host_templates'];
			} elseif (is_array($automation_rule) && $automation_rule['leaf_type'] == TREE_ITEM_TYPE_GRAPH) {
				$title  = __('Graph Match Rule');
				$tables = ['host', 'host_templates'];

				// add some more filter columns for a GRAPH match
				$query_fields += get_query_fields('graph_templates', ['id', 'hash']);
				$query_fields += ['gtg.title' => 'GTG: title - varchar(255)'];
				$query_fields += ['gtg.title_cache' => 'GTG: title_cache - varchar(255)'];
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
				[$rule_id]);

			$_fields_rule_item_edit = $fields_automation_tree_rule_item_edit;

			$query_fields  = get_query_fields('host_template', ['id', 'hash']);
			$query_fields += get_query_fields('host', ['id', 'host_template_id']);
			$query_fields += get_query_fields('sites', ['id']);
			$query_fields += get_query_fields('host_snmp_cache', ['host_id', 'snmp_query_id', 'oid', 'present', 'last_updated', 'snmp_index']);

			/* list of allowed header types depends on rule leaf_type
			 * e.g. for a Device Rule, only Device-related header types make sense
			 */
			if (is_array($automation_rule) && $automation_rule['leaf_type'] == TREE_ITEM_TYPE_HOST) {
				$title  = __('Create Tree Rule (Device)');
				$tables = ['host', 'host_templates'];
			} elseif (is_array($automation_rule) && $automation_rule['leaf_type'] == TREE_ITEM_TYPE_GRAPH) {
				$title  = __('Create Tree Rule (Graph)');
				$tables = ['host', 'host_templates'];

				// add some more filter columns for a GRAPH match
				$query_fields += get_query_fields('graph_templates', ['id', 'hash']);
				$query_fields += ['gtg.title' => 'GTG: title - varchar(255)'];
				$query_fields += ['gtg.title_cache' => 'GTG: title_cache - varchar(255)'];
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
			[$rule_item_id]);

		if (cacti_sizeof($automation_item)) {
			$missing_key = $automation_item['field'];

			if (empty($missing_key)) {
				// Fixed String
			} elseif (isset($_fields_rule_item_edit) && !array_key_exists($missing_key, $_fields_rule_item_edit['field']['array'])) {
				$missing_array = explode('.',$missing_key);

				if (cacti_sizeof($missing_array) > 1) {
					$missing_table = cacti_strtoupper($missing_array[0]);
					$missing_value = cacti_strtolower($missing_array[1]);
				} else {
					$missing_table = '';
					$missing_value = cacti_strtolower($missing_array[0]);
				}

				$_fields_rule_item_edit['field']['array'] = array_merge(
					[$automation_item['field'] => 'Unknown: ' . $missing_table . ': ' . $missing_value],
					$_fields_rule_item_edit['field']['array']
				);
			}
		}

		$header_label = __esc('Rule Item [edit rule item for %s: %s]', $title, is_array($automation_rule) ? $automation_rule['name'] : '');
	} else {
		$header_label = __esc('Rule Item [new rule item for %s: %s]', $title, is_array($automation_rule) ? $automation_rule['name'] : '');

		$automation_item = [];

		$automation_item['sequence'] = get_sequence(0, 'sequence', $item_table, 'rule_id=' . $rule_id . $sql_and);
	}

	form_start($module, 'form_automation_global_item_edit');

	html_start_box($header_label, '100%', true, 3, 'center', '');

	if (isset($_fields_rule_item_edit)) {
		draw_edit_form(
			[
				'config' => ['no_form_tag' => true],
				'fields' => inject_form_variables($_fields_rule_item_edit, $automation_item, $automation_rule)
			]
		);
	}

	html_end_box(true, true);
}

/**
 * Automation hook for applying a graph template to a host.
 *
 * @param int $host_id           The ID of the host to which the graph template will be applied.
 * @param int $graph_template_id The ID of the graph template to be applied to the host.
 *
 * @return void
 */
function automation_hook_graph_template(int $host_id, int $graph_template_id) : void {
	$function = automation_function_with_pid(__FUNCTION__);

	cacti_log($function . ' called: Device[' . $host_id . '], GT[' . $graph_template_id . ']', false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	if (read_config_option('automation_graphs_enabled') == '') {
		cacti_log($function . ' Device[' . $host_id . '] - skipped: Graph Creation Switch is: ' . (read_config_option('automation_graphs_enabled') == '' ? 'off' : 'on'), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

		return;
	}

	automation_execute_graph_template($host_id, $graph_template_id);
}

/**
 * Hook function to create a graph tree.
 *
 * This function is triggered during the automation process to create a graph tree
 * based on the provided data.
 *
 * @param array $data An associative array containing the necessary data to create the graph tree.
 *
 * @return array
 */
function automation_hook_graph_create_tree(array $data) : array {
	$function = automation_function_with_pid(__FUNCTION__);

	cacti_log($function . ' called: ' . json_encode($data), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	if (read_config_option('automation_tree_enabled') == '') {
		cacti_log($function . ' skipped: Tree Creation Switch is: ' . (read_config_option('automation_tree_enabled') == '' ? 'off' : 'on'), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

		return [];
	}

	automation_execute_graph_create_tree($data['id']);

	// make sure, the next plugin gets required $data
	return $data;
}

/**
 * Executes a data query for a given host and SNMP query ID.
 *
 * @param int $host_id       The ID of the host for which the data query is to be executed.
 * @param int $snmp_query_id The ID of the SNMP query to be executed.
 *
 * @return void
 */
function automation_execute_data_query(int $host_id, int $snmp_query_id) : void {
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
		[$host_id]);

	// see if this is a new style automation or legacy
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

	$rules = db_fetch_assoc_prepared($sql, [$snmp_query_id, $host_id]);

	cacti_log($function . ' Device[' . $host_id . '] - sql: ' . str_replace("\t", '', str_replace("\n", ' ', $sql)) . ' - found: ' . cacti_sizeof($rules), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_DEBUG);

	if (!cacti_sizeof($rules)) {
		return;
	}

	// now walk all rules and create graphs
	if (cacti_sizeof($rules)) {
		foreach ($rules as $rule) {
			cacti_log($function . ' Device[' . $host_id . '] - rule=' . $rule['id'] . ' name: ' . $rule['name'], false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

			// build magic query, for matching hosts JOIN tables host and host_template
			$sql_query = 'SELECT h.id AS host_id, h.hostname,
				h.description, ht.name AS host_template_name
				FROM host AS h
				LEFT JOIN host_template AS ht
				ON h.host_template_id = ht.id';

			// get the WHERE clause for matching hosts
			$sql_filter = build_matching_objects_filter($rule['id'], AUTOMATION_RULE_TYPE_GRAPH_MATCH);

			// now we build up a new query for counting the rows
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
 * @param int $graph_template_id
 *
 * @return bool eligibility
 */
function automation_graph_automation_eligible(int $graph_template_id) : bool {
	$graph_template = db_fetch_row_prepared('SELECT *
		FROM graph_templates_graph
		WHERE graph_template_id = ?
		AND local_graph_id = 0',
		[$graph_template_id]);

	// Check the Graph Template first for adherence
	if (cacti_sizeof($graph_template)) {
		foreach ($graph_template as $field => $value) {
			if (str_starts_with($field, 't_')) {
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
		[$graph_template_id]);

	if (cacti_sizeof($data_templates)) {
		foreach ($data_templates as $dtd) {
			foreach ($dtd as $field => $value) {
				if (str_starts_with($field, 't_')) {
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
				[$dtd['id']]);

			if (cacti_sizeof($input_fields)) {
				return false;
			}
		}
	}

	return true;
}

/**
 * Executes a graph template automation for a given host.
 *
 * @param int $host_id           The ID of the host for which the graph template is to be executed.
 * @param int $graph_template_id The ID of the graph template to be executed.
 *
 * @return void
 */
function automation_execute_graph_template(int $host_id, int $graph_template_id) : void {
	include_once(CACTI_PATH_LIBRARY . '/template.php');
	include_once(CACTI_PATH_LIBRARY . '/api_automation_tools.php');
	include_once(CACTI_PATH_LIBRARY . '/utility.php');

	$dataSourceId     = '';
	$returnArray      = [];
	$suggested_values = [];

	$function  = automation_function_with_pid(__FUNCTION__);
	cacti_log($function . ' called: Device[' . $host_id . '] - GT[' . $graph_template_id . ']', false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	// are there any input fields? if so use the default values
	if ($graph_template_id > 0) {
		$input_fields = getInputFields($graph_template_id);

		if (cacti_sizeof($input_fields)) {
			$suggested_vals[$graph_template_id]['custom_data'] = [];

			foreach ($input_fields as $field) {
				$suggested_vals[$graph_template_id]['custom_data'][$field['data_template_id']][$field['data_input_field_id']] = $field['default'];
			}
		}
	}

	// graph already present?
	$existsAlready = db_fetch_cell_prepared('SELECT id
		FROM graph_local
		WHERE graph_template_id = ?
		AND host_id = ?',
		[$graph_template_id, $host_id]);

	if ($existsAlready > 0) {
		$dataSourceId  = db_fetch_cell_prepared('SELECT data_template_rrd.local_data_id
			FROM graph_templates_item, data_template_rrd
			WHERE graph_templates_item.local_graph_id = ?
			AND graph_templates_item.task_item_id = data_template_rrd.id
			LIMIT 1', [$existsAlready]);

		cacti_log('NOTE: ' . $function . ' Device[' . $host_id . "] Graph Creation Skipped - Already Exists - Graph[$existsAlready] - DS[$dataSourceId]", false, 'AUTOM8', POLLER_VERBOSITY_MEDIUM);

		return;
	}

	if (automation_graph_automation_eligible($graph_template_id)) {
		if (test_data_sources($graph_template_id, $host_id)) {
			cacti_log('NOTE: Data Check Succeeded for - Device[' . $host_id . '], GT[' . $graph_template_id . ']', false, 'AUTOM8');

			$returnArray  = create_complete_graph_from_template($graph_template_id, $host_id, [], $suggested_values);

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
 * Executes the automation process to create a tree for a specified device.
 *
 * @param int $host_id The ID of the host device for which the tree is to be created.
 *
 * @return void
 */
function automation_execute_device_create_tree(int $host_id) : void {
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
		[$host_id]);

	$host_template_id = db_fetch_cell_prepared('SELECT host_template_id
		FROM host
		WHERE id = ?',
		[$host_id]);

	if (cacti_sizeof($rules)) {
		$sql = "SELECT atr.id, atr.name, atr.tree_id, atr.tree_item_id,
			atr.leaf_type, atr.host_grouping_type, aatr.exit_rules, aatr.sequence
			FROM automation_tree_rules AS atr
			INNER JOIN automation_templates_rules AS aatr
			ON atr.id = aatr.rule_id
			AND aatr.rule_type = 2
			WHERE enabled = 'on'
			AND leaf_type = " . TREE_ITEM_TYPE_HOST . '
			AND host_template_id = ?
			ORDER BY aatr.sequence';

		$rules = db_fetch_assoc_prepared($sql, [$host_template_id]);
	} else {
		$sql = "SELECT atr.id, atr.name, atr.tree_id, atr.tree_item_id, '0' AS exit_rules, '1' AS sequence,
			atr.leaf_type, atr.host_grouping_type
			FROM automation_tree_rules AS atr
			WHERE enabled='on'
			AND leaf_type=" . TREE_ITEM_TYPE_HOST;

		$rules = db_fetch_assoc($sql);
	}

	cacti_log($function . ' Device[' . $host_id . '], matching rule sql: ' . str_replace("\n", '', $sql) . ' matches: ' . cacti_sizeof($rules), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_DEBUG);

	// now walk all rules
	if (cacti_sizeof($rules)) {
		foreach ($rules as $rule) {
			cacti_log($function . " Device[$host_id], rule: " . $rule['id'] . ' name: ' . $rule['name'] . ' type: ' . $rule['leaf_type'], false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

			/**
			 * does the rule apply to the current host?
			 * test 'eligible objects' rule items
			 */
			$matches = get_matching_hosts($rule, AUTOMATION_RULE_TYPE_TREE_MATCH, 'h.id=' . $host_id);

			cacti_log($function . " Device[$host_id], rule: " . $rule['id'] . ', matching hosts: ' . json_encode($matches), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

			// if the rule produces a match, we will have to create all required tree nodes
			if (cacti_sizeof($matches)) {
				// create the bunch of header nodes
				$parent = create_all_header_nodes($host_id, $rule);

				cacti_log($function . " Device[$host_id], rule: " . $rule['id'] . ', parent: ' . $parent, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

				// now that all rule items have been executed, add the item itself
				$node = create_device_node($host_id, $parent, $rule);

				cacti_log($function . " Device[$host_id], rule: " . $rule['id'] . ', node: ' . $node, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

				// if the rule is setup to exit after the first match, exit
				if ($rule['exit_rules'] == 1) {
					return;
				}
			}
		}
	}
}

/**
 * Executes the automation process to create a graph tree.
 *
 * @param int $graph_id The ID of the graph to be used in the tree creation process.
 *
 * @return void
 */
function automation_execute_graph_create_tree(int $graph_id) : void {
	/* the $data array holds all information about the graph we're just working on
	 * even if we selected multiple graphs, the calling code will scan through the list
	 * so we only have a single graph here
	 */

	$function = automation_function_with_pid(__FUNCTION__);
	cacti_log($function . ' Graph[' . $graph_id . '] called', false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	$host_id = db_fetch_cell_prepared('SELECT host_id
		FROM graph_local
		WHERE id = ?',
		[$graph_id]);

	$host_template_id = db_fetch_cell_prepared('SELECT host_template_id
		FROM host
		WHERE id = ?',
		[$host_id]);

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
		[$host_id]);

	if (cacti_sizeof($rules)) {
		$sql = "SELECT atr.id, atr.name, atr.tree_id, atr.tree_item_id, aatr.exit_rules, aatr.sequence,
			atr.leaf_type, atr.host_grouping_type
			FROM automation_tree_rules AS atr
			INNER JOIN automation_templates_rules AS aatr
			ON atr.id = aatr.rule_id
			AND aatr.rule_type = 2
			WHERE enabled = 'on'
			AND leaf_type = " . TREE_ITEM_TYPE_GRAPH . '
			AND host_template_id = ?
			ORDER BY aatr.sequence';

		$rules = db_fetch_assoc_prepared($sql, [$host_template_id]);
	} else {
		$sql = "SELECT atr.id, atr.name, atr.tree_id, atr.tree_item_id, '0' AS exit_rules, '0' AS sequence,
			atr.leaf_type, atr.host_grouping_type
			FROM automation_tree_rules AS atr
			WHERE enabled='on'
			AND leaf_type=" . TREE_ITEM_TYPE_GRAPH;

		$rules = db_fetch_assoc($sql);
	}

	cacti_log($function . ' Graph[' . $graph_id . '], Matching rule sql: ' . str_replace("\n",' ', $sql) . ' matches: ' . cacti_sizeof($rules), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_DEBUG);

	// now walk all rules
	if (cacti_sizeof($rules)) {
		foreach ($rules as $rule) {
			cacti_log($function . ' Graph[' . $graph_id . '], rule: ' . $rule['id'] . ', name: ' . $rule['name'] . ', type: ' . $rule['leaf_type'], false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

			/* does this rule apply to the current graph?
			 * test 'eligible objects' rule items */
			$matches = get_matching_graphs($rule, AUTOMATION_RULE_TYPE_TREE_MATCH, 'gl.id=' . $graph_id);

			cacti_log($function . ' Graph[' . $graph_id . '], rule: ' . $rule['id'] . ', matching graphs: ' . json_encode($matches), false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

			// if the rule produces a match, we will have to create all required tree nodes
			if (cacti_sizeof($matches)) {
				// create the bunch of header nodes
				$parent = create_all_header_nodes($graph_id, $rule);
				cacti_log($function . ' Graph[' . $graph_id . '], Rule: ' . $rule['id'] . ', Parent: ' . $parent, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

				// now that all rule items have been executed, add the item itself
				$node = create_graph_node($graph_id, $parent, $rule);
				cacti_log($function . ' Graph[' . $graph_id . '], Rule: ' . $rule['id'] . ', Node: ' . $node, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

				// if the rule is setup to exit after the first match, exit
				if ($rule['exit_rules'] == 1) {
					return;
				}
			}
		}
	}
}

/**
 * Creates data query graphs for a specified host and SNMP query.
 *
 * @param int   $host_id       The ID of the host for which the graphs are being created.
 * @param int   $snmp_query_id The ID of the SNMP query to be used.
 * @param array $rule          An associative array containing the rules for graph creation.
 *
 * @return bool
 */
function create_dq_graphs(int $host_id, int $snmp_query_id, array $rule) : bool {
	global $automation_op_array, $automation_oper;

	$function = automation_function_with_pid(__FUNCTION__);
	cacti_log($function . ' Device[' . $host_id . "] - snmp query: $snmp_query_id - rule: " . $rule['name'], false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	$snmp_query_array                        = [];
	$snmp_query_array['snmp_query_id']       = $rule['snmp_query_id'];
	$snmp_query_array['snmp_index_on']       = get_best_data_query_index_type($host_id, $rule['snmp_query_id']);
	$snmp_query_array['snmp_query_graph_id'] = $rule['graph_type_id'];

	// get all rule items
	$automation_rule_items = db_fetch_assoc_prepared('SELECT *
		FROM automation_graph_rule_items AS agri
		WHERE rule_id = ?
		ORDER BY sequence',
		[$rule['id']]);

	$automation_rule_fields = array_rekey(
		db_fetch_assoc_prepared('SELECT field
			FROM automation_graph_rule_items AS agri
			WHERE field != ""
			AND rule_id = ?',
			[$rule['id']]),
		'field', 'field'
	);

	// and all matching snmp_indices from snmp_cache
	$rule_name = db_fetch_cell_prepared('SELECT name
		FROM automation_graph_rules
		WHERE id = ?',
		[$rule['id']]);

	// get the unique field values from the database
	$field_names = array_rekey(
		db_fetch_assoc_prepared('SELECT DISTINCT field_name
			FROM host_snmp_cache AS hsc
			WHERE snmp_query_id= ?
			AND host_id = ?',
			[$snmp_query_id, $host_id]),
		'field_name', 'field_name'
	);

	// build magic query
	$sql_query  = 'SELECT host_id, snmp_query_id, snmp_index';

	// check for possible SQL errors
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

	// fetch snmp indices
	$dq_indexes = db_fetch_assoc($sql_query);

	// now create the graphs
	if (cacti_sizeof($dq_indexes)) {
		$graph_template_id = db_fetch_cell_prepared('SELECT graph_template_id
			FROM snmp_query_graph
			WHERE id = ?',
			[$rule['graph_type_id']]);

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
				[$rule['graph_type_id'], $host_id, $rule['snmp_query_id'], $snmp_query_array['snmp_index']]);

			if (isset($existsAlready) && $existsAlready > 0) {
				cacti_log('NOTE: ' . $function . ' Device[' . $host_id . "] Graph Creation Skipped - Already Exists - Graph[$existsAlready]", false, 'AUTOM8', POLLER_VERBOSITY_HIGH);

				continue;
			}

			$suggested_values = [];

			if (test_data_sources($graph_template_id, $host_id, $rule['snmp_query_id'], $snmp_query_array['snmp_index'])) {
				$return_array = create_complete_graph_from_template($graph_template_id, $host_id, $snmp_query_array, $suggested_values);

				if ($return_array !== false) {
					if (cacti_sizeof($return_array) &&
						array_key_exists('local_graph_id', $return_array) &&
						array_key_exists('local_data_id', $return_array)) {
						$data_source_id = db_fetch_cell_prepared('SELECT data_template_rrd.local_data_id
							FROM graph_templates_item, data_template_rrd
							WHERE graph_templates_item.local_graph_id = ?
							AND graph_templates_item.task_item_id = data_template_rrd.id
							LIMIT 1',
							[$return_array['local_graph_id']]);

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

	return true;
}

/**
 * Creates all header nodes based on the provided item ID and rule.
 *   - get all related rule items
 *   - take header type into account
 *   - create (multiple) header nodes
 *
 * @param int   $item_id The ID of the item for which header nodes are to be created.
 * @param array $rule    The rule array that defines how the header nodes should be created.
 *
 * @return int the last tree item that was hooked into the tree
 */
function create_all_header_nodes(int $item_id, array $rule) : int {
	global $automation_tree_header_types;

	// get all related rules that are enabled
	$tree_items = db_fetch_assoc_prepared('SELECT *
        FROM automation_tree_rule_items AS atri
        WHERE atri.rule_id = ?
        ORDER BY sequence',
		[$rule['id']]);

	$function = automation_function_with_pid(__FUNCTION__);
	cacti_log($function . " called: Item $item_id matches: " . cacti_sizeof($tree_items) . ' items', false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	/* start at the given tree item
	 * it may be worth verifying existence of this entry
	 * in case it was selected once but then deleted
	 */
	$parent_tree_item_id = $rule['tree_item_id'];

	$sql_where  = '';
	$sql_tables = '';

	// now walk all rules and create tree nodes
	if (cacti_sizeof($tree_items)) {
		/* build magic query,
		 * for matching hosts JOIN tables host and host_template */
		if ($rule['leaf_type'] == TREE_ITEM_TYPE_HOST) {
			$sql_tables = 'FROM host AS h
				LEFT JOIN host_template AS ht
				ON h.host_template_id=ht.id ';

			$sql_where = 'WHERE h.id=' . $item_id . ' AND h.deleted = "" ';
		} elseif ($rule['leaf_type'] == TREE_ITEM_TYPE_GRAPH) {
			// graphs require a different set of tables to be joined
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

		// get the WHERE clause for matching hosts
		$sql_filter = build_matching_objects_filter($rule['id'], AUTOMATION_RULE_TYPE_TREE_MATCH);

		foreach ($tree_items as $tree_item) {
			if ($tree_item['field'] === AUTOMATION_TREE_ITEM_TYPE_STRING) {
				// for a fixed string, use the given text
				$sql    = '';
				$target = $automation_tree_header_types[AUTOMATION_TREE_ITEM_TYPE_STRING];
			} elseif (api_automation_column_exists($tree_item['field'], ['host', 'host_template', 'graph_local', 'graph_templates_graph', 'graph_templates'])) {
				$sql_field = $tree_item['field'] . ' AS source ';

				// now we build up a new query for counting the rows
				$sql = 'SELECT ' .
				$sql_field .
				$sql_tables .
				$sql_where . ' AND (' . $sql_filter . ')';

				$target = db_fetch_cell($sql, '', false);
			} else {
				cacti_log("Attempted SQL Injection found in Tree Automation for the field variable {$tree_item['field']}.", false, 'AUTOM8');
				raise_message('sql_injection', __("Attempted SQL Injection found in Tree Automation for the field variable {$tree_item['field']}."), MESSAGE_LEVEL_ERROR);

				$sql    = '';
				$target = '';
			}

			cacti_log($function . ' Item ' . $item_id . ' - sql: ' . str_replace("\m",'',$sql) . ' matches: ' . $target, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_DEBUG);

			$parent_tree_item_id = (int) create_multi_header_node($target, $rule, $tree_item, $parent_tree_item_id);
		}
	}

	return $parent_tree_item_id;
}

/**
 * Creates a multi-header node in the specified object.
 *   - evaluate replacement rule
 *   - this may return an array of new header items
 *   - walk that array to create all header items for this single rule item
 *
 * @param string $object              The object where the multi-header node will be created.
 * @param array  $rule                The rule to be applied for creating the multi-header node.
 * @param array  $tree_item           The tree item details for the multi-header node.
 * @param int    $parent_tree_item_id The ID of the parent tree item.
 *
 * @return int
 */
function create_multi_header_node(string $object, array $rule, array $tree_item, int $parent_tree_item_id) : int {
	$function = automation_function_with_pid(__FUNCTION__);
	cacti_log($function . " - object: '" . $object . "', Header: '" . $tree_item['search_pattern'] . "', parent: " . $parent_tree_item_id, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	if ($tree_item['field'] === AUTOMATION_TREE_ITEM_TYPE_STRING) {
		$parent_tree_item_id = (int) create_header_node($tree_item['search_pattern'], $rule, $tree_item, $parent_tree_item_id);
		cacti_log($function . " called - object: '" . $object . "', Header: '" . $tree_item['search_pattern'] . "', hooked at: " . $parent_tree_item_id, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);
	} else {
		$replacement = automation_string_replace($tree_item['search_pattern'], $tree_item['replace_pattern'], $object);
		// build multiline <td> entry
		// print '<pre>'; print_r($replacement); print '</pre>';

		for ($j = 0; cacti_sizeof($replacement); $j++) {
			$title               = array_shift($replacement);
			$parent_tree_item_id = (int) create_header_node($title ?? '', $rule, $tree_item, $parent_tree_item_id);
			cacti_log($function . " - object: '" . $object . "', Header: '" . $title . "', hooked at: " . $parent_tree_item_id, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);
		}
	}

	return $parent_tree_item_id;
}

/**
 * Create a single tree header node
 *
 * @param string $title               The title of the header node.
 * @param array  $rule                The rule associated with the header node.
 * @param array  $item                The item associated with the header node.
 * @param int    $parent_tree_item_id The ID of the parent tree item.
 *
 * @return int|bool
 */
function create_header_node(string $title, array $rule, array $item, int $parent_tree_item_id) : int|bool {
	$id             = 0;  // create a new entry
	$local_graph_id = 0;  // headers don't need no graph_id
	$host_id        = 0;  // or a host_id
	$site_id        = 0;  // or a site_id
	$propagate      = ($item['propagate_changes'] != '');
	$function       = automation_function_with_pid(__FUNCTION__);

	if (api_tree_branch_exists($rule['tree_id'], $parent_tree_item_id, $title)) {
		$new_item = api_tree_get_branch_id($rule['tree_id'], $parent_tree_item_id, $title);
		cacti_log('NOTE: ' . $function . ' Parent[' . $parent_tree_item_id . '] Tree Item - Already Exists', false, 'AUTOM8', POLLER_VERBOSITY_MEDIUM);
	} else {
		$new_item = api_tree_item_save($id, $rule['tree_id'], TREE_ITEM_TYPE_HEADER, $parent_tree_item_id,
			$title, $local_graph_id, $host_id, $site_id, $rule['host_grouping_type'], $item['sort_type'], $propagate);

		if ($new_item > 0) {
			cacti_log('NOTE: ' . $function . ' Parent[' . $parent_tree_item_id . '] Tree Item - Added - id: (' . $new_item . ') Title: (' . $title . ')', false, 'AUTOM8');
		} else {
			cacti_log('WARNING: ' . $function . ' Parent[' . $parent_tree_item_id . '] Tree Item - Not Added', false, 'AUTOM8');
		}
	}

	return $new_item;
}

/**
 * Add a device to the tree
 *
 * @param int   $host_id The ID of the host.
 * @param int   $parent  The parent node or identifier.
 * @param array $rule    The rule set to apply for the device node creation.
 *
 * @return int id of new item
 */
function create_device_node(int $host_id, int $parent, array $rule) : int {
	$id             = 0;      // create a new entry
	$local_graph_id = 0;      // hosts don't need no graph_id
	$site_id        = 0;      // hosts don't need no site_id
	$title          = '';     // nor a title
	$sort_type      = 0;      // nor a sort type
	$propagate      = false;  // nor a propagation flag
	$function       = automation_function_with_pid(__FUNCTION__);

	if (api_tree_host_exists($rule['tree_id'], $parent, $host_id)) {
		$new_item = (int) db_fetch_cell_prepared('SELECT id
			FROM graph_tree_items
			WHERE host_id = ?
			AND parent = ?
			AND graph_tree_id = ?',
			[$host_id, $parent, $rule['tree_id']]);

		cacti_log('NOTE: ' . $function . ' Device[' . $host_id . '] Tree Item - Already Exists', false, 'AUTOM8', POLLER_VERBOSITY_MEDIUM);
	} else {
		$new_item = (int) api_tree_item_save($id, $rule['tree_id'], TREE_ITEM_TYPE_HOST, $parent, $title,
			$local_graph_id, $host_id, $site_id, $rule['host_grouping_type'], $sort_type, $propagate);

		if ($new_item > 0) {
			cacti_log('NOTE: ' . $function . ' Device[' . $host_id . '] Tree Item - Added - Parent[' . $parent . '] Id[' . $new_item . ']', false, 'AUTOM8');
		} else {
			cacti_log('WARNING: ' . $function . ' Device[' . $host_id . '] Tree Item - Not Added', false, 'AUTOM8');
		}
	}

	return $new_item;
}

/**
 * Creates a site node.
 *
 * @param int   $site_id The ID of the site.
 * @param int   $parent  The parent node.
 * @param array $rule    The rule array to apply.
 *
 * @return int id of new item
 */
function create_site_node(int $site_id, int $parent, array $rule) : int {
	$id             = 0;      // create a new entry
	$local_graph_id = 0;      // hosts don't need no graph_id
	$host_id        = 0;      // hosts don't need no host_id
	$title          = '';     // nor a title
	$sort_type      = 0;      // nor a sort type
	$propagate      = false;  // nor a propagation flag
	$function       = 'Function[' . __FUNCTION__ . ']';

	if (api_tree_site_exists($rule['tree_id'], $parent, $site_id)) {
		$new_item = (int) db_fetch_cell_prepared('SELECT id
			FROM graph_tree_items
			WHERE site_id = ?
			AND parent = ?
			AND graph_tree_id = ?',
			[$site_id, $parent, $rule['tree_id']]);

		cacti_log('NOTE: ' . $function . ' Site[' . $host_id . '] Tree Item - Already Exists', false, 'AUTOM8', POLLER_VERBOSITY_MEDIUM);
	} else {
		$new_item = (int) api_tree_item_save($id, $rule['tree_id'], TREE_ITEM_TYPE_HOST, $parent, $title,
			$local_graph_id, $host_id, $site_id, $rule['host_grouping_type'], $sort_type, $propagate);

		if ($new_item > 0) {
			cacti_log('NOTE: ' . $function . ' Site[' . $site_id . '] Tree Item - Added - id: (' . $new_item . ')', false, 'AUTOM8');
		} else {
			cacti_log('WARNING: ' . $function . ' Site[' . $site_id . '] Tree Item - Not Added', false, 'AUTOM8');
		}
	}

	return $new_item;
}

/**
 * Add a device to the tree
 *
 * @param int   $graph_id The ID of the graph.
 * @param int   $parent   The parent node.
 * @param array $rule     The rule to apply to the graph node.
 *
 * @return int id of new item
 */
function create_graph_node(int $graph_id, int $parent, array $rule) : int {
	$id        = 0;      // create a new entry
	$host_id   = 0;      // graphs don't need no host_id
	$site_id   = 0;      // graphs don't need no site_id
	$title     = '';     // nor a title
	$sort_type = 0;      // nor a sort type
	$propagate = false;  // nor a propagation flag
	$function  = automation_function_with_pid(__FUNCTION__);

	if (api_tree_graph_exists($rule['tree_id'], $parent, $graph_id)) {
		$new_item = (int) db_fetch_cell_prepared('SELECT id
			FROM graph_tree_items
			WHERE local_graph_id = ?
			AND parent = ?
			AND graph_tree_id = ?',
			[$graph_id, $parent, $rule['tree_id']]);

		cacti_log('NOTE: ' . $function . ' Graph[' . $graph_id . '] Tree Item - Already Exists', false, 'AUTOM8', POLLER_VERBOSITY_MEDIUM);
	} else {
		$new_item = (int) api_tree_item_save($id, $rule['tree_id'], TREE_ITEM_TYPE_GRAPH, $parent, $title,
			$graph_id, $host_id, $site_id, $rule['host_grouping_type'], $sort_type, $propagate);

		if ($new_item > 0) {
			cacti_log('NOTE: ' . $function . ' Graph[' . $graph_id . '] Tree Item - Added - id: (' . $new_item . ')', false, 'AUTOM8');
		} else {
			cacti_log('WARNING: ' . $function . ' Graph[' . $graph_id . '] Tree Item - Not Added', false, 'AUTOM8');
		}
	}

	return $new_item;
}

/**
 * Executes tasks that need to be performed at the end of the poller process.
 *
 * @return void
 */
function automation_poller_bottom() : void {
	$command_string = cacti_escapeshellcmd(read_config_option('path_php_binary'));

	// If its not set, just assume its in the path
	if (trim($command_string) == '') {
		$command_string = 'php';
	}

	$extra_args = ' -q ' . cacti_escapeshellarg(CACTI_PATH_BASE . '/poller_automation.php') . ' -M';

	exec_background($command_string, $extra_args);
}

/**
 * Adds a device to the automation system.
 *
 * @param array $device An associative array containing device details.
 * @param bool  $web    Optional. Indicates if the request is coming from a web interface. Default is false.
 *
 * @return int The ID of the device that was added.
 */
function automation_add_device(array $device, bool $web = false) : int {
	global $plugins;

	$template_id          = $device['host_template'];
	$snmp_sysName         = $device['snmp_sysName'];

	$description          = $device['description'] ?? $snmp_sysName != '' ? $snmp_sysName : ($device['hostname'] == '' ? $device['ip'] : $device['hostname']);

	$poller_id            = $device['poller_id'] ?? read_config_option('default_poller');
	$site_id              = $device['site_id'] ?? read_config_option('default_site');
	$ip                   = $device['ip'] ?? $device['ip_address'];
	$snmp_community       = $device['snmp_community'];
	$snmp_ver             = $device['snmp_version'];
	$snmp_username        = $device['snmp_username'];
	$snmp_password        = $device['snmp_password'];
	$snmp_port            = $device['snmp_port'];
	$snmp_timeout         = $device['snmp_timeout'] ?? read_config_option('snmp_timeout');
	$disable              = '';
	$availability_method  = $device['availability_method'] ?? read_config_option('availability_method');
	$ping_method          = $device['ping_method'] ?? read_config_option('ping_method');
	$ping_port            = $device['ping_port'] ?? read_config_option('ping_port');
	$ping_timeout         = $device['ping_timeout'] ?? read_config_option('ping_timeout');
	$ping_retries         = $device['ping_retries'] ?? read_config_option('ping_retries');
	$notes                = $device['notes'] ?? __('Added by Cacti Automation');
	$snmp_auth_protocol   = $device['snmp_auth_protocol'];
	$snmp_priv_passphrase = $device['snmp_priv_passphrase'];
	$snmp_priv_protocol   = $device['snmp_priv_protocol'];
	$snmp_context         = $device['snmp_context'];
	$snmp_engine_id       = $device['snmp_engine_id'];
	$max_oids             = $device['max_oids'] ?? 10;
	$device_threads       = $device['device_threads'] ?? 1;
	$external_id          = $device['external_id'] ?? '';
	$location             = $device['location'] ?? '';
	$bulk_walk_size       = $device['bulk_walk_size'] ?? -1;

	automation_debug(' - Adding Device');

	$host_id = api_device_save(0, $template_id, $description, $ip,
		$snmp_community, $snmp_ver, $snmp_username, $snmp_password,
		$snmp_port, $snmp_timeout, $disable, $availability_method,
		$ping_method, $ping_port, $ping_timeout, $ping_retries,
		$notes, $snmp_auth_protocol, $snmp_priv_passphrase,
		$snmp_priv_protocol, $snmp_context, $snmp_engine_id, $max_oids,
		$device_threads, $poller_id, $site_id, $external_id, $location, $bulk_walk_size);

	if ($host_id) {
		automation_debug(" - Success\n");

		// Use the thold plugin if it exists
		api_plugin_hook_function('device_threshold_autocreate', $host_id);

		db_execute_prepared('DELETE FROM automation_devices WHERE ip = ? LIMIT 1', [$ip]);
	} else {
		automation_debug(" - Failed\n");
	}

	return $host_id;
}

/**
 * Adds a host to a specified tree in the automation process.
 *
 * @param int $host_id The ID of the host to be added.
 * @param int $tree    The tree structure where the host will be added.
 *
 * @return void
 */
function automation_add_tree(int $host_id, int $tree) : void {
	automation_debug("     Adding to tree\n");

	if ($tree > 1000000) {
		$tree_id = $tree - 1000000;
		$parent  = 0;
	} else {
		$tree_item = db_fetch_row_prepared('SELECT * FROM graph_tree_items WHERE id = ?', [$tree]);

		if (!isset($tree_item['graph_tree_id'])) {
			return;
		}
		$tree_id = $tree_item['graph_tree_id'];
		$parent  = $tree;
	}

	$nodeId = api_tree_item_save(0, $tree_id, 3, $parent, '', 0, $host_id, 0, 1, 1, false);
}

/**
 * Finds the operating system based on system description, system object, and system name.
 *
 * @param string $sysDescr  The system description.
 * @param string $sysObject The system object identifier.
 * @param string $sysName   The system name.
 *
 * @return array|false The matched automation template row, or false if none found.
 */
function automation_find_os(string $sysDescr, string $sysObject, string $sysName) : array|false {
	$sql_where  = '';
	$params     = [];

	if ($sysDescr != '') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . "(? REGEXP CONCAT('/', sysDescr, '/') OR ? LIKE CONCAT('%', sysDescr, '%'))";

		$params[] = $sysDescr;
		$params[] = $sysDescr;
	}

	if ($sysObject != '') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . "(? REGEXP CONCAT('/', sysOid, '/') OR ? LIKE CONCAT('%', sysOid, '%'))";

		$params[] = $sysObject;
		$params[] = $sysObject;
	}

	if ($sysName != '') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . "(? REGEXP CONCAT('/', sysName, '/') OR ? LIKE CONCAT('%', sysName, '%'))";

		$params[] = $sysName;
		$params[] = $sysName;
	}

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

/**
 * Logs debug information for automation processes.
 *
 * @param string $text The debug text to be logged.
 *
 * @return void
 */
function automation_debug(string $text) : void {
	global $debug;
	static $message = '';

	if (str_contains($text, "\n")) {
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
		if (!CACTI_WEB) {
			print $text;
		}
		$message .= $text;
	}
}

/**
 * Converts a subnet mask to CIDR notation.
 *
 * @param  string      $mask The subnet mask to convert.
 * @return float|false The CIDR notation as a float, or false on failure.
 */
function automation_masktocidr(string $mask) : float|false {
	$cidr = false;
	$long = ip2long($mask);

	if ($long !== false) {
		$base = ip2long('255.255.255.255');
		$cidr = 32 - log(($long ^ $base) + 1, 2);
	}

	return $cidr;
}

/**
 * Retrieves a valid IP address from the given range.
 *
 * @param  string       $range The range of IP addresses to validate.
 * @return string|false Returns a valid IP address as a string if found, or false if no valid IP address is found.
 */
function automation_get_valid_ip(string $range) : string|false {
	$long = ip2long($range);

	return $long === false ? false : long2ip($long);
}

/**
 * Retrieves a valid subnet CIDR from the given range.
 *
 * @param  string      $range The IP range to validate and extract the subnet CIDR from.
 * @return array|false Returns an array containing the valid subnet CIDR if found,
 *                     otherwise returns false if the range is invalid.
 */
function automation_get_valid_subnet_cidr(string $range) : array|false {
	$long = ip2long($range);
	$cidr = 0;

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

	return $long === false ? false : ['cidr' => $cidr, 'subnet' => long2ip($long)];
}

/**
 * Retrieves a valid mask for the given range.
 *
 * @param  string      $range The range for which to get the valid mask.
 * @return array|false The valid mask for the specified range.
 */
function automation_get_valid_mask(string $range) : array|false {
	$cidr = false;

	if (is_numeric($range)) {
		if ($range > 0 && $range < 33) {
			$cidr = $range;
			$mask = [
				'cidr'   => $cidr,
				'subnet' => long2ip((2 ** $range - 1) << (32 - $range)),
			];
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

/**
 * Retrieves network information for a given range.
 *
 * @param  string      $range The network range to retrieve information for.
 * @return array|false An associative array containing network information.
 */
function automation_get_network_info(string $range) : array|false {
	$network   = false;
	$broadcast = false;
	$mask      = [];
	$detail    = [];

	$range = trim($range);

	if (str_contains($range, '/')) {
		// 10.1.0.0/24 or 10.1.0.0/255.255.255.0
		$range_parts = explode('/', $range);

		if (!filter_var($range_parts[0], FILTER_VALIDATE_IP)) {
			return false;
		}

		$mask = automation_get_valid_mask($range_parts[1]);

		if (cacti_sizeof($mask)) {
			$network = automation_get_valid_ip($range_parts[0]);

			if ($network !== false && $mask['cidr'] != 0) {
				$dec       = ip2long($network) & ip2long($mask['subnet']);
				$count     = $mask['cidr'] == 32 ? 0 : $mask['count'];
				$network   = long2ip($dec);
				$broadcast = long2ip($dec + $count);
			}
		}
	} elseif (str_contains($range, '*') && !str_contains($range, '-')) {
		$test = str_replace('*', '0', $range);

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

			return automation_get_network_info(rtrim($network,'.') . '/' . rtrim($broadcast,'.'));
		}
	} elseif (str_contains($range, '-')) {
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
			$detail['cidr']      = $mask['cidr'] ?? false;

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

/**
 * Calculates the start time for an automation process based on the given range.
 *
 * @param  string       $range The range value used to calculate the start time.
 * @return string|false The calculated start time.
 */
function automation_calculate_start(string $range) : string|false {
	$detail = automation_get_network_info($range);

	if ($detail) {
		return $detail['start'];
	}

	automation_debug('  Could not calculate starting IP!');

	return false;
}

/**
 * Calculate the total number of IPs in a given range.
 *
 * @param  string    $range The IP range in CIDR notation (e.g., '192.168.1.0/24').
 * @return int|false The total number of IPs in the specified range.
 */
function automation_calculate_total_ips(string $range) : int|false {
	$detail = automation_get_network_info($range);

	if ($detail) {
		return $detail['count'];
	}

	automation_debug('  Could not calculate total IPs!');

	return false;
}

/**
 * Retrieves the next host in the automation sequence.
 *
 * @param  string       $start The starting point for the host retrieval.
 * @param  int          $total The total number of hosts available.
 * @param  int          $count The current count of hosts processed.
 * @param  string       $range The range within which to retrieve the next host.
 * @return string|false Returns the next host as a string, or false if no more hosts are available.
 */
function automation_get_next_host(string $start, int $total, int $count, string $range) : string|false {
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
			$y /= 256;

			if ($ip[$x] == 256 && $x > 0) {
				$ip[$x] = 0;
				$ip[$x - 1] += 1;
			}
		}

		return implode('.', $ip);
	}
}

/**
 * Primes the IP address table for a given network.
 *
 * This function initializes or updates the IP address table associated with the specified network ID.
 *
 * @param int $network_id The ID of the network for which the IP address table is to be primed.
 *
 * @return void
 */
function automation_primeIPAddressTable(int $network_id) : void {
	$subNets = db_fetch_cell_prepared('SELECT subnet_range
		FROM automation_networks
		WHERE id = ?',
		[$network_id]);

	$ignore_ips = db_fetch_cell_prepared('SELECT ignore_ips
		FROM automation_networks
		WHERE id = ?',
		[$network_id]);

	$subNets    = explode(',', trim($subNets));
	$total      = 0;

	if ($ignore_ips != '') {
		$ignore_ips = explode(',', $ignore_ips);

		foreach ($ignore_ips as $index => $ip) {
			$ignore_ips[$index] = trim($ip);
		}
	} else {
		$ignore_ips = [];
	}

	if (cacti_sizeof($subNets)) {
		foreach ($subNets as $subNet) {
			$count       = 1;
			$sql         = [];
			$subNetTotal = automation_calculate_total_ips($subNet);
			$total += $subNetTotal;

			$start = automation_calculate_start($subNet);

			if ($start === false) {
				continue;
			}

			if ($start != '' && !in_array($start, $ignore_ips, true)) {
				$sql[] = "('$start', '', $network_id, '0', '0', '0')";
			}

			while ($count < $subNetTotal) {
				$ip = automation_get_next_host($start, $subNetTotal, $count, $subNet);

				if ($ip != '' && !in_array($ip, $ignore_ips, true)) {
					$sql[] = "('$ip', '', $network_id, '0', '0', '0')";

					$count++;
				}

				if ($count % 1000 == 0) {
					db_execute('INSERT INTO automation_ips
						(ip_address, hostname, network_id, pid, status, thread)
						VALUES ' . implode(',', $sql));
					$sql = [];
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

/**
 * Validates if the given device is a valid SNMP device.
 *
 * @param array $device Reference to the device array to be validated.
 *
 * @return bool Returns true if the device is a valid SNMP device, false otherwise.
 */
function automation_valid_snmp_device(array &$device) : bool {
	global $snmp_logging;

	// initialize variable
	$host_up               = false;
	$snmp_logging          = false;
	$device['snmp_status'] = HOST_DOWN;
	$device['ping_status'] = 0;
	$session               = false;
	$snmp_sysObjectID      = '';

	// force php to return numeric oid's
	cacti_oid_numeric_format();

	$snmp_items = db_fetch_assoc_prepared('SELECT *
		FROM automation_snmp_items
		WHERE snmp_id = ?
		ORDER BY sequence ASC',
		[$device['snmp_id']]);

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
				// Community string is not used for v3
				$snmp_sysObjectID = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.2.0');

				if (is_string($snmp_sysObjectID) && $snmp_sysObjectID != 'U') {
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

			if ($host_up) {
				break;
			}
		}

		if ($host_up && $session !== false) {
			$device['snmp_sysObjectID'] = $snmp_sysObjectID;

			// get system name
			$snmp_sysName = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.5.0');

			if ($snmp_sysName != '') {
				$snmp_sysName           = trim(strtr($snmp_sysName,'"',' '));
				$device['snmp_sysName'] = $snmp_sysName;
				automation_debug($snmp_sysName);
			} else {
				automation_debug('Unknown System');
			}

			// get system location
			$snmp_sysLocation = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.6.0');

			if ($snmp_sysLocation != '') {
				$snmp_sysLocation           = trim(strtr($snmp_sysLocation,'"',' '));
				$device['snmp_sysLocation'] = $snmp_sysLocation;
			}

			// get system contact
			$snmp_sysContact = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.4.0');

			if ($snmp_sysContact != '') {
				$snmp_sysContact           = trim(strtr($snmp_sysContact,'"',' '));
				$device['snmp_sysContact'] = $snmp_sysContact;
			}

			// get system description
			$snmp_sysDescr = cacti_snmp_session_get($session, '.1.3.6.1.2.1.1.1.0');

			if ($snmp_sysDescr != '') {
				$snmp_sysDescr           = trim(strtr($snmp_sysDescr,'"',' '));
				$device['snmp_sysDescr'] = $snmp_sysDescr;
			}

			// get system uptime
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

/**
 * Retrieves the DNS name associated with a given IP address.
 *
 * @param  string $ip      The IP address to look up.
 * @param  string $dns     The DNS server to use for the lookup.
 * @param  int    $timeout The timeout for the DNS lookup in milliseconds. Default is 1000 ms.
 * @return string The DNS name associated with the IP address, or null if not found.
 */
function automation_get_dns_from_ip(string $ip, string $dns, int $timeout = 1000) : string {
	// random transaction number (for routers etc to get the reply back)
	$data = (string) random_int(10, 99);

	// trim it to 2 bytes
	$data = substr($data, 0, 2);

	// create request header
	$data .= "\1\0\0\1\0\0\0\0\0\0";

	// split IP into octets
	$octets = explode('.', $ip);

	// perform a quick error check
	if (cacti_count($octets) != 4) {
		return 'ERROR';
	}

	// needs a byte to indicate the length of each segment of the request
	for ($x = 3; $x >= 0; $x--) {
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

		// and the segment itself
		$data .= $octets[$x];
	}

	// and the final bit of the request
	$data .= "\7in-addr\4arpa\0\0\x0C\0\1";

	// create UDP socket
	$handle = @fsockopen("udp://$dns", 53);

	if ($handle === false) {
		return cacti_strtoupper($ip);
	}

	@stream_set_timeout($handle, intval(floor($timeout / 1000)), ($timeout * 1000) % 1000000);
	@stream_set_blocking($handle, true);

	// send our request (and store request size so we can cheat later)
	$requestsize = @fwrite($handle, $data);

	// get the response
	$response = @fread($handle, 1000);

	// check to see if it timed out
	$info = @stream_get_meta_data($handle);

	// close the socket
	@fclose($handle);

	if ($info['timed_out'] == true) {
		return 'timed_out';
	}

	// more error handling
	if ($response == '' || $requestsize == false || strlen($response) <= $requestsize) {
		return $ip;
	}

	// parse the response and find the response type
	$type = @unpack('s', substr($response, $requestsize + 2));

	if (isset($type[1]) && $type[1] == 0x0C00) {
		// set up our variables
		$host = '';
		$len  = 0;

		/* set our pointer at the beginning of the hostname uses the request
		   size from earlier rather than work it out.
		*/
		$position = $requestsize + 12;

		// reconstruct the hostname
		do {
			// get segment size
			$len = unpack('c', substr($response, $position));

			if (!is_array($len)) {
				break;
			}

			// null terminated string, so length 0 = finished
			if ($len[1] == 0) {
				// return the hostname, without the trailing '.'
				return cacti_strtoupper(substr($host, 0, strlen($host) - 1));
			}

			// add the next segment to our host
			$host .= substr($response, $position + 1, $len[1]) . '.';

			// move pointer on to the next segment
			$position += $len[1] + 1;
		} while ($len != false);
	}

	// error - return the hostname
	return cacti_strtoupper($ip);
}

/**
 * Pings a NetBIOS name for a given IP address.
 *
 * @param string $ip         - The IP address to ping.
 * @param int    $timeout_ms - The timeout in milliseconds for the ping. Default is 1000 ms.
 *
 * @return mixed - Returns the netbios name is successful or false otherwise
 */
function ping_netbios_name(string $ip, int $timeout_ms = 1000) : mixed {
	$handle = @fsockopen("udp://$ip", 137);

	if (is_resource($handle)) {
		stream_set_timeout($handle, intval(floor($timeout_ms / 1000)), ($timeout_ms * 1000) % 1000000);
		stream_set_blocking($handle, true);

		$packet = "\x99\x99\x00\x00\x00\x01\x00\x00\x00\x00\x00\x00\x20\x43\x4b" . str_repeat("\x41", 30) . "\x00\x00\x21\x00\x01";

		// send our request (and store request size so we can cheat later)
		$requestsize = @fwrite($handle, $packet);

		// get the response
		$response = @fread($handle, 2048);

		// check to see if it timed out
		$info = @stream_get_meta_data($handle);

		// close the socket
		fclose($handle);

		if ($info['timed_out']) {
			return false;
		}

		if (!isset($response[56])) {
			return false;
		}

		// parse the response and find the response type
		$names = hexdec((string) ord($response[56]));

		if ($names > 0) {
			$host = '';

			for ($i = 57; $i < strlen($response); $i += 1) {
				if (hexdec((string) ord($response[$i])) == 0) {
					break;
				}
				$host .= $response[$i];
			}

			return trim(cacti_strtolower($host));
		} else {
			return false;
		}
	}

	return false;
}

/**
 * Updates the automation settings for a specific device.
 *
 * @param int $host_id - The ID of the host device to update.
 *
 * @return void
 */
function automation_update_device(int $host_id) : void {
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

	// create all graph template graphs
	if (cacti_sizeof($graph_templates)) {
		foreach ($graph_templates as $graph_template) {
			cacti_log($function . ' Found GT[' . $graph_template['id'] . '] for Device[' . $host_id . ']', true, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

			automation_execute_graph_template($host_id, $graph_template['id']);
		}
	}

	// all associated data queries
	$data_queries = db_fetch_assoc_prepared('SELECT sq.*,
		hsq.reindex_method
		FROM snmp_query AS sq
		INNER JOIN host_snmp_query AS hsq
		ON sq.id=hsq.snmp_query_id
		WHERE hsq.host_id = ?', [$host_id]);

	// create all data query graphs
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

	// now handle tree rules for that host
	cacti_log($function . ' Create Tree for Device[' . $host_id . ']', true, 'AUTOM8 TRACE', POLLER_VERBOSITY_MEDIUM);
	automation_execute_device_create_tree($host_id);
}

/**
 * Executes an automation function with a process ID.
 *
 * @param  string $functionName The name of the function to be executed.
 * @return string The function name with the process ID appended.
 */
function automation_function_with_pid(string $functionName) : string {
	return automation_get_pid() . ' ' . $functionName . '()';
}

/**
 * Retrieves the process ID (PID) for the automation process.
 *
 * @return string The PID of the automation process.
 */
function automation_get_pid() : string {
	static $pid;

	if (!isset($pid)) {
		$pid = getmypid();
	}

	return "[PID: $pid]";
}

/**
 * Changes the type of a tree rule leaf.
 *
 * @param string $leaf_type The new type of the leaf.
 * @param string $rule_id   The ID of the rule to be updated.
 *
 * @return void
 */
function automation_change_tree_rule_leaf_type(string $leaf_type, string $rule_id) : void {
	$function = automation_function_with_pid(__FUNCTION__);

	$leaf_old = db_fetch_cell_prepared('SELECT leaf_type
		FROM automation_tree_rules
		WHERE id = ?',
		[$rule_id]);

	if ($leaf_old != $leaf_type) {
		cacti_log($function . ' Found leaf change from Leaf[' . $leaf_old . '] to Leaf[' . $leaf_type . '] for TreeRule[' . $rule_id . ']', true, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

		if ($leaf_type == 3) {
			cacti_log($function . ' Found leaf changed to \'Device\' for TreeRule[' . $rule_id . ']', true, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

			$rule_items = db_fetch_assoc_prepared('SELECT *
				FROM automation_tree_rule_items
				WHERE rule_id = ?
				AND (field like \'gtg.%\' or field like \'gt.%\')',
				[$rule_id]);

			if (cacti_sizeof($rule_items)) {
				cacti_log($function . ' ' . cacti_sizeof($rule_items) . ' invalid Tree Creation rule items found for TreeRule[' . $rule_id . ']', true, 'AUTOM8 TRACE', POLLER_VERBOSITY_DEBUG);

				foreach ($rule_items as $rule_item) {
					cacti_log($function . ' Removing invalid Tree Creation rule item TreeRule[' . $rule_id . '] TreeRuleItem[' . $rule_item['id'] . '] Field[' . htmle($rule_item['field']) . '] with Search[' . htmle($rule_item['search_pattern']) . '] Replace[' . htmle($rule_item['replace_pattern']) . ']', true, 'AUTOM8 TRACE', POLLER_VERBOSITY_DEBUG);

					db_execute_prepared('DELETE
						FROM automation_tree_rule_items
						WHERE id = ?',
						[$rule_item['id']]);
				}
			} else {
				cacti_log($function . ' No invalid Tree Creation rule items found for TreeRule[' . $rule_id . ']', true, 'AUTOM8 TRACE', POLLER_VERBOSITY_DEBUG);
			}

			$match_items = db_fetch_assoc_prepared('SELECT *
				FROM automation_match_rule_items
				WHERE rule_id = ?
				AND (field like \'gtg.%\' or field like \'gt.%\')',
				[$rule_id]);

			if (cacti_sizeof($match_items)) {
				cacti_log($function . ' ' . cacti_sizeof($match_items) . ' invalid Object Selection rule items found for TreeRule[' . $rule_id . ']', true, 'AUTOM8 TRACE', POLLER_VERBOSITY_DEBUG);

				foreach ($match_items as $match_item) {
					cacti_log($function . ' Removing invalid Object Selection rule item TreeRule[' . $rule_id . '] TreeMatchItem[' . $match_item['id'] . '] Field[' . htmle($match_item['field']) . '] with Pattern[' . htmle($match_item['pattern']) . ']', true, 'AUTOM8 TRACE', POLLER_VERBOSITY_DEBUG);

					db_execute_prepared('DELETE
						FROM automation_match_rule_items
						WHERE id = ?',
						[$match_item['id']]);
				}
			} else {
				cacti_log($function . ' No invalid Object Selection rule items found for TreeRule[' . $rule_id . ']', true, 'AUTOM8 TRACE', POLLER_VERBOSITY_DEBUG);
			}
		}

		db_execute_prepared('UPDATE automation_tree_rules
			SET leaf_type = ?
			WHERE id = ?',
			[$leaf_type, $rule_id]);
	}
}

/**
 * Converts an automation type to its corresponding database table name.
 *
 * @param  string $type The type of automation to convert.
 * @return string The name of the corresponding database table.
 */
function automation_type_to_table(string $type) : string {
	$table = '';

	$table = match ($type) {
		'network'      => 'automation_networks',
		'device'       => 'automation_templates',
		'device_rules' => 'automation_templates_rules',
		'graph'        => 'automation_graph_rules',
		'graph_items'  => 'automation_graph_rule_items',
		'tree'         => 'automation_tree_rules',
		'tree_items'   => 'automation_tree_rule_items',
		'snmp'         => 'automation_snmp',
		'snmp_items'   => 'automation_snmp_items',
		default        => $table,
	};

	return $table;
}

/**
 * Converts an automation ID to a hash.
 *
 * @param string $table The name of the table.
 * @param int    $id    The ID to be converted.
 *
 * @return mixed - The resulting hash.
 */
function automation_id_to_hash(string $table, int $id) : mixed {
	$table = automation_type_to_table($table);

	if ($table != '') {
		return db_fetch_cell_prepared("SELECT hash FROM $table WHERE id = ?", [$id]);
	}

	return false;
}

/**
 * Converts a given hash to its corresponding ID based on the specified type.
 *
 * @param  string    $type The type of the entity for which the hash is being converted.
 * @param  string    $hash The hash value that needs to be converted to an ID.
 * @return int|false The ID corresponding to the given hash and type.
 */
function automation_hash_to_id(string $type, string $hash) : int|false {
	$table = automation_type_to_table($type);

	if ($table != '') {
		return db_fetch_cell_prepared("SELECT id
			FROM $table
			WHERE hash = ?",
			[$hash]);
	}

	return false;
}

/**
 * Updates the automation hashes.
 *
 * This function is responsible for updating the hashes used in the automation process.
 *
 * @return void
 */
function automation_update_hashes() : void {
	$tables = [
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
	];

	foreach ($tables as $table) {
		$items = db_fetch_assoc("SELECT id FROM $table WHERE hash = ''");

		if (cacti_sizeof($items)) {
			foreach ($items as $item) {
				$hash = get_hash_automation(0, $table);

				db_execute_prepared("UPDATE $table
					SET hash = ?
					WHERE id = ?",
					[$hash, $item['id']]);
			}
		}
	}
}

/**
 * Exports network data for the given network IDs.
 *
 * @param mixed $network_ids An array of network IDs to export or a single id.
 *
 * @return array An array containing the exported network data.
 */
function automation_network_export(mixed $network_ids) : array {
	if (!is_array($network_ids)) {
		$export_name = db_fetch_cell_prepared("SELECT CONCAT('automation_network_', name)
			FROM automation_networks
			WHERE id = ?",
			[$network_ids]);

		$network_ids = [$network_ids];
	} else {
		$export_name = 'automation_network_multiple';
	}

	$json_array = [];

	$json_array['name']        = clean_up_name(cacti_strtolower($export_name));
	$json_array['export_name'] = $json_array['name'] . '.json';

	if (cacti_sizeof($network_ids)) {
		$networks = [];

		foreach ($network_ids as $id) {
			// get the row of data
			$network = db_fetch_row_prepared('SELECT *
				FROM automation_networks
				WHERE id = ?',
				[$id]);

			if (!is_array($network)) {
				continue;
			}

			$snmp_id = $network['snmp_id'];

			$network['snmp_id'] = db_fetch_cell_prepared('SELECT hash
				FROM automation_snmp
				WHERE id = ?', [$network['snmp_id']]);

			// set some safe defaults
			$network['poller_id'] = 1;

			// remove objects that have a hash
			unset($network['id']);
			unset($network['up_hosts']);
			unset($network['snmp_hosts']);
			unset($network['last_runtime']);
			unset($network['last_started']);
			unset($network['last_status']);

			// get the snmp options data
			$snmp = db_fetch_row_prepared('SELECT *
				FROM automation_snmp
				WHERE id = ?',
				[$snmp_id]);

			// remove objects that have a hash
			unset($snmp['id']);

			// get the snmp options item data
			$snmp_items = db_fetch_assoc_prepared('SELECT *
				FROM automation_snmp_items
				WHERE snmp_id = ?',
				[$snmp_id]);

			// remove objects that have a hash
			foreach ($snmp_items as $index => $item) {
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

			// collapse the snmp items into snmp
			$snmp['snmp_items'] = $snmp_items;

			// collapse the snmp object into the network
			$network['snmp'] = $snmp;

			// collapse the data object into the json object
			$networks[] = $network;
		}

		$json_array['network'] = $networks;
	}

	return $json_array;
}

/**
 * Exports automation device rules based on the provided template IDs.
 *
 * @param mixed $template_ids An array of template IDs to export the device rules for or a single id.
 *
 * @return array An array containing the exported device rules.
 */
function automation_device_rule_export(mixed $template_ids) : array {
	if (!is_array($template_ids)) {
		$export_name = db_fetch_cell_prepared("SELECT CONCAT('automation_device_rule_', name)
			FROM automation_templates AS at
			INNER JOIN host_template AS ht
			ON at.host_template = ht.id
			WHERE at.id = ?",
			[$template_ids]);

		$template_ids = [$template_ids];
	} else {
		$export_name = 'automation_device_rules_multiple';
	}

	$json_array = [];

	$json_array['name']        = clean_up_name(cacti_strtolower($export_name));
	$json_array['export_name'] = $json_array['name'] . '.json';

	if (cacti_sizeof($template_ids)) {
		$devices = [];

		foreach ($template_ids as $id) {
			// get the row of data
			$device = db_fetch_row_prepared('SELECT *
				FROM automation_templates
				WHERE id = ?',
				[$id]);

			if (!is_array($device)) {
				continue;
			}

			$device['host_template'] = db_fetch_cell_prepared('SELECT hash
				FROM host_template
				WHERE id = ?',
				[$device['host_template']]);

			// remove objects that have a hash
			unset($device['id']);

			// get the snmp options data
			$device_rule_items = db_fetch_assoc_prepared('SELECT *
				FROM automation_templates_rules
				WHERE template_id = ?',
				[$id]);

			foreach ($device_rule_items as $index => $rule) {
				if ($rule['rule_type'] == 1) {
					$device_rule_items[$index]['rule_id'] = db_fetch_cell_prepared('SELECT hash
						FROM automation_graph_rules
						WHERE id = ?',
						[$rule['rule_id']]);
				} else {
					$device_rule_items[$index]['rule_id'] = db_fetch_cell_prepared('SELECT hash
						FROM automation_tree_rules
						WHERE id = ?',
						[$rule['rule_id']]);
				}

				unset($device_rule_items[$index]['id']);
				unset($device_rule_items[$index]['template_id']);
			}

			$device['device_rules'] = $device_rule_items;

			// get the snmp options item data
			$graph_rules = db_fetch_assoc_prepared('SELECT *
				FROM automation_graph_rules
				WHERE id IN (SELECT rule_id FROM automation_templates_rules WHERE rule_type = 1 AND template_id = ?)',
				[$id]);

			// remove objects that have a hash
			foreach ($graph_rules as $index => $rule) {
				$rule_id = $rule['id'];

				$graph_rules[$index]['snmp_query_id'] = db_fetch_cell_prepared('SELECT hash
					FROM snmp_query
					WHERE id = ?',
					[$rule['snmp_query_id']]);

				$graph_rules[$index]['graph_type_id'] = db_fetch_cell_prepared('SELECT hash
					FROM snmp_query_graph
					WHERE id = ?',
					[$rule['graph_type_id']]);

				unset($graph_rules[$index]['id']);

				// get the snmp options item data
				$graph_rule_items = db_fetch_assoc_prepared('SELECT gri.*
					FROM automation_graph_rule_items AS gri
					WHERE gri.rule_id = ?',
					[$rule_id]);

				// remove objects that have a hash
				foreach ($graph_rule_items as $grindex => $rule_item) {
					unset($graph_rule_items[$grindex]['id']);
					unset($graph_rule_items[$grindex]['rule_id']);
				}

				// collapse the graph rule items
				$graph_rules[$index]['graph_rule_items'] = $graph_rule_items;

				// match items
				$graph_match_items = db_fetch_assoc_prepared('SELECT mri.*
					FROM automation_match_rule_items AS mri
					WHERE mri.rule_id = ?
					AND mri.rule_type IN (1,2)',
					[$rule_id]);

				/* remove objects that have a hash
				 * rule_types 1,2 are Graph Rules
				 * rule_types 3,4 are Tree Rules
				 */
				foreach ($graph_match_items as $gmindex => $rule_item) {
					unset($graph_match_items[$gmindex]['id']);
					unset($graph_match_items[$gmindex]['rule_id']);
				}

				$graph_rules[$index]['graph_match_items'] = $graph_match_items;
			}

			$device['graph_rules'] = $graph_rules;

			// get the snmp options item data
			$tree_rules = db_fetch_assoc_prepared('SELECT *
				FROM automation_tree_rules
				WHERE id IN (SELECT rule_id FROM automation_templates_rules WHERE rule_type = 2 AND template_id = ?)',
				[$id]);

			// remove objects that have a hash
			foreach ($tree_rules as $index => $rule) {
				$rule_id = $rule['id'];

				// get the snmp options item data
				$tree_rule_items = db_fetch_assoc_prepared('SELECT gri.*
					FROM automation_tree_rule_items AS gri
					WHERE gri.rule_id = ?',
					[$rule_id]);

				// remove objects that have a hash
				foreach ($tree_rule_items as $trindex => $rule_item) {
					unset($tree_rule_items[$trindex]['id']);

					$tree_rule_items[$trindex]['rule_id'] = db_fetch_cell_prepared('SELECT hash
						FROM automation_tree_rules
						WHERE id = ?',
						[$rule_item['id']]);
				}

				// unset the rule id
				unset($tree_rules[$index]['id']);

				// pick up the tree and branch name as they may not be on the foreign system
				$tree_rules[$index]['tree_data']        = db_fetch_row_prepared('SELECT name, sort_type FROM graph_tree WHERE id = ?', [$rule['tree_id']]);
				$tree_rules[$index]['tree_branch_data'] = automation_device_rule_export_branches($rule['tree_id'], $rule['tree_item_id']);

				// collapse the tree rule items
				$tree_rules[$index]['tree_rule_items'] = $tree_rule_items;

				// match items
				$tree_match_items = db_fetch_assoc_prepared('SELECT mri.*
					FROM automation_match_rule_items AS mri
					WHERE mri.rule_id = ?
					AND mri.rule_type IN (3,4)',
					[$rule_id]);

				/* remove objects that have a hash
				 * rule_types 1,2 are Graph Rules
				 * rule_types 3,4 are Tree Rules
				 */
				foreach ($tree_match_items as $tmindex => $rule_item) {
					unset($tree_match_items[$tmindex]['id']);
					unset($tree_match_items[$tmindex]['rule_id']);
				}

				$tree_rules[$index]['tree_match_items'] = $tree_match_items;
			}

			$device['tree_rules'] = $tree_rules;

			if (db_table_exists('plugin_thold_host_template')) {
				$host_template_id = db_fetch_cell_prepared('SELECT host_template
					FROM automation_templates
					WHERE id = ?',
					[$id]);

				$rules = db_fetch_assoc_prepared('SELECT *
					FROM plugin_thold_host_template
					WHERE host_template_id = ?',
					[$host_template_id]);

				$thold_rules = [];

				if (cacti_sizeof($rules)) {
					foreach ($rules as $r) {
						$ht_hash = db_fetch_cell_prepared('SELECT hash
							FROM host_template
							WHERE id = ?',
							[$r['host_template_id']]);

						$tt_details = db_fetch_row_prepared('SELECT *
							FROM thold_template
							WHERE id = ?',
							[$r['thold_template_id']]);

						if (!is_array($tt_details)) {
							continue;
						}

						$data_source_hash = db_fetch_cell_prepared('SELECT hash
							FROM data_template_rrd
							WHERE id = ?',
							[$tt_details['data_source_id']]);

						$tt_details['data_source_hash'] = $data_source_hash;

						// unset id's that will be recalculated on import
						unset($tt_details['id']);
						unset($rr_details['data_source_id']);
						unset($tt_details['data_template_id']);

						$thold_rules[] = [
							'host_template_id'  => $ht_hash,
							'thold_template_id' => $tt_details['hash'],
							'thold_template'    => $tt_details
						];
					}
				}

				$device['thold_rules'] = $thold_rules;
			}

			// collapse the data object into the json object
			$devices[] = $device;
		}

		$json_array['device'] = $devices;
	}

	return $json_array;
}

/**
 * Creates a tree structure based on the provided tree and branch data.
 *
 * @param array $tree_data   An associative array containing the tree data.
 * @param array $branch_data Data related to the branches of the tree.
 *
 * @return array The resulting tree structure as an associative array.
 */
function automation_tree_rule_create_tree(array $tree_data, array $branch_data) : array {
	if (cacti_sizeof($tree_data)) {
		$tree_id = db_fetch_cell_prepared('SELECT id FROM graph_tree WHERE name = ?', [$tree_data['name']]);

		$parent = 0;

		if (empty($tree_id)) {
			$save       = [];
			$save['id'] = 0;

			$save['name']          = $tree_data['name'];
			$save['sort_type']     = $tree_data['sort_type'];
			$save['last_modified'] = date('Y-m-d H:i:s', time());
			$save['enabled']       = 'on';
			$save['modified_by']   = $_SESSION[SESS_USER_ID];
			$save['sequence']      = api_tree_get_max_sequence() + 1;
			$save['user_id']       = $_SESSION[SESS_USER_ID];

			$tree_id = sql_save($save, 'graph_tree');
		}

		if (!empty($tree_id)) {
			if (cacti_sizeof($branch_data)) {
				$parent = 0;

				foreach ($branch_data as $branch) {
					$branch_id = db_fetch_cell_prepared('SELECT id
						FROM graph_tree_items
						WHERE parent = ?
						AND title = ?',
						[$parent, $branch['title']]);

					if (empty($branch_id)) {
						$save = [];
						$save = $branch;

						// what we know from the imported object
						$save['id']            = 0;
						$save['parent']        = $parent;
						$save['graph_tree_id'] = $tree_id;

						// since we are only dealing with branches
						$save['local_graph_id'] = 0;
						$save['host_id']        = 0;
						$save['site_id']        = 0;

						$parent = sql_save($save, 'graph_tree_items');
					} else {
						$parent = $branch_id;
					}
				}
			}
		}

		return [$tree_id, $parent];
	} else {
		return [0, 0];
	}
}

/**
 * Exports the branches of a device rule automation for a given tree and branch.
 *
 * @param  int   $tree_id   The ID of the tree.
 * @param  int   $branch_id The ID of the branch.
 * @return array The exported branches.
 */
function automation_device_rule_export_branches(int $tree_id, int $branch_id) : array {
	if ($branch_id == 0) {
		return [];
	}

	$branches = [];

	while (true) {
		$branch = db_fetch_row_prepared('SELECT *
			FROM graph_tree_items
			WHERE graph_tree_id = ?
			AND id = ?',
			[$tree_id, $branch_id]);

		if (cacti_sizeof($branch)) {
			$parent = $branch['parent'];

			unset($branch['id']);
			unset($branch['parent']);
			unset($branch['graph_tree_id']);
			unset($branch['local_graph_id']);
			unset($branch['host_id']);
			unset($branch['site_id']);

			$branches[] = $branch;

			if ($parent == 0) {
				break;
			} else {
				$branch_id = $parent;
			}
		} else {
			break;
		}
	}

	return array_reverse($branches);
}

/**
 * Exports automation graph rules based on the provided graph rule IDs.
 *
 * @param mixed $graph_rule_ids An array of graph rule IDs to be exported or a single id.
 *
 * @return array The exported graph rules.
 */
function automation_graph_rule_export(mixed $graph_rule_ids) : array {
	if (!is_array($graph_rule_ids)) {
		$export_name = db_fetch_cell_prepared("SELECT CONCAT('automation_graphs_rule_', name)
			FROM automation_graph_rules
			WHERE id = ?",
			[$graph_rule_ids]);

		$graph_rule_ids = [$graph_rule_ids];
	} else {
		$export_name = 'automation_graph_rules_multiple';
	}

	$json_array = [];

	$json_array['name']        = clean_up_name(cacti_strtolower($export_name));
	$json_array['export_name'] = $json_array['name'] . '.json';

	if (cacti_sizeof($graph_rule_ids)) {
		$graph_rules = [];

		foreach ($graph_rule_ids as $rule_id) {
			// get the snmp options item data
			$graph_rule = db_fetch_row_prepared('SELECT *
				FROM automation_graph_rules
				WHERE id = ?',
				[$rule_id]);

			if (!is_array($graph_rule)) {
				continue;
			}

			$graph_rule['snmp_query_id'] = db_fetch_cell_prepared('SELECT hash
				FROM snmp_query
				WHERE id = ?',
				[$graph_rule['snmp_query_id']]);

			$graph_rule['graph_type_id'] = db_fetch_cell_prepared('SELECT hash
				FROM snmp_query_graph
				WHERE id = ?',
				[$graph_rule['graph_type_id']]);

			// get the snmp options item data
			$graph_rule_items = db_fetch_assoc_prepared('SELECT gri.*
				FROM automation_graph_rule_items AS gri
				WHERE gri.rule_id = ?',
				[$rule_id]);

			// remove objects that have a hash
			foreach ($graph_rule_items as $grindex => $rule_item) {
				unset($graph_rule_items[$grindex]['id']);
				unset($graph_rule_items[$grindex]['rule_id']);
			}

			// unset the rule id
			unset($graph_rule['id']);

			// collapse the graph rule items
			$graph_rule['graph_rule_items'] = $graph_rule_items;

			// match items
			$graph_match_items = db_fetch_assoc_prepared('SELECT mri.*
				FROM automation_match_rule_items AS mri
				WHERE mri.rule_id = ?
				AND mri.rule_type IN (1,2)',
				[$rule_id]);

			// remove objects that have a hash
			foreach ($graph_match_items as $gmindex => $rule_item) {
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

/**
 * Exports automation tree rules based on the provided tree rule IDs.
 *
 * @param mixed $tree_rule_ids An array of tree rule IDs to be exported or a single id.
 *
 * @return array The exported tree rules.
 */
function automation_tree_rule_export(mixed $tree_rule_ids) : array {
	if (!is_array($tree_rule_ids)) {
		$export_name = db_fetch_cell_prepared("SELECT CONCAT('automation_tree_rule_', name)
			FROM automation_tree_rules
			WHERE id = ?",
			[$tree_rule_ids]);

		$tree_rule_ids = [$tree_rule_ids];
	} else {
		$export_name = 'automation_tree_rules_multiple';
	}

	$json_array = [];

	$json_array['name']        = clean_up_name(cacti_strtolower($export_name));
	$json_array['export_name'] = $json_array['name'] . '.json';

	if (cacti_sizeof($tree_rule_ids)) {
		$tree_rules = [];

		foreach ($tree_rule_ids as $rule_id) {
			// get the snmp options item data
			$tree_rule = db_fetch_row_prepared('SELECT *
				FROM automation_tree_rules
				WHERE id = ?',
				[$rule_id]);

			if (!cacti_sizeof($tree_rule)) {
				raise_message('rule_missing', __('Can not find the Tree Rule with the ID %s', $rule_id), MESSAGE_LEVEL_ERROR);

				return [];
			}

			// get the snmp options item data
			$tree_rule_items = db_fetch_assoc_prepared('SELECT gri.*
				FROM automation_tree_rule_items AS gri
				WHERE gri.rule_id = ?',
				[$rule_id]);

			// remove objects that have a hash
			foreach ($tree_rule_items as $trindex => $rule_item) {
				unset($tree_rule_items[$trindex]['id']);
				unset($tree_rule_items[$trindex]['rule_id']);
			}

			// unset the rule id
			unset($tree_rule['id']);

			// pick up the tree and branch name as they may not be on the foreign system
			$tree_rule['tree_data']        = db_fetch_row_prepared('SELECT name, sort_type FROM graph_tree WHERE id = ?', [$tree_rule['tree_id']]); // @phpstan-ignore-line
			$tree_rule['tree_branch_data'] = automation_device_rule_export_branches($tree_rule['tree_id'], $tree_rule['tree_item_id']); // @phpstan-ignore-line

			// collapse the graph rule items
			$tree_rule['tree_rule_items'] = $tree_rule_items;

			// match items
			$tree_match_items = db_fetch_assoc_prepared('SELECT mri.*
				FROM automation_match_rule_items AS mri
				WHERE mri.rule_id = ?
				AND mri.rule_type IN (3,4)',
				[$rule_id]);

			// remove objects that have a hash
			foreach ($tree_match_items as $tmindex => $rule_item) {
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

/**
 * Exports SNMP options based on the provided SNMP option IDs.
 *
 * @param mixed $snmp_option_ids An array of SNMP option IDs to be exported or a single id.
 *
 * @return array An array containing the exported SNMP options.
 */
function automation_snmp_option_export(mixed $snmp_option_ids) : array {
	if (!is_array($snmp_option_ids)) {
		$export_name = db_fetch_cell_prepared("SELECT CONCAT('automation_snmp_option_', name)
			FROM automation_snmp
			WHERE id = ?",
			[$snmp_option_ids]);

		$snmp_option_ids = [$snmp_option_ids];
	} else {
		$export_name = 'automation_snmp_options_multiple';
	}

	$json_array = [];

	$json_array['name']        = clean_up_name(cacti_strtolower($export_name));
	$json_array['export_name'] = $json_array['name'] . '.json';

	if (cacti_sizeof($snmp_option_ids)) {
		$options = [];

		foreach ($snmp_option_ids as $option) {
			// get the snmp options data
			$snmp_option = db_fetch_row_prepared('SELECT *
				FROM automation_snmp
				WHERE id = ?',
				[$option]);

			// remove objects that have a hash
			unset($snmp_option['id']);

			// get the snmp options item data
			$snmp_items = db_fetch_assoc_prepared('SELECT *
				FROM automation_snmp_items
				WHERE snmp_id = ?',
				[$option]);

			// remove objects that have a hash
			foreach ($snmp_items as $index => $item) {
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

			// collapse the snmp items into snmp
			$snmp_option['snmp_items'] = $snmp_items;

			$options[] = $snmp_option;
		}

		$json_array['snmp'] = $options;
	}

	return $json_array;
}

/**
 * Validates the uploaded automation file.
 *
 * @return array|bool An array containing the uploaded data if the file is valid, or false if the file is invalid.
 */
function automation_validate_upload() : array|bool {
	// check file transfer if used
	if (isset($_FILES['import_file'])) {
		// check for errors first
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

		// check mine type of the uploaded file
		if ($_FILES['import_file']['type'] != 'application/json') {
			raise_message('ife', __('Invalid file extension.'), MESSAGE_LEVEL_ERROR);

			return false;
		}

		$content = file_get_contents($_FILES['import_file']['tmp_name']);

		if ($content === false) {
			return false;
		}

		return json_decode($content, true);
	}

	raise_message('nfu2', __('No file uploaded.'), MESSAGE_LEVEL_ERROR);

	return false;
}

/**
 * Imports SNMP options for automation.
 *
 * @param  array $snmp An array containing SNMP options to be imported.
 * @return array An array containing the results of the import operation.
 */
function automation_snmp_option_import(array $snmp) : array {
	$debug_data = [];

	$snmp_id = 0;

	foreach ($snmp as $column => $coldata) {
		switch($column) {
			case 'hash':
				$save['id']   = automation_hash_to_id('snmp', $coldata);
				$save['hash'] = $coldata;

				break;
			case 'name':
				$save['name'] = $coldata;
				$snmp_id      = sql_save($save, 'automation_snmp');

				if ($snmp_id) {
					if (CACTI_WEB) {
						$debug_data['success'][] = __esc('Automation Network SNMP Rule \'%s\' %s!', $save['name'], ($save['id'] > 0 ? __('Updated') : __('Imported')));
					} else {
						$debug_data['success'][] = __('Automation Network SNMP Rule \'%s\' %s!', $save['name'], ($save['id'] > 0 ? __('Updated') : __('Imported')));
					}
				} else {
					if (CACTI_WEB) {
						$debug_data['failure'][] = __esc('Automation Network SNMP Rule \'%s\' %s Failed!', $save['name'], ($save['id'] > 0 ? __('Update') : __('Import')));
					} else {
						$debug_data['failure'][] = __('Automation Network SNMP Rule \'%s\' %s Failed!', $save['name'], ($save['id'] > 0 ? __('Update') : __('Import')));
					}
				}

				break;
			case 'snmp_items':
				foreach ($coldata as $snmp_options) {
					$save = [];

					$save['snmp_id'] = $snmp_id;

					foreach ($snmp_options as $option => $optdata) {
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
						if (CACTI_WEB) {
							$debug_data['success'][] = __esc('Automation Network SNMP Option %s!', ($save['id'] > 0 ? __('Updated') : __('Imported')));
						} else {
							$debug_data['success'][] = __('Automation Network SNMP Option %s!', ($save['id'] > 0 ? __('Updated') : __('Imported')));
						}
					} else {
						if (CACTI_WEB) {
							$debug_data['failure'][] = __esc('Automation Network SNMP Option %s Failed!', ($save['id'] > 0 ? __('Update') : __('Import')));
						} else {
							$debug_data['failure'][] = __('Automation Network SNMP Option %s Failed!', ($save['id'] > 0 ? __('Update') : __('Import')));
						}
					}
				}

				break;
		}
	}

	return $debug_data;
}

/**
 * Imports network data from a JSON string.
 *
 * @param mixed $json_data The JSON string containing network data to be imported.
 *
 * @return array An array containing the results of the import operation.
 */
function automation_network_import(mixed $json_data) : array {
	$debug_data = [];

	/**
	 * This routine will work from the bottom up so that we can maintain hash to id rules
	 * all the way up the rules.  So, in this case we will import the snmp_options first
	 * followed by the network's.
	 */
	if (cacti_sizeof($json_data) && isset($json_data['network'])) {
		$error = false;
		$save  = [];

		foreach ($json_data['network'] as $data) {
			if (isset($data['snmp'])) {
				$debug_data += automation_snmp_option_import($data['snmp']);
				unset($data['snmp']);

				$save  = [];
			} else {
				$error = true;

				$debug_data['errors'][] = __('The Automation Network Rule does not include any SNMP Options!');
			}

			if (!automation_validate_import_columns('automation_networks', $data, $debug_data)) {
				$debug_data['errors'][] = __('The Automation Network Rule import columns do not match the database schema');

				$error = true;
			}

			$name = 'Unknown Device Template';

			if (!$error) {
				foreach ($data as $column => $coldata) {
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
								FROM host_template
								WHERE hash = ?',
								[$coldata]);

							if (!cacti_sizeof($template)) {
								$debug_data['errors'][] = __('The Device Template related to the Network Rule is not loaded in this Cacti System.  Please edit this Network and set the Device Template, or Import the Device Template and then re-import this Automation Rule.');

								$error = true;

								$save['host_template'] = 0;

								$name = __('Unknown Device Template');
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

				// save the automation network first
				$id = sql_save($save, 'automation_networks');

				if ($id) {
					if (CACTI_WEB) {
						$debug_data['success'][] = __esc('Automation Network Rule \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
					} else {
						$debug_data['success'][] = __('Automation Network Rule \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
					}
				} else {
					if (CACTI_WEB) {
						$debug_data['failure'][] = __esc('Automation Network Rule \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
					} else {
						$debug_data['failure'][] = __('Automation Network Rule \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
					}
				}
			}
		}
	} else {
		$debug_data['failure'][] = __('Automation Network Rule Import data is either for another object type or not JSON formatted.');
	}

	return $debug_data;
}

/**
 * Imports automation graph rules from JSON data.
 *
 * @param mixed $json_data The JSON data containing the automation graph rules.
 *
 * @return array An array containing the results of the import process.
 */
function automation_graph_rule_import(mixed $json_data) : array {
	$debug_data = [];

	/**
	 * This routine will take two passes through the data.  In the first pass, we will ensure
	 * that all the required Cacti Template objects are in the database.  If they are not
	 * then we will abandon the import process.
	 *
	 * Once we have verified all the hashes to id's we will commence with the import from
	 * top to bottom in the JSON array.
	 */
	if (cacti_sizeof($json_data) && isset($json_data['graph_rules'])) {
		$error   = false;
		$save    = [];
		$sqids   = [];
		$sqgtids = [];

		foreach ($json_data['graph_rules'] as $rule) {
			$hash = $rule['hash'];

			if (isset($rule['snmp_query_id']) && $rule['snmp_query_id'] != '') {
				$hash          = $rule['snmp_query_id'];
				$snmp_query_id = db_fetch_cell_prepared('SELECT id FROM snmp_query WHERE hash = ?', [$hash]);

				if (empty($snmp_query_id)) {
					$error                  = true;
					$debug_data['errors'][] = __('The Cacti install does not include the Data Query with the hash \'%s\'!', $hash);
				} else {
					$sqids[$hash] = $snmp_query_id;
				}
			}

			if (isset($rule['graph_type_id']) && $rule['graph_type_id'] != '') {
				$hash          = $rule['graph_type_id'];
				$graph_type_id = db_fetch_cell_prepared('SELECT id FROM snmp_query_graph WHERE hash = ?', [$hash]);

				if (empty($graph_type_id)) {
					$error                  = true;
					$debug_data['errors'][] = __('The Cacti install does not include the Data Query Graph mapping with the hash \'%s\'!', $hash);
				} else {
					$sqgtids[$hash] = $graph_type_id;
				}
			}
		}

		if (!$error) {
			foreach ($json_data['graph_rules'] as $rule) {
				$hash = $rule['hash'];

				// prepare the save array
				$save = $rule;

				// remove object that don't belong
				unset($save['graph_rule_items']);
				unset($save['graph_match_items']);

				// Save the name
				$name = $save['name'];

				$save['id']            = db_fetch_cell_prepared('SELECT id FROM automation_graph_rules WHERE hash = ?', [$hash]);
				$save['snmp_query_id'] = $sqids[$rule['snmp_query_id']];
				$save['graph_type_id'] = $sqgtids[$rule['graph_type_id']];

				$graph_rule_id = sql_save($save, 'automation_graph_rules');

				$graph_rule_ids[] = $graph_rule_id;

				if ($graph_rule_id) {
					if (CACTI_WEB) {
						$debug_data['success'][] = __esc('Automation Graph Rule \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
					} else {
						$debug_data['success'][] = __('Automation Graph Rule \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
					}
				} else {
					if (CACTI_WEB) {
						$debug_data['failure'][] = __esc('Automation Graph Rule \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
					} else {
						$debug_data['failure'][] = __('Automation Graph Rule \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
					}
				}

				if (cacti_sizeof($rule['graph_rule_items'])) {
					foreach ($rule['graph_rule_items'] as $rule_item) {
						$hash = $rule_item['hash'];

						$save = $rule_item;

						$save['id']      = db_fetch_cell_prepared('SELECT id FROM automation_graph_rule_items WHERE hash = ?', [$hash]);
						$save['rule_id'] = $graph_rule_id;

						$rule_item_id = sql_save($save, 'automation_graph_rule_items');

						$graph_rule_item_ids[] = $rule_item_id;

						if ($rule_item_id) {
							if (CACTI_WEB) {
								$debug_data['success'][] = __esc('Automation Graph Rule Item \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
							} else {
								$debug_data['success'][] = __('Automation Graph Rule Item \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
							}
						} else {
							if (CACTI_WEB) {
								$debug_data['failure'][] = __esc('Automation Graph Rule Item \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
							} else {
								$debug_data['failure'][] = __('Automation Graph Rule Item \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
							}
						}
					}
				}

				if (cacti_sizeof($rule['graph_match_items'])) {
					foreach ($rule['graph_match_items'] as $match_item) {
						$hash = $match_item['hash'];

						$save = $match_item;

						$save['id']      = db_fetch_cell_prepared('SELECT id FROM automation_match_rule_items WHERE hash = ?', [$hash]);
						$save['rule_id'] = $graph_rule_id;

						$rule_item_id = sql_save($save, 'automation_match_rule_items');

						$graph_rule_item_ids[] = $rule_item_id;

						if ($rule_item_id) {
							if (CACTI_WEB) {
								$debug_data['success'][] = __esc('Automation Graph Rule Match Item \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
							} else {
								$debug_data['success'][] = __('Automation Graph Rule Match Item \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
							}
						} else {
							if (CACTI_WEB) {
								$debug_data['failure'][] = __esc('Automation Graph Rule Match Item \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
							} else {
								$debug_data['failure'][] = __('Automation Graph Rule Match Item \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
							}
						}
					}
				}
			}
		}
	} else {
		$debug_data['failure'][] = __('Automation Graph Rule Import data is either for another object type or not JSON formatted.');
	}

	return $debug_data;
}

/**
 * Imports automation tree rules from JSON data.
 *
 * @param mixed $json_data     The JSON data containing the automation tree rules.
 * @param bool  $tree_branches Optional. Whether to include tree branches in the import. Default is false.
 *
 * @return array An array containing the results of the import process.
 */
function automation_tree_rule_import(mixed $json_data, bool $tree_branches = false) : array {
	$debug_data = [];

	/**
	 * This routine will take two passes through the data.  In the first pass, we will ensure
	 * that all the required Cacti Template objects are in the database.  If they are not
	 * then we will abandon the import process.
	 *
	 * Once we have verified all the hashes to id's we will commence with the import from
	 * top to bottom in the JSON array.
	 */
	if (cacti_sizeof($json_data) && isset($json_data['tree_rules'])) {
		$error   = false;
		$save    = [];

		foreach ($json_data['tree_rules'] as $rule) {
			$hash = $rule['hash'];
			$name = $rule['name'];

			// prepare the save array
			$save = $rule;

			// unset the Tree and Branch ids
			$save['tree_id']      = 0;
			$save['tree_item_id'] = 0;

			// until we get the tree create done
			unset($save['tree_data']);
			unset($save['tree_branch_data']);

			if ($tree_branches) {
				if (isset($rule['tree_data']) && isset($rule['tree_branch_data'])) {
					[$save['tree_id'], $save['tree_item_id']] = automation_tree_rule_create_tree($rule['tree_data'], $rule['tree_branch_data']);
				}
			}

			$save['id'] = db_fetch_cell_prepared('SELECT id FROM automation_tree_rules WHERE hash = ?', [$hash]);

			// unset things that don't belong
			unset($save['tree_rule_items']);
			unset($save['tree_match_items']);

			$tree_rule_id = sql_save($save, 'automation_tree_rules');

			$tree_rule_ids[] = $tree_rule_id;

			if ($tree_rule_id) {
				if (CACTI_WEB) {
					$debug_data['success'][] = __esc('Automation Tree Rule \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
				} else {
					$debug_data['success'][] = __('Automation Tree Rule \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
				}
			} else {
				if (CACTI_WEB) {
					$debug_data['failure'][] = __esc('Automation Tree Device Rule \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
				} else {
					$debug_data['failure'][] = __('Automation Tree Device Rule \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
				}
			}

			if (cacti_sizeof($rule['tree_rule_items'])) {
				foreach ($rule['tree_rule_items'] as $rule_item) {
					$hash = $rule_item['hash'];

					$save = $rule_item;

					$save['id']      = db_fetch_cell_prepared('SELECT id FROM automation_tree_rule_items WHERE hash = ?', [$hash]);
					$save['rule_id'] = $tree_rule_id;

					$rule_item_id = sql_save($save, 'automation_tree_rule_items');

					if ($rule_item_id) {
						if (CACTI_WEB) {
							$debug_data['success'][] = __esc('Automation Tree Rule Item \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
						} else {
							$debug_data['success'][] = __('Automation Tree Rule Item \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
						}
					} else {
						if (CACTI_WEB) {
							$debug_data['failure'][] = __esc('Automation Tree Device Rule Item \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
						} else {
							$debug_data['failure'][] = __('Automation Tree Device Rule Item \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
						}
					}

					$tree_rule_item_ids[] = $rule_item_id;
				}
			}

			if (cacti_sizeof($rule['tree_match_items'])) {
				foreach ($rule['tree_match_items'] as $match_item) {
					$hash = $match_item['hash'];

					$save = $match_item;

					$save['id']      = db_fetch_cell_prepared('SELECT id FROM automation_match_rule_items WHERE hash = ?', [$hash]);
					$save['rule_id'] = $tree_rule_id;

					$rule_item_id = sql_save($save, 'automation_match_rule_items');

					$tree_rule_item_ids[] = $rule_item_id;

					if ($rule_item_id) {
						if (CACTI_WEB) {
							$debug_data['success'][] = __esc('Automation Tree Rule Match Item \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
						} else {
							$debug_data['success'][] = __('Automation Tree Rule Match Item \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
						}
					} else {
						if (CACTI_WEB) {
							$debug_data['failure'][] = __esc('Automation Tree Device Rule Match Item \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
						} else {
							$debug_data['failure'][] = __('Automation Tree Device Rule Match Item \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
						}
					}
				}
			}
		}
	} else {
		$debug_data['failure'][] = __('Automation Tree Rule Import data is either for another object type or not JSON formatted.');
	}

	return $debug_data;
}

/**
 * Imports an automation template from JSON data.
 *
 * @param mixed $json_data     The JSON data to import.
 * @param bool  $tree_branches Optional. Whether to include tree branches in the import. Default is false.
 *
 * @return array An array containing the result of the import process.
 */
function automation_template_import(mixed $json_data, bool $tree_branches = false) : array {
	$debug_data = [];

	/**
	 * This routine will take two passes through the data.  In the first pass, we will ensure
	 * that all the required Cacti Template objects are in the database.  If they are not
	 * then we will abandon the import process.
	 *
	 * Once we have verified all the hashes to id's we will commence with the import from
	 * top to bottom in the JSON array.
	 */
	if (cacti_sizeof($json_data) && isset($json_data['device'])) {
		$error   = false;
		$save    = [];
		$htids   = [];
		$sqids   = [];
		$sqgtids = [];

		foreach ($json_data['device'] as $data) {
			$hash             = $data['host_template'];
			$host_template_id = db_fetch_cell_prepared('SELECT id FROM host_template WHERE hash = ?', [$hash]);

			if (empty($host_template_id)) {
				$error                  = true;
				$debug_data['errors'][] = __('The Cacti install does not include the Device Template with the hash \'%s\'!', $hash);
			} else {
				$htids[$hash] = $host_template_id;
			}

			if (cacti_sizeof($data['graph_rules'])) {
				foreach ($data['graph_rules'] as $rule) {
					$hash = $rule['hash'];

					if (isset($rule['snmp_query_id']) && $rule['snmp_query_id'] != '') {
						$hash          = $rule['snmp_query_id'];
						$snmp_query_id = db_fetch_cell_prepared('SELECT id FROM snmp_query WHERE hash = ?', [$hash]);

						if (empty($snmp_query_id)) {
							$error                  = true;
							$debug_data['errors'][] = __('The Cacti install does not include the Data Query with the hash \'%s\'!', $hash);
						} else {
							$sqids[$hash] = $snmp_query_id;
						}
					}

					if (isset($rule['graph_type_id']) && $rule['graph_type_id'] != '') {
						$hash          = $rule['graph_type_id'];
						$graph_type_id = db_fetch_cell_prepared('SELECT id FROM snmp_query_graph WHERE hash = ?', [$hash]);

						if (empty($graph_type_id)) {
							$error                  = true;
							$debug_data['errors'][] = __('The Cacti install does not include the Data Query Graph mapping with the hash \'%s\'!', $hash);
						} else {
							$sqgtids[$hash] = $graph_type_id;
						}
					}
				}
			}
		}

		if (!$error) {
			foreach ($json_data['device'] as $data) {
				$hash = $data['hash'];

				// prepare the save array
				$save = $data;

				$save['id']            = db_fetch_cell_prepared('SELECT id FROM automation_templates WHERE hash = ?', [$hash]);
				$save['host_template'] = $htids[$save['host_template']];

				unset($save['device_rules']);
				unset($save['graph_rules']);
				unset($save['tree_rules']);
				unset($save['thold_rules']);

				$device_rule_id = sql_save($save, 'automation_templates');

				$name = db_fetch_cell_prepared('SELECT name
					FROM host_template
					WHERE id = ?',
					[$save['host_template']]);

				if ($device_rule_id) {
					if (CACTI_WEB) {
						$debug_data['success'][] = __esc('Automation Device Rule \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
					} else {
						$debug_data['success'][] = __('Automation Device Rule \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
					}
				} else {
					if (CACTI_WEB) {
						$debug_data['failure'][] = __esc('Automation Device Rule \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
					} else {
						$debug_data['failure'][] = __('Automation Device Rule \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
					}
				}

				/**
				 * We have to do Graph Rules and Tree Rules Next as
				 * the Device Template rules require that they exist first.
				 * Also, it must be noted that the Tree rules specify a
				 * destination Tree, which may or may not exist on the
				 * foreign system.
				 *
				 * So, for these next set of data, we have to go out of order.
				 * We will process in the order below.
				 *
				 * - Graph Rules
				 * - Tree Rules (where the tree_id will be set to 0
				 * - Device Rules
				 *
				 */

				// we will use these ID's to remove deleted objects
				$device_rule_ids      = [];
				$graph_rule_ids       = [];
				$graph_rule_items_ids = [];
				$graph_match_item_ids = [];

				if (cacti_sizeof($data['graph_rules'])) {
					foreach ($data['graph_rules'] as $rule) {
						$hash = $rule['hash'];

						// prepare the save array
						$save = $rule;

						// Set the name
						$name = $save['name'];

						// remove object that don't belong
						unset($save['graph_rule_items']);
						unset($save['graph_match_items']);

						$save['id']            = db_fetch_cell_prepared('SELECT id FROM automation_graph_rules WHERE hash = ?', [$hash]);
						$save['snmp_query_id'] = $sqids[$rule['snmp_query_id']];
						$save['graph_type_id'] = $sqgtids[$rule['graph_type_id']];

						$graph_rule_id = sql_save($save, 'automation_graph_rules');

						$graph_rule_ids[] = $graph_rule_id;

						if ($graph_rule_id) {
							if (CACTI_WEB) {
								$debug_data['success'][] = __esc('Automation Graph Rule \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
							} else {
								$debug_data['success'][] = __('Automation Graph Rule \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
							}
						} else {
							if (CACTI_WEB) {
								$debug_data['failure'][] = __esc('Automation Graph Rule \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
							} else {
								$debug_data['failure'][] = __('Automation Graph Rule \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
							}
						}

						if (cacti_sizeof($rule['graph_rule_items'])) {
							foreach ($rule['graph_rule_items'] as $rule_item) {
								$hash = $rule_item['hash'];

								$save = $rule_item;

								$save['id']      = db_fetch_cell_prepared('SELECT id FROM automation_graph_rule_items WHERE hash = ?', [$hash]);
								$save['rule_id'] = $graph_rule_id;

								$rule_item_id = sql_save($save, 'automation_graph_rule_items');

								$graph_rule_item_ids[] = $rule_item_id;

								if ($rule_item_id) {
									if (CACTI_WEB) {
										$debug_data['success'][] = __esc('Automation Graph Rule Item \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
									} else {
										$debug_data['success'][] = __('Automation Graph Rule Item \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
									}
								} else {
									if (CACTI_WEB) {
										$debug_data['failure'][] = __esc('Automation Graph Rule Item \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
									} else {
										$debug_data['failure'][] = __('Automation Graph Rule Item \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
									}
								}
							}
						}

						if (cacti_sizeof($rule['graph_match_items'])) {
							foreach ($rule['graph_match_items'] as $match_item) {
								$hash = $match_item['hash'];

								$save = $match_item;

								$save['id']      = db_fetch_cell_prepared('SELECT id FROM automation_match_rule_items WHERE hash = ?', [$hash]);
								$save['rule_id'] = $graph_rule_id;

								$rule_item_id = sql_save($save, 'automation_match_rule_items');

								$graph_rule_item_ids[] = $rule_item_id;

								if ($rule_item_id) {
									if (CACTI_WEB) {
										$debug_data['success'][] = __esc('Automation Graph Rule Match Item \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
									} else {
										$debug_data['success'][] = __('Automation Graph Rule Match Item \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
									}
								} else {
									if (CACTI_WEB) {
										$debug_data['failure'][] = __esc('Automation Graph Rule Match Item \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
									} else {
										$debug_data['failure'][] = __('Automation Graph Rule Match Item \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
									}
								}
							}
						}
					}
				}

				if (cacti_sizeof($data['tree_rules'])) {
					foreach ($data['tree_rules'] as $rule) {
						$hash = $rule['hash'];

						// prepare the save array
						$save = $rule;

						// unset the Tree id
						// ToDo: Actually Export the names of these to recreate them
						$save['tree_id']      = 0;
						$save['tree_item_id'] = 0;

						// Save the name for logging
						$name = $save['name'];

						// until we get the tree create done
						unset($save['tree_data']);
						unset($save['tree_branch_data']);

						if ($tree_branches) {
							if (isset($rule['tree_data']) && isset($rule['tree_branch_data'])) {
								[$save['tree_id'], $save['tree_item_id']] = automation_tree_rule_create_tree($rule['tree_data'], $rule['tree_branch_data']);
							}
						}

						$save['id'] = db_fetch_cell_prepared('SELECT id FROM automation_tree_rules WHERE hash = ?', [$hash]);

						// unset things that don't belong
						unset($save['tree_rule_items']);
						unset($save['tree_match_items']);

						$tree_rule_id = sql_save($save, 'automation_tree_rules');

						$tree_rule_ids[] = $tree_rule_id;

						if ($tree_rule_id) {
							if (CACTI_WEB) {
								$debug_data['success'][] = __esc('Automation Tree Rule \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
							} else {
								$debug_data['success'][] = __('Automation Tree Rule \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
							}
						} else {
							if (CACTI_WEB) {
								$debug_data['failure'][] = __esc('Automation Tree Device Rule \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
							} else {
								$debug_data['failure'][] = __('Automation Tree Device Rule \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
							}
						}

						if (cacti_sizeof($rule['tree_rule_items'])) {
							foreach ($rule['tree_rule_items'] as $rule_item) {
								$hash = $rule_item['hash'];

								$save = $rule_item;

								$save['id']      = db_fetch_cell_prepared('SELECT id FROM automation_tree_rule_items WHERE hash = ?', [$hash]);
								$save['rule_id'] = $tree_rule_id;

								$rule_item_id = sql_save($save, 'automation_tree_rule_items');

								if ($rule_item_id) {
									if (CACTI_WEB) {
										$debug_data['success'][] = __esc('Automation Tree Rule Item \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
									} else {
										$debug_data['success'][] = __('Automation Tree Rule Item \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
									}
								} else {
									if (CACTI_WEB) {
										$debug_data['failure'][] = __esc('Automation Tree Device Rule Item \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
									} else {
										$debug_data['failure'][] = __('Automation Tree Device Rule Item \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
									}
								}

								$tree_rule_item_ids[] = $rule_item_id;
							}
						}

						if (cacti_sizeof($rule['tree_match_items'])) {
							foreach ($rule['tree_match_items'] as $match_item) {
								$hash = $match_item['hash'];

								$save = $match_item;

								$save['id']      = db_fetch_cell_prepared('SELECT id FROM automation_match_rule_items WHERE hash = ?', [$hash]);
								$save['rule_id'] = $tree_rule_id;

								$rule_item_id = sql_save($save, 'automation_match_rule_items');

								$tree_rule_item_ids[] = $rule_item_id;

								if ($rule_item_id) {
									if (CACTI_WEB) {
										$debug_data['success'][] = __esc('Automation Tree Rule Match Item \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
									} else {
										$debug_data['success'][] = __('Automation Tree Rule Match Item \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
									}
								} else {
									if (CACTI_WEB) {
										$debug_data['failure'][] = __esc('Automation Tree Device Rule Match Item \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
									} else {
										$debug_data['failure'][] = __('Automation Tree Device Rule Match Item \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
									}
								}
							}
						}
					}
				}

				/**
				 * When we process these actions, we must be aware that the rule types for devices are:
				 * rule type 1 - Graph Rule
				 * rule type 2 - Tree Rule
				 */
				if (cacti_sizeof($data['device_rules'])) {
					foreach ($data['device_rules'] as $rule) {
						$hash = $rule['hash'];

						$save = $rule;

						$save['id'] = db_fetch_cell_prepared('SELECT id
							FROM automation_templates_rules
							WHERE hash = ?',
							[$hash]);

						$save['template_id'] = $device_rule_id;

						$name = db_fetch_cell_prepared('SELECT name
							FROM host_template
							WHERE id = ?',
							[$save['template_id']]);

						if ($rule['rule_type'] == 1) {      // Graph Rules
							$save['rule_id'] = db_fetch_cell_prepared('SELECT id
								FROM automation_graph_rules
								WHERE hash = ?',
								[$rule['rule_id']]);
						} elseif ($rule['rule_type'] == 2) { // Tree Rules
							$save['rule_id'] = db_fetch_cell_prepared('SELECT id
								FROM automation_tree_rules
								WHERE hash = ?',
								[$rule['rule_id']]);
						}

						// save the automation network first
						$id = sql_save($save, 'automation_templates_rules');

						if ($id) {
							if (CACTI_WEB) {
								$debug_data['success'][] = __esc('Automation Device Rule Item \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
							} else {
								$debug_data['success'][] = __('Automation Device Rule Item \'%s\' %s!', $name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
							}
						} else {
							if (CACTI_WEB) {
								$debug_data['failure'][] = __esc('Automation Device Rule Item \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
							} else {
								$debug_data['failure'][] = __('Automation Device Rule Item \'%s\' %s Failed!', $name, ($save['id'] > 0 ? __('Update') : __('Import')));
							}
						}
					}
				}
			}

			if (isset($data['thold_rules']) && cacti_sizeof($data['thold_rules'])) {
				if (!db_table_exists('plugin_thold_host_template')) {
					$debug_data['failure'][] = __('The Thold Plugin is not Installed on this system.  So, Thresholds will not be imported.');
				} else {
					foreach ($data['thold_rules'] as $rule) {
						$thold_name = $rule['thold_template']['name'];

						$thold_template_id = db_fetch_cell_prepared('SELECT id
							FROM thold_template
							WHERE hash = ?',
							[$rule['thold_template_id']]);

						$host_template_id = db_fetch_cell_prepared('SELECT id
							FROM host_template
							WHERE hash = ?',
							[$rule['host_template_id']]);

						if (empty($thold_template_id)) {
							$save       = $rule;
							$save['id'] = 0;

							$save['data_template_id'] = db_fetch_cell_prepared('SELECT id
								FROM data_template
								WHERE hash = ?',
								[$rule['thold_template']['data_template_hash']]);

							$save['data_source_id'] = db_fetch_cell_prepared('SELECT id
								FROM data_template_rrd
								WHERE hash = ?',
								[$rule['thold_template']['data_source_hash']]);

							unset($save['data_source_hash']);

							$thold_template_id = sql_save($save, 'thold_template');

							if ($thold_template_id) {
								if (CACTI_WEB) {
									$debug_data['success'][] = __esc('Automation Threshold Template \'%s\' %s!', $thold_name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
								} else {
									$debug_data['success'][] = __('Automation Threshold Template \'%s\' %s!', $thold_name, ($save['id'] > 0 ? __('Updated') : __('Imported')));
								}
							} else {
								if (CACTI_WEB) {
									$debug_data['failure'][] = __esc('Automation Threshold Template Device \'%s\' %s Failed!', $thold_name, ($save['id'] > 0 ? __('Update') : __('Import')));
								} else {
									$debug_data['failure'][] = __('Automation Threshold Template Device \'%s\' %s Failed!', $thold_name, ($save['id'] > 0 ? __('Update') : __('Import')));
								}
							}
						}

						if ($thold_template_id > 0) {
							db_execute_prepared('REPLACE INTO plugin_thold_host_template
								(host_template_id, thold_template_id)
								VALUES (?, ?)',
								[$host_template_id, $thold_template_id]);
						}
					}
				}
			}
		}
	} else {
		$debug_data['failure'][] = __('Automation Device Rule Import data is either for another object type or not JSON formatted.');
	}

	return $debug_data;
}

/**
 * Validates the import columns for a given table.
 *
 * @param  string $table       The name of the table to validate.
 * @param  array  &$data       The data to be validated.
 * @param  array  &$debug_data An array to store debug information.
 * @return bool   Returns true if the columns are valid, false otherwise.
 */
function automation_validate_import_columns(string $table, array &$data, array &$debug_data) : bool {
	if (cacti_sizeof($data)) {
		foreach ($data as $column => $cdata) {
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

/**
 * Logs a string to Cacti's log file or optionally to the browser
 *
 * @param string $string The message to log.
 * @param int    $level  The log level, default is AUTOMATION_LOG_LOW.
 *
 * @return void
 */
function automation_log(string $string, int $level = AUTOMATION_LOG_LOW) : void {
	if (!defined('AUTOMATION_LEVEL')) {
		define('AUTOMATION_LEVEL', read_config_option('automation_log_level'));
	}

	if (AUTOMATION_LEVEL >= $level) {
		cacti_log($string, false, 'AUTOMATION');
	}
}
