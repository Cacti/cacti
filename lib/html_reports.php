<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2014 The Cacti Group                                 |
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

/* get the format files */
$formats = reports_get_format_files();

$fields_reports_edit = array(
	'genhead' => array(
		'friendly_name' => 'General Settings',
		'method' => 'spacer',
		'collapsible' => 'true'
		),
	'name' => array(
		'friendly_name' => 'Report Name',
		'method' => 'textbox',
		'default' => 'New Report',
		'description' => 'Give this Report a descriptive Name',
		'max_length' => 99,
		'value' => '|arg1:name|'
		),
	'enabled' => array(
		'friendly_name' => 'Enable Report',
		'method' => 'checkbox',
		'default' => '',
		'description' => 'Check this box to enable this Report.',
		'value' => '|arg1:enabled|',
		'form_id' => false
		),
	'formathead' => array(
		'friendly_name' => 'Output Formatting',
		'method' => 'spacer',
		'collapsible' => 'true'
		),
	'cformat' => array(
		'friendly_name' => 'Use Custom Format HTML',
		'method' => 'checkbox',
		'default' => '',
		'description' => 'Check this box if you want to use custom html and CSS for the report.',
		'value' => '|arg1:cformat|',
		'form_id' => false
		),
	'format_file' => array(
		'friendly_name' => 'Format File to Use',
		'method' => 'drop_array',
		'default' => 'default.format',
		'description' => htmlspecialchars('Choose the custom html wrapper and CSS file to use.  This file contains both html and CSS to wrap around your report.
		If it contains more than simply CSS, you need to place a special <REPORT> tag inside of the file.  This format tag will be replaced by the report content.
		These files are located in the \'formats\' directory.'
		),
		'value' => '|arg1:format_file|',
		'array' => $formats
		),
	'font_size' => array(
		'friendly_name' => 'Default Text Font Size',
		'description' => 'Defines the default font size for all text in the report including the Report Title.',
		'default' => 16,
		'method' => 'drop_array',
		'array' => array(7 => 7, 8 => 8, 10 => 10, 12 => 12, 14 => 14, 16 => 16, 18 => 18, 20 => 20, 24 => 24, 28 => 28, 32 => 32),
		'value' => '|arg1:font_size|'
		),
	'alignment' => array(
		'friendly_name' => 'Default Object Alignment',
		'description' => 'Defines the default Alignment for Text and Graphs.',
		'default' => 0,
		'method' => 'drop_array',
		'array' => $alignment,
		'value' => '|arg1:alignment|'
		),
	'graph_linked' => array(
		'friendly_name' => 'Graph Linked',
		'method' => 'checkbox',
		'default' => '',
		'description' => 'Should the Graphs be linked back to the Cacti site?',
		'value' => '|arg1:graph_linked|'
		),
	'graphhead' => array(
		'friendly_name' => 'Graph Settings',
		'method' => 'spacer',
		'collapsible' => 'true'
		),
	'graph_columns' => array(
		'friendly_name' => 'Graph Columns',
		'method' => 'drop_array',
		'default' => '1',
		'array' => array(1 => 1, 2, 3, 4, 5),
		'description' => 'The number of Graph columns.',
		'value' => '|arg1:graph_columns|'
		),
	'graph_width' => array(
		'friendly_name' => 'Graph Width',
		'method' => 'drop_array',
		'default' => '300',
		'array' => array(100 => 100, 150 => 150, 200 => 200, 250 => 250, 300 => 300, 350 => 350, 400 => 400, 500 => 500, 600 => 600, 700 => 700, 800 => 800, 900 => 900, 1000 => 1000),
		'description' => 'The Graph width in pixels.',
		'value' => '|arg1:graph_width|'
		),
	'graph_height' => array(
		'friendly_name' => 'Graph Height',
		'method' => 'drop_array',
		'default' => '125',
		'array' => array(75 => 75, 100 => 100, 125 => 125, 150 => 150, 175 => 175, 200 => 200, 250 => 250, 300 => 300),
		'description' => 'The Graph height in pixels.',
		'value' => '|arg1:graph_height|'
		),
	'thumbnails' => array(
		'friendly_name' => 'Thumbnails',
		'method' => 'checkbox',
		'default' => '',
		'description' => 'Should the Graphs be rendered as Thumbnails?',
		'value' => '|arg1:thumbnails|'
		),
	'freqhead' => array(
		'friendly_name' => 'Email Frequency',
		'method' => 'spacer',
		'collapsible' => 'true'
		),
	'mailtime' => array(
		'friendly_name' => 'Next Timestamp for Sending Mail Report',
		'description' => 'Start time for [first|next] mail to take place. All future
			mailing times will be based upon this start time. A good example would be 2:00am. The time
			must be in the future.  If a fractional time is used, say 2:00am, it is assumed to be in the future.',
		'default' => 0,
		'method' => 'textbox',
		'size' => 20,
		'max_length' => 20,
		'value' => '|arg1:mailtime|'
		),
	'intrvl' => array(
		'friendly_name' => 'Report Interval',
		'description' => 'Defines a Report Frequency relative to the given Mailtime above.' . '<br>' .
			'e.g. "Week(s)" represents a weekly Reporting Interval.',
		'default' => REPORTS_SCHED_INTVL_DAY,
		'method' => 'drop_array',
		'array' => $reports_interval,
		'value' => '|arg1:intrvl|'
		),
	'count' => array(
		'friendly_name' => 'Interval Frequency',
		'description' => 'Based upon the Timespan of the Report Interval above, defines the Frequency within that Interval.<br>' .
			'e.g. If the Report Interval is "Month(s)", then "2" indicates Every "2 Month(s) from the next Mailtime."' .
			'Lastly, if using the Month(s) Report Intervals, the "Day of Week" and the "Day of Month" are both calculated based upon the Mailtime you specify above.',
		'default' => REPORTS_SCHED_COUNT,
		'method' => 'textbox',
		'size' => 10,
		'max_length' => 10,
		'value' => '|arg1:count|'
		),
	'emailhead' => array(
		'friendly_name' => 'Email Sender/Receiver Details',
		'method' => 'spacer',
		'collapsible' => 'true'
		),
	'subject' => array(
		'friendly_name' => 'Subject',
		'method' => 'textbox',
		'default' => 'Cacti Report',
		'description' => 'This value will be used as the default Email subject.  The report name will be used if left blank.',
		'max_length' => 255,
		'value' => '|arg1:subject|'
		),
	'from_name' => array(
		'friendly_name' => 'From Name',
		'method' => 'textbox',
		'default' => read_config_option("settings_from_name"),
		'description' => 'This Name will be used as the default E-mail Sender',
		'max_length' => 255,
		'value' => '|arg1:from_name|'
		),
	'from_email' => array(
		'friendly_name' => 'From Email Address',
		'method' => 'textbox',
		'default' => read_config_option("settings_from_email"),
		'description' => 'This Adress will be used as the E-mail Senders address',
		'max_length' => 255,
		'value' => '|arg1:from_email|'
		),
	'email' => array(
		'friendly_name' => 'To Email Address(es)',
		'method' => 'textarea',
		'textarea_rows' => '5',
		'textarea_cols' => '60',
		'class' => 'textAreaNotes',
		'default' => '',
		'description' => 'Please seperate multiple adresses by comma (,)',
		'max_length' => 255,
		'value' => '|arg1:email|'
		),
	'bcc' => array(
		'friendly_name' => 'BCC Address(es)',
		'method' => 'textarea',
		'textarea_rows' => '5',
		'textarea_cols' => '60',
		'class' => 'textAreaNotes',
		'default' => '',
		'description' => 'Blind carbon copy. Please seperate multiple adresses by comma (,)',
		'max_length' => 255,
		'value' => '|arg1:bcc|'
		),
	'attachment_type' => array(
		'friendly_name' => 'Image attach type',
		'method' => 'drop_array',
		'default' => read_config_option("reports_default_image_format"),
		'description' => 'Select one of the given Types for the Image Attachments',
		'value' => '|arg1:attachment_type|',
		'array' => $attach_types
		),
);

