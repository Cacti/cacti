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

/* get the format files */
$formats = reports_get_format_files();

$fields_reports_edit = array(
	'genhead' => array(
		'friendly_name' => __('General Settings'),
		'method' => 'spacer',
		'collapsible' => 'true'
		),
	'name' => array(
		'friendly_name' => __('Report Name'),
		'method' => 'textbox',
		'default' => __('New Report'),
		'description' => __('Give this Report a descriptive Name'),
		'max_length' => 99,
		'value' => '|arg1:name|'
		),
	'enabled' => array(
		'friendly_name' => __('Enable Report'),
		'method' => 'checkbox',
		'default' => '',
		'description' => __('Check this box to enable this Report.'),
		'value' => '|arg1:enabled|',
		'form_id' => false
		),
	'formathead' => array(
		'friendly_name' => __('Output Formatting'),
		'method' => 'spacer',
		'collapsible' => 'true'
		),
	'cformat' => array(
		'friendly_name' => __('Use Custom Format HTML'),
		'method' => 'checkbox',
		'default' => '',
		'description' => __('Check this box if you want to use custom html and CSS for the report.'),
		'value' => '|arg1:cformat|',
		'form_id' => false
		),
	'format_file' => array(
		'friendly_name' => __('Format File to Use'),
		'method' => 'drop_array',
		'default' => 'default.format',
		'description' => __('Choose the custom html wrapper and CSS file to use.  This file contains both html and CSS to wrap around your report.
		If it contains more than simply CSS, you need to place a special <REPORT> tag inside of the file.  This format tag will be replaced by the report content.  These files are located in the \'formats\' directory.'),
		'value' => '|arg1:format_file|',
		'array' => $formats
		),
	'font_size' => array(
		'friendly_name' => __('Default Text Font Size'),
		'description' => __('Defines the default font size for all text in the report including the Report Title.'),
		'default' => 16,
		'method' => 'drop_array',
		'array' => array(7 => 7, 8 => 8, 10 => 10, 12 => 12, 14 => 14, 16 => 16, 18 => 18, 20 => 20, 24 => 24, 28 => 28, 32 => 32),
		'value' => '|arg1:font_size|'
		),
	'alignment' => array(
		'friendly_name' => __('Default Object Alignment'),
		'description' => __('Defines the default Alignment for Text and Graphs.'),
		'default' => 0,
		'method' => 'drop_array',
		'array' => $alignment,
		'value' => '|arg1:alignment|'
		),
	'graph_linked' => array(
		'friendly_name' => __('Graph Linked'),
		'method' => 'checkbox',
		'default' => '',
		'description' => __('Should the Graphs be linked back to the Cacti site?'),
		'value' => '|arg1:graph_linked|'
		),
	'graphhead' => array(
		'friendly_name' => __('Graph Settings'),
		'method' => 'spacer',
		'collapsible' => 'true'
		),
	'graph_columns' => array(
		'friendly_name' => __('Graph Columns'),
		'method' => 'drop_array',
		'default' => '1',
		'array' => array(1 => 1, 2, 3, 4, 5),
		'description' => __('The number of Graph columns.'),
		'value' => '|arg1:graph_columns|'
		),
	'graph_width' => array(
		'friendly_name' => __('Graph Width'),
		'method' => 'drop_array',
		'default' => '300',
		'array' => array(100 => 100, 150 => 150, 200 => 200, 250 => 250, 300 => 300, 350 => 350, 400 => 400, 500 => 500, 600 => 600, 700 => 700, 800 => 800, 900 => 900, 1000 => 1000),
		'description' => __('The Graph width in pixels.'),
		'value' => '|arg1:graph_width|'
		),
	'graph_height' => array(
		'friendly_name' => __('Graph Height'),
		'method' => 'drop_array',
		'default' => '125',
		'array' => array(75 => 75, 100 => 100, 125 => 125, 150 => 150, 175 => 175, 200 => 200, 250 => 250, 300 => 300),
		'description' => __('The Graph height in pixels.'),
		'value' => '|arg1:graph_height|'
		),
	'thumbnails' => array(
		'friendly_name' => __('Thumbnails'),
		'method' => 'checkbox',
		'default' => '',
		'description' => __('Should the Graphs be rendered as Thumbnails?'),
		'value' => '|arg1:thumbnails|'
		),
	'freqhead' => array(
		'friendly_name' => __('Email Frequency'),
		'method' => 'spacer',
		'collapsible' => 'true'
		),
	'mailtime' => array(
		'friendly_name' => __('Next Timestamp for Sending Mail Report'),
		'description' => __('Start time for [first|next] mail to take place. All future mailing times will be based upon this start time. A good example would be 2:00am. The time must be in the future.  If a fractional time is used, say 2:00am, it is assumed to be in the future.'),
		'default' => 0,
		'method' => 'textbox',
		'size' => 20,
		'max_length' => 20,
		'value' => '|arg1:mailtime|'
		),
	'intrvl' => array(
		'friendly_name' => __('Report Interval'),
		'description' => __('Defines a Report Frequency relative to the given Mailtime above.') . '<br>' .
			__('e.g. \'Week(s)\' represents a weekly Reporting Interval.'),
		'default' => REPORTS_SCHED_INTVL_DAY,
		'method' => 'drop_array',
		'array' => $reports_interval,
		'value' => '|arg1:intrvl|'
		),
	'count' => array(
		'friendly_name' => __('Interval Frequency'),
		'description' => __('Based upon the Timespan of the Report Interval above, defines the Frequency within that Interval.') . '<br>' .
			__('e.g. If the Report Interval is \'Month(s)\', then \'2\' indicates Every \'2 Month(s) from the next Mailtime.\' Lastly, if using the Month(s) Report Intervals, the \'Day of Week\' and the \'Day of Month\' are both calculated based upon the Mailtime you specify above.'),
		'default' => REPORTS_SCHED_COUNT,
		'method' => 'textbox',
		'size' => 10,
		'max_length' => 10,
		'value' => '|arg1:count|'
		),
	'emailhead' => array(
		'friendly_name' => __('Email Sender/Receiver Details'),
		'method' => 'spacer',
		'collapsible' => 'true'
		),
	'subject' => array(
		'friendly_name' => __('Subject'),
		'method' => 'textbox',
		'default' => __('Cacti Report'),
		'description' => __('This value will be used as the default Email subject.  The report name will be used if left blank.'),
		'max_length' => 255,
		'value' => '|arg1:subject|'
		),
	'from_name' => array(
		'friendly_name' => __('From Name'),
		'method' => 'textbox',
		'default' => read_config_option('settings_from_name'),
		'description' => __('This Name will be used as the default E-mail Sender'),
		'max_length' => 255,
		'value' => '|arg1:from_name|'
		),
	'from_email' => array(
		'friendly_name' => __('From Email Address'),
		'method' => 'textbox',
		'default' => read_config_option('settings_from_email'),
		'description' => __('This Address will be used as the E-mail Senders address'),
		'max_length' => 255,
		'value' => '|arg1:from_email|'
		),
	'email' => array(
		'friendly_name' => __('To Email Address(es)'),
		'method' => 'textarea',
		'textarea_rows' => '5',
		'textarea_cols' => '60',
		'class' => 'textAreaNotes',
		'default' => '',
		'description' => __('Please separate multiple addresses by comma (,)'),
		'max_length' => 255,
		'value' => '|arg1:email|'
		),
	'bcc' => array(
		'friendly_name' => __('BCC Address(es)'),
		'method' => 'textarea',
		'textarea_rows' => '5',
		'textarea_cols' => '60',
		'class' => 'textAreaNotes',
		'default' => '',
		'description' => __('Blind carbon copy. Please separate multiple addresses by comma (,)'),
		'max_length' => 255,
		'value' => '|arg1:bcc|'
		),
	'attachment_type' => array(
		'friendly_name' => __('Image attach type'),
		'method' => 'drop_array',
		'default' => read_config_option('reports_default_image_format'),
		'description' => __('Select one of the given Types for the Image Attachments'),
		'value' => '|arg1:attachment_type|',
		'array' => $attach_types
		),
);

