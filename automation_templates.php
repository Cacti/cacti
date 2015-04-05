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
		$save['snmp_version']  = form_input_validate($_POST['snmp_version'], 'snmp_version', '', false, 3);
		$save['tree']          = form_input_validate($_POST['tree'], 'tree', '', false, 3);
		$save['sysdescr']      = db_qstr($_POST['sysdescr']);
		$save['sysname']       = db_qstr($_POST['sysname']);
		$save['sysoid']        = db_qstr($_POST['sysoid']);
		if (function_exists('filter_var')) {
			$save['sysdescr'] = filter_var($save['sysdescr'], FILTER_SANITIZE_STRING);
		} else {
			$save['sysdescr'] = strip_tags($save['sysdescr']);
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
			header('Location: automation_templates.php?id=' . (empty($template_id) ? $_POST['id'] : $template_id));
		}else{
			header('Location: automation_templates.php');
		}
	}
}

function automation_get_tree_headers() {
	$headers = array();
	$trees = db_fetch_assoc('SELECT id, name FROM graph_tree ORDER BY name');
	foreach ($trees as $tree) {
		$headers[($tree['id'] + 1000000)] = $tree['name'];
		$items = db_fetch_assoc('SELECT id, title 
			FROM graph_tree_items 
			WHERE graph_tree_id=' . $tree['id'] . ' 
			AND host_id=0 
			AND local_graph_id=0 
			AND parent=0 
			ORDER BY position');

		foreach ($items as $item) {
			$order_key = $item['order_key'];
			$len = strlen($order_key);
			$spaces = '';
			for ($a = 0; $a < $len; $a=$a+3) {
				$n = substr($order_key, $a, 3);
				if ($n != '000') {
					$spaces .= '--';
				} else {
					$a = $len;
				}
			}

			$headers[$item['id']] = $spaces . $item['title'];
		}
	}

	return $headers;
}

function template_edit() {
	global $colors, $snmp_versions;

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
		'tree' => array(
			'method' => 'drop_array',
			'friendly_name' => 'Tree Location',
			'description' => 'Select a location in the tree to place the host under.',
			'value' => '|arg1:tree|',
			'array' => automation_get_tree_headers(),
			),
		'snmp_version' => array(
			'method' => 'drop_array',
			'friendly_name' => 'SNMP Version',
			'description' => 'Choose the SNMP version for this host.',
			'value' => '|arg1:snmp_version|',
			'default' => read_config_option('snmp_ver'),
			'array' => $snmp_versions,
			),
		'sysdescr' => array(
			'method' => 'textbox',
			'friendly_name' => 'System Description Match',
			'description' => 'This is a unique string that will be matched to a devices sysDescr string to pair it to this Discovery Template.  Any perl regular expression can be used.',
			'value' => '|arg1:sysdescr|',
			'max_length' => '255',
			),
		'sysname' => array(
			'method' => 'textbox',
			'friendly_name' => 'System Name Match',
			'description' => 'This is a unique string that will be matched to a devices sysName string to pair it to this Automation Template.  Any perl regular expression can be used.',
			'value' => '|arg1:sysname|',
			'max_length' => '128',
			),
		'sysoid' => array(
			'method' => 'textbox',
			'friendly_name' => 'System OID Match',
			'description' => 'This is a unique string that will be matched to a devices sysOid string to pair it to this Automation Template.  Any perl regular expression can be used.',
			'value' => '|arg1:sysoid|',
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

	html_start_box("<strong>Automation Templates</strong> $header_label", '100%', $colors['header'], '3', 'center', '');

	draw_edit_form(array(
		'config' => array(),
		'fields' => inject_form_variables($fields_automation_template_edit, (isset($host_template) ? $host_template : array()))
		));

	html_end_box();

	form_save_button('automation_templates.php');
}

function template() {
	global $colors, $host_actions;

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

	html_start_box('<strong>Automation Device Templates</strong>', '100%', $colors['header'], '3', 'center', 'automation_templates.php?action=edit');

	$display_text = array(
		array('display' => 'Template Name', 'align' => 'left'),
		array('display' => 'System Description Match', 'align' => 'left'),
		array('display' => 'System Name Match', 'align' => 'left'),
		array('display' => 'System ObjectId Match', 'align' => 'left'),
		array('display' => 'Action', 'align' => 'right'));

	html_header($display_text);

	$dts = db_fetch_assoc("SELECT at.*, '' AS sysname, ht.name
		FROM automation_templates AS at
		LEFT JOIN host_template AS ht
		ON ht.id=at.host_template
		ORDER BY sequence");

	$i = 1;
	if (sizeof($dts)) {
		$total_items = sizeof($dts);

		foreach ($dts as $dt) {
			form_alternate_row();
			echo "<td><a class='linkEditMain' href='automation_templates.php?action=edit&id=" . $dt['id'] . "'>" . htmlspecialchars($dt['name']) . "</a></td>\n";
			echo "<td>" . htmlspecialchars($dt['sysdescr']) . "</td>\n";
			echo "<td>" . htmlspecialchars($dt['sysname'])  . "</td>\n";
			echo "<td>" . htmlspecialchars($dt['sysoid'])   . "</td>\n";

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
			action = $(this).attr('href');
			$.get(action, function(data) {
				$('#main').html(data);
				applySkin();
			});
		});
	});
	</script>
	<?php
}
?>