/* get the hosts sql first */
if (read_config_option("auth_method") != 0) {
	/* get policy information for the sql where clause */
	$current_user = db_fetch_row("select * from user_auth where id=" . $_SESSION["sess_user_id"]);
	$sql_where    = get_graph_permissions_sql($current_user["policy_graphs"], $current_user["policy_hosts"], $current_user["policy_graph_templates"]);

	$hosts_sql = "SELECT DISTINCT host.id, CONCAT_WS('',host.description,' (',host.hostname,')') as name
		FROM (graph_templates_graph,host)
		LEFT JOIN graph_local ON (graph_local.host_id=host.id)
		LEFT JOIN graph_templates ON (graph_templates.id=graph_local.graph_template_id)
		LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . "))
		WHERE graph_templates_graph.local_graph_id=graph_local.id
		AND host_template_id=|arg1:host_template_id|
		" . (empty($sql_where) ? "" : "AND $sql_where") . "
		ORDER BY name";
}else{
	$hosts_sql = "SELECT DISTINCT host.id, CONCAT_WS('',host.description,' (',host.hostname,')') as name
		FROM host
		WHERE host_template_id=|arg1:host_template_id|
		ORDER BY name";
}

/* next do the templates sql */
if (read_config_option("auth_method") != 0) {
	$templates_sql = "SELECT DISTINCT graph_templates.id, graph_templates.name
		FROM (graph_templates_graph,graph_local)
		LEFT JOIN host ON (host.id=graph_local.host_id)
		LEFT JOIN graph_templates ON (graph_templates.id=graph_local.graph_template_id)
		LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . "))
		WHERE graph_templates_graph.local_graph_id=graph_local.id
		AND graph_templates.id IS NOT NULL
		AND host_id=|arg1:host_id|
		" . (empty($sql_where) ? "" : "AND $sql_where") . "
		ORDER BY name";
}else{
	$templates_sql = "SELECT DISTINCT graph_templates.id, graph_templates.name
		FROM graph_templates
		WHERE host_id=|arg1:host_id|
		ORDER BY name";
}