$report_item = array();
if (isset_request_var('item_id')) {
	$report_item = db_fetch_row_prepared('SELECT *
		FROM reports_items WHERE id = ?',
		array(get_filter_request_var('item_id')));
}

/* get the hosts sql first */
$hosts = null;
if (isset_request_var('host_template_id')) {
	if (get_filter_request_var('host_template_id') > 0) {
		$hosts = array_rekey(
			get_allowed_devices('h.host_template_id =' . get_request_var('host_template_id')),
			'id', 'description'
		);
	}
} elseif (sizeof($report_item) && $report_item['host_template_id'] > 0) {
	$hosts = array_rekey(
		get_allowed_devices('h.host_template_id =' . $report_item['host_template_id']),
		'id', 'description'
	);
}

if ($hosts == null) {
	$hosts = array_rekey(get_allowed_devices(),'id','description');
}

$graph_templates = array();
if (isset_request_var('host_id') && get_filter_request_var('host_id') > 0) {
	$graph_templates = array_rekey(
		get_allowed_graph_templates('h.id = ' . get_request_var('host_id')),
		'id', 'name'
	);
} elseif (sizeof($report_item) && $report_item['host_id'] > 0) {
	$graph_templates = array_rekey(
		get_allowed_graph_templates('h.id = ' . $report_item['host_id']),
		'id', 'name'
	);
}

$sql_where = '';
$graphs    = array();
if (isset_request_var('host_template_id')) {
	if (get_filter_request_var('host_template_id') > 0) {
		$sql_where = 'h.host_template_id=' . get_request_var('host_template_id');
	}
} elseif (sizeof($report_item) && $report_item['host_template_id'] > 0) {
	$sql_where = 'h.host_template_id=' . $report_item['host_template_id'];
}

if (isset_request_var('host_id')) {
	if (get_filter_request_var('host_id') > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ':'') . 'gl.host_id=' . get_request_var('host_id');
	}
} elseif (sizeof($report_item) && $report_item['host_id'] > 0) {
	$sql_where .= ($sql_where != '' ? ' AND ':'') . 'gl.host_id=' . $report_item['host_id'];
}

if (isset_request_var('graph_template_id')) {
	if (get_filter_request_var('graph_template_id') > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ':'') . 'gl.graph_template_id=' . get_request_var('graph_template_id');
	}
} elseif (sizeof($report_item) && $report_item['graph_template_id'] > 0) {
	$sql_where .= ($sql_where != '' ? ' AND ':'') . 'gl.graph_template_id=' . $report_item['graph_template_id'];
}

if ($sql_where != '') {
	$graphs = array_rekey(
		get_allowed_graphs($sql_where),
		'local_graph_id', 'title_cache'
	);
} else {
	$sql_where = 'gl.graph_template_id=0';
	$graphs = array_rekey(
		get_allowed_graphs($sql_where),
		'local_graph_id', 'title_cache'
	);
}

$trees = array_rekey(
	get_allowed_trees(),
	'id', 'name'
);

$sql_where = '';
if (isset_request_var('tree_id')) {
	if (get_filter_request_var('tree_id') > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ':'') . 'gt.id=' . get_request_var('tree_id');
	}
} elseif (sizeof($report_item) && $report_item['tree_id'] > 0) {
	$sql_where .= ($sql_where != '' ? ' AND ':'') . 'gt.id=' . $report_item['tree_id'];
}

$branches = array_rekey(
	get_allowed_branches($sql_where),
	'id', 'name'
);

$fields_reports_item_edit = array(
	'item_type' => array(
		'friendly_name' => __('Type'),
		'method' => 'drop_array',
		'default' => REPORTS_ITEM_GRAPH,
		'description' => __('Item Type to be added.'),
		'value' => '|arg1:item_type|',
		'on_change' => 'toggle_item_type()',
		'array' => $item_types
	),
	'tree_id' => array(
		'friendly_name' => __('Graph Tree'),
		'method' => 'drop_array',
		'default' => REPORTS_TREE_NONE,
		'none_value' => __('None'),
		'description' => __('Select a Tree to use.'),
		'value' => '|arg1:tree_id|',
		'on_change' => 'applyChange(document.reports_item_edit)',
		'array' => $trees
	),
	'branch_id' => array(
		'friendly_name' => __('Graph Tree Branch'),
		'method' => 'drop_array',
		'default' => REPORTS_TREE_NONE,
		'none_value' => __('All'),
		'description' => __('Select a Tree Branch to use.'),
		'value' => '|arg1:branch_id|',
		'on_change' => 'applyChange(document.reports_item_edit)',
		'array' => $branches
/*
		'sql' => "(SELECT id, CONCAT('" . __('Branch:') . " ', title) AS name
			FROM graph_tree_items
			WHERE graph_tree_id=|arg1:tree_id| AND host_id=0 AND local_graph_id=0
			GROUP BY name
			ORDER BY position)
			UNION
			(SELECT graph_tree_items.id, CONCAT('" . __('Device:') . " ', description) AS name
			FROM graph_tree_items
			INNER JOIN host
			ON host.id=graph_tree_items.host_id
			WHERE graph_tree_id=|arg1:tree_id|
			AND host.id IN(" . implode(',', array_keys(array_rekey(get_allowed_devices(), 'id', 'description'))) . ")
			GROUP BY name)
			ORDER BY name"
*/
	),
	'tree_cascade' => array(
		'friendly_name' => __('Cascade to Branches'),
		'method' => 'checkbox',
		'default' => '',
		'description' => __('Should all children branch Graphs be rendered?'),
		'value' => '|arg1:tree_cascade|'
	),
	'graph_name_regexp' => array(
		'friendly_name' => __('Graph Name Regular Expression'),
		'method' => 'textbox',
		'default' => '',
		'description' => __('A Perl compatible regular expression (REGEXP) used to select graphs to include from the tree.'),
		'max_length' => 255,
		'size' => 80,
		'value' => '|arg1:graph_name_regexp|'
	),
	'host_template_id' => array(
		'friendly_name' => __('Device Template'),
		'method' => 'drop_sql',
		'default' => REPORTS_HOST_NONE,
		'none_value' => __('None'),
		'description' => __('Select a Device Template to use.'),
		'value' => '|arg1:host_template_id|',
		'on_change' => 'applyChange(document.reports_item_edit)',
		'sql' => "SELECT DISTINCT ht.id, ht.name FROM host_template AS ht INNER JOIN host AS h ON h.host_template_id=ht.id ORDER BY name"
	),
	'host_id' => array(
		'friendly_name' => __('Device'),
		'method' => 'drop_array',
		'default' => REPORTS_HOST_NONE,
		'description' => __('Select a Device to specify a Graph'),
		'value' => '|arg1:host_id|',
		'none_value' => __('None'),
		'on_change' => 'applyChange(document.reports_item_edit)',
		'array' => $hosts
	),
	'graph_template_id' => array(
		'friendly_name' => __('Graph Template'),
		'method' => 'drop_array',
		'default' => '0',
		'description' => __('Select a Graph Template for the host'),
		'none_value' => __('None'),
		'on_change' => 'applyChange(document.reports_item_edit)',
		'value' => '|arg1:graph_template_id|',
		'array' => $graph_templates
	),
	'local_graph_id' => array(
		'friendly_name' => __('Graph Name'),
		'method' => 'drop_array',
		'default' => '0',
		'description' => __('The Graph to use for this report item.'),
		'none_value' => __('None'),
		'on_change' => 'graphImage(this.value)',
		'value' => '|arg1:local_graph_id|',
		'array' => $graphs
	),
	'timespan' => array(
		'friendly_name' => __('Graph Timespan'),
		'method' => 'drop_array',
		'default' => GT_LAST_DAY,
		'description' => __("Graph End Time is always set to Cacti's schedule.") . '<br>' .
			__('Graph Start Time equals Graph End Time minus given timespan'),
		'array' => $graph_timespans,
		'value' => '|arg1:timespan|'
	),
	'align' => array(
		'friendly_name' => __('Alignment'),
		'method' => 'drop_array',
		'default' => REPORTS_ALIGN_LEFT,
		'description' => __('Alignment of the Item'),
		'value' => '|arg1:align|',
		'array' => $alignment
	),
	'item_text' => array(
		'friendly_name' => __('Fixed Text'),
		'method' => 'textbox',
		'default' => '',
		'description' => __('Enter descriptive Text'),
		'max_length' => 255,
		'value' => '|arg1:item_text|'
	),
	'font_size' => array(
		'friendly_name' => __('Font Size'),
		'method' => 'drop_array',
		'default' => REPORTS_FONT_SIZE,
		'array' => array(7 => 7, 8 => 8, 10 => 10, 12 => 12, 14 => 14, 16 => 16, 18 => 18, 20 => 20, 24 => 24, 28 => 28, 32 => 32),
		'description' => __('Font Size of the Item'),
		'value' => '|arg1:font_size|'
	),
	'sequence' => array(
		'method' => 'view',
		'friendly_name' => __('Sequence'),
		'description' => __('Sequence of Item.'),
		'value' => '|arg1:sequence|'
	),
);

