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
include_once('./lib/utility.php');

$host_actions = array(
	1 => 'Delete'
);

/* set default action */
if (!isset($_REQUEST['action'])) { $_REQUEST['action'] = ''; }

switch ($_REQUEST['action']) {
	case 'save':
		form_save();

		break;
    case 'movedown':
        automation_movedown();

        header('Location: automation_templates.php?header=false');
		break;
    case 'moveup':
        automation_moveup();

        header('Location: automation_templates.php?header=false');
		break;
    case 'remove':
        automation_remove();

        header('Location: automation_templates.php?header=false');
		break;
	case 'edit':
		top_header();
		template_edit();
		bottom_footer();
		break;
	default:
		top_header();
		template();
		bottom_footer();
		break;
}

function automation_movedown() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	/* ==================================================== */
	move_item_down('automation_templates', get_request_var_request('id'));
}

function automation_moveup() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	/* ==================================================== */
	move_item_up('automation_templates', get_request_var_request('id'));
}

function automation_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	/* ==================================================== */
	db_execute('DELETE FROM automation_templates WHERE id=' . get_request_var_request('id'));
}

function form_save() {
	if (isset($_POST['save_component_template'])) {
		$redirect_back = false;

		$save['id'] = $_POST['id'];
		$save['host_template'] = form_input_validate($_POST['host_template'], 'host_template', '', false, 3);
		$save['availability_method']  = form_input_validate($_POST['availability_method'], 'availability_method', '', false, 3);
		$save['sysDescr']      = $_POST['sysDescr'];
		$save['sysName']       = $_POST['sysName'];
		$save['sysOid']        = $_POST['sysOid'];
		if (function_exists('filter_var')) {
			$save['sysDescr'] = filter_var($save['sysDescr'], FILTER_SANITIZE_STRING);
		} else {
			$save['sysDescr'] = strip_tags($save['sysDescr']);
		}

		if (!is_error_message()) {
			$template_id = sql_save($save, 'automation_templates');

			if ($template_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (is_error_message() || empty($_POST['id'])) {
			header('Location: automation_templates.php?header=false&id=' . (empty($template_id) ? $_POST['id'] : $template_id));
		}else{
			header('Location: automation_templates.php?header=false');
		}
	}
}

function automation_get_child_branches($tree_id, $id, $spaces, $headers) {
	$items = db_fetch_assoc('SELECT id, title
		FROM graph_tree_items 
		WHERE graph_tree_id=' . $tree_id  . '
		AND host_id=0
		AND local_graph_id=0 
		AND parent=' . $id . '
		ORDER BY position');

	$spaces .= '--';

	if (sizeof($items)) {
	foreach($items as $i) {
		$headers['tr_' . $tree_id . '_bi_' . $i['id']] = $spaces . ' ' . $i['title'];
		$headers = automation_get_child_branches($tree_id, $i['id'], $spaces, $headers);
	}
	}
	
	return $headers;
}

function automation_get_tree_headers() {
	$headers = array();
	$trees   = db_fetch_assoc('SELECT id, name FROM graph_tree ORDER BY name');
	foreach ($trees as $tree) {
		$headers['tr_' . $tree['id'] . '_br_0'] = $tree['name'];
		$spaces = '';
		$headers = automation_get_child_branches($tree['id'], 0, $spaces, $headers);
	}

	return $headers;
}

function template_edit() {
	global $availability_options;

	$host_template_names = db_fetch_assoc('SELECT id, name FROM host_template');
	$template_names = array();

	if (sizeof($host_template_names) > 0) {
		foreach ($host_template_names as $ht) {
			$template_names[$ht['id']] = $ht['name'];
		}
	}

	$fields_automation_template_edit = array(
		'host_template' => array(
			'method' => 'drop_array',
			'friendly_name' => 'Host Template',
			'description' => 'Select a Device Template that Devices will be matched to.',
			'value' => '|arg1:host_template|',
			'array' => $template_names,
			),
		'availability_method' => array(
			'method' => 'drop_array',
			'friendly_name' => 'Availability Method',
			'description' => 'Choose the Availability Method to use for Discovered Devices.',
			'value' => '|arg1:availability_method|',
			'default' => read_config_option('availability_method'),
			'array' => $availability_options,
			),
		'sysDescr' => array(
			'method' => 'textbox',
			'friendly_name' => 'System Description Match',
			'description' => 'This is a unique string that will be matched to a devices sysDescr string to pair it to this Discovery Template.  Any perl regular expression can be used in addition to any wildcardable SQL Where expression.',
			'value' => '|arg1:sysDescr|',
			'max_length' => '255',
			),
		'sysName' => array(
			'method' => 'textbox',
			'friendly_name' => 'System Name Match',
			'description' => 'This is a unique string that will be matched to a devices sysName string to pair it to this Automation Template.  Any perl regular expression can be used in addition to any wildcardable SQL Where expression.',
			'value' => '|arg1:sysName|',
			'max_length' => '128',
			),
		'sysOid' => array(
			'method' => 'textbox',
			'friendly_name' => 'System OID Match',
			'description' => 'This is a unique string that will be matched to a devices sysOid string to pair it to this Automation Template.  Any perl regular expression can be used in addition to any wildcardable SQL Where expression.',
			'value' => '|arg1:sysOid|',
			'max_length' => '128',
			),
		'id' => array(
			'method' => 'hidden_zero',
			'value' => '|arg1:id|'
			),
		'save_component_template' => array(
			'method' => 'hidden',
			'value' => '1'
			)
		);

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	/* ==================================================== */

	display_output_messages();

	if (!empty($_GET['id'])) {
		$host_template = db_fetch_row('SELECT * FROM automation_templates WHERE id=' . $_GET['id']);
		$header_label = '[edit: ' . $template_names[$host_template['host_template']] . ']';
	}else{
		$header_label = '[new]';
		$_GET['id'] = 0;
	}

	form_start('automation_templates.php', 'form_network');

	html_start_box("<strong>Automation Templates</strong> $header_label", '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => 'true'),
		'fields' => inject_form_variables($fields_automation_template_edit, (isset($host_template) ? $host_template : array()))
		));

	html_end_box();

	form_save_button('automation_templates.php');
}

function template() {
	global $host_actions, $availability_options;

	/* clean up sort_column */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var('sort_column'));
	}

	/* clean up search string */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var('sort_direction'));
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('sort_column', 'sess_automation_temp_column', 'name');
	load_current_session_value('sort_direction', 'sess_automation_temp_sort_direction', 'ASC');

	display_output_messages();

	html_start_box('<strong>Automation Device Templates</strong>', '100%', '', '3', 'center', 'automation_templates.php?action=edit');

	$display_text = array(
		array('display' => 'Template Name', 'align' => 'left'),
		array('display' => 'Availability Method', 'align' => 'left'),
		array('display' => 'System Description Match', 'align' => 'left'),
		array('display' => 'System Name Match', 'align' => 'left'),
		array('display' => 'System ObjectId Match', 'align' => 'left'),
		array('display' => 'Action', 'align' => 'right'));

	html_header($display_text);

	$dts = db_fetch_assoc("SELECT at.*, '' AS sysName, ht.name
		FROM automation_templates AS at
		LEFT JOIN host_template AS ht
		ON ht.id=at.host_template
		ORDER BY sequence");

	$i = 1;
	if (sizeof($dts)) {
		$total_items = sizeof($dts);

		foreach ($dts as $dt) {
			form_alternate_row("at$i", true);
			echo "<td><a class='linkEditMain' href='automation_templates.php?action=edit&id=" . $dt['id'] . "'>" . htmlspecialchars($dt['name']) . "</a></td>\n";
			echo "<td>" . $availability_options[$dt['availability_method']] . "</td>\n";
			echo "<td>" . htmlspecialchars($dt['sysDescr']) . "</td>\n";
			echo "<td>" . htmlspecialchars($dt['sysName'])  . "</td>\n";
			echo "<td>" . htmlspecialchars($dt['sysOid'])   . "</td>\n";

			if ($i < $total_items && $total_items > 1) {
				$form_data = '<img style="padding:2px;cursor:pointer;" class="action" href="' . htmlspecialchars('automation_templates.php?action=movedown&id=' . $dt['id']) . '" src="images/move_down.gif" border="0" alt="Move Down">';
			}else{
				$form_data = '<img height="14" width="14" src="images/view_none.gif" border="0" alt="">';
			}

			if ($i > 1 && $i <= $total_items) {
				$form_data .= '<img style="padding:2px;cursor:pointer;" class="action" href="' . htmlspecialchars('automation_templates.php?action=moveup&id=' . $dt['id']) . '" src="images/move_up.gif" border="0" alt="Move Up">';
			}else{
				$form_data .= '<img height="14" width="14" src="images/view_none.gif" border="0" alt="">';
			}
			$form_data .= '<img style="padding:2px;cursor:pointer;" class="action" href="' . htmlspecialchars('automation_templates.php?action=remove&id=' . $dt['id']) . '" src="images/delete_icon.gif" border="0" width="10" height="10" alt="Delete">';

			echo "<td style='white-space:nowrap;'><div style='text-align:right;'>" . $form_data . "</div></td>\n";
			form_end_row();

			$i++;
		}
	}else{
		print "<tr><td><em>No Automation Device Templates</em></td></tr>\n";
	}

	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($host_actions);

	print "</form>\n";
	?>
	<script type='text/javascript'>
	$(function() {
		$('img.action').click(function() {
			strURL = $(this).attr('href');
			loadPageNoHeader(strURL);
		});
	});
	</script>
	<?php
}
?>