/* last do the tree permissions */
if (read_config_option("auth_method") != 0) {
	/* all allowed by default */
	$sql_in = "";
	if ($current_user["policy_trees"] == 1) {
		$exclude_trees = db_fetch_assoc("SELECT item_id
			FROM user_auth_perms
			WHERE user_id=" . $_SESSION["sess_user_id"] . "
			AND type=2");

		if (sizeof($exclude_trees)) {
		foreach($exclude_trees as $tree) {
			$sql_in .= (strlen($sql_in) ? ", ":"") . $tree["item_id"];
		}
		}

		$sql_where = (strlen($sql_in) ? "WHERE id NOT IN ($sql_in)":"");
	}else{
		$include_trees = db_fetch_assoc("SELECT item_id
			FROM user_auth_perms
			WHERE user_id=" . $_SESSION["sess_user_id"] . "
			AND type=2");

		if (sizeof($include_trees)) {
		foreach($include_trees as $tree) {
			$sql_in .= (strlen($sql_in) ? ", ":"") . $tree["item_id"];
		}
		}

		$sql_where = (strlen($sql_in) ? "WHERE id IN ($sql_in)":"");
	}

	$trees_sql = "SELECT id, name FROM graph_tree $sql_where ORDER BY name";
}else{
	$trees_sql = "SELECT id, name FROM graph_tree ORDER BY name";
}

$fields_reports_item_edit = array(
	'item_type' => array(
		'friendly_name' => 'Type',
		'method' => 'drop_array',
		'default' => REPORTS_ITEM_GRAPH,
		'description' => 'Item Type to be added.',
		'value' => '|arg1:item_type|',
		'on_change' => 'toggle_item_type()',
		'array' => $item_types),
	'tree_id' => array(
		'friendly_name' => 'Graph Tree',
		'method' => 'drop_sql',
		'default' => REPORTS_TREE_NONE,
		'none_value' => "None",
		'description' => 'Select a Tree to use.',
		'value' => '|arg1:tree_id|',
		'on_change' => 'applyChange(document.reports_item_edit)',
		'sql' => $trees_sql),
	'branch_id' => array(
		'friendly_name' => 'Graph Tree Branch',
		'method' => 'drop_sql',
		'default' => REPORTS_TREE_NONE,
		'none_value' => "All",
		'description' => 'Select a Tree Branch to use.',
		'value' => '|arg1:branch_id|',
		'sql' => "(SELECT id, CONCAT_WS('', title, ' (Branch)') AS name
			FROM graph_tree_items
			WHERE graph_tree_id=|arg1:tree_id| AND host_id=0 AND local_graph_id=0
			ORDER BY order_key)
			UNION
			(SELECT graph_tree_items.id, CONCAT_WS('', description, ' (Host)') AS name
			FROM graph_tree_items
			INNER JOIN host
			ON host.id=graph_tree_items.host_id
			WHERE graph_tree_id=|arg1:tree_id|)
			ORDER BY name"),
	'tree_cascade' => array(
		'friendly_name' => 'Cascade to Branches',
		'method' => 'checkbox',
		'default' => '',
		'description' => 'Should all children branch Graphs be rendered?',
		'value' => '|arg1:tree_cascade|' ),
	'graph_name_regexp' => array(
		'friendly_name' => 'Graph Name Regular Expression',
		'method' => 'textbox',
		'default' => '',
		'description' => 'A Perl compatible regular expression (REGEXP) used to select graphs to include from the tree.',
		'max_length' => 255,
		'size' => 80,
		'value' => '|arg1:graph_name_regexp|'),
	'host_template_id' => array(
		'friendly_name' => 'Host Template',
		'method' => 'drop_sql',
		'default' => REPORTS_HOST_NONE,
		'none_value' => "None",
		'description' => 'Select a Host Template to use.',
		'value' => '|arg1:host_template_id|',
		'on_change' => 'applyChange(document.reports_item_edit)',
		'sql' => "SELECT DISTINCT ht.id, ht.name FROM host_template AS ht INNER JOIN host AS h ON h.host_template_id=ht.id ORDER BY name"),
	'host_id' => array(
		'friendly_name' => 'Host',
		'method' => 'drop_sql',
		'default' => REPORTS_HOST_NONE,
		'description' => 'Select a Host to specify a Graph',
		'value' => '|arg1:host_id|',
		'none_value' => "None",
		'on_change' => 'applyChange(document.reports_item_edit)',
		'sql' => $hosts_sql),
	'graph_template_id' => array(
		'friendly_name' => 'Graph Template',
		'method' => 'drop_sql',
		'default' => '0',
		'description' => 'Select a Graph Template for the host',
		'none_value' => "None",
		'on_change' => 'applyChange(document.reports_item_edit)',
		'value' => '|arg1:graph_template_id|',
		'sql' => $templates_sql),
	'local_graph_id' => array(
		'friendly_name' => 'Graph Name',
		'method' => 'drop_sql',
		'default' => '0',
		'description' => 'The Graph to use for this report item.',
		'none_value' => "None",
		'on_change' => 'graphImage(this.value)',
		'value' => '|arg1:local_graph_id|',
		'sql' => "SELECT graph_templates_graph.local_graph_id as id, graph_templates_graph.title_cache as name
			FROM graph_local LEFT JOIN graph_templates_graph ON (graph_local.id=graph_templates_graph.local_graph_id)
			WHERE graph_local.host_id=|arg1:host_id| AND graph_templates_graph.graph_template_id=|arg1:graph_template_id|
			ORDER BY name"),
	'timespan' => array(
		'friendly_name' => 'Graph Timespan',
		'method' => 'drop_array',
		'default' => GT_LAST_DAY,
		'description' => "Graph End Time is always set to Cacti's schedule." . '<br>' .
			'Graph Start Time equals Graph End Time minus given timespan',
		'array' => $graph_timespans,
		'value' => '|arg1:timespan|'),
	'align' => array(
		'friendly_name' => 'Alignment',
		'method' => 'drop_array',
		'default' => REPORTS_ALIGN_LEFT,
		'description' => 'Alignment of the Item',
		'value' => '|arg1:align|',
		'array' => $alignment),
	'item_text' => array(
		'friendly_name' => 'Fixed Text',
		'method' => 'textbox',
		'default' => '',
		'description' => 'Enter descriptive Text',
		'max_length' => 255,
		'value' => '|arg1:item_text|'),
	'font_size' => array(
		'friendly_name' => 'Font Size',
		'method' => 'drop_array',
		'default' => REPORTS_FONT_SIZE,
		'array' => array(7 => 7, 8 => 8, 10 => 10, 12 => 12, 14 => 14, 16 => 16, 18 => 18, 20 => 20, 24 => 24, 28 => 28, 32 => 32),
		'description' => 'Font Size of the Item',
		'value' => '|arg1:font_size|'),
	'sequence' => array(
		'method' => 'view',
		'friendly_name' => 'Sequence',
		'description' => 'Sequence of Item.',
		'value' => '|arg1:sequence|'),
);

function reports_form_save() {
	global $config, $messages;
	# when using cacti_log: include_once($config['library_path'] . '/functions.php');

	if (isset($_POST['save_component_report'])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('id'));
		input_validate_input_number(get_request_var_post('font_size'));
		input_validate_input_number(get_request_var_post('graph_width'));
		input_validate_input_number(get_request_var_post('graph_height'));
		input_validate_input_number(get_request_var_post('graph_columns'));
		/* ==================================================== */
		$now = time();

		if ($_POST['id'] == 0 || $_POST['id'] == '') {
			$save['user_id'] = $_SESSION['sess_user_id'];
		}else{
			$save['user_id'] = db_fetch_cell('SELECT user_id FROM reports WHERE id=' . $_POST['id']);
		}

		$save['id']				= $_POST['id'];
		$save['name']			= sql_sanitize(form_input_validate($_POST['name'], 'name', '', false, 3));
		$save['email']			= sql_sanitize(form_input_validate($_POST['email'], 'email', '', false, 3));
		$save['enabled']		= (isset($_POST['enabled']) ? 'on' : '');

		$save['cformat']		= (isset($_POST['cformat']) ? 'on' : '');
		$save['format_file']	= sql_sanitize($_POST['format_file']);
		$save['font_size']		= form_input_validate($_POST['font_size'], 'font_size', '^[0-9]+$', false, 3);
		$save['alignment']		= form_input_validate($_POST['alignment'], 'alignment', '^[0-9]+$', false, 3);
		$save['graph_linked']	= (isset($_POST['graph_linked']) ? 'on' : '');

		$save['graph_columns']	= form_input_validate($_POST['graph_columns'], 'graph_columns', '^[0-9]+$', false, 3);
		$save['graph_width']	= form_input_validate($_POST['graph_width'], 'graph_width', '^[0-9]+$', false, 3);
		$save['graph_height']	= form_input_validate($_POST['graph_height'], 'graph_height', '^[0-9]+$', false, 3);
		$save['thumbnails']		= form_input_validate((isset($_POST['thumbnails']) ? $_POST['thumbnails']:''), 'thumbnails', '', true, 3);

		$save['intrvl']			= form_input_validate($_POST['intrvl'], 'intrvl', '^[-+]?[0-9]+$', false, 3);
		$save['count']			= form_input_validate($_POST['count'], 'count', '^[0-9]+$', false, 3);
		$save['offset']			= '0';

		/* adjust mailtime according to rules */
		$timestamp = strtotime($_POST['mailtime']);
		if ($timestamp === false) {
			$timestamp  = $now;
		} elseif (($timestamp + read_config_option('poller_interval')) < $now) {
			$timestamp += 86400;

			/* if the time is far into the past, make it the correct time, but tomorrow */
		if (($timestamp + read_config_option('poller_interval')) < $now) {
				$timestamp = strtotime('12:00am') + 86400 + date('H', $timestamp) * 3600 + date('i', $timestamp) * 60 + date('s', $timestamp);
		}
			$_SESSION['reports_message'] = 'Date/Time moved to the same time Tomorrow';

			raise_message('reports_message');
		}

		$save['mailtime']     = form_input_validate($timestamp, 'mailtime', '^[0-9]+$', false, 3);

		if (strlen($_POST['subject'])) {
			$save['subject']          = sql_sanitize($_POST['subject']);
		}else{
			$save['subject'] = $save['name'];
		}
		$save['from_name']        = sql_sanitize($_POST['from_name']);
		$save['from_email']       = sql_sanitize($_POST['from_email']);
		$save['bcc']              = sql_sanitize($_POST['bcc']);
		if (($_POST['attachment_type'] != REPORTS_TYPE_INLINE_PNG) &&
			($_POST['attachment_type'] != REPORTS_TYPE_INLINE_JPG) &&
			($_POST['attachment_type'] != REPORTS_TYPE_INLINE_GIF) &&
			($_POST['attachment_type'] != REPORTS_TYPE_ATTACH_PNG) &&
			($_POST['attachment_type'] != REPORTS_TYPE_ATTACH_JPG) &&
			($_POST['attachment_type'] != REPORTS_TYPE_ATTACH_GIF) &&
			($_POST['attachment_type'] != REPORTS_TYPE_INLINE_PNG_LN) &&
			($_POST['attachment_type'] != REPORTS_TYPE_INLINE_JPG_LN) &&
			($_POST['attachment_type'] != REPORTS_TYPE_INLINE_GIF_LN) &&
			($_POST['attachment_type'] != REPORTS_TYPE_ATTACH_PDF)) {
			$_POST['attachment_type'] = REPORTS_TYPE_INLINE_PNG;
		}
		$save['attachment_type']  = form_input_validate($_POST['attachment_type'], 'attachment_type', '^[0-9]+$', false, 3);
		$save['lastsent']         = 0;

		if (!is_error_message()) {
			$id = sql_save($save, 'reports');

			if ($id) {
				raise_message('reports_save');
			}else{
				raise_message('reports_save_failed');
			}
		}

		header('Location: ' . get_reports_page() . '?action=edit&id=' . (empty($id) ? $_POST['id'] : $id));
		exit;
	}elseif (isset($_POST['save_component_report_item'])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('report_id'));
		input_validate_input_number(get_request_var_post('id'));
		/* ==================================================== */

		$save = array();

		$save['id']                = $_POST['id'];
		$save['report_id']         = form_input_validate($_POST['report_id'], 'report_id', '^[0-9]+$', false, 3);
		$save['sequence']          = form_input_validate($_POST['sequence'], 'sequence', '^[0-9]+$', false, 3);
		$save['item_type']         = form_input_validate($_POST['item_type'], 'item_type', '^[-0-9]+$', false, 3);
		$save['tree_id']           = (isset($_POST['tree_id']) ? form_input_validate($_POST['tree_id'], 'tree_id', '^[-0-9]+$', true, 3) : 0);
		$save['branch_id']         = (isset($_POST['branch_id']) ? form_input_validate($_POST['branch_id'], 'branch_id', '^[-0-9]+$', true, 3) : 0);
		$save['tree_cascade']      = (isset($_POST['tree_cascade']) ? 'on':'');
		$save['graph_name_regexp'] = sql_sanitize($_POST['graph_name_regexp']);
		$save['host_template_id']  = (isset($_POST['host_template_id']) ? form_input_validate($_POST['host_template_id'], 'host_template_id', '^[-0-9]+$', true, 3) : 0);
		$save['host_id']           = (isset($_POST['host_id']) ? form_input_validate($_POST['host_id'], 'host_id', '^[-0-9]+$', true, 3) : 0);
		$save['graph_template_id'] = (isset($_POST['graph_template_id']) ? form_input_validate($_POST['graph_template_id'], 'graph_template_id', '^[-0-9]+$', true, 3) : 0);
		$save['local_graph_id']    = (isset($_POST['local_graph_id']) ? form_input_validate($_POST['local_graph_id'], 'local_graph_id', '^[0-9]+$', true, 3) : 0);
		$save['timespan']          = (isset($_POST['timespan']) ? form_input_validate($_POST['timespan'], 'timespan', '^[0-9]+$', true, 3) : 0);
		$save['item_text']         = (isset($_POST['item_text']) ? sql_sanitize(form_input_validate($_POST['item_text'], 'item_text', '', true, 3)) : '');
		$save['align']             = (isset($_POST['align']) ? form_input_validate($_POST['align'], 'align', '^[0-9]+$', true, 3) : REPORTS_ALIGN_LEFT);
		$save['font_size']         = (isset($_POST['font_size']) ? form_input_validate($_POST['font_size'], 'font_size', '^[0-9]+$', true, 3) : REPORTS_FONT_SIZE);

		if (!is_error_message()) {
			$item_id = sql_save($save, 'reports_items');

			if ($item_id) {
				raise_message('reports_item_save');
			}else{
				raise_message('reports_item_save_failed');
			}
		}

		header('Location: ' . get_reports_page() . '?action=item_edit&id=' . $_POST['report_id'] . '&item_id=' . (empty($item_id) ? $_POST['id'] : $item_id));
	} else {
		header('Location: ' . get_reports_page());
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
	input_validate_input_number(get_request_var_post('drp_action'));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset($_POST['selected_items'])) {
		$selected_items = unserialize(stripslashes($_POST['selected_items']));

		if ($_POST['drp_action'] == REPORTS_DELETE) { /* delete */
			db_execute('DELETE FROM reports WHERE ' . array_to_sql_or($selected_items, 'id'));
			db_execute('DELETE FROM reports_items WHERE ' . str_replace('id', 'report_id', array_to_sql_or($selected_items, 'id')));
		}elseif ($_POST['drp_action'] == REPORTS_OWN) { /* take ownership */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
				reports_log(__FUNCTION__ . ', takeown: ' . $selected_items[$i] . ' user: ' . $_SESSION['sess_user_id'], false, 'REPORTS TRACE', POLLER_VERBOSITY_MEDIUM);
				db_execute('UPDATE reports SET user_id=' . $_SESSION['sess_user_id'] . ' WHERE id=' . $selected_items[$i]);
			}
		}elseif ($_POST['drp_action'] == REPORTS_DUPLICATE) { /* duplicate */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
				reports_log(__FUNCTION__ . ', duplicate: ' . $selected_items[$i] . ' name: ' . $_POST['name_format'], false, 'REPORTS TRACE', POLLER_VERBOSITY_MEDIUM);
				duplicate_report($selected_items[$i], $_POST['name_format']);
			}
		}elseif ($_POST['drp_action'] == REPORTS_ENABLE) { /* enable */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
				reports_log(__FUNCTION__ . ', enable: ' . $selected_items[$i], false, 'REPORTS TRACE', POLLER_VERBOSITY_MEDIUM);
				db_execute("UPDATE reports SET enabled='on' WHERE id=" . $selected_items[$i]);
			}
		}elseif ($_POST['drp_action'] == REPORTS_DISABLE) { /* disable */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
				reports_log(__FUNCTION__ . ', disable: ' . $selected_items[$i], false, 'REPORTS TRACE', POLLER_VERBOSITY_MEDIUM);
				db_execute("UPDATE reports SET enabled='' WHERE id=" . $selected_items[$i]);
			}
		}elseif ($_POST['drp_action'] == REPORTS_SEND_NOW) { /* send now */
			include_once($config['base_path'] . '/lib/reports.php');
			$message = '';

			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				$_SESSION['reports_message'] = '';
				$_SESSION['reports_error']   = '';

				reports_send($selected_items[$i]);

				if (isset($_SESSION['reports_message']) && strlen($_SESSION['reports_message'])) {
					$message .= (strlen($message) ? '<br>':'') . $_SESSION['reports_message'];
				}
				if (isset($_SESSION['reports_error']) && strlen($_SESSION['reports_error'])) {
					$message .= (strlen($message) ? '<br>':'') . "<span style='color:red;'>" . $_SESSION['reports_error'] . '</span>';
				}
			}

			if (strlen($message)) {
				$_SESSION['reports_message'] = $message;
				raise_message('reports_message');
			}
		}

		header('Location: ' . get_reports_page());
		exit;
	}

	/* setup some variables */
	$reports_list = ''; $i = 0;
	/* loop through each of the graphs selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */
			$reports_list .= '<li>' . db_fetch_cell('SELECT name FROM reports WHERE id=' . $matches[1]) . '</li>';
			$reports_array[$i] = $matches[1];
			$i++;
		}
	}

	general_header();

	?>
	<script type='text/javascript'>
	<!--
	function goTo(location) {
		document.location = location;
	}
	-->
	</script><?php

	print "<form name='report' action='report" . get_reports_page() . " method='post'>";

	html_start_box('<strong>' . $reports_actions{$_POST['drp_action']} . '</strong>', '60%', '', '3', 'center', '');

	if (!isset($reports_array)) {
		print "<tr><td class='even'><span class='textError'>You must select at least one Report.</span></td></tr>\n";
		$save_html = '';
	}else{
		$save_html = "<input type='submit' value='Yes' name='save'>";

		if ($_POST['drp_action'] == REPORTS_DELETE) { /* delete */
			print "	<tr>
				<td class='textArea'>
					<p>Are you sure you want to delete the following Reports?</p>
					<ul>$reports_list</ul>
				</td>
			</tr>\n
			";
		}elseif (is_reports_admin() && $_POST['drp_action'] == REPORTS_OWN) { /* take ownership */
			print "	<tr>
				<td class='textArea'>
					<p>Are you sure you wish to take ownership of the following reports?</p>
					<ul>$reports_list</ul>
				</td>
			</tr>\n
			";
		}elseif ($_POST['drp_action'] == REPORTS_DUPLICATE) { /* duplicate */
			print "	<tr>
				<td class='textArea'>
					<p>When you click save, the following Reports will be duplicated. You can
					optionally change the title format for the new Reports.</p>
					<ul>$reports_list</ul>
					<p><strong>Name Format:</strong><br>"; form_text_box('name_format', '<name> (1)', '', '255', '30', 'text'); print "</p>
				</td>
			</tr>\n
			";
		}elseif ($_POST['drp_action'] == REPORTS_ENABLE) { /* enable */
			print "	<tr>
				<td class='textArea'>
					<p>Are you sure you wish to enable the following reports?</p>
					<ul>$reports_list</ul>
					<p><strong>Make sure, that those Reports have successfully been tested!</strong></p>
				</td>
			</tr>\n
			";
		}elseif ($_POST['drp_action'] == REPORTS_DISABLE) { /* disable */
			print "	<tr>
				<td class='textArea'>
					<p>Are you sure you wish to disable the following reports?</p>
					<ul>$reports_list</ul>
				</td>
			</tr>\n
			";
		}elseif ($_POST['drp_action'] == REPORTS_SEND_NOW) { /* send now */
			print "	<tr>
				<td class='textArea'>
					<p>Are you sure you want to send the following reports now?</p>
					<ul>$reports_list</ul>
				</td>
			</tr>\n
			";
		}
	}

	print "	<tr>
		<td align='right' class='saveRow>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($reports_array) ? serialize($reports_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . $_POST['drp_action'] . "'>
			<input type='button' onClick='goTo(\"" . get_reports_page() . "\")' value='" . ($save_html == '' ? 'Return':'No') . "' name='cancel'>
			$save_html
		</td>
	</tr>";

	html_end_box();

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

	$report = db_fetch_row('SELECT * FROM reports WHERE id=' . $id);

	if (!sizeof($report)) {
		/* set error condition */
	}elseif ($report['user_id'] == $_SESSION['sess_user_id']) {
		reports_log(__FUNCTION__ . ', send now, report_id: ' . $id, false, 'REPORTS TRACE', POLLER_VERBOSITY_MEDIUM);
		/* use report name as default EMail title */
		if (!strlen($report['subject'])) {
			$report['subject'] = $report['name'];
		};
		if (!strlen($report['email'])) {
			$_SESSION['reports_error'] = "Unable to send Report '" . $report['name'] . "'.  Please set destination e-mail addresses";
			if (!isset($_POST['selected_items'])) {
				raise_message('reports_error');
			}
		}elseif (!strlen($report['subject'])) {
			$_SESSION['reports_error'] = "Unable to send Report '" . $report['name'] . "'.  Please set an e-mail subject";
			if (!isset($_POST['selected_items'])) {
				raise_message('reports_error');
			}
		}elseif (!strlen($report['from_name'])) {
			$_SESSION['reports_error'] = "Unable to send Report '" . $report['name'] . "'.  Please set an e-mail From Name";
			if (!isset($_POST['selected_items'])) {
				raise_message('reports_error');
			}
		}elseif (!strlen($report['from_email'])) {
			$_SESSION['reports_error'] = "Unable to send Report '" . $report['name'] . "'.  Please set an e-mail from address";
			if (!isset($_POST['selected_items'])) {
				raise_message('reports_error');
			}
		}else{
			generate_report($report, true);
		}
	}
}