function reports_form_save() {
	global $config, $messages;
	# when using cacti_log: include_once($config['library_path'] . '/functions.php');

	if (isset_request_var('save_component_report')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		get_filter_request_var('font_size');
		get_filter_request_var('graph_width');
		get_filter_request_var('graph_height');
		get_filter_request_var('graph_columns');
		/* ==================================================== */
		$now = time();

		if (isempty_request_var('id')) {
			$save['user_id'] = $_SESSION['sess_user_id'];
		} else {
			$save['user_id'] = db_fetch_cell_prepared('SELECT user_id FROM reports WHERE id = ?', array(get_nfilter_request_var('id')));
		}

		$save['id']            = get_nfilter_request_var('id');
		$save['name']          = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
		$save['email']         = form_input_validate(get_nfilter_request_var('email'), 'email', '', false, 3);
		$save['enabled']       = (isset_request_var('enabled') ? 'on' : '');

		$save['cformat']       = (isset_request_var('cformat') ? 'on' : '');
		$save['format_file']   = get_nfilter_request_var('format_file');
		$save['font_size']     = form_input_validate(get_nfilter_request_var('font_size'), 'font_size', '^[0-9]+$', false, 3);
		$save['alignment']     = form_input_validate(get_nfilter_request_var('alignment'), 'alignment', '^[0-9]+$', false, 3);
		$save['graph_linked']  = (isset_request_var('graph_linked') ? 'on' : '');

		$save['graph_columns'] = form_input_validate(get_nfilter_request_var('graph_columns'), 'graph_columns', '^[0-9]+$', false, 3);
		$save['graph_width']   = form_input_validate(get_nfilter_request_var('graph_width'), 'graph_width', '^[0-9]+$', false, 3);
		$save['graph_height']  = form_input_validate(get_nfilter_request_var('graph_height'), 'graph_height', '^[0-9]+$', false, 3);
		$save['thumbnails']    = form_input_validate((isset_request_var('thumbnails') ? get_nfilter_request_var('thumbnails'):''), 'thumbnails', '', true, 3);

		$save['intrvl']        = form_input_validate(get_nfilter_request_var('intrvl'), 'intrvl', '^[-+]?[0-9]+$', false, 3);
		$save['count']         = form_input_validate(get_nfilter_request_var('count'), 'count', '^[0-9]+$', false, 3);
		$save['offset']        = '0';

		/* adjust mailtime according to rules */
		$timestamp = strtotime(get_nfilter_request_var('mailtime'));
		if ($timestamp === false) {
			$timestamp  = $now;
		} elseif (($timestamp + read_config_option('poller_interval')) < $now) {
			$timestamp += 86400;

			/* if the time is far into the past, make it the correct time, but tomorrow */
			if (($timestamp + read_config_option('poller_interval')) < $now) {
				$timestamp = strtotime('12:00am') + 86400 + date('H', $timestamp) * 3600 + date('i', $timestamp) * 60 + date('s', $timestamp);
			}

			$_SESSION['reports_message'] = __('Date/Time moved to the same time Tomorrow');

			raise_message('reports_message');
		}

		$save['mailtime']     = form_input_validate($timestamp, 'mailtime', '^[0-9]+$', false, 3);

		if (get_nfilter_request_var('subject') != '') {
			$save['subject'] = get_nfilter_request_var('subject');
		} else {
			$save['subject'] = $save['name'];
		}

		$save['from_name']        = get_nfilter_request_var('from_name');
		$save['from_email']       = get_nfilter_request_var('from_email');
		$save['bcc']              = get_nfilter_request_var('bcc');

		$atype = get_nfilter_request_var('attachment_type');
		if (($atype != REPORTS_TYPE_INLINE_PNG) &&
			($atype != REPORTS_TYPE_INLINE_JPG) &&
			($atype != REPORTS_TYPE_INLINE_GIF) &&
			($atype != REPORTS_TYPE_ATTACH_PNG) &&
			($atype != REPORTS_TYPE_ATTACH_JPG) &&
			($atype != REPORTS_TYPE_ATTACH_GIF) &&
			($atype != REPORTS_TYPE_INLINE_PNG_LN) &&
			($atype != REPORTS_TYPE_INLINE_JPG_LN) &&
			($atype != REPORTS_TYPE_INLINE_GIF_LN)) {
			$atype = REPORTS_TYPE_INLINE_PNG;
		}

		$save['attachment_type']  = form_input_validate($atype, 'attachment_type', '^[0-9]+$', false, 3);
		$save['lastsent']         = 0;

		if (!is_error_message()) {
			$id = sql_save($save, 'reports');

			if ($id) {
				raise_message('reports_save');
			} else {
				raise_message('reports_save_failed');
			}
		}

		header('Location: ' . get_reports_page() . '?action=edit&header=false&id=' . (empty($id) ? get_nfilter_request_var('id') : $id));
		exit;
	} elseif (isset_request_var('save_component_report_item')) {
		/* ================= input validation ================= */
		get_filter_request_var('report_id');
		get_filter_request_var('id');
		/* ==================================================== */

		$save = array();

		$save['id']                = get_nfilter_request_var('id');
		$save['report_id']         = form_input_validate(get_nfilter_request_var('report_id'), 'report_id', '^[0-9]+$', false, 3);
		$save['sequence']          = form_input_validate(get_nfilter_request_var('sequence'), 'sequence', '^[0-9]+$', false, 3);
		$save['item_type']         = form_input_validate(get_nfilter_request_var('item_type'), 'item_type', '^[-0-9]+$', false, 3);
		$save['tree_id']           = (isset_request_var('tree_id') ? form_input_validate(get_nfilter_request_var('tree_id'), 'tree_id', '^[-0-9]+$', true, 3) : 0);
		$save['branch_id']         = (isset_request_var('branch_id') ? form_input_validate(get_nfilter_request_var('branch_id'), 'branch_id', '^[-0-9]+$', true, 3) : 0);
		$save['tree_cascade']      = (isset_request_var('tree_cascade') ? 'on':'');
		$save['graph_name_regexp'] = get_nfilter_request_var('graph_name_regexp');
		$save['host_template_id']  = (isset_request_var('host_template_id') ? form_input_validate(get_nfilter_request_var('host_template_id'), 'host_template_id', '^[-0-9]+$', true, 3) : 0);
		$save['host_id']           = (isset_request_var('host_id') ? form_input_validate(get_nfilter_request_var('host_id'), 'host_id', '^[-0-9]+$', true, 3) : 0);
		$save['graph_template_id'] = (isset_request_var('graph_template_id') ? form_input_validate(get_nfilter_request_var('graph_template_id'), 'graph_template_id', '^[-0-9]+$', true, 3) : 0);
		$save['local_graph_id']    = (isset_request_var('local_graph_id') ? form_input_validate(get_nfilter_request_var('local_graph_id'), 'local_graph_id', '^[0-9]+$', true, 3) : 0);
		$save['timespan']          = (isset_request_var('timespan') ? form_input_validate(get_nfilter_request_var('timespan'), 'timespan', '^[0-9]+$', true, 3) : 0);
		$save['item_text']         = (isset_request_var('item_text') ? form_input_validate(get_nfilter_request_var('item_text'), 'item_text', '', true, 3) : '');
		$save['align']             = (isset_request_var('align') ? form_input_validate(get_nfilter_request_var('align'), 'align', '^[0-9]+$', true, 3) : REPORTS_ALIGN_LEFT);
		$save['font_size']         = (isset_request_var('font_size') ? form_input_validate(get_nfilter_request_var('font_size'), 'font_size', '^[0-9]+$', true, 3) : REPORTS_FONT_SIZE);

		if (!is_error_message()) {
			$item_id = sql_save($save, 'reports_items');

			if ($item_id) {
				raise_message('reports_item_save');
			} else {
				raise_message('reports_item_save_failed');
			}
		}

		header('Location: ' . get_reports_page() . '?action=item_edit&header=false&id=' . get_nfilter_request_var('report_id') . '&item_id=' . (empty($item_id) ? get_nfilter_request_var('id') : $item_id));
	} else {
		header('Location: ' . get_reports_page() . '?header=false');
	}
	exit;
}


/* ------------------------
 The 'actions' function
 ------------------------ */
function reports_form_actions() {
	global $config, $reports_actions;
	include_once($config['base_path'].'/lib/reports.php');
	/* ================= input validation ================= */
	get_filter_request_var('drp_action');
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == REPORTS_DELETE) { // delete
				db_execute('DELETE FROM reports WHERE ' . array_to_sql_or($selected_items, 'id'));
				db_execute('DELETE FROM reports_items WHERE ' . str_replace('id', 'report_id', array_to_sql_or($selected_items, 'id')));
			} elseif (get_nfilter_request_var('drp_action') == REPORTS_OWN) { // take ownership
				for ($i=0;($i<count($selected_items));$i++) {
					reports_log(__FUNCTION__ . ', takeown: ' . $selected_items[$i] . ' user: ' . $_SESSION['sess_user_id'], false, 'REPORTS TRACE', POLLER_VERBOSITY_MEDIUM);
					db_execute_prepared('UPDATE reports SET user_id = ? WHERE id = ?', array($_SESSION['sess_user_id'], $selected_items[$i]));
				}
			} elseif (get_nfilter_request_var('drp_action') == REPORTS_DUPLICATE) { // duplicate
				for ($i=0;($i<count($selected_items));$i++) {
					reports_log(__FUNCTION__ . ', duplicate: ' . $selected_items[$i] . ' name: ' . get_nfilter_request_var('name_format'), false, 'REPORTS TRACE', POLLER_VERBOSITY_MEDIUM);
					duplicate_reports($selected_items[$i], get_nfilter_request_var('name_format'));
				}
			} elseif (get_nfilter_request_var('drp_action') == REPORTS_ENABLE) { // enable
				for ($i=0;($i<count($selected_items));$i++) {
					reports_log(__FUNCTION__ . ', enable: ' . $selected_items[$i], false, 'REPORTS TRACE', POLLER_VERBOSITY_MEDIUM);
					db_execute_prepared('UPDATE reports SET enabled="on" WHERE id = ?', array($selected_items[$i]));
				}
			} elseif (get_nfilter_request_var('drp_action') == REPORTS_DISABLE) { // disable
				for ($i=0;($i<count($selected_items));$i++) {
					reports_log(__FUNCTION__ . ', disable: ' . $selected_items[$i], false, 'REPORTS TRACE', POLLER_VERBOSITY_MEDIUM);
					db_execute_prepared('UPDATE reports SET enabled="" WHERE id = ?', array($selected_items[$i]));
				}
			} elseif (get_nfilter_request_var('drp_action') == REPORTS_SEND_NOW) { // send now
				include_once($config['base_path'] . '/lib/reports.php');
				$message = '';

				for ($i=0;($i<count($selected_items));$i++) {
					$_SESSION['reports_message'] = '';
					$_SESSION['reports_error']   = '';

					reports_send($selected_items[$i]);

					if (isset($_SESSION['reports_message']) && $_SESSION['reports_message'] != '') {
						$message .= ($message != '' ? '<br>':'') . $_SESSION['reports_message'];
					}
					if (isset($_SESSION['reports_error']) && $_SESSION['reports_error'] != '') {
						$message .= ($message != '' ? '<br>':'') . "<span style='color:red;'>" . $_SESSION['reports_error'] . '</span>';
					}
				}

				if ($message != '') {
					$_SESSION['reports_message'] = $message;
					raise_message('reports_message');
				}
			}
		}

		header('Location: ' . get_reports_page());
		exit;
	}

	/* setup some variables */
	$reports_list = ''; $i = 0;
	/* loop through each of the graphs selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */
			$reports_list .= '<li>' . db_fetch_cell_prepared('SELECT name FROM reports WHERE id = ?', array($matches[1])) . '</li>';
			$reports_array[$i] = $matches[1];
			$i++;
		}
	}

	general_header();

	?>
	<script type='text/javascript'>
	function goTo(location) {
		document.location = location;
	}
	</script><?php

	print "<form name='report' action='" . get_reports_page() . "' method='post'>";

	html_start_box($reports_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (!isset($reports_array)) {
		print "<tr><td class='even'><span class='textError'>" . __('You must select at least one Report.') . "</span></td></tr>\n";
		$save_html = '';
	} else {
		$save_html = "<input type='submit' value='" . __esc('Continue') . "' name='save'>";

		if (get_nfilter_request_var('drp_action') == REPORTS_DELETE) { // delete
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to delete the following Report(s).') . "</p>
					<div class='itemlist'><ul>$reports_list</ul></div>
				</td>
			</tr>\n";
		} elseif (is_reports_admin() && get_nfilter_request_var('drp_action') == REPORTS_OWN) { // take ownership
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to take ownership of the following Report(s).') . "</p>
					<div class='itemlist'><ul>$reports_list</ul></div>
				</td>
			</tr>\n";
		} elseif (get_nfilter_request_var('drp_action') == REPORTS_DUPLICATE) { // duplicate
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to duplicate the following Report(s).  You may optionally change the title for the new Reports') . ".</p>
					<div class='itemlist'><ul>$reports_list</ul></div>
					<p>" . __('Name Format:') . "<br>\n";

			form_text_box('name_format', '<name> (1)', '', '255', '30', 'text');

			print "</p>
				</td>
			</tr>\n";
		} elseif (get_nfilter_request_var('drp_action') == REPORTS_ENABLE) { // enable
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to enable the following Report(s).') . "</p>
					<div class='itemlist'><ul>$reports_list</ul></div>
					<p>" . __('Please be certain that those Report(s) have successfully been tested first!') . "</p>
				</td>
			</tr>\n";
		} elseif (get_nfilter_request_var('drp_action') == REPORTS_DISABLE) { // disable
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to disable the following Reports.') . "</p>
					<div class='itemlist'><ul>$reports_list</ul></div>
				</td>
			</tr>\n";
		} elseif (get_nfilter_request_var('drp_action') == REPORTS_SEND_NOW) { // send now
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to send the following Report(s) now.') . "</p>
					<div class='itemlist'><ul>$reports_list</ul></div>
				</td>
			</tr>\n";
		}
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($reports_array) ? serialize($reports_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_nfilter_request_var('drp_action') . "'>
			<input type='button' onClick='cactiReturnTo()' value='" . ($save_html == '' ? 'Return':'Cancel') . "' name='cancel'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	print "</form>\n";

	bottom_footer();
}

/* --------------------------
 Report Item Functions
 -------------------------- */
function reports_send($id) {
	global $config;

	/* ================= input validation ================= */
	input_validate_input_number($id);
	/* ==================================================== */
	include_once($config['base_path'] . '/lib/reports.php');

	$report = db_fetch_row_prepared('SELECT * FROM reports WHERE id = ?', array($id));

	if (!sizeof($report)) {
		/* set error condition */
	} elseif ($report['user_id'] == $_SESSION['sess_user_id']) {
		reports_log(__FUNCTION__ . ', send now, report_id: ' . $id, false, 'REPORTS TRACE', POLLER_VERBOSITY_MEDIUM);
		/* use report name as default EMail title */
		if ($report['subject'] == '') {
			$report['subject'] = $report['name'];
		};
		if ($report['email'] == '') {
			$_SESSION['reports_error'] = __('Unable to send Report \'%s\'.  Please set destination e-mail addresses',  $report['name']);
			if (!isset_request_var('selected_items')) {
				raise_message('reports_error');
			}
		} elseif ($report['subject'] == '') {
			$_SESSION['reports_error'] = __('Unable to send Report \'%s\'.  Please set an e-mail subject',  $report['name']);
			if (!isset_request_var('selected_items')) {
				raise_message('reports_error');
			}
		} elseif ($report['from_name'] == '') {
			$_SESSION['reports_error'] = __('Unable to send Report \'%s\'.  Please set an e-mail From Name',  $report['name']);
			if (!isset_request_var('selected_items')) {
				raise_message('reports_error');
			}
		} elseif ($report['from_email'] == '') {
			$_SESSION['reports_error'] = __('Unable to send Report \'%s\'.  Please set an e-mail from address',  $report['name']);
			if (!isset_request_var('selected_items')) {
				raise_message('reports_error');
			}
		} else {
			generate_report($report, true);
		}
	}
}

function reports_item_movedown() {
	/* ================= input validation ================= */
	get_filter_request_var('item_id');
	get_filter_request_var('id');
	/* ==================================================== */

	move_item_down('reports_items', get_request_var('item_id'), 'report_id=' . get_request_var('id'));
}

function reports_item_moveup() {
	/* ================= input validation ================= */
	get_filter_request_var('item_id');
	get_filter_request_var('id');
	/* ==================================================== */
	move_item_up('reports_items', get_request_var('item_id'), 'report_id=' . get_request_var('id'));
}

function reports_item_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('item_id');
	/* ==================================================== */
	db_execute_prepared('DELETE FROM reports_items WHERE id = ?', array(get_request_var('item_id')));
}

function reports_item_edit() {
	global $config;
	global $fields_reports_item_edit;

	# fetch the current report record
	$report = db_fetch_row_prepared('SELECT * FROM reports WHERE id = ?', array(get_filter_request_var('id')));

	# if an existing item was requested, fetch data for it
	if (isset_request_var('item_id') && (get_filter_request_var('item_id') > 0)) {
		$reports_item = db_fetch_row_prepared('SELECT * FROM reports_items WHERE id = ?', array(get_request_var('item_id')));

		$header_label = __('Report Item [edit Report: %s]', $report['name']);
	} else {
		$header_label = __('Report Item [new Report: %s]', $report['name']);
		$reports_item = array();
		$reports_item['report_id'] = get_request_var('id');
		$reports_item['sequence']  = get_sequence('', 'sequence', 'reports_items', 'report_id=' . get_request_var('id'));
		$reports_item['host_id']   = REPORTS_HOST_NONE;
	}

	// if a different item_type was selected, use it
	if (isset_request_var('item_type')) {
		if (get_filter_request_var('item_type') > 0) {
			$reports_item['item_type'] = get_request_var('item_type');
		}
	}

	$reports_item = set_reports_item_var($reports_item, 'host_id');
	$reports_item = set_reports_item_var($reports_item, 'branch_id');
	$reports_item = set_reports_item_var($reports_item, 'tree_id');
	$reports_item = set_reports_item_var($reports_item, 'host_template_id');
	$reports_item = set_reports_item_var($reports_item, 'graph_template_id');

	if ($reports_item['tree_id'] == 0) {
		$reports_item['branch_id'] = 0;
	}

	load_current_session_value('host_template_id', 'sess_reports_edit_host_template_id', 0);
	load_current_session_value('host_id', 'sess_reports_edit_host_id', 0);
	load_current_session_value('tree_id', 'sess_reports_edit_tree_id', 0);

	/* set the default item alignment */
	$fields_reports_item_edit['align']['default'] = $report['alignment'];

	/* set the default item alignment */
	$fields_reports_item_edit['font_size']['default'] = $report['font_size'];

	form_start(get_current_page(), 'reports_item_edit');

	# ready for displaying the fields
	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_reports_item_edit, (isset($reports_item) ? $reports_item : array()))
		)
	);

	html_end_box(true, true);

	form_hidden_box('id', (isset($reports_item['id']) ? $reports_item['id'] : '0'), '');
	form_hidden_box('report_id', (isset($reports_item['report_id']) ? $reports_item['report_id'] : '0'), '');
	form_hidden_box('save_component_report_item', '1', '');

	echo "<table id='graphdiv' style='text-align:center;width:100%;display:none;'><tr><td align='center' id='graph'></td></tr></table>";

	form_save_button(get_reports_page() . '?action=edit&tab=items&id=' . get_request_var('id'), 'return');

	if (isset($item['item_type']) && $item['item_type'] == REPORTS_ITEM_GRAPH) {
		$timespan = array();
		# get config option for first-day-of-the-week
		$first_weekdayid = read_user_setting('first_weekdayid');
		# get start/end time-since-epoch for actual time (now()) and given current-session-timespan
		get_timespan($timespan, time(), $item['timespan'], $first_weekdayid);
	}

	/* don't cache previews */
	$_SESSION['custom'] = 'true';

	?>
	<script type='text/javascript'>

	useCss=<?php print ($report['cformat'] == 'on' ? 'true':'false');?>;

	function toggle_item_type() {
		//console.log($('#item_type').val());
		// right bracket ')' does not come with a field
		if ($('#item_type').val() == '<?php print REPORTS_ITEM_GRAPH;?>') {
			$('#row_align').show();
			$('#row_tree_id').hide();
			$('#row_branch_id').hide();
			$('#row_tree_cascade').hide();
			$('#row_graph_name_regexp').hide();
			$('#row_host_template_id').show();
			$('#row_host_id').show();
			$('#row_graph_template_id').show();
			$('#row_local_graph_id').show();
			$('#row_timespan').show();
			$('#item_text').val('');
			$('#row_item_text').hide();
			$('#row_font_size').hide();
		} else if ($('#item_type').val() == '<?php print REPORTS_ITEM_TEXT;?>') {
			$('#row_align').show();
			$('#row_tree_id').hide();
			$('#row_branch_id').hide();
			$('#row_tree_cascade').hide();
			$('#row_graph_name_regexp').hide();
			$('#row_host_template_id').hide();
			$('#row_host_id').hide();
			$('#row_graph_template_id').hide();
			$('#row_local_graph_id').hide();
			$('#row_timespan').hide();
			$('#row_item_text').show();
			if (useCss) {
				$('#row_font_size').hide();
			} else {
				$('#row_font_size').show();
			}
		} else if ($('#item_type').val() == '<?php print REPORTS_ITEM_TREE;?>') {
			$('#row_align').show();
			$('#row_tree_id').show();
			$('#row_branch_id').show();
			$('#row_tree_cascade').show();
			$('#row_graph_name_regexp').show();
			$('#row_host_template_id').hide();
			$('#row_host_id').hide();
			$('#row_graph_template_id').hide();
			$('#row_local_graph_id').hide();
			$('#row_timespan').show();
			$('#row_item_text').hide();
			if (useCss) {
				$('#row_font_size').hide();
			} else {
				$('#row_font_size').show();
			}
		} else {
			$('#row_tree_id').hide();
			$('#row_branch_id').hide();
			$('#row_tree_cascade').hide();
			$('#row_graph_name_regexp').hide();
			$('#row_host_template_id').hide();
			$('#row_host_id').hide();
			$('#row_graph_template_id').hide();
			$('#row_local_graph_id').hide();
			$('#row_timespan').hide();
			$('#row_item_text').hide();
			$('#row_font_size').hide();
			$('#row_align').hide();
		}
	}

	function applyChange() {
		strURL  = '?action=item_edit&header=false'
		strURL += '&id=' + $('#report_id').val();
		strURL += '&item_id=' + $('#id').val();
		strURL += '&item_type=' + $('#item_type').val();
		strURL += '&tree_id=' + $('#tree_id').val();
		strURL += '&branch_id=' + $('#branch_id').val();
		strURL += '&host_template_id=' + $('#host_template_id').val();
		strURL += '&host_id=' + $('#host_id').val();
		strURL += '&graph_template_id=' + $('#graph_template_id').val();
		loadPageNoHeader(strURL);
	}

	function graphImage(graphId) {
		if (graphId > 0) {
			$('#graphdiv').show();
			$('#graph').html("<img align='center' src='<?php print $config['url_path'];?>graph_image.php"+
					"?local_graph_id="+graphId+
					"&image_format=png"+
					"<?php print (($report["graph_width"] > 0) ? "&graph_width=" . $report["graph_width"]:"");?>"+
					"<?php print (($report["graph_height"] > 0) ? "&graph_height=" . $report["graph_height"]:"");?>"+
					"<?php print (($report["thumbnails"] == "on") ? "&graph_nolegend=true":"");?>"+
					"<?php print ((isset($timespan["begin_now"])) ? "&graph_start=" . $timespan["begin_now"]:"");?>"+
					"<?php print ((isset($timespan["end_now"])) ? "&graph_end=" . $timespan["end_now"]:"");?>"+
					"&rra_id=0'>");
		} else {
			$('#graphdiv').hide();
			$('#graph').html('');
		}
	}

	$(function() {
		$('#item_type').change(function() {
			toggle_item_type();
			applyChange();
		});

		toggle_item_type();

		if ($('#item_type').val() == 1) {
			graphImage($('#local_graph_id').val());
		}
	});
	</script>
	<?php
}