function reports_item_movedown() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('item_id'));
	input_validate_input_number(get_request_var_request('id'));
	/* ==================================================== */

	move_item_down('reports_items', get_request_var_request('item_id'), 'report_id=' . get_request_var_request('id'));
}

function reports_item_moveup() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('item_id'));
	input_validate_input_number(get_request_var_request('id'));
	/* ==================================================== */
	move_item_up('reports_items', get_request_var_request('item_id'), 'report_id=' . get_request_var_request('id'));
}

function reports_item_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('item_id'));
	/* ==================================================== */
	db_execute('DELETE FROM reports_items WHERE id=' . get_request_var_request('item_id'));
}

function reports_item_edit() {
	global $config;
	global $fields_reports_item_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	input_validate_input_number(get_request_var_request('item_id'));
	input_validate_input_number(get_request_var_request('item_type'));
	input_validate_input_number(get_request_var_request('branch_id'));
	input_validate_input_number(get_request_var_request('tree_id'));
	input_validate_input_number(get_request_var_request('host_id'));
	input_validate_input_number(get_request_var_request('host_template_id'));
	input_validate_input_number(get_request_var_request('graph_template_id'));
	/* ==================================================== */

	# fetch the current report record
	$report = db_fetch_row('SELECT *
		FROM reports 
		WHERE id=' . get_request_var_request('id'));

	# if an existing item was requested, fetch data for it
	if (isset($_REQUEST['item_id']) && ($_REQUEST['item_id'] > 0)) {
		$reports_item = db_fetch_row('SELECT *
			FROM reports_items
			WHERE id=' . get_request_var_request('item_id'));

		$header_label = '[edit Report Item: ' . $report['name'] . ']';
	}else{
		$header_label = '[new Report Item: ' . $report['name'] . ']';
		$reports_item = array();
		$reports_item['report_id'] = get_request_var_request('id');
		$reports_item['sequence']  = get_sequence('', 'sequence', 'reports_items', 'report_id=' . get_request_var_request('id'));
		$reports_item['host_id']   = REPORTS_HOST_NONE;
	}

	# if a different host_template_id was selected, use it
	if (get_request_var_request('item_type', '') !== '') {
		$reports_item['item_type'] = get_request_var_request('item_type');
	}

	if (get_request_var_request('tree_id', '') !== '') {
		$reports_item['tree_id'] = get_request_var_request('tree_id');
	}else if (!isset($reports_item['tree_id'])) {
		$reports_item['tree_id'] = 0;
	}

	if (get_request_var_request('host_template_id', '') !== '') {
		$reports_item['host_template_id'] = get_request_var_request('host_template_id');
	}else if (!isset($reports_item['host_template_id'])) {
		$reports_item['host_template_id'] = 0;
	}

	# if a different host_id was selected, use it
	if (get_request_var_request('host_id', '') !== '') {
		$reports_item['host_id'] = get_request_var_request('host_id');
	}

	# if a different graph_template_id was selected, use it
	if (get_request_var_request('graph_template_id', '') !== '') {
		$reports_item['graph_template_id'] = get_request_var_request('graph_template_id');
	}else if (!isset($reports_item['graph_template_id'])) {
		$reports_item['graph_template_id'] = 0;
	}

	load_current_session_value('host_template_id', 'sess_reports_edit_host_template_id', 0);
	load_current_session_value('host_id', 'sess_reports_edit_host_id', 0);
	load_current_session_value('tree_id', 'sess_reports_edit_tree_id', 0);

	/* set the default item alignment */
	$fields_reports_item_edit['align']['default'] = $report['alignment'];

	/* set the default item alignment */
	$fields_reports_item_edit['font_size']['default'] = $report['font_size'];

	print "<form method='post' action='" .  basename($_SERVER['PHP_SELF']) . "' name='reports_item_edit'>\n";

	# ready for displaying the fields
	html_start_box("<strong>Report Item</strong> $header_label", '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => inject_form_variables($fields_reports_item_edit, (isset($reports_item) ? $reports_item : array()), (isset($report) ? $report : array()))
	));

	html_end_box();

	form_hidden_box('id', (isset($reports_item['id']) ? $reports_item['id'] : '0'), '');
	form_hidden_box('report_id', (isset($reports_item['report_id']) ? $reports_item['report_id'] : '0'), '');
	form_hidden_box('save_component_report_item', '1', '');

	echo "<table id='graphdiv' style='display:none;' align='center' width='100%'><tr><td align='center' id='graph'></td></tr></table>";

	form_save_button(htmlspecialchars(get_reports_page() . '?action=edit&tab=items&id=' . get_request_var_request('id')), 'return');

	if (isset($item['item_type']) && $item['item_type'] == REPORTS_ITEM_GRAPH) {
		$timespan = array();
		# get config option for first-day-of-the-week
		$first_weekdayid = read_graph_config_option('first_weekdayid');
		# get start/end time-since-epoch for actual time (now()) and given current-session-timespan
		get_timespan($timespan, time(), $item['timespan'], $first_weekdayid);
	}

	/* don't cache previews */
	$_SESSION['custom'] = 'true';

	?>
	<script type='text/javascript'>

	useCss=<?php print ($report['cformat'] == 'on' ? 'true':'false');?>;

	toggle_item_type();
	graphImage(document.getElementById('local_graph_id').value);

	function toggle_item_type() {
		// right bracket ')' does not come with a field
		if (document.getElementById('item_type').value == '<?php print REPORTS_ITEM_GRAPH;?>') {
			document.getElementById('row_align').style.display='';
			document.getElementById('row_tree_id').style.display='none';
			document.getElementById('row_branch_id').style.display='none';
			document.getElementById('row_tree_cascade').style.display='none';
			document.getElementById('row_graph_name_regexp').style.display='none';
			document.getElementById('row_host_template_id').style.display='';
			document.getElementById('row_host_id').style.display='';
			document.getElementById('row_graph_template_id').style.display='';
			document.getElementById('row_local_graph_id').style.display='';
			document.getElementById('row_timespan').style.display='';
			document.getElementById('item_text').value = '';
			document.getElementById('row_item_text').style.display='none';
			document.getElementById('row_font_size').style.display='none';
		} else if (document.getElementById('item_type').value == '<?php print REPORTS_ITEM_TEXT;?>') {
			document.getElementById('row_align').style.display='';
			document.getElementById('row_tree_id').style.display='none';
			document.getElementById('row_branch_id').style.display='none';
			document.getElementById('row_tree_cascade').style.display='none';
			document.getElementById('row_graph_name_regexp').style.display='none';
			document.getElementById('row_host_template_id').style.display='none';
			document.getElementById('row_host_id').style.display='none';
			document.getElementById('row_graph_template_id').style.display='none';
			document.getElementById('row_local_graph_id').style.display='none';
			document.getElementById('row_timespan').style.display='none';
			document.getElementById('row_item_text').style.display='';
			if (useCss) {
				document.getElementById('row_font_size').style.display='none';
			}else{
				document.getElementById('row_font_size').style.display='';
			}
		} else if (document.getElementById('item_type').value == '<?php print REPORTS_ITEM_TREE;?>') {
			document.getElementById('row_align').style.display='';
			document.getElementById('row_tree_id').style.display='';
			document.getElementById('row_branch_id').style.display='';
			document.getElementById('row_tree_cascade').style.display='';
			document.getElementById('row_graph_name_regexp').style.display='';
			document.getElementById('row_host_template_id').style.display='none';
			document.getElementById('row_host_id').style.display='none';
			document.getElementById('row_graph_template_id').style.display='none';
			document.getElementById('row_local_graph_id').style.display='none';
			document.getElementById('row_timespan').style.display='';
			document.getElementById('row_item_text').style.display='none';
			if (useCss) {
				document.getElementById('row_font_size').style.display='none';
			}else{
				document.getElementById('row_font_size').style.display='';
			}
		} else {
			document.getElementById('row_tree_id').style.display='none';
			document.getElementById('row_branch_id').style.display='none';
			document.getElementById('row_tree_cascade').style.display='none';
			document.getElementById('row_graph_name_regexp').style.display='none';
			document.getElementById('row_host_template_id').style.display='none';
			document.getElementById('row_host_id').style.display='none';
			document.getElementById('row_graph_template_id').style.display='none';
			document.getElementById('row_local_graph_id').style.display='none';
			document.getElementById('row_timespan').style.display='none';
			document.getElementById('row_item_text').style.display='none';
			document.getElementById('row_font_size').style.display='none';
			document.getElementById('row_align').style.display='none';
		}
	}

	function applyChange(objForm) {
		strURL = '?action=item_edit'
		strURL = strURL + '&id=' + objForm.report_id.value
		strURL = strURL + '&item_id=' + objForm.id.value
		strURL = strURL + '&item_type=' + objForm.item_type.value;
		strURL = strURL + '&tree_id=' + objForm.tree_id.value;
		strURL = strURL + '&branch_id=' + objForm.branch_id.value;
		strURL = strURL + '&host_template_id=' + objForm.host_template_id.value;
		strURL = strURL + '&host_id=' + objForm.host_id.value;
		strURL = strURL + '&graph_template_id=' + objForm.graph_template_id.value;
		document.location = strURL;
	}

	function graphImage(graphId) {
		if (graphId > 0) {
			document.getElementById('graphdiv').style.display='';
			document.getElementById('graph').innerHTML="<img align='center' src='<?php print $config['url_path'];?>graph_image.php"+
					"?local_graph_id="+graphId+
					"<?php print (($report["graph_width"] > 0) ? "&graph_width=" . $report["graph_width"]:"");?>"+
					"<?php print (($report["graph_height"] > 0) ? "&graph_height=" . $report["graph_height"]:"");?>"+
					"<?php print (($report["thumbnails"] == "on") ? "&graph_nolegend=true":"");?>"+
					"<?php print ((isset($timespan["begin_now"])) ? "&graph_start=" . $timespan["begin_now"]:"");?>"+
					"<?php print ((isset($timespan["end_now"])) ? "&graph_end=" . $timespan["end_now"]:"");?>"+
					"&rra_id=0'>";
		}else{
			document.getElementById('graphdiv').style.display='none';
			document.getElementById('graph').innerHTML='';
		}
	}
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

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	input_validate_input_number(get_request_var_request('page'));
	/* ==================================================== */

	/* clean up rule name */
	if (isset($_REQUEST['name'])) {
		$_REQUEST['name'] = sanitize_search_string(get_request_var_request('name'));
	}

	/* clean up tab name */
	if (isset($_REQUEST['tab'])) {
		$_REQUEST['tab'] = sanitize_search_string(get_request_var_request('tab'));
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_reports_edit_current_page', '1');
	load_current_session_value('tab', 'sess_reports_edit_tab', 'detail');
	load_current_session_value('rows', 'sess_reports_edit_rows', read_config_option('num_rows_table'));

	/* display the report */
	$report = array();
	if (!empty($_REQUEST['id'])) {
		$report = db_fetch_row('SELECT * FROM reports WHERE id=' . $_REQUEST['id']);
		# reformat mailtime to human readable format
		$report['mailtime'] = date(reports_date_time_format(), $report['mailtime']);
		# setup header
		$header_label = '[edit: ' . $report['name'] . ']';
	}else{
		$header_label = '[new]';
		# initialize mailtime with current timestamp
		$report['mailtime'] = date(reports_date_time_format(), floor(time() / read_config_option('poller_interval')) * read_config_option('poller_interval'));
	}
	/* if there was an error on the form, display the date in the correct format */
	if (isset($_SESSION['sess_field_values']['mailtime'])) {
		$_SESSION['sess_field_values']['mailtime'] = date(reports_date_time_format(), $_SESSION['sess_field_values']['mailtime']);
	}

	$tabs = array('details' => 'Details', 'items' => 'Items', 'preview' => 'Preview', 'events' => 'Events');

	/* set the default settings category */
	if (!isset($_REQUEST['tab'])) $_REQUEST['tab'] = 'details';
	$current_tab = $_REQUEST['tab'];

	print "<table width='100%' cellpadding='0' cellspacing='0' border='0'><tr><td><div class='tabs'><nav><ul>\n";
	if (sizeof($tabs)) {
		foreach (array_keys($tabs) as $tab_short_name) {
			if (($tab_short_name != 'details' && !empty($_REQUEST['id'])) || $tab_short_name == 'details') {
				print "<li class='subTab'><a " . (($tab_short_name == $current_tab) ? "class='selected'" : "") .  " href='" . $config['url_path'] . get_reports_page() . "?" .
					"action=edit&tab=" . $tab_short_name .
					(isset($report["id"]) ? "&id=" . $_REQUEST["id"]:"") .
					"'>$tabs[$tab_short_name]</a></li>\n";
			}
		}
	}
	print "</ul></nav></div></td>\n";
	if (!empty($_REQUEST['id'])) {
		print "<td align='right'><a class='textHeader' href='" . htmlspecialchars(get_reports_page() . '?action=send&id=' . $_REQUEST['id'] . '&tab=' . $_REQUEST['tab']) . "'>Send Report</a></td>\n";
	}
	print "</tr></table>\n";

	switch($_REQUEST['tab']) {
	case 'details':
		print '<form name="report" action="' . get_reports_page() . '" method="post">';
		html_start_box("<strong>Report Details</strong> $header_label", '100%', '', '3', 'center', '');

		draw_edit_form(array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_reports_edit, $report)
		));

		html_end_box();
		form_hidden_box('id', (isset($report['id']) ? $report['id'] : '0'), '');
		form_hidden_box('save_component_report', '1', '');

		?>
		<script type='text/javascript'>
		var cformat = document.getElementById('cformat');
		cformat.setAttribute('onclick', 'changeFormat()');

		function addLoadEvent(func) {
			if(typeof window.onload != 'function')
				window.onload = func;
			else {
				var oldLoad = window.onload;

				window.onload = function() {
					if(oldLoad) oldLoad();
					func();
				}
			}
		}
					
		function changeFormat() {
			if (cformat && cformat.checked) {
				document.getElementById('row_font_size').style.display='none';
				document.getElementById('row_format_file').style.display='';
			}else{
				document.getElementById('row_font_size').style.display='';
				document.getElementById('row_format_file').style.display='none';
			}
		}

		addLoadEvent(changeFormat);
		</script>
		<?php

		form_save_button(get_reports_page(), 'return');

		break;
	case 'items':
		html_start_box("<strong>Report Items</strong> $header_label", '100%', '', '3', 'center', get_reports_page() . '?action=item_edit&id=' . $_REQUEST['id']);

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

		html_start_box("<strong>Scheduled Events</strong> $header_label", '100%', '', '3', 'center', '');
		for ($i=0; $i<14; $i++) {
			form_alternate_row('line' . $i, true);
			form_selectable_cell(date($date_format, $next), $i);
			form_end_row();
			$next = reports_interval_start($report['intrvl'], $report['count'], $report['offset'], $next);
		}
		html_end_box(false);

		break;
	case 'preview':
		html_start_box("<strong>Report Preview</strong> $header_label", '100%', '', '3', 'center', '');
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

	$items = db_fetch_assoc('SELECT *
		FROM reports_items
		WHERE report_id=' . $report_id . '
		ORDER BY sequence');

	$css = db_fetch_cell('SELECT cformat FROM reports WHERE id=' . $report_id);

	html_header(array('Item', 'Sequence', 'Type', 'Item Details', 'Timespan', 'Alignment', 'Font Size', 'Actions'), 2);

	$i = 0;
	if (sizeof($items) > 0) {
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
					$branch_details = db_fetch_row('SELECT * FROM graph_tree_items WHERE id=' . $item['branch_id']);
				}else{
					$branch_details = array();
				}
				$tree_name      = db_fetch_cell('SELECT name FROM graph_tree WHERE id=' . $item['tree_id']);

				$item_details = 'Tree: ' . $tree_name;
				if ($item['branch_id'] > 0) {
					if ($branch_details['host_id'] > 0) {
						$item_details .= ', Host: ' . db_fetch_cell('SELECT description FROM host WHERE id=' . $branch_details['host_id']);
					}else{
						$item_details .= ', Branch: ' . $branch_details['title'];

						if ($item['tree_cascade'] == 'on') {
							$item_details .= ' (All Branches)';
						}else{
							$item_details .= ' (Current Branch)';
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
			$form_data = '<td><a class="linkEditMain" href="' . htmlspecialchars(get_reports_page() . '?action=item_edit&id=' . $report_id. '&item_id=' . $item['id']) . '">Item#' . $i . '</a></td>';$i++;
			$form_data .= '<td>' . $item['sequence'] . '</td>';
			$form_data .= '<td>' . $item_types{$item['item_type']} . '</td>';
			$form_data .= '<td style="white-space:nowrap;">' . $item_details . '</td>';
			$form_data .= '<td style="white-space:nowrap;">' . $timespan . '</td>';
			$form_data .= '<td>' . $align . '</td>';
			$form_data .= '<td>' . $size . '</td>';

			$form_data .= '<td align="right" style="text-align:right;min-width:5%;white-space:nowrap;"><a href="' . htmlspecialchars(get_reports_page() . '?action=item_movedown&item_id=' . $item['id'] . '&id=' . $report_id) . '"><img src="images/move_down.gif" border="0" alt="Move Down"></a>' . '<a	href="' . htmlspecialchars(get_reports_page() . '?action=item_moveup&item_id=' . $item['id'] .	'&id=' . $report_id) . '"><img src="images/move_up.gif" border="0" alt="Move Up"></a>' . '</td>';

			$form_data .= '<td align="right"><a href="' . htmlspecialchars(get_reports_page() . '?action=item_remove&item_id=' . $item['id'] . '&id=' . $report_id) . '"><img src="images/delete_icon.gif" border="0" width="10" height="10" alt="Delete"></a>' . '</td></tr>';
			print $form_data;
		}
	} else {
		print "<tr><td><em>No Report Items</em></td></tr>\n";
	}
}

function get_reports_page() {
	return (is_realm_allowed(22) ? 'reports_admin.php':'reports_user.php');
}

function is_reports_admin() {
	return (is_realm_allowed(22) ? true:false);
}

function reports() {
	global $config, $item_rows, $reports_interval;
	global $reports_actions, $attach_types;

	include_once($config['base_path'] . '/lib/reports.php');

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('page'));
	input_validate_input_number(get_request_var_request('status'));
	input_validate_input_number(get_request_var_request('rows'));
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
		kill_session_var('sess_reports_current_page');
		kill_session_var('sess_reports_filter');
		kill_session_var('sess_reports_sort_column');
		kill_session_var('sess_reports_sort_direction');
		kill_session_var('sess_status');
		kill_session_var('sess_rows');

		unset($_REQUEST['page']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
		unset($_REQUEST['status']);
		unset($_REQUEST['rows']);
	}

	if ((!empty($_SESSION['sess_status'])) && (!empty($_REQUEST['status']))) {
		if ($_SESSION['sess_status'] != $_REQUEST['status']) {
			$_REQUEST['page'] = 1;
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_reports_current_page', '1');
	load_current_session_value('filter', 'sess_reports_filter', '');
	load_current_session_value('sort_column', 'sess_reports_sort_column', 'name');
	load_current_session_value('sort_direction', 'sess_reports_sort_direction', 'ASC');
	load_current_session_value('status', 'sess_reports_status', '-1');
	load_current_session_value('rows', 'sess_rows', read_config_option('num_rows_table'));

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST['rows'] == -1) {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var_request('rows');
	}

	print '<form id="form_report" action="' . get_reports_page() . '">';

	html_start_box('<strong>Reports</strong>' . (is_reports_admin() ? ' [ Administrator Level ]':' [ User Level ]'), '100%', '', '3', 'center', get_reports_page() . '?action=edit&tab=details');

	print "<tr class='even'>
		<td>
			<table cellpadding='2' cellspacing='0'>
				<tr>
					<td width='50'>
						Search
					</td>
					<td>
						<input type='text' id='filter' size='20' value='" . get_request_var_request('filter') . "'>
					</td>
					<td>
						Status
					</td>
					<td>
						<select id='status' onChange='applyFilter(document.report)'>
							<option value='-1'" . (get_request_var_request('status') == '-1' ? 'selected':'') . ">Any</option>
							<option value='-2'" . (get_request_var_request('status') == '-2' ? 'selected':'') . ">Enabled</option>
							<option value='-3'" . (get_request_var_request('status') == '-3' ? 'selected':'') . ">Disabled</option>
						</select>
					</td>
					<td>
						Rows
					</td>
					<td>
						<select id='rows' onChange='applyFilter(document.report)'>
							<option value='-1'" . (get_request_var_request('rows') == '-1' ? 'selected':'') . '>Default</option>';
							if (sizeof($item_rows)) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'" . 
									(get_request_var_request('rows') == $key ? ' selected':'') . ">$value</option>\n";
							}
							}
	print "				</select>
					</td>
					<td>
						<input id='refresh' type='button' value='Go' name='go'>
					</td>
					<td>
						<input id='clear' type='button' value='Clear' name='clear_x'>
					</td>
				</tr>
			</table>
		</td>
		<td><input type='hidden' name='page' value='" . $_REQUEST['page']. "'></td>
	</tr>\n";

	html_end_box(TRUE);

	print "</form>\n";

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var_request('filter'))) {
		$sql_where = "WHERE (reports.name LIKE '%%" . get_request_var_request('filter') . "%%')";
	}else{
		$sql_where = '';
	}

	if (get_request_var_request('status') == '-1') {
		/* Show all items */
	}elseif (get_request_var_request('status') == '-2') {
		$sql_where .= (strlen($sql_where) ? " AND reports.enabled='on'" : " WHERE reports.enabled='on'");
	}elseif (get_request_var_request('status') == '-3') {
		$sql_where .= (strlen($sql_where) ? " AND reports.enabled=''" : " WHERE reports.enabled=''");
	}

	/* account for permissions */
	if (is_reports_admin()) {
		$sql_join = 'LEFT JOIN user_auth ON user_auth.id=reports.user_id';
	}else{
		$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' user_auth.id=' . $_SESSION['sess_user_id'];
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
		get_request_var_request('sort_column') . ' ' .
		get_request_var_request('sort_direction') .
		' LIMIT ' . ($rows*(get_request_var_request('page')-1)) . ',' . $rows);

	$nav = html_nav_bar(get_reports_page() . 'filter=' . get_request_var_request('filter') . '&host_id=' . get_request_var_request('host_id'), MAX_DISPLAY_PAGES, get_request_var_request('page'), $rows, $total_rows, 10, 'Reports', 'page', 'main');

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='". get_reports_page() . "'>\n";

	html_start_box('', '100%', '', '3', 'center', '');

	print $nav;
	
	if (is_reports_admin()) {
		$display_text = array(
			'name'            => array('display' => 'Report Name', 'align' => 'left', 'sort' => 'ASC'),
			'full_name'       => array('display' => 'Owner',       'align' => 'left', 'sort' => 'ASC'),
			'cint'            => array('display' => 'Frequency',   'align' => 'left', 'sort' => 'ASC'),
			'lastsent'        => array('display' => 'Last Run',    'align' => 'left', 'sort' => 'ASC'),
			'mailtime'        => array('display' => 'Next Run',    'align' => 'left', 'sort' => 'ASC'),
			'from_name'       => array('display' => 'From',        'align' => 'left', 'sort' => 'ASC'),
			'nosort'          => array('display' => 'To',          'align' => 'left', 'sort' => 'ASC'),
			'attachment_type' => array('display' => 'Type',        'align' => 'left', 'sort' => 'ASC'),
			'enabled'         => array('display' => 'Enabled',     'align' => 'left', 'sort' => 'ASC'),
		);
	}else{
		$display_text = array(
			'name'            => array('display' => 'Report Title', 'align' => 'left', 'sort' => 'ASC'),
			'cint'            => array('display' => 'Frequency',    'align' => 'left', 'sort' => 'ASC'),
			'lastsent'        => array('display' => 'Last Run',     'align' => 'left', 'sort' => 'ASC'),
			'mailtime'        => array('display' => 'Next Run',     'align' => 'left', 'sort' => 'ASC'),
			'from_name'       => array('display' => 'From',         'align' => 'left', 'sort' => 'ASC'),
			'nosort'          => array('display' => 'To',           'align' => 'left', 'sort' => 'ASC'),
			'attachment_type' => array('display' => 'Type',         'align' => 'left', 'sort' => 'ASC'),
			'enabled'         => array('display' => 'Enabled',      'align' => 'left', 'sort' => 'ASC'),
		);
	}

	html_header_sort_checkbox($display_text, get_request_var_request('sort_column'), get_request_var_request('sort_direction'), false);

	$i = 0;
	if (sizeof($reports_list) > 0) {
		$date_format = reports_date_time_format();

		foreach ($reports_list as $report) {
			form_alternate_row('line' . $report['id'], true);

			form_selectable_cell("<a style='white-space:nowrap;' class='linkEditMain' href='" . htmlspecialchars(get_reports_page() . '?action=edit&tab=details&id=' . $report['id'] . "&page=1 ' title='" . $report['name']) . "'>" . ((get_request_var_request('filter') != '') ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", $report['name']) : $report['name']) . '</a>', $report['id']);
			if (is_reports_admin()) form_selectable_cell($report['full_name'], $report['id']);
			$interval = 'Every ' . $report['count'] . ' ' . $reports_interval[$report['intrvl']];

			form_selectable_cell($interval, $report['id']);
			form_selectable_cell(($report['lastsent'] == 0) ? 'Never' : date($date_format, $report['lastsent']), $report['lastsent']);
			form_selectable_cell(date($date_format, $report['mailtime']), $report['id']);
			form_selectable_cell($report['from_name'], $report['id']);
			form_selectable_cell((substr_count($report['email'], ',') ? 'Multiple': $report['email']), $report['id']);
			form_selectable_cell((isset($attach_types{$report['attachment_type']})) ? $attach_types{$report['attachment_type']} : 'Invalid', $report['id']);
			form_selectable_cell($report['enabled'] ? 'Enabled' : 'Disabled', $report['id']);
			form_checkbox_cell($report['name'], $report['id']);

			form_end_row();
		}
		print $nav;
	}else{
		print "<tr><td><em>No Reports Found</em></td></tr>\n";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($reports_actions);

	print "</form>\n";
	?>
	<script type='text/javascript'>
	<!--
	function applyFilter() {
		strURL = '<?php print get_reports_page();?>?header=false&status=' + $('#status').val();
		strURL = strURL + '&rows=' + $('#rows').val();
		strURL = strURL + '&filter=' + $('#filter').val();
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	function clearFilter() {
		$.get('<?php print get_reports_page();?>?header=false&clear_x=1', function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	$(function(data) {
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

	-->
	</script>
	<?php
}