/* ---------------------
 Report Functions
 --------------------- */

function reports_edit() {
	global $config;
	global $fields_reports_edit;

	include_once($config['base_path'] . '/lib/reports.php');

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
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
		'name' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'tab' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'details',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_reports');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	/* display the report */
	$report = array();
	if (get_filter_request_var('id') > 0) {
		$report = db_fetch_row_prepared('SELECT * FROM reports WHERE id = ?', array(get_request_var('id')));
		# reformat mailtime to human readable format
		$report['mailtime'] = date(reports_date_time_format(), $report['mailtime']);
		# setup header
		$header_label = __('[edit: %s]', $report['name']);
		$tabs = array('details' => __('Details'), 'items' => __('Items'), 'preview' => __('Preview'), 'events' => __('Events'));
	} else {
		$header_label = __('[new]');
		# initialize mailtime with current timestamp
		$report['mailtime'] = date(reports_date_time_format(), floor(time() / read_config_option('poller_interval')) * read_config_option('poller_interval'));
		$tabs = array('details' => __('Details'));
	}
	/* if there was an error on the form, display the date in the correct format */
	if (isset($_SESSION['sess_field_values']['mailtime'])) {
		$_SESSION['sess_field_values']['mailtime'] = date(reports_date_time_format(), $_SESSION['sess_field_values']['mailtime']);
	}

	/* set the default settings category */
	if (!isset_request_var('tab')) set_request_var('tab', 'details');
	$current_tab = get_request_var('tab');

	if (sizeof($tabs) && isset_request_var('id')) {
		$i = 0;

		/* draw the tabs */
		print "<div class='tabs'><nav><ul role='tablist'>\n";

		foreach (array_keys($tabs) as $tab_short_name) {
			print "<li class='subTab'><a class='tab" . (($tab_short_name == $current_tab) ? " selected'" : "'") .
				" href='" . htmlspecialchars($config['url_path'] .
				get_reports_page() . '?action=edit&id=' . get_request_var('id') .
				'&tab=' . $tab_short_name) .
				"'>" . $tabs[$tab_short_name] . "</a></li>\n";

			$i++;
		}


		if (!isempty_request_var('id')) {
			print "<li style='float:right;position:relative;'><a class='tab' href='" . htmlspecialchars(get_reports_page() . '?action=send&id=' . get_request_var('id') . '&tab=' . get_request_var('tab')) . "'>" . __('Send Report') . "</a></li>\n";
		}

		print "</ul></nav></div>\n";
	}

	switch(get_request_var('tab')) {
	case 'details':
		form_start(get_reports_page());

		html_start_box(__('Details') . " $header_label", '100%', true, '3', 'center', '');

		draw_edit_form(array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_reports_edit, $report)
		));

		html_end_box(true, true);

		form_hidden_box('id', (isset($report['id']) ? $report['id'] : '0'), '');
		form_hidden_box('save_component_report', '1', '');

		?>
		<script type='text/javascript'>
		function changeFormat() {
			if (cformat && cformat.checked) {
				$('#row_font_size').hide();
				$('#row_format_file').show();
			} else {
				$('#row_font_size').show();
				$('#row_format_file').hide();
			}
		}

		$(function() {
	                $('#mailtime').datetimepicker({
				minuteGrid: 10,
				stepMinute: 1,
				showAnim: 'slideDown',
				numberOfMonths: 1,
				timeFormat: 'HH:mm',
				dateFormat: 'yy-mm-dd'
			});

			$('#cformat').click(function() {
				changeFormat();
			});

			changeFormat();
		});
		</script>
		<?php

		form_save_button(get_reports_page(), 'return');

		break;
	case 'items':
		html_start_box(__('Items') . " $header_label", '100%', '', '3', 'center', get_reports_page() . '?action=item_edit&id=' . get_request_var('id'));

		/* display the items */
		if (!empty($report['id'])) {
			display_reports_items($report['id']);
		}

		html_end_box();

		break;
	case 'events':
		if (($timestamp = strtotime($report['mailtime'])) === false) {
			$timestamp = time();
		}
		$poller_interval = read_config_option('poller_interval');
		if ($poller_interval == '') $poller_interval = 300;

		$timestamp   = floor($timestamp / $poller_interval) * $poller_interval;
		$next        = reports_interval_start($report['intrvl'], $report['count'], $report['offset'], $timestamp);
		$date_format = reports_date_time_format() . ' - l';

		html_start_box(__('Scheduled Events') . " $header_label", '100%', '', '3', 'center', '');
		for ($i=0; $i<14; $i++) {
			form_alternate_row('line' . $i, true);
			form_selectable_cell(date($date_format, $next), $i);
			form_end_row();
			$next = reports_interval_start($report['intrvl'], $report['count'], $report['offset'], $next);
		}
		html_end_box(false);

		break;
	case 'preview':
		html_start_box(__('Report Preview') . " $header_label", '100%', '', '0', 'center', '');
		print "\t\t\t\t\t<tr><td>\n";
		print reports_generate_html($report['id'], REPORTS_OUTPUT_STDOUT);
		print "\t\t\t\t\t</td></tr>\n";
		html_end_box(false);

		break;
	}
}

/* display_reports_items		display the list of all items related to a single report
 * @arg $report_id				id of the report
 */
function display_reports_items($report_id) {
	global $graph_timespans;
	global $item_types, $alignment;

	$items = db_fetch_assoc_prepared('SELECT *
		FROM reports_items
		WHERE report_id = ?
		ORDER BY sequence', array($report_id));

	$css = db_fetch_cell_prepared('SELECT cformat FROM reports WHERE id = ?', array($report_id));

	html_header(
		array(
			array('display' => __('Item'), 'align' => 'left'),
			array('display' => __('Sequence'), 'align' => 'left'),
			array('display' => __('Type'), 'align' => 'left'),
			array('display' => __('Item Details'), 'align' => 'left'),
			array('display' => __('Timespan'), 'align' => 'left'),
			array('display' => __('Alignment'), 'align' => 'left'),
			array('display' => __('Font Size'), 'align' => 'left'),
			array('display' => __('Actions'), 'align' => 'right')
		), 2);

	$i = 1;
	if (sizeof($items)) {
		foreach ($items as $item) {
			switch ($item['item_type']) {
			case REPORTS_ITEM_GRAPH:
				$item_details = get_graph_title($item['local_graph_id']);
				$align = ($item['align'] > 0 ? $alignment[$item['align']] : '');
				$size = '';
				$timespan = ($item['timespan'] > 0 ? $graph_timespans[$item['timespan']] : '');
				break;
			case REPORTS_ITEM_TEXT:
				$item_details = $item['item_text'];
				$align = ($item['align'] > 0 ? $alignment[$item['align']] : '');
				$size = ($item['font_size'] > 0 ? $item['font_size'] : '');
				$timespan = '';
				break;
			case REPORTS_ITEM_TREE:
				if ($item['branch_id'] > 0) {
					$branch_details = db_fetch_row_prepared('SELECT * FROM graph_tree_items WHERE id = ?', array($item['branch_id']));
				} else {
					$branch_details = array();
				}

				$tree_name = db_fetch_cell_prepared('SELECT name FROM graph_tree WHERE id = ?', array($item['tree_id']));

				$item_details = 'Tree: ' . $tree_name;
				if ($item['branch_id'] > 0) {
					if ($branch_details['host_id'] > 0) {
						$item_details .= ', Device: ' . db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', array($branch_details['host_id']));
					} else {
						$item_details .= ', Branch: ' . $branch_details['title'];

						if ($item['tree_cascade'] == 'on') {
							$item_details .= ' ' . __('(All Branches)');
						} else {
							$item_details .= ' ' . __('(Current Branch)');
						}
					}
				}

				$align = ($item['align'] > 0 ? $alignment[$item['align']] : '');
				$size = ($item['font_size'] > 0 ? $item['font_size'] : '');
				$timespan = ($item['timespan'] > 0 ? $graph_timespans[$item['timespan']] : '');
				break;
			default:
				$item_details = '';
				$align = '';
				$size = '';
				$timespan = '';
			}

			if ($css == 'on') {
				$size = '';
			}

			form_alternate_row();
			$form_data = '<td><a class="linkEditMain" href="' . htmlspecialchars(get_reports_page() . '?action=item_edit&id=' . $report_id. '&item_id=' . $item['id']) . '">Item#' . $i . '</a></td>';
			$form_data .= '<td>' . $item['sequence'] . '</td>';
			$form_data .= '<td>' . $item_types[$item['item_type']] . '</td>';
			$form_data .= '<td class="nowrap">' . $item_details . '</td>';
			$form_data .= '<td class="nowrap">' . $timespan . '</td>';
			$form_data .= '<td>' . $align . '</td>';
			$form_data .= '<td>' . $size . '</td>';

			if ($i == 1) {
				$form_data .= '<td class="right nowrap"><a class="remover fa fa-caret-down moveArrow" title="' . __esc('Move Down') . '" href="' . htmlspecialchars(get_reports_page() . '?action=item_movedown&item_id=' . $item['id'] . '&id=' . $report_id) . '"></a>' . '<span class="moveArrowNone"</span></td>';
			} elseif ($i > 1 && $i < sizeof($items)) {
				$form_data .= '<td class="right nowrap"><a class="remover fa fa-caret-down moveArrow" title="' . __esc('Move Down') . '" href="' . htmlspecialchars(get_reports_page() . '?action=item_movedown&item_id=' . $item['id'] . '&id=' . $report_id) . '"></a>' . '<a class="remover fa fa-caret-up moveArrow" title="' . __esc('Move Up') . '" href="' . htmlspecialchars(get_reports_page() . '?action=item_moveup&item_id=' . $item['id'] .	'&id=' . $report_id) . '"></a>' . '</td>';
			} else {
				$form_data .= '<td class="right nowrap"><span class="moveArrowNone"></span>' . '<a class="remover fa fa-caret-up moveArrow" title="' . __esc('Move Up') . '" href="' . htmlspecialchars(get_reports_page() . '?action=item_moveup&item_id=' . $item['id'] .	'&id=' . $report_id) . '"></a>' . '</td>';
			}

			$form_data .= '<td align="right"><a class="pic deleteMarker fa fa-remove" href="' . htmlspecialchars(get_reports_page() . '?action=item_remove&item_id=' . $item['id'] . '&id=' . $report_id) . '" title="' . __esc('Delete') . '"></a>' . '</td></tr>';
			print $form_data;

			$i++;
		}
	} else {
		print '<tr><td colspan="9"><em>' . __('No Report Items') . '</em></td></tr>';
	}
}

function get_reports_page() {
	return (is_realm_allowed(22) ? 'reports_admin.php' : 'reports_user.php');
}

function is_reports_admin() {
	return (is_realm_allowed(22) ? true:false);
}

function reports() {
	global $config, $item_rows, $reports_interval;
	global $reports_actions, $attach_types;

	include_once($config['base_path'] . '/lib/reports.php');

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
		'status' => array(
			'filter' => FILTER_VALIDATE_INT,
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
			'default' => 'name',
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

	validate_store_request_vars($filters, 'sess_reports');
	/* ================= input validation ================= */

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	if ((!empty($_SESSION['sess_status'])) && (!isempty_request_var('status'))) {
		if ($_SESSION['sess_status'] != get_request_var('status')) {
			set_request_var('page', '1');
		}
	}

	form_start(get_reports_page(), 'form_report');

	html_start_box(__('Reports [%s]', (is_reports_admin() ? __('Administrator Level'):__('User Level'))), '100%', '', '3', 'center', get_reports_page() . '?action=edit&tab=details');

	print "<tr class='even'>
		<td>
			<table class='filterTable'>
				<tr>
					<td>
						" . __('Search') . "
					</td>
					<td>
						<input type='text' id='filter' size='25' value='" . html_escape_request_var('filter') . "'>
					</td>
					<td>
						" . __('Status') . "
					</td>
					<td>
						<select id='status' onChange='applyFilter()'>
							<option value='-1'" . (get_request_var('status') == '-1' ? ' selected':'') . ">" . __('Any') . "</option>
							<option value='-2'" . (get_request_var('status') == '-2' ? ' selected':'') . ">" . __('Enabled') . "</option>
							<option value='-3'" . (get_request_var('status') == '-3' ? ' selected':'') . ">" . __('Disabled') . "</option>
						</select>
					</td>
					<td>
						" . __('Reports') . "
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'" . (get_request_var('rows') == '-1' ? ' selected':'') . '>' . __('Default') . '</option>';
							if (sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'" .
										(get_request_var('rows') == $key ? ' selected':'') . ">$value</option>\n";
								}
							}
	print "				</select>
					</td>
					<td>
						<span>
							<input id='refresh' type='button' value='" . __esc('Go') . "' name='go'>
							<input id='clear' type='button' value='" . __esc('Clear') . "' name='clear'>
						</span>
					</td>
				</tr>
			</table>
		</td>
	</tr>\n";

	html_end_box(true);

	form_end();

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = "WHERE (reports.name LIKE '%%" . get_request_var('filter') . "%%')";
	} else {
		$sql_where = '';
	}

	if (get_request_var('status') == '-1') {
		/* Show all items */
	} elseif (get_request_var('status') == '-2') {
		$sql_where .= ($sql_where != '' ? " AND reports.enabled='on'" : " WHERE reports.enabled='on'");
	} elseif (get_request_var('status') == '-3') {
		$sql_where .= ($sql_where != '' ? " AND reports.enabled=''" : " WHERE reports.enabled=''");
	}

	/* account for permissions */
	if (is_reports_admin()) {
		$sql_join = 'LEFT JOIN user_auth ON user_auth.id=reports.user_id';
	} else {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' user_auth.id=' . $_SESSION['sess_user_id'];
		$sql_join = 'INNER JOIN user_auth ON user_auth.id=reports.user_id';
	}

	$total_rows = db_fetch_cell("SELECT
		COUNT(reports.id)
		FROM reports
		$sql_join
		$sql_where");

	$reports_list = db_fetch_assoc("SELECT
		user_auth.full_name,
		reports.*,
		CONCAT_WS('', intrvl, ' ', count, ' ', offset, '') AS cint
		FROM reports
		$sql_join
		$sql_where
		ORDER BY " .
		get_request_var('sort_column') . ' ' .
		get_request_var('sort_direction') .
		' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows);

	form_start(get_reports_page(), 'chk');

	$nav = html_nav_bar(get_reports_page() . 'filter=' . get_request_var('filter') . '&host_id=' . get_request_var('host_id'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 10, __('Reports'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	if (is_reports_admin()) {
		$display_text = array(
			'name'            => array('display' => __('Report Name'), 'align' => 'left', 'sort' => 'ASC'),
			'full_name'       => array('display' => __('Owner'),       'align' => 'left', 'sort' => 'ASC'),
			'cint'            => array('display' => __('Frequency'),   'align' => 'left', 'sort' => 'ASC'),
			'lastsent'        => array('display' => __('Last Run'),    'align' => 'left', 'sort' => 'ASC'),
			'mailtime'        => array('display' => __('Next Run'),    'align' => 'left', 'sort' => 'ASC'),
			'from_name'       => array('display' => __('From'),        'align' => 'left', 'sort' => 'ASC'),
			'nosort'          => array('display' => __('To'),          'align' => 'left', 'sort' => 'ASC'),
			'attachment_type' => array('display' => __('Type'),        'align' => 'left', 'sort' => 'ASC'),
			'enabled'         => array('display' => __('Enabled'),     'align' => 'left', 'sort' => 'ASC'),
		);
	} else {
		$display_text = array(
			'name'            => array('display' => __('Report Title'), 'align' => 'left', 'sort' => 'ASC'),
			'cint'            => array('display' => __('Frequency'),    'align' => 'left', 'sort' => 'ASC'),
			'lastsent'        => array('display' => __('Last Run'),     'align' => 'left', 'sort' => 'ASC'),
			'mailtime'        => array('display' => __('Next Run'),     'align' => 'left', 'sort' => 'ASC'),
			'from_name'       => array('display' => __('From'),         'align' => 'left', 'sort' => 'ASC'),
			'nosort'          => array('display' => __('To'),           'align' => 'left', 'sort' => 'ASC'),
			'attachment_type' => array('display' => __('Type'),         'align' => 'left', 'sort' => 'ASC'),
			'enabled'         => array('display' => __('Enabled'),      'align' => 'left', 'sort' => 'ASC'),
		);
	}

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;
	if (sizeof($reports_list)) {
		$date_format = reports_date_time_format();

		foreach ($reports_list as $report) {
			if (!reports_html_account_exists($report['user_id'])) {
				reports_html_report_disable($report['id']);
				$report['enabled'] = '';
			}

			form_alternate_row('line' . $report['id'], true);

			form_selectable_cell(filter_value($report['name'], get_request_var('filter'), get_reports_page() . '?action=edit&tab=details&id=' . $report['id'] . '&page=1'), $report['id']);

			if (is_reports_admin()) {
				if (reports_html_account_exists($report['user_id'])) {
					form_selectable_cell($report['full_name'], $report['id']);
				} else {
					form_selectable_cell(__('Report Disabled - No Owner'), $report['id']);
				}
			}

			$interval = __('Every') . ' ' . $report['count'] . ' ' . $reports_interval[$report['intrvl']];

			form_selectable_cell($interval, $report['id']);
			form_selectable_cell(($report['lastsent'] == 0) ? __('Never') : date($date_format, $report['lastsent']), $report['lastsent']);
			form_selectable_cell(date($date_format, $report['mailtime']), $report['id']);
			form_selectable_cell($report['from_name'], $report['id']);
			form_selectable_cell((substr_count($report['email'], ',') ? __('Multiple'): $report['email']), $report['id']);
			form_selectable_cell((isset($attach_types[$report['attachment_type']])) ? $attach_types[$report['attachment_type']] : __('Invalid'), $report['id']);
			form_selectable_cell($report['enabled'] ? __('Enabled'): __('Disabled'), $report['id']);
			form_checkbox_cell($report['name'], $report['id']);

			form_end_row();
		}
	} else {
		print "<tr><td colspan='" . (sizeof($display_text)+1) . "'><em>" . __('No Reports Found') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (sizeof($reports_list)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($reports_actions);

	form_end();

	?>
	<script type='text/javascript'>
	function applyFilter() {
		strURL  = '<?php print get_reports_page();?>?header=false&status=' + $('#status').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = '<?php print get_reports_page();?>?header=false&clear=1';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_report').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php
}

function reports_html_account_exists($user_id) {
	return db_fetch_cell_prepared('SELECT id FROM user_auth WHERE id = ?', array($user_id));
}

function reports_html_report_disable($report_id) {
	db_execute_prepared('UPDATE reports SET enabled="" WHERE id = ?', array($report_id));
}

function set_reports_item_var($reports_item, $var_id) {
	// if a different host_id was selected, use it
	if (isset_request_var($var_id) && get_filter_request_var($var_id) >= 0) {
		$reports_item[$var_id] = get_request_var($var_id);
	}

	// Check that we have set a host_id, if not, default to 0
	if (!isset($reports_item[$var_id])) {
		$reports_item[$var_id] = 0;
	}

	return $reports_item;
}
