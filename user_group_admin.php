<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2010 The Cacti Group                                 |
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

define('MAX_DISPLAY_PAGES', 21);

//print_r($_POST);exit;

$group_actions = array(
	1 => 'Delete',
	3 => 'Enable',
	4 => 'Disable'
	);

$href_options = array(
	3 => array(
		'radio_value' => '4',
		'radio_caption' => 'Defer to the Users Setting'
		),
	0 => array(
		'radio_value' => '1',
		'radio_caption' => 'Show the Page that the User pointed their browser to'
		),
	1 => array(
		'radio_value' => '2',
		'radio_caption' => 'Show the Console'
		),
	2 => array(
		'radio_value' => '3',
		'radio_caption' => 'Show the default Graph Screen'
		)
);

$gperm_options = array(
	0 => array(
		'radio_value' => '1',
		'radio_caption' => 'Defer to the Users Setting'
		),
	1 => array(
		'radio_value' => '2',
		'radio_caption' => 'Grant Access'
		),
	2 => array(
		'radio_value' => '3',
		'radio_caption' => 'Restrict Access'
		)
);

//if (isset($_REQUEST['id']) && !group_group_console_allowed($_REQUEST['id'])) {
//	unset($href_options[1]);
//}

$fields_user_group_edit = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => 'Group Name',
		'description' => 'The name for this Group.',
		'value' => '|arg1:name|',
		'max_length' => '255'
		),
	'description' => array(
		'method' => 'textbox',
		'friendly_name' => 'Group Description',
		'description' => 'A more descriptive name for this group, that can include spaces or special characters.',
		'value' => '|arg1:description|',
		'max_length' => '255'
		),
	'enabled' => array(
		'method' => 'checkbox',
		'friendly_name' => 'Enabled',
		'description' => 'Determines if user is able to login.',
		'value' => '|arg1:enabled|',
		'default' => ''
		),
	'grp1' => array(
		'friendly_name' => 'General Group Options',
		'method' => 'checkbox_group',
		'description' => 'Set any user account-specific options here.',
		'items' => array(
			'graph_settings' => array(
				'value' => '|arg1:graph_settings|',
				'friendly_name' => 'Allow Users of this Group to keep custom Graph Settings',
				'form_id' => '|arg1:id|',
				'default' => 'on'
				)
			)
		),
	'show_tree' => array(
		'friendly_name' => 'Graph Tree Rights',
		'method' => 'radio',
		'description' => 'Should Users of this Group have access to the Tree?',
		'value' => '|arg1:show_tree|',
		'default' => '1',
		'items' => $gperm_options
		),
	'show_list' => array(
		'friendly_name' => 'Graph List Rights',
		'method' => 'radio',
		'description' => 'Should Users of this Group have access to the Graph List?',
		'value' => '|arg1:show_list|',
		'default' => '1',
		'items' => $gperm_options
		),
	'show_preview' => array(
		'friendly_name' => 'Graph Preview Rights',
		'method' => 'radio',
		'description' => 'Should Users of this Group have access to the Graph Preview?',
		'value' => '|arg1:show_preview|',
		'default' => '1',
		'items' => $gperm_options
		),
	'login_opts' => array(
		'friendly_name' => 'Login Options',
		'method' => 'radio',
		'default' => '1',
		'description' => 'What to do when a User from this User Group logs in.',
		'value' => '|arg1:login_opts|',
		'items' => $href_options
		),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
		),
	'_policy_graphs' => array(
		'method' => 'hidden',
		'default' => '2',
		'value' => '|arg1:policy_graphs|'
		),
	'_policy_trees' => array(
		'method' => 'hidden',
		'default' => '2',
		'value' => '|arg1:policy_trees|'
		),
	'_policy_hosts' => array(
		'method' => 'hidden',
		'default' => '2',
		'value' => '|arg1:policy_hosts|'
		),
	'_policy_graph_templates' => array(
		'method' => 'hidden',
		'default' => '2',
		'value' => '|arg1:policy_graph_templates|'
		),
	'save_component_group' => array(
		'method' => 'hidden',
		'value' => '1'
		)
);

if (isset($_POST['update_policy'])) {
	update_policies();
}else{
	switch (get_request_var_request('action')) {
	case 'actions':
		form_actions();

		break;
	case 'save':
		form_save();

		break;
	case 'perm_remove':
		perm_remove();

		break;
	case 'edit':
		top_header();
		group_edit();
		bottom_footer();

		break;
	default:
		if (!api_plugin_hook_function('user_group_admin_action', get_request_var_request('action'))) {
			top_header();
			user_group();
			bottom_footer();
		}

		break;
	}
}

/* --------------------------
    Actions Function
   -------------------------- */

function user_group_disable($id) {
	db_execute_prepared("UPDATE user_auth_group SET enabled = '' WHERE id = ?", array($id));
}

function user_group_enable($id) {
	db_execute_prepared("UPDATE user_auth_group SET enabled = 'on' WHERE id = ?", array($id));
}

function user_group_remove($id) {
	db_execute_prepared("DELETE FROM user_auth_group WHERE id = ?", array($id));
	db_execute_prepared("DELETE FROM user_auth_group_members WHERE group_id = ?", array($id));
	db_execute_prepared("DELETE FROM user_auth_group_realm WHERE group_id = ?", array($id));
	db_execute_prepared("DELETE FROM user_auth_group_perms WHERE group_id = ?", array($id));
}

function update_policies() {
	$set = '';

	$set .= isset($_POST['policy_graphs']) ? 'policy_graphs=' . get_request_var_post('policy_graphs'):'';
	$set .= isset($_POST['policy_trees']) ? (strlen($set) ? ',':'') . 'policy_trees=' . get_request_var_post('policy_trees'):'';
	$set .= isset($_POST['policy_hosts']) ? (strlen($set) ? ',':'') . 'policy_hosts=' . get_request_var_post('policy_hosts'):'';
	$set .= isset($_POST['policy_graph_templates']) ? (strlen($set) ? ',':'') . 'policy_graph_templates=' . get_request_var_post('policy_graph_templates'):'';

	if (strlen($set)) {
		db_execute_prepared("UPDATE user_auth_group SET $set WHERE id = ?", array(get_request_var_post('id')));
	}

	header('Location: user_group_admin.php?action=edit&tab=' .  get_request_var_post('tab') . '&id=' . get_request_var_post('id'));
	exit;
}

function form_actions() {
	global $group_actions, $user_auth_realms;

	/* if we are to save this form, instead of display it */
	if (isset($_POST['associate_host'])) {
		while (list($var,$val) = each($_POST)) {
			if (ereg('^chk_([0-9]+)$', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				if (get_request_var_post('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_group_perms (group_id, item_id, type) VALUES (?, ?, 3)', array(get_request_var_post('id'), $matches[1]));
				}else{
					db_execute_prepared('DELETE FROM user_auth_group_perms WHERE group_id = ? AND item_id = ? AND type = 3', array(get_request_var_post('id'), $matches[1]));
				}
			}
		}

		header('Location: user_group_admin.php?action=edit&tab=permsd&id=' . get_request_var_post('id'));
		exit;
	}elseif (isset($_POST['associate_graph'])) {
		while (list($var,$val) = each($_POST)) {
			if (ereg('^chk_([0-9]+)$', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				if (get_request_var_post('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_group_perms (group_id, item_id, type) VALUES (?, ?, 1)', array(get_request_var_post('id'), $matches[1]));
				}else{
					db_execute_prepared('DELETE FROM user_auth_group_perms WHERE group_id = ? AND item_id = ? AND type = 1', array(get_request_var_post('id'), $matches[1]));
				}
			}
		}

		header('Location: user_group_admin.php?action=edit&tab=permsg&id=' . get_request_var_post('id'));
		exit;
	}elseif (isset($_POST['associate_template'])) {
		while (list($var,$val) = each($_POST)) {
			if (ereg('^chk_([0-9]+)$', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				if (get_request_var_post('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_group_perms (group_id, item_id, type) VALUES (?, ?, 4)', array(get_request_var_post('id'), $matches[1]));
				}else{
					db_execute_prepared('DELETE FROM user_auth_group_perms WHERE group_id = ? AND item_id = ? AND type = 4', array(get_request_var_post('id'), $matches[1]));
				}
			}
		}

		header('Location: user_group_admin.php?action=edit&tab=permste&id=' . get_request_var_post('id'));
		exit;
	}elseif (isset($_POST['associate_tree'])) {
		while (list($var,$val) = each($_POST)) {
			if (ereg('^chk_([0-9]+)$', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				if (get_request_var_post('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_group_perms (group_id, item_id, type) VALUES (?, ?, 2)', array(get_request_var_post('id'), $matches[1]));
				}else{
					db_execute_prepared('DELETE FROM user_auth_group_perms WHERE group_id = ? AND item_id = ? AND type = 2', array(get_request_var_post('id'), $matches[1]));
				}
			}
		}

		header('Location: user_group_admin.php?action=edit&tab=permstr&id=' . get_request_var_post('id'));
		exit;
	}elseif (isset($_POST['associate_member'])) {
		while (list($var,$val) = each($_POST)) {
			if (ereg('^chk_([0-9]+)$', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				if (get_request_var_post('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_group_members (group_id, user_id) VALUES (?, ?)', array(get_request_var_post('id'), $matches[1]));
				}else{
					db_execute_prepared('DELETE FROM user_auth_group_members WHERE group_id = ? AND user_id = ?', array(get_request_var_post('id'), $matches[1]));
				}
			}
		}

		header('Location: user_group_admin.php?action=edit&tab=members&id=' . get_request_var_post('id'));
		exit;
	}elseif (isset($_POST['selected_items'])) {
		if (get_request_var_post('drp_action') != '2') {
			$selected_items = unserialize(stripslashes(get_request_var_post('selected_items')));
		}

		if (get_request_var_post('drp_action') == '1') { /* delete */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				user_group_remove($selected_items[$i]);
			}
		}

		if (get_request_var_post('drp_action') == '3') { /* enable */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				user_group_enable($selected_items[$i]);
			}
		}

		if (get_request_var_post('drp_action') == '4') { /* disable */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				user_group_disable($selected_items[$i]);
			}
		}

		header('Location: user_group_admin.php');
		exit;
	}

	/* loop through each of the users and process them */
	$group_list = '';
	$group_array = array();
	$i = 0;
	while (list($var,$val) = each($_POST)) {
		if (ereg('^chk_([0-9]+)$', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			if (get_request_var_post('drp_action') != '2') {
				$group_list .= '<li>' . db_fetch_cell_prepared('SELECT name FROM user_auth_group WHERE id = ?', array($matches[1])) . '</li>';
			}
			$group_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	html_start_box('<strong>' . $group_actions[get_request_var_post('drp_action')] . '</strong>', '60%', '', '3', 'center', '');

	print "<form action='user_group_admin.php' method='post'>\n";

	if (isset($group_array) && sizeof($group_array)) {
		if ((get_request_var_post('drp_action') == '1') && (sizeof($group_array))) { /* delete */
			print "
				<tr>
					<td class='textArea'>
						<p>When you click \"Continue\", the selected User Group(s) will be deleted.</p>
						<p><ul>$group_list</ul></p>
					</td>
				</tr>\n";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Delete User Group(s)'>";
		}
		$group_id = '';

		if ((get_request_var_post('drp_action') == '3') && (sizeof($group_array))) { /* enable */
			print "
				<tr>
					<td class='textArea'>
						<p>When you click \"Continue\" the selected User Group(s) will be enabled.</p>
						<p><ul>$group_list</ul></p>
					</td>
				</tr>\n";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Enable User Group(s)'>";
		}

		if ((get_request_var_post('drp_action') == '4') && (sizeof($group_array))) { /* disable */
			print "
				<tr>
					<td class='textArea'>
						<p>When you click \"Continue\" the selected User Group(s) will be disabled.</p>
						<p><ul>$group_list</ul></p>
					</td>
				</tr>\n";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Disable User Group(s)'>";
		}
	}else{
		print "<tr><td class='even'><span class='textError'>You must select at least one Group.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
	}

	print " <tr>
			<td align='right' class='saveRow'>
				<input type='hidden' name='action' value='actions'>";
	if (get_request_var_post('drp_action') == '2') { /* copy */
		print "				<input type='hidden' name='selected_items' value='" . $group_id . "'>\n";
	}else{
		print "				<input type='hidden' name='selected_items' value='" . (isset($group_array) ? serialize($group_array) : '') . "'>\n";
	}
	print "				<input type='hidden' name='drp_action' value='" . get_request_var_post('drp_action') . "'>
				$save_html
			</td>
		</tr>
		";

	html_end_box();

	bottom_footer();
}

/* --------------------------
    Save Function
   -------------------------- */

function form_save() {
	global $settings_graphs;

	if (isset($_POST['save_component_group'])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('id'));
		input_validate_input_number(get_request_var_post('realm'));
		/* ==================================================== */

		/* check duplicate group */
		if (sizeof(db_fetch_row_prepared('SELECT * FROM user_auth_group WHERE name = ? AND id != ?', array(get_request_var_post('name'), get_request_var_post('id'))))) {
			raise_message(12);
		}

		$save['id'] = get_request_var_post('id');
		$save['name'] = form_input_validate(get_request_var_post('name'), 'name', "^[A-Za-z0-9\._\\\@\ -]+$", false, 3);
		$save['description'] = form_input_validate(get_request_var_post('description'), 'description', '', true, 3);
		$save['show_tree'] = form_input_validate(get_request_var_post('show_tree', ''), 'show_tree', '', true, 3);
		$save['show_list'] = form_input_validate(get_request_var_post('show_list', ''), 'show_list', '', true, 3);
		$save['show_preview'] = form_input_validate(get_request_var_post('show_preview', ''), 'show_preview', '', true, 3);
		$save['graph_settings'] = form_input_validate(get_request_var_post('graph_settings', ''), 'graph_settings', '', true, 3);
		$save['login_opts'] = form_input_validate(get_request_var_post('login_opts'), 'login_opts', '', true, 3);
		$save['enabled'] = form_input_validate(get_request_var_post('enabled', ''), 'enabled', '', true, 3);
		$save = api_plugin_hook_function('user_group_admin_setup_sql_save', $save);

		if (!is_error_message()) {
			$group_id = sql_save($save, 'user_auth_group');

			if ($group_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}

		}

		header('Location: user_group_admin.php?action=edit&tab=general&id=' . (isset($group_id) && $group_id > 0 ? $group_id : get_request_var_post('id')));
		exit;
	}elseif (isset($_POST['save_component_realm_perms'])) {
		db_execute_prepared('DELETE FROM user_auth_group_realm WHERE group_id = ?', array(get_request_var_post('id')));

		while (list($var, $val) = each($_POST)) {
			if (eregi('^[section]', $var)) {
				if (substr($var, 0, 7) == 'section') {
				    db_execute_prepared('REPLACE INTO user_auth_group_realm (group_id, realm_id) VALUES (?, ?)', array(get_request_var_post('id'), substr($var, 7)));
				}
			}
		}

		raise_message(1);

		header('Location: user_group_admin.php?action=edit&tab=realms&id=' . get_request_var_post('id'));
		exit;
	}elseif (isset($_POST['save_component_graph_settings'])) {
		while (list($tab_short_name, $tab_fields) = each($settings_graphs)) {
			while (list($field_name, $field_array) = each($tab_fields)) {
				if ((isset($field_array['items'])) && (is_array($field_array['items']))) {
					while (list($sub_field_name, $sub_field_array) = each($field_array['items'])) {
						db_execute_prepared('REPLACE INTO settings_graphs_group (group_id, name, value) VALUES (?, ?, ?)', array(get_request_var_post('id'), $sub_field_name, get_request_var_post($sub_field_name, '')));
					}
				}else{
					db_execute_prepared('REPLACE INTO settings_graphs_group (group_id, name, value) VALUES (?, ?, ?)', array(get_request_var_post('id'), $field_name, get_request_var_post($field_name)));
				}
			}
		}

		/* reset local settings cache so the user sees the new settings */
		kill_session_var('sess_graph_config_array');

		raise_message(1);

		header('Location: user_group_admin.php?action=edit&tab=settings&id=' . get_request_var_post('id'));
		exit;
	} else {
		api_plugin_hook('user_group_admin_save');
	}

	/* redirect to the appropriate page */
	header('Location: user_group_admin.php?action=edit&tab=general&id=' .  get_request_var_post('id'));
}

/* --------------------------
    Graph Permissions
   -------------------------- */

function perm_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	input_validate_input_number(get_request_var_request('group_id'));
	/* ==================================================== */

	if (get_request_var_request('type') == 'graph') {
		db_execute_prepared('DELETE FROM user_auth_group_perms WHERE type=1 AND group_id = ? AND item_id = ?', array(get_request_var_request('group_id'), get_request_var_request('id')));
	}elseif (get_request_var_request('type') == 'tree') {
		db_execute_prepared('DELETE FROM user_auth_group_perms WHERE type=2 AND group_id = ? AND item_id = ?', array(get_request_var_request('group_id'), get_request_var_request('id')));
	}elseif (get_request_var_request('type') == 'host') {
		db_execute_prepared('DELETE FROM user_auth_group_perms WHERE type=3 AND group_id = ? AND item_id = ?', array(get_request_var_request('group_id'), get_request_var_request('id')));
	}elseif (get_request_var_request('type') == 'graph_template') {
		db_execute_prepared('DELETE FROM user_auth_group_perms WHERE type=4 AND group_id = ? AND item_id = ?', array(get_request_var_request('group_id'), get_request_var_request('id')));
	}

	header('Location: user_group_admin.php?action=edit&tab=gperms&id=' . get_request_var_request('group_id'));
}

function user_group_members_edit($header_label) {
	global $config, $auth_realms;

	process_member_request_vars();

	member_filter($header_label);

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST['rows'] == -1) {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = $_REQUEST['rows'];
	}

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var_request('filter'))) {
		$sql_where = "WHERE (username LIKE '%%" . get_request_var_request('filter') . "%%' OR full_name LIKE '%%" . get_request_var_request('filter') . "%%')";
	} else {
		$sql_where = '';
	}

	if (get_request_var_request('associated') == 'false') {
		/* Show all items */
	} else {
		$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' (user_auth_group_members.group_id=' . get_request_var_request('id', 0) . ')';
	}

	$total_rows = db_fetch_cell("SELECT
		COUNT(ua.id)
		FROM user_auth AS ua
		LEFT JOIN user_auth_group_members 
		ON (ua.id = user_auth_group_members.user_id)
		$sql_where");

	$sql_query = "SELECT DISTINCT ua.id, ua.username, ua.full_name, ua.enabled, ua.realm
		FROM user_auth AS ua
		LEFT JOIN user_auth_group_members 
		ON (ua.id = user_auth_group_members.user_id)
		$sql_where 
		ORDER BY username, full_name
		LIMIT " . ($rows*(get_request_var_request('page')-1)) . ',' . $rows;

	$members = db_fetch_assoc($sql_query);

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='" . htmlspecialchars('user_group_admin.php?action=edit&tab=members&id=' . get_request_var_request('id')) . "'>\n";

	html_start_box('', '100%', '', '3', 'center', '');

	$nav = html_nav_bar('user_group_admin.php?action=edit&tab=members&id=' . get_request_var_request('id'), MAX_DISPLAY_PAGES, get_request_var_request('page'), $rows, $total_rows, 7, 'Users', 'page', 'main');

	print $nav;

	$display_text = array('Login Name', 'Full Name', 'ID', 'Membership', 'Enabled', 'Realm');

	html_header_checkbox($display_text, false);

	if (sizeof($members)) {
		foreach ($members as $g) {
			form_alternate_row('line' . $g['id'], true);
			form_selectable_cell('<a href="' . htmlspecialchars('user_admin.php?action=user_edit&id=' . $g['id']) . '"><strong>' . (strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($g['username'])) : htmlspecialchars($g['username'])) . '</strong></a>', $g['id']);
			form_selectable_cell((strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($g['full_name'])) : htmlspecialchars($g['full_name'])), $g['id']);
			form_selectable_cell($g['id'], $g['id']);
			if (user_group_is_member($g['id'], get_request_var_request('id'))) {
				form_selectable_cell('<span style="color:green;font-weight:bold;">Group Member</span>', $g['id']);
			}else{
				form_selectable_cell('<span style="color:red;font-weight:bold;">Non Member</span>', $g['id']);
			}
			form_selectable_cell(($g['enabled'] == 'on' ? 'Enabled':'Disabled'), $g['id']);
			form_selectable_cell((isset($auth_realms[$g['realm']]) ? $auth_realms[$g['realm']]:'Unknown'), $g['id']);
			form_checkbox_cell($g['full_name'], $g['id']);
			form_end_row();
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	} else {
		print '<tr><td><em>No Matching Group Members Found</em></td></tr>';
	}
	html_end_box(false);

	form_hidden_box('action', 'edit', '');
	form_hidden_box('tab', 'members', '');
	form_hidden_box('id', get_request_var_request('id'), '');
	form_hidden_box('associate_member', '1', '');

	$assoc_actions = array(
		1 => 'Add to Group',
		2 => 'Remove from Group'
	);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($assoc_actions);

	print '</form>';
}

function user_group_graph_perms_edit($tab, $header_label) {
	global $config, $assoc_actions;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	/* ==================================================== */

	$policy_array = array(
		1 => 'Allow',
		2 => 'Deny');

	if (!empty($_REQUEST['id'])) {
		$policy = db_fetch_row_prepared('SELECT policy_graphs, policy_trees, policy_hosts, policy_graph_templates FROM user_auth_group WHERE id = ?', array(get_request_var_request('id')));
	}

	switch($tab) {
	case 'permsg':
		process_graph_request_vars();

		graph_filter($header_label);

		/* print checkbox form for validation */
		print "<form name='policy' method='post' action='user_group_admin.php'>\n";

		/* box: device permissions */
		html_start_box('<strong>Default Graph Policy</strong>', '100%', '', '3', 'center', '');

		?>
		<tr class='odd'>
			<td><table cellpadding="2" cellspacing="0"><tr>
			<td style="white-space:nowrap;" width="120">Default Graph policy for this User Group</td>
			<td width="10"> 
				<?php form_dropdown('policy_graphs',$policy_array,'','',$policy['policy_graphs'],'',''); ?>
			</td>
			<td>
				<input type="submit" name="update_policy" value="Update">
				<input type="hidden" name="tab" value="<?php print $tab;?>">
				<input type="hidden" name="id" value="<?php print get_request_var_request('id');?>">
			</td>
			</tr></table></td>
		</tr>
		<?php

		html_end_box();

		print "</form>\n";

		/* if the number of rows is -1, set it to the default */
		if ($_REQUEST['rows'] == -1) {
			$rows = read_config_option('num_rows_table');
		}else{
			$rows = $_REQUEST['rows'];
		}

		/* form the 'where' clause for our main sql query */
		if (strlen(get_request_var_request('filter'))) {
			$sql_where = "WHERE (gtg.title_cache LIKE '%%" . get_request_var_request('filter') . "%%' AND gtg.local_graph_id>0)";
		} else {
			$sql_where = 'WHERE (gtg.local_graph_id>0)';
		}

		if (get_request_var_request('graph_template_id') == '-1') {
			/* Show all items */
		}elseif (get_request_var_request('graph_template_id') == '0') {
			$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' gtg.graph_template_id=0';
		}elseif (!empty($_REQUEST['graph_template_id'])) {
			$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' gtg.graph_template_id=' . get_request_var_request('graph_template_id');
		}

		if (get_request_var_request('associated') == 'false') {
			/* Show all items */
		} else {
			$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' (user_auth_group_perms.type=1 AND user_auth_group_perms.group_id=' . get_request_var_request('id', 0) . ')';
		}

		/* print checkbox form for validation */
		print "<form name='chk' method='post' action='" . htmlspecialchars('user_group_admin.php?action=edit&tab=permsg&id=' . get_request_var_request('id')) . "'>\n";

		html_start_box('', '100%', '', '3', 'center', '');

		$total_rows = db_fetch_cell("select
			COUNT(gtg.id)
			FROM graph_templates_graph AS gtg
			LEFT JOIN user_auth_group_perms 
			ON (gtg.local_graph_id = user_auth_group_perms.item_id AND user_auth_group_perms.type = 1)
			$sql_where");

		$sql_query = "SELECT gtg.local_graph_id, gtg.title_cache, user_auth_group_perms.group_id
			FROM graph_templates_graph AS gtg
			LEFT JOIN user_auth_group_perms 
			ON (gtg.local_graph_id=user_auth_group_perms.item_id AND user_auth_group_perms.type=1)
			$sql_where 
			ORDER BY title_cache
			LIMIT " . ($rows*(get_request_var_request('page')-1)) . ',' . $rows;

		$graphs = db_fetch_assoc($sql_query);

		$nav = html_nav_bar('user_group_admin.php?action=edit&tab=permsg&id=' . get_request_var_request('id'), MAX_DISPLAY_PAGES, get_request_var_request('page'), $rows, $total_rows, 7, 'Graphs', 'page', 'main');
	
		print $nav;

		$display_text = array('Graph Title', 'ID', 'Effective Policy');

		html_header_checkbox($display_text, false);

		if (sizeof($graphs)) {
			foreach ($graphs as $g) {
				form_alternate_row('line' . $g['local_graph_id'], true);
				form_selectable_cell((strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($g['title_cache'])) : htmlspecialchars($g['title_cache'])), $g['local_graph_id'], 250);
				form_selectable_cell($g['local_graph_id'], $g['local_graph_id']);
				if (empty($g['group_id']) || $g['group_id'] == NULL) {
					if ($policy['policy_graphs'] == 1) {
						form_selectable_cell('<span style="color:green;font-weight:bold;">Access Granted</span>', $g['local_graph_id']);
					}else{
						form_selectable_cell('<span style="color:red;font-weight:bold;">Access Restricted</span>', $g['local_graph_id']);
					}
				} else {
					if ($policy['policy_graphs'] == 1) {
						form_selectable_cell('<span style="color:red;font-weight:bold;">Access Restricted</span>', $g['local_graph_id']);
					}else{
						form_selectable_cell('<span style="color:green;font-weight:bold;">Access Granted</span>', $g['local_graph_id']);
					}
				}
				form_checkbox_cell($g['title_cache'], $g['local_graph_id']);
				form_end_row();
			}
	
			/* put the nav bar on the bottom as well */
			print $nav;
		} else {
			print '<tr><td><em>No Matching Graphs Found</em></td></tr>';
		}
		html_end_box(false);

		form_hidden_box('action', 'edit', '');
		form_hidden_box('tab',$tab,'');
		form_hidden_box('id', get_request_var_request('id'), '');
		form_hidden_box('associate_graph', '1', '');

		if ($policy['policy_graphs'] == 1) {
			$assoc_actions = array(
				1 => 'Revoke Access',
				2 => 'Grant Access'
			);
		}else{
			$assoc_actions = array(
				1 => 'Grant Access',
				2 => 'Revoke Access'
			);
		}

		/* draw the dropdown containing a list of available actions for this form */
		draw_actions_dropdown($assoc_actions);

		print '</form>';

		break;
	case 'permsd':
		process_device_request_vars();

		device_filter($header_label);

		/* print checkbox form for validation */
		print "<form name='policy' method='post' action='user_group_admin.php'>\n";

		/* box: device permissions */
		html_start_box('<strong>Default Device Policy</strong>', '100%', '', '3', 'center', '');

		?>
		<tr class='even'>
			<td><table cellspacing="0" cellpadding="2"><tr>
			<td style="white-space:nowrap;" width="120">Default Graph policy for this User Group</td>
			<td width="10"> 
				<?php form_dropdown('policy_hosts',$policy_array,'','',$policy['policy_hosts'],'',''); ?>
			</td>
			<td>
				<input type="submit" name="update_policy" value="Update">
				<input type="hidden" name="tab" value="<?php print $tab;?>">
				<input type="hidden" name="id" value="<?php print get_request_var_request('id');?>">
			</td>
			</tr></table></td>
		</tr>
		<?php

		html_end_box();

		print "</form>\n";

		/* if the number of rows is -1, set it to the default */
		if ($_REQUEST['rows'] == -1) {
			$rows = read_config_option('num_rows_table');
		}else{
			$rows = $_REQUEST['rows'];
		}

		/* form the 'where' clause for our main sql query */
		/* form the 'where' clause for our main sql query */
		if (strlen(get_request_var_request('filter'))) {
			$sql_where = "WHERE (host.hostname LIKE '%%" . get_request_var_request('filter') . "%%' OR host.description LIKE '%%" . get_request_var_request('filter') . "%%')";
		} else {
			$sql_where = '';
		}

		if (get_request_var_request('host_template_id') == '-1') {
			/* Show all items */
		}elseif (get_request_var_request('host_template_id') == '0') {
			$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' host.host_template_id=0';
		}elseif (!empty($_REQUEST['host_template_id'])) {
			$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' host.host_template_id=' . get_request_var_request('host_template_id');
		}

		if (get_request_var_request('associated') == 'false') {
			/* Show all items */
		} else {
			$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' user_auth_group_perms.group_id=' . get_request_var_request('id', 0);
		}

		/* print checkbox form for validation */
		print "<form name='chk' method='post' action='" . htmlspecialchars('user_group_admin.php?action=edit&tab=permsd&id=' . get_request_var_request('id')) . "'>\n";

		html_start_box('', '100%', '', '3', 'center', '');

		$total_rows = db_fetch_cell("SELECT
			COUNT(host.id)
			FROM host
			LEFT JOIN user_auth_group_perms 
			ON (host.id = user_auth_group_perms.item_id AND user_auth_group_perms.type = 3)
			$sql_where");

		$host_graphs       = array_rekey(db_fetch_assoc('SELECT host_id, count(*) AS graphs FROM graph_local GROUP BY host_id'), 'host_id', 'graphs');
		$host_data_sources = array_rekey(db_fetch_assoc('SELECT host_id, count(*) AS data_sources FROM data_local GROUP BY host_id'), 'host_id', 'data_sources');

		$sql_query = "SELECT host.*, user_auth_group_perms.group_id
			FROM host 
			LEFT JOIN user_auth_group_perms 
			ON (host.id=user_auth_group_perms.item_id AND user_auth_group_perms.type=3)
			$sql_where 
			ORDER BY description
			LIMIT " . ($rows*(get_request_var_request('page')-1)) . ',' . $rows;

		$hosts = db_fetch_assoc($sql_query);

		$nav = html_nav_bar('user_group_admin.php?action=edit&tab=permsd&id=' . get_request_var_request('id'), MAX_DISPLAY_PAGES, get_request_var_request('page'), $rows, $total_rows, 11, 'Devices', 'page', 'main');
	
		print $nav;

		$display_text = array('Description', 'ID', 'Effective Policy', 'Graphs', 'Data Sources', 'Status', 'Hostname');

		html_header_checkbox($display_text, false);

		if (sizeof($hosts)) {
			foreach ($hosts as $host) {
				form_alternate_row('line' . $host['id'], true);
				form_selectable_cell((strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($host['description'])) : htmlspecialchars($host['description'])), $host['id'], 250);
				form_selectable_cell(round(($host['id']), 2), $host['id']);
				if (empty($host['group_id']) || $host['group_id'] == NULL) {
					if ($policy['policy_hosts'] == 1) {
						form_selectable_cell('<span style="color:green;font-weight:bold;">Access Granted</span>', $host['id']);
					}else{
						form_selectable_cell('<span style="color:red;font-weight:bold;">Access Restricted</span>', $host['id']);
					}
				} else {
					if ($policy['policy_hosts'] == 1) {
						form_selectable_cell('<span style="color:red;font-weight:bold;">Access Restricted</span>', $host['id']);
					}else{
						form_selectable_cell('<span style="color:green;font-weight:bold;">Access Granted</span>', $host['id']);
					}
				}
				form_selectable_cell((isset($host_graphs[$host['id']]) ? $host_graphs[$host['id']] : 0), $host['id']);
				form_selectable_cell((isset($host_data_sources[$host['id']]) ? $host_data_sources[$host['id']] : 0), $host['id']);
				form_selectable_cell(get_colored_device_status(($host['disabled'] == 'on' ? true : false), $host['status']), $host['id']);
				form_selectable_cell((strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($host['hostname'])) : htmlspecialchars($host['hostname'])), $host['id']);
				form_checkbox_cell($host['description'], $host['id']);
				form_end_row();
			}
	
			/* put the nav bar on the bottom as well */
			print $nav;
		} else {
			print '<tr><td><em>No Matching Devices Found</em></td></tr>';
		}
		html_end_box(false);

		form_hidden_box('action', 'edit', '');
		form_hidden_box('tab',$tab,'');
		form_hidden_box('id', get_request_var_request('id'), '');
		form_hidden_box('associate_host', '1', '');

		if ($policy['policy_hosts'] == 1) {
			$assoc_actions = array(
				1 => 'Revoke Access',
				2 => 'Grant Access'
			);
		}else{
			$assoc_actions = array(
				1 => 'Grant Access',
				2 => 'Revoke Access'
			);
		}

		/* draw the dropdown containing a list of available actions for this form */
		draw_actions_dropdown($assoc_actions);

		print "</form>\n";

		break;
	case 'permste':
		process_template_request_vars();

		template_filter($header_label);

		/* print checkbox form for validation */
		print "<form name='policy' method='post' action='user_group_admin.php'>\n";

		/* box: device permissions */
		html_start_box('<strong>Default Graph Template Policy</strong>', '100%', '', '3', 'center', '');

		?>
		<tr class='even'>
			<td><table cellspacing="0" cellpadding="2"><tr>
			<td style="white-space:nowrap;" width="120">Default Graph Template Template policy for this User Group</td>
			<td width="10"> 
				<?php form_dropdown('policy_graph_templates',$policy_array,'','',$policy['policy_graph_templates'],'',''); ?>
			</td>
			<td>
				<input type="submit" name="update_policy" value="Update">
				<input type="hidden" name="tab" value="<?php print $tab;?>">
				<input type="hidden" name="id" value="<?php print get_request_var_request('id');?>">
			</td>
			</tr></table></td>
		</tr>
		<?php

		html_end_box();

		print "</form>\n";

		/* if the number of rows is -1, set it to the default */
		if ($_REQUEST['rows'] == -1) {
			$rows = read_config_option('num_rows_table');
		}else{
			$rows = $_REQUEST['rows'];
		}

		/* form the 'where' clause for our main sql query */
		/* form the 'where' clause for our main sql query */
		if (strlen(get_request_var_request('filter'))) {
			$sql_where = "WHERE (gt.name LIKE '%%" . get_request_var_request('filter') . "%%')";
		} else {
			$sql_where = '';
		}

		if (get_request_var_request('associated') == 'false') {
			/* Show all items */
		} else {
			$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' (user_auth_group_perms.type=4 AND user_auth_group_perms.group_id=' . get_request_var_request('id', 0) . ')';
		}

		/* print checkbox form for validation */
		print "<form name='chk' method='post' action='" . htmlspecialchars('user_group_admin.php?action=edit&tab=permste&id=' . get_request_var_request('id')) . "'>\n";

		html_start_box('', '100%', '', '3', 'center', '');

		$total_rows = db_fetch_cell("SELECT
			COUNT(gt.id)
			FROM graph_templates AS gt
			INNER JOIN graph_local AS gl
			ON gt.id = gl.graph_template_id
			LEFT JOIN user_auth_group_perms 
			ON (gt.id = user_auth_group_perms.item_id AND user_auth_group_perms.type = 4)
			$sql_where
			GROUP BY gl.graph_template_id");

		$sql_query = "SELECT gt.id, gt.name, count(*) AS totals, user_auth_group_perms.group_id
			FROM graph_templates AS gt
			INNER JOIN graph_local AS gl
			ON gt.id = gl.graph_template_id
			LEFT JOIN user_auth_group_perms 
			ON (gt.id = user_auth_group_perms.item_id AND user_auth_group_perms.type = 4)
			$sql_where 
			GROUP BY gl.graph_template_id
			ORDER BY name
			LIMIT " . ($rows*(get_request_var_request('page')-1)) . ',' . $rows;

		$graphs = db_fetch_assoc($sql_query);

		$nav = html_nav_bar('user_group_admin.php?action=edit&tab=permste&id=' . get_request_var_request('id'), MAX_DISPLAY_PAGES, get_request_var_request('page'), $rows, $total_rows, 11, 'Graph Templates', 'page', 'main');

		print $nav;

		$display_text = array('Template Name', 'ID', 'Effective Policy', 'Total Graphs');

		html_header_checkbox($display_text, false);

		if (sizeof($graphs)) {
			foreach ($graphs as $g) {
				form_alternate_row('line' . $g['id'], true);
				form_selectable_cell((strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($g['name'])) : htmlspecialchars($g['name'])), $g['id'], 250);
				form_selectable_cell($g['id'], $g['id']);
				if (empty($g['group_id']) || $g['group_id'] == NULL) {
					if ($policy['policy_graph_templates'] == 1) {
						form_selectable_cell('<span style="color:green;font-weight:bold;">Access Granted</span>', $g['id']);
					}else{
						form_selectable_cell('<span style="color:red;font-weight:bold;">Access Restricted</span>', $g['id']);
					}
				} else {
					if ($policy['policy_graph_templates'] == 1) {
						form_selectable_cell('<span style="color:red;font-weight:bold;">Access Restricted</span>', $g['id']);
					}else{
						form_selectable_cell('<span style="color:green;font-weight:bold;">Access Granted</span>', $g['id']);
					}
				}
				form_selectable_cell($g['totals'], $g['id']);
				form_checkbox_cell($g['name'], $g['id']);
				form_end_row();
			}
	
			/* put the nav bar on the bottom as well */
			print $nav;
		} else {
			print '<tr><td><em>No Matching Graph Templates Found</em></td></tr>';
		}
		html_end_box(false);

		form_hidden_box('action', 'edit', '');
		form_hidden_box('tab',$tab,'');
		form_hidden_box('id', get_request_var_request('id'), '');
		form_hidden_box('associate_template', '1', '');

		if ($policy['policy_graph_templates'] == 1) {
			$assoc_actions = array(
				1 => 'Revoke Access',
				2 => 'Grant Access'
			);
		}else{
			$assoc_actions = array(
				1 => 'Grant Access',
				2 => 'Revoke Access'
			);
		}

		/* draw the dropdown containing a list of available actions for this form */
		draw_actions_dropdown($assoc_actions);

		print '</form>';

		break;
	case 'permstr':
		process_tree_request_vars();

		tree_filter($header_label);

		/* print checkbox form for validation */
		print "<form name='policy' method='post' action='user_group_admin.php'>\n";

		/* box: device permissions */
		html_start_box('<strong>Default Tree Policy</strong>', '100%', '', '3', 'center', '');

		?>
		<tr class='even'>
			<td><table cellspacing="0" cellpadding="2"><tr>
			<td style="white-space:nowrap;" width="120">Default Tree policy for this User Group</td>
			<td width="10"> 
				<?php form_dropdown('policy_trees',$policy_array,'','',$policy['policy_trees'],'',''); ?>
			</td>
			<td>
				<input type="submit" name="update_policy" value="Update">
				<input type="hidden" name="tab" value="<?php print $tab;?>">
				<input type="hidden" name="id" value="<?php print get_request_var_request('id');?>">
			</td>
			</tr></table></td>
		</tr>
		<?php

		html_end_box();

		print "</form>\n";

		/* if the number of rows is -1, set it to the default */
		if ($_REQUEST['rows'] == -1) {
			$rows = read_config_option('num_rows_table');
		}else{
			$rows = $_REQUEST['rows'];
		}

		/* form the 'where' clause for our main sql query */
		if (strlen(get_request_var_request('filter'))) {
			$sql_where = "WHERE (gt.name LIKE '%%" . get_request_var_request('filter') . "%%')";
		} else {
			$sql_where = '';
		}

		if (get_request_var_request('associated') == 'false') {
			/* Show all items */
		} else {
			$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' (user_auth_group_perms.type=2 AND user_auth_group_perms.group_id=' . get_request_var_request('id', 0) . ')';
		}

		/* print checkbox form for validation */
		print "<form name='chk' method='post' action='" . htmlspecialchars('user_group_admin.php?action=edit&tab=permstr&id=' . get_request_var_request('id')) . "'>\n";

		html_start_box('', '100%', '', '3', 'center', '');

		$total_rows = db_fetch_cell("SELECT
			COUNT(gt.id)
			FROM graph_tree AS gt
			LEFT JOIN user_auth_group_perms 
			ON (gt.id = user_auth_group_perms.item_id AND user_auth_group_perms.type = 2)
			$sql_where");

		$sql_query = "SELECT gt.id, gt.name, user_auth_group_perms.group_id
			FROM graph_tree AS gt
			LEFT JOIN user_auth_group_perms 
			ON (gt.id = user_auth_group_perms.item_id AND user_auth_group_perms.type = 2)
			$sql_where 
			ORDER BY name
			LIMIT " . ($rows*(get_request_var_request('page')-1)) . ',' . $rows;

		$trees = db_fetch_assoc($sql_query);

		$nav = html_nav_bar('user_group_admin.php?action=edit&tab=permstr&id=' . get_request_var_request('id'), MAX_DISPLAY_PAGES, get_request_var_request('page'), $rows, $total_rows, 11, 'Trees', 'page', 'main');

		print $nav;

		$display_text = array('Tree Name', 'ID', 'Effective Policy');

		html_header_checkbox($display_text, false);

		if (sizeof($trees)) {
			foreach ($trees as $t) {
				form_alternate_row('line' . $t['id'], true);
				form_selectable_cell((strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($t['name'])) : htmlspecialchars($t['name'])), $t['id'], 250);
				form_selectable_cell($t['id'], $t['id']);
				if (empty($t['group_id']) || $t['group_id'] == NULL) {
					if ($policy['policy_trees'] == 1) {
						form_selectable_cell('<span style="color:green;font-weight:bold;">Access Granted</span>', $t['id']);
					}else{
						form_selectable_cell('<span style="color:red;font-weight:bold;">Access Restricted</span>', $t['id']);
					}
				} else {
					if ($policy['policy_trees'] == 1) {
						form_selectable_cell('<span style="color:red;font-weight:bold;">Access Restricted</span>', $t['id']);
					}else{
						form_selectable_cell('<span style="color:green;font-weight:bold;">Access Granted</span>', $t['id']);
					}
				}
				form_checkbox_cell($t['name'], $t['id']);
				form_end_row();
			}
	
			/* put the nav bar on the bottom as well */
			print $nav;
		} else {
			print '<tr><td><em>No Matching Trees Found</em></td></tr>';
		}
		html_end_box(false);

		form_hidden_box('action', 'edit', '');
		form_hidden_box('tab',$tab,'');
		form_hidden_box('id', get_request_var_request('id'), '');
		form_hidden_box('associate_tree', '1', '');

		if ($policy['policy_graph_templates'] == 1) {
			$assoc_actions = array(
				1 => 'Revoke Access',
				2 => 'Grant Access'
			);
		}else{
			$assoc_actions = array(
				1 => 'Grant Access',
				2 => 'Revoke Access'
			);
		}

		/* draw the dropdown containing a list of available actions for this form */
		draw_actions_dropdown($assoc_actions);

		print '</form>';

		break;
	}
}

function user_group_is_member($user_id, $group_id) {
	return db_fetch_cell_prepared('SELECT COUNT(*) FROM user_auth_group_members WHERE user_id = ? AND group_id = ?', array($user_id, $group_id));
}

function user_group_realms_edit($header_label) {
	global $user_auth_realms;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	/* ==================================================== */

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='user_group_admin.php'>\n";

	$all_realms = $user_auth_realms;

	html_start_box('', '100%', '', '3', 'center', '');

	print "	<tr class='cactiTableTitle'>
			<td class='textHeaderDark'><strong>Realm Permissions</strong> $header_label</td>
			<td class='tableHader' width='1%' align='center'><input type='checkbox' style='margin: 0px;' name='all' title='Select All' onClick='selectAllRealms(this.checked)'></td>\n
		</tr>\n";

	/* do cacti realms first */
	print "<tr class='tableHeader'><th colspan='2'>Base Permissions</th></tr>\n";
	print "<tr class='odd'><td><table width='100%'><tr><td valign='top' style='white-space:nowrap;' width='20%'>\n";
	$i = 1;
	$j = 1;
	$base = array(7,8,15,1,2,3,4,5,6,9,10,11,12,13,14,16,17,18,19,101);
	foreach($base as $realm) {
		if (isset($user_auth_realms[$realm])) {
			if (sizeof(db_fetch_assoc_prepared('SELECT realm_id FROM user_auth_group_realm WHERE group_id = ? AND realm_id = ?', array(get_request_var_request('id', 0), $realm))) > 0) {
				$old_value = 'on';
			}else{
				$old_value = '';
			}

			unset($all_realms[$realm]);

			if ($j == 5) {
				print "</td><td valign='top' width='20%' style='white-space:nowrap;'>\n";
				$j = 1;
			}

			form_checkbox('section' . $realm, $old_value, $user_auth_realms[$realm], '', '', '', (!empty($_REQUEST['id']) ? 1 : 0)); print '<br>';

			$j++;
		}
	}
	print "</td></tr></table></td></tr>\n";
	print "<script type='text/javascript'>function selectAllRealms(checked) { if (checked) { $('input[id^=\"section\"]').prop('checked', true); } else { $('input[id^=\"section\"]').prop('checked', false); } }</script>\n";

	/* do plugin realms */
	$realms = db_fetch_assoc('SELECT pc.name, pr.id AS realm_id, pr.display
		FROM plugin_config AS pc
		INNER JOIN plugin_realms AS pr
		ON pc.directory = pr.plugin
		ORDER BY pc.name, pr.display');

	print "<tr class='tableHeader'><th colspan='2'>Plugin Permissions</th></tr>\n";
	print "<tr class='odd'><td colspan='4'><table width='100%'><tr><td valign='top' width='20%' style='white-space:nowrap;'>\n";
	if (sizeof($realms)) {
		$last_plugin = 'none';
		$i = 1;
		$j = 1;
		$level = floor(sizeof($all_realms) / 4); 
		$break = false;

		foreach($realms as $r) {
			if ($last_plugin != $r['name']) {
				if ($break) {
					print "</td><td valign='top' width='20%' style='white-space:nowrap;'>\n";
					$break = false;
					$j = 1;
				}
				print '<strong>' . $r['name'] . "</strong><br>\n";
				$last_plugin = $r['name'];
			}elseif ($break) {
				print "</td><td valign='top' width='20%' style='white-space:nowrap;'>\n";
				$break = false;
				$j = 1;
				print '<strong>' . $r['name'] . " (cont)</strong><br>\n";
			}

			if ($j == 6) {
				$break = true;;
			}

			$realm = $r['realm_id'] + 100;

			if (sizeof(db_fetch_assoc_prepared('SELECT realm_id FROM user_auth_group_realm WHERE group_id = ? AND realm_id = ?', array(get_request_var_request('id', 0), $realm))) > 0) {
				$old_value = 'on';
			}else{
				$old_value = '';
			}

			unset($all_realms[$realm]);

			$pos = (strpos($user_auth_realms[$realm], '->') !== false ? strpos($user_auth_realms[$realm], '->')+2:0);

			form_checkbox('section' . $realm, $old_value, substr($user_auth_realms[$realm], $pos), '', '', '', (!empty($_REQUEST['id']) ? 1 : 0)); print '<br>';

			$j++;
		}
	}

	/* get the old PIA 1.x realms */
	if (sizeof($all_realms)) {
		if ($break) {
			print "</td><td valign='top' width='20%' style='white-space:nowrap;'>\n";
		}
		print "<strong>Legacy 1.x Plugins</strong><br>\n";
		foreach($all_realms as $realm => $name) {
			if (sizeof(db_fetch_assoc_prepared('SELECT realm_id FROM user_auth_group_realm WHERE group_id = ? AND realm_id = ?', array(get_request_var_request('id', 0), $realm))) > 0) {
				$old_value = 'on';
			}else{
				$old_value = '';
			}

			$pos = (strpos($user_auth_realms[$realm], '->') !== false ? strpos($user_auth_realms[$realm], '->')+2:0);

			form_checkbox('section' . $realm, $old_value, substr($user_auth_realms[$realm], $pos), '', '', '', (!empty($_REQUEST['id']) ? 1 : 0)); print '<br>';


		}
	}

	print "</td></tr></table></td></tr>\n";

	html_end_box();

	form_hidden_box('save_component_realm_perms','1','');
	form_hidden_box('tab','realms','');
	form_hidden_box('id',get_request_var_request('id'),'');
	form_save_button('user_group_admin.php', 'return');
}

function user_group_graph_settings_edit($header_label) {
	global $settings_graphs, $tabs_graphs, $graph_views;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	/* ==================================================== */

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='user_group_admin.php'>\n";

	html_start_box("<strong>Graph Settings</strong> $header_label", '100%', '', '3', 'center', '');

	while (list($tab_short_name, $tab_fields) = each($settings_graphs)) {
		?>
		<tr id='row_<?php print $tab_short_name;?>' class='tableHeader'>
			<th colspan='2' style='padding: 3px;'>
				<?php print $tabs_graphs[$tab_short_name];?>
			</th>
		</tr>
		<?php

		$form_array = array();

		while (list($field_name, $field_array) = each($tab_fields)) {
			$form_array += array($field_name => $tab_fields[$field_name]);

			if ((isset($field_array['items'])) && (is_array($field_array['items']))) {
				while (list($sub_field_name, $sub_field_array) = each($field_array['items'])) {
					if (graph_config_value_exists($sub_field_name, $_REQUEST['id'])) {
						$form_array[$field_name]['items'][$sub_field_name]['form_id'] = 1;
					}

					$form_array[$field_name]['items'][$sub_field_name]['value'] =  db_fetch_cell_prepared('SELECT value FROM settings_graphs_group WHERE name = ? AND group_id = ?', array($sub_field_name, get_request_var_request('id')));
				}
			}else{
				if (graph_config_value_exists($field_name, $_REQUEST['id'])) {
					$form_array[$field_name]['form_id'] = 1;
				}

				$form_array[$field_name]['value'] = db_fetch_cell_prepared('SELECT value FROM settings_graphs_group WHERE name = ? AND group_id = ?', array($field_name, $_REQUEST['id']));
			}
		}

		draw_edit_form(
			array(
				'config' => array(
					'no_form_tag' => true
					),
				'fields' => $form_array
				)
			);
	}

	html_end_box();

	form_hidden_box('save_component_graph_settings','1','');
	form_hidden_box('tab','settings','');
	form_hidden_box('id',get_request_var_request('id'),'');
	form_save_button('user_group_admin.php', 'return');

	?>
	<script type="text/javascript">
	<!--
	var themeFonts=<?php print read_config_option('font_method');?>;

	function graphSettings() {
		if (themeFonts == 1) {
				$('#row_fonts').hide();
				$('#row_custom_fonts').hide();
				$('#row_title_size').hide();
				$('#row_title_font').hide();
				$('#row_legend_size').hide();
				$('#row_legend_font').hide();
				$('#row_axis_size').hide();
				$('#row_axis_font').hide();
				$('#row_unit_size').hide();
				$('#row_unit_font').hide();
		}else{
			var custom_fonts = $('#custom_fonts').is(':checked');

			switch(custom_fonts) {
			case true:
				$('#row_fonts').show();
				$('#row_title_size').show();
				$('#row_title_font').show();
				$('#row_legend_size').show();
				$('#row_legend_font').show();
				$('#row_axis_size').show();
				$('#row_axis_font').show();
				$('#row_unit_size').show();
				$('#row_unit_font').show();
				break;
			case false:
				$('#row_fonts').show();
				$('#row_title_size').hide();
				$('#row_title_font').hide();
				$('#row_legend_size').hide();
				$('#row_legend_font').hide();
				$('#row_axis_size').hide();
				$('#row_axis_font').hide();
				$('#row_unit_size').hide();
				$('#row_unit_font').hide();
				break;
			}
		}
	}

	$(function() {
		graphSettings();
	});

	-->
	</script>
	<?php
}

/* --------------------------
    User Administration
   -------------------------- */

function group_edit() {
	global $config, $fields_user_group_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	/* ==================================================== */

	/* present a tabbed interface */
	$tabs = array(
		'general' => 'General',
		'members' => 'Members',
		'realms' => 'Realm Perms',
		'permsg' => 'Graph Perms',
		'permsd' => 'Device Perms',
		'permste' => 'Template Perms',
		'permstr' => 'Tree Perms',
		'settings' => 'Graph Settings'
	);

	$tabs = api_plugin_hook_function('ugroup_tabs', $tabs);
	if (!empty($_REQUEST['id'])) {
		$group = db_fetch_row_prepared('SELECT * FROM user_auth_group WHERE id = ?', array(get_request_var_request('id')));
		$header_label = '[edit: ' . $group['name'] . ']';
	}else{
		$header_label = '[new]';
	}

    /* set the default tab */
    load_current_session_value('tab', 'sess_ugroup_tab', 'general');
    $current_tab = $_REQUEST['tab'];

	if (sizeof($tabs) && isset($_REQUEST['id'])) {
		/* draw the tabs */
		print "<div class='tabs'><nav><ul>\n";

		foreach (array_keys($tabs) as $tab_short_name) {
			print "<li class='subTab'><a " . (($tab_short_name == $current_tab) ? "class='selected'" : '') .
				" href='" . htmlspecialchars($config['url_path'] .
				'user_group_admin.php?action=edit&id=' . get_request_var_request('id') .
				'&tab=' . $tab_short_name) .
				"'>$tabs[$tab_short_name]</a></li>\n";
		}

		print "</ul></nav></div>\n";

		if (read_config_option('legacy_menu_nav') != 'on') { ?>
		<script type='text/javascript'>

		$('.subTab').find('a').click(function(event) {
			event.preventDefault();
			href = $(this).attr('href');
			href = href+ (href.indexOf('?') > 0 ? '&':'?') + 'header=false';
			$.get(href, function(data) {
				$('#main').html(data);
				applySkin();
			});
		});
		</script>
		<?php }
	}

	switch($_REQUEST['tab']) {
	case 'general':
		api_plugin_hook_function('user_group_admin_edit', (isset($user) ? get_request_var_request('id') : 0));

		html_start_box("<strong>User Group Management</strong> $header_label", '100%', '', '3', 'center', '');

		draw_edit_form(array(
			'config' => array('form_name' => 'chk'),
			'fields' => inject_form_variables($fields_user_group_edit, (isset($group) ? $group : array()))
		));

		html_end_box();

		form_save_button('user_group_admin.php', 'return');

		break;
	case 'settings':
		user_group_graph_settings_edit($header_label);

		break;
	case 'realms':
		user_group_realms_edit($header_label);

		break;
	case 'permsg':
	case 'permsd':
	case 'permste':
	case 'permstr':
		user_group_graph_perms_edit($_REQUEST['tab'], $header_label);

		break;
	case 'members':
		user_group_members_edit($header_label);

		break;
	}
}

function user_group() {
	global $group_actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('page'));
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

	/* clean up sort_direction string */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var_request('sort_direction'));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear_x'])) {
		kill_session_var('sess_user_group_admin_page');
		kill_session_var('sess_user_group_admin_rows');
		kill_session_var('sess_user_group_admin_filter');
		kill_session_var('sess_user_group_admin_sort_column');
		kill_session_var('sess_user_group_admin_sort_direction');

		unset($_REQUEST['page']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
	}else{
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_user_group_admin_page', '1');
	load_current_session_value('rows', 'sess_user_group_admin_rows', '-1');
	load_current_session_value('filter', 'sess_user_group_admin_filter', '');
	load_current_session_value('sort_column', 'sess_user_group_admin_sort_column', 'name');
	load_current_session_value('sort_direction', 'sess_user_group_admin_sort_direction', 'ASC');

	?>
	<script type="text/javascript">
	function applyFilter(objForm) {
		strURL = '?rows=' + objForm.rows.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		document.location = strURL;
	}

	function applyFilter() {
		strURL = 'user_group_admin.php?rows=' + $('#rows').val();
		strURL = strURL + '&page=' + $('#page').val();
		strURL = strURL + '&filter=' + $('#filter').val();
		strURL = strURL + '&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	function clearFilter() {
		strURL = 'user_group_admin.php?clear_x=1';
		strURL = strURL + '&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	$(function(data) {
		$('#form_group').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box('<strong>User Group Management</strong>', '100%', '', '3', 'center', 'user_group_admin.php?action=edit&tab=general');

	if ($_REQUEST['rows'] == '-1') {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = $_REQUEST['rows'];
	}

	?>
	<tr class='even'>
		<td>
		<form id="form_group" action='user_group_admin.php'>
			<table cellpadding="2" cellspacing="0">
				<tr>
					<td width='50'>
						Search
					</td>
					<td>
						<input type='text' id='filter' size="25" value="<?php print htmlspecialchars(get_request_var_request('filter'));?>">
					</td>
					<td>
						Groups
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var_request('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type='button' value='Go' title='Set/Refresh Filters' onClick='applyFilter()'>
					</td>
					<td>
						<input type='button' name="clear_x" value='Clear' title='Clear Filters' onClick='clearFilter()'>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' value='<?php print $_REQUEST['page'];?>'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST['rows'] == -1) {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = $_REQUEST['rows'];
	}

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var_request('filter'))) {
		$sql_where = "WHERE (name LIKE '%" . get_request_var_request('filter') . "%' OR description LIKE '%" . get_request_var_request('filter') . "%')";
	}else{
		$sql_where = '';
	}

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='user_group_admin.php'>\n";

	html_start_box('', '100%', '', '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM user_auth_group
		$sql_where");

	$group_list = db_fetch_assoc("SELECT uag.id, uag.name, uag.description, 
		uag.policy_graphs, uag.policy_hosts, uag.policy_graph_templates, 
		uag.enabled, count(uagm.group_id) AS members
		FROM user_auth_group AS uag
		LEFT JOIN user_auth_group_members AS uagm
		ON uag.id = uagm.group_id
		$sql_where
		GROUP BY uag.id
		ORDER BY " . get_request_var_request('sort_column') . ' ' . get_request_var_request('sort_direction') .
		' LIMIT ' . ($rows * (get_request_var_request('page') - 1)) . ',' . $rows);

	$nav = html_nav_bar('user_group_admin.php?filter=' . get_request_var_request('filter'), MAX_DISPLAY_PAGES, get_request_var_request('page'), $rows, $total_rows, 8, 'Groups', 'page', 'main');

	print $nav;

	$display_text = array(
		'name' => array('Group Name', 'ASC'),
		'members' => array('Members', 'ASC'),
		'description' => array('Description', 'ASC'),
		'policy_graphs' => array('Graph Policy', 'ASC'),
		'policy_hosts' => array('Device Policy', 'ASC'),
		'policy_graph_templates' => array('Template Policy', 'ASC'),
		'enabled' => array('Enabled', 'ASC'));

	html_header_sort_checkbox($display_text, get_request_var_request('sort_column'), get_request_var_request('sort_direction'), false);

	if (sizeof($group_list)) {
		foreach ($group_list as $group) {
			if ($group['enabled'] == 'on') {
				$enabled = 'Yes';
			}else{
				$enabled = 'No';
			}

			form_alternate_row('line' . $group['id'], true);
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars('user_group_admin.php?action=edit&tab=general&id=' . $group['id']) . "'>" .
			(strlen(get_request_var_request('filter')) ? eregi_replace('(' . preg_quote(get_request_var_request('filter')) . ')', "<span class='filteredValue'>\\1</span>",  htmlspecialchars($group['name'])) : htmlspecialchars($group['name'])) . '</a>', $group['id']);
			form_selectable_cell(($group['members'] > 0 ? number_format($group['members']):'None'), $group['id']);
			form_selectable_cell((strlen(get_request_var_request('filter')) ? eregi_replace('(' . preg_quote(get_request_var_request('filter')) . ')', "<span class='filteredValue'>\\1</span>", htmlspecialchars($group['description'])) : htmlspecialchars($group['description'])), $group['id']);
			form_selectable_cell(($group['policy_graphs'] == 1 ? 'ALLOW':'DENY'), $group['id']);
			form_selectable_cell(($group['policy_hosts'] == 1 ? 'ALLOW':'DENY'), $group['id']);
			form_selectable_cell(($group['policy_graph_templates'] == 1 ? 'ALLOW':'DENY'), $group['id']);
			form_selectable_cell($enabled, $group['id']);
			form_checkbox_cell($group['name'], $group['id']);
			form_end_row();
		}

		print $nav;
	}else{
		print '<tr><td><em>No User Groups Found</em></td></tr>';
	}
	html_end_box(false);

	draw_actions_dropdown($group_actions);
}

function process_graph_request_vars() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('graph_template_id'));
	input_validate_input_number(get_request_var_request('rows'));
	input_validate_input_number(get_request_var_request('page'));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var_request('filter'));
	}

	/* clean up sort solumn */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var_request('sort_column'));
	}

	/* clean up sort direction */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var_request('sort_direction'));
	}

	/* clean up associated */
	if (isset($_REQUEST['associated'])) {
		$_REQUEST['associated'] = sanitize_search_string(get_request_var_request('associated'));
	}elseif (isset($_SESSION['sess_ugg_associated'])) {
		$_REQUEST['associated'] = $_SESSION['sess_ugg_associated'];
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clearf'])) {
		kill_session_var('sess_ugg_page');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_ugg_filter');
		kill_session_var('sess_ugg_graph_template_id');
		kill_session_var('sess_ugg_associated');
		kill_session_var('sess_ugg_sort_column');
		kill_session_var('sess_ugg_sort_direction');

		unset($_REQUEST['page']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['graph_template_id']);
		unset($_REQUEST['associated']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
	}else{
		$changed = 0;
		$changed += check_changed('rows', 'sess_default_rows');
		$changed += check_changed('filter', 'sess_ugg_filter');
		$changed += check_changed('graph_template_id', 'sess_ugg_graph_template_id');
		$changed += check_changed('associated', 'sess_ugg_associated');
		$changed += check_changed('sort_column', 'sess_ugg_sort_column');
		$changed += check_changed('sort_direction', 'sess_ugg_sort_direction');
		if ($changed) {
			$_REQUEST['page'] = '1';
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_ugg_page', '1');
	load_current_session_value('filter', 'sess_ugg_filter', '');
	load_current_session_value('associated', 'sess_ugg_associated', 'true');
	load_current_session_value('graph_template_id', 'sess_ugd_graph_template_id', '-1');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));
}

function process_device_request_vars() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('host_template_id'));
	input_validate_input_number(get_request_var_request('rows'));
	input_validate_input_number(get_request_var_request('page'));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var_request('filter'));
	}

	/* clean up sort solumn */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var_request('sort_column'));
	}

	/* clean up sort direction */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var_request('sort_direction'));
	}

	/* clean up associated */
	if (isset($_REQUEST['associated'])) {
		$_REQUEST['associated'] = sanitize_search_string(get_request_var_request('associated'));
	}elseif (isset($_SESSION['sess_ugd_associated'])) {
		$_REQUEST['associated'] = $_SESSION['sess_ugd_associated'];
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clearf'])) {
		kill_session_var('sess_ugd_page');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_ugd_filter');
		kill_session_var('sess_ugd_host_template_id');
		kill_session_var('sess_ugd_associated');
		kill_session_var('sess_ugd_sort_column');
		kill_session_var('sess_ugd_sort_direction');

		unset($_REQUEST['page']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['host_template_id']);
		unset($_REQUEST['associated']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
	}else{
		$changed = 0;
		$changed += check_changed('rows', 'sess_default_rows');
		$changed += check_changed('filter', 'sess_ugd_filter');
		$changed += check_changed('host_template_id', 'sess_ugd_host_template_id');
		$changed += check_changed('associated', 'sess_ugd_associated');
		$changed += check_changed('sort_column', 'sess_ugd_sort_column');
		$changed += check_changed('sort_direction', 'sess_ugd_sort_direction');
		if ($changed) {
			$_REQUEST['page'] = '1';
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_ugd_page', '1');
	load_current_session_value('filter', 'sess_ugd_filter', '');
	load_current_session_value('associated', 'sess_ugd_associated', 'true');
	load_current_session_value('host_template_id', 'sess_ugd_host_template_id', '-1');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));
}

function process_template_request_vars() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('rows'));
	input_validate_input_number(get_request_var_request('page'));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var_request('filter'));
	}

	/* clean up sort solumn */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var_request('sort_column'));
	}

	/* clean up sort direction */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var_request('sort_direction'));
	}

	/* clean up associated */
	if (isset($_REQUEST['associated'])) {
		$_REQUEST['associated'] = sanitize_search_string(get_request_var_request('associated'));
	}elseif (isset($_SESSION['sess_ugte_associated'])) {
		$_REQUEST['associated'] = $_SESSION['sess_ugte_associated'];
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clearf'])) {
		kill_session_var('sess_ugte_page');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_ugte_filter');
		kill_session_var('sess_ugte_associated');
		kill_session_var('sess_ugte_sort_column');
		kill_session_var('sess_ugte_sort_direction');

		unset($_REQUEST['page']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['associated']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
	}else{
		$changed = 0;
		$changed += check_changed('rows', 'sess_default_rows');
		$changed += check_changed('filter', 'sess_ugte_filter');
		$changed += check_changed('associated', 'sess_ugte_associated');
		$changed += check_changed('sort_column', 'sess_ugte_sort_column');
		$changed += check_changed('sort_direction', 'sess_ugte_sort_direction');
		if ($changed) {
			$_REQUEST['page'] = '1';
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_ugte_page', '1');
	load_current_session_value('filter', 'sess_ugte_filter', '');
	load_current_session_value('associated', 'sess_ugte_associated', 'true');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));
}

function process_tree_request_vars() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('rows'));
	input_validate_input_number(get_request_var_request('page'));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var_request('filter'));
	}

	/* clean up sort solumn */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var_request('sort_column'));
	}

	/* clean up sort direction */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var_request('sort_direction'));
	}

	/* clean up associated */
	if (isset($_REQUEST['associated'])) {
		$_REQUEST['associated'] = sanitize_search_string(get_request_var_request('associated'));
	}elseif (isset($_SESSION['sess_ugtr_associated'])) {
		$_REQUEST['associated'] = $_SESSION['sess_ugtr_associated'];
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clearf'])) {
		kill_session_var('sess_ugtr_page');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_ugtr_filter');
		kill_session_var('sess_ugtr_associated');
		kill_session_var('sess_ugtr_sort_column');
		kill_session_var('sess_ugtr_sort_direction');

		unset($_REQUEST['page']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['associated']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
	}else{
		$changed = 0;
		$changed += check_changed('rows', 'sess_default_rows');
		$changed += check_changed('filter', 'sess_ugtr_filter');
		$changed += check_changed('associated', 'sess_ugtr_associated');
		$changed += check_changed('sort_column', 'sess_ugtr_sort_column');
		$changed += check_changed('sort_direction', 'sess_ugtr_sort_direction');
		if ($changed) {
			$_REQUEST['page'] = '1';
		}

		$reset_multi = false;
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_ugtr_page', '1');
	load_current_session_value('filter', 'sess_ugtr_filter', '');
	load_current_session_value('associated', 'sess_ugtr_associated', 'true');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));
}

function process_member_request_vars() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('rows'));
	input_validate_input_number(get_request_var_request('page'));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var_request('filter'));
	}

	/* clean up sort solumn */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var_request('sort_column'));
	}

	/* clean up sort direction */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var_request('sort_direction'));
	}

	/* clean up associated */
	if (isset($_REQUEST['associated'])) {
		$_REQUEST['associated'] = sanitize_search_string(get_request_var_request('associated'));
	}elseif (isset($_SESSION['sess_ugm_associated'])) {
		$_REQUEST['associated'] = $_SESSION['sess_ugm_associated'];
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clearf'])) {
		kill_session_var('sess_ugm_page');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_ugm_filter');
		kill_session_var('sess_ugm_associated');
		kill_session_var('sess_ugm_sort_column');
		kill_session_var('sess_ugm_sort_direction');

		unset($_REQUEST['page']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['associated']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
	}else{
		$changed = 0;
		$changed += check_changed('rows', 'sess_default_rows');
		$changed += check_changed('filter', 'sess_ugm_filter');
		$changed += check_changed('associated', 'sess_ugm_associated');
		$changed += check_changed('sort_column', 'sess_ugm_sort_column');
		$changed += check_changed('sort_direction', 'sess_ugm_sort_direction');
		if ($changed) {
			$_REQUEST['page'] = '1';
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_ugm_page', '1');
	load_current_session_value('filter', 'sess_ugm_filter', '');
	load_current_session_value('associated', 'sess_ugm_associated', 'true');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));
}

function graph_filter($header_label) {
	global $config, $item_rows;

	?>
	<script type="text/javascript">
	function applyFilter() {
		strURL = 'user_group_admin.php?action=edit&tab=permsg&id=<?php print get_request_var_request('id');?>'
		strURL = strURL + '&rows=' + $('#rows').val();
		strURL = strURL + '&page=' + $('#page').val();
		strURL = strURL + '&graph_template_id=' + $('#graph_template_id').val();
		strURL = strURL + '&associated=' + $('#associated').is(':checked');
		strURL = strURL + '&filter=' + $('#filter').val();
		strURL = strURL + '&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	function clearFilter() {
		strURL = 'user_group_admin.php?action=edit&tab=permsg&id=<?php print get_request_var_request('id');?>&clearf=true'
		strURL = strURL + '&header=false';
		$.get(strURL, function(data) {
		$('#main').html(data);
		applySkin();
		});
	}

	$(function(data) {
		$('#forms').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box('<strong>Graph Permissions</strong> ' . htmlspecialchars($header_label), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='forms' method='post' action='user_group_admin.php'>
			<table cellpadding="2" cellspacing="0" border="0">
				<tr>
					<td>
						Template
					</td>
					<td>
						<select id="graph_template_id" onChange='applyFilter()'>
							<option value="-1"<?php if (get_request_var_request('graph_template_id') == '-1') {?> selected<?php }?>>Any</option>
							<option value="0"<?php if (get_request_var_request('graph_template_id') == '0') {?> selected<?php }?>>None</option>
							<?php
							$graph_templates = db_fetch_assoc('SELECT DISTINCT gt.id, gt.name 
								FROM graph_templates AS gt
								INNER JOIN graph_local AS gl
								ON gl.graph_template_id = gt.id
								ORDER BY name');

							if (sizeof($graph_templates)) {
								foreach ($graph_templates as $gt) {
									print "<option value='" . $gt['id'] . "'"; if (get_request_var_request('graph_template_id') == $gt['id']) { print ' selected'; } print '>' . htmlspecialchars($gt['name']) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						Search
					</td>
					<td>
						<input type='text' id='filter' size='25' value="<?php print htmlspecialchars(get_request_var_request('filter'));?>" onChange='applyFilter()'>
					</td>
					<td>
						Graphs
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var_request('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type='checkbox' name='associated' id='associated' onChange='applyFilter()' <?php print ($_REQUEST['associated'] == 'true' || $_REQUEST['associated'] == 'on' ? 'checked':'');?>>
					</td>
					<td>
						<label style='white-space:nowrap;' for='associated'>Show Exceptions</label>
					</td>
					<td>
						<input type='button' value='Go' onClick='applyFilter()' title='Set/Refresh Filters'>
					</td>
					<td>
						<input type='button' name="clearf" value='Clear' onClick='clearFilter()' title='Clear Filters'>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' value='<?php print $_REQUEST['page'];?>'>
			<input type='hidden' name='action' value='edit'>
			<input type='hidden' name='tab' value='permsg'>
			<input type='hidden' name='id' value='<?php print get_request_var_request('id');?>'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();
}

function device_filter($header_label) {
	global $config, $item_rows;

	?>
	<script type="text/javascript">
	function applyFilter() {
		strURL = 'user_group_admin.php?action=edit&tab=permsd&id=<?php print get_request_var_request('id');?>'
		strURL = strURL + '&rows=' + $('#rows').val();
		strURL = strURL + '&page=' + $('#page').val();
		strURL = strURL + '&host_template_id=' + $('#host_template_id').val();
		strURL = strURL + '&associated=' + $('#associated').is(':checked');
		strURL = strURL + '&filter=' + $('#filter').val();
		strURL = strURL + '&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	function clearFilter() {
		strURL = 'user_group_admin.php?action=edit&tab=permsd&id=<?php print get_request_var_request('id');?>&clearf=true'
		strURL = strURL + '&header=false';
		$.get(strURL, function(data) {
		$('#main').html(data);
		applySkin();
		});
	}

	$(function(data) {
		$('#forms').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box('<strong>Devices Permission</strong> ' . htmlspecialchars($header_label), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='forms' method='post' action='user_group_admin.php'>
			<table cellpadding="2" cellspacing="0">
				<tr>
					<td>
						Template
					</td>
					<td>
						<select id="host_template_id" onChange='applyFilter()'>
							<option value="-1"<?php if (get_request_var_request('host_template_id') == '-1') {?> selected<?php }?>>Any</option>
							<option value="0"<?php if (get_request_var_request('host_template_id') == '0') {?> selected<?php }?>>None</option>
							<?php
							$host_templates = db_fetch_assoc('SELECT id, name FROM host_template ORDER BY name');

							if (sizeof($host_templates) > 0) {
								foreach ($host_templates as $host_template) {
									print "<option value='" . $host_template['id'] . "'"; if (get_request_var_request('host_template_id') == $host_template['id']) { print ' selected'; } print '>' . htmlspecialchars($host_template['name']) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						Search
					</td>
					<td>
						<input type='text' id='filter' size='25' value="<?php print htmlspecialchars(get_request_var_request('filter'));?>" onChange='applyFilter()'>
					</td>
					<td>
						Devices
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var_request('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type='checkbox' name='associated' id='associated' onChange='applyFilter()' <?php print ($_REQUEST['associated'] == 'true' || $_REQUEST['associated'] == 'on' ? 'checked':'');?>>
					</td>
					<td>
						<label style='white-space:nowrap;' for='associated'>Show Exceptions</label>
					</td>
					<td>
						<input type='button' value='Go' onClick='applyFilter()' title='Set/Refresh Filters'>
					</td>
					<td>
						<input type='button' name="clearf" value='Clear' onClick='clearFilter()' title='Clear Filters'>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' value='<?php print $_REQUEST['page'];?>'>
			<input type='hidden' name='action' value='edit'>
			<input type='hidden' name='tab' value='permsd'>
			<input type='hidden' name='id' value='<?php print get_request_var_request('id');?>'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();
}

function template_filter($header_label) {
	global $config, $item_rows;

	?>
	<script type="text/javascript">
	function applyFilter() {
		strURL = 'user_group_admin.php?action=edit&tab=permste&id=<?php print get_request_var_request('id');?>'
		strURL = strURL + '&rows=' + $('#rows').val();
		strURL = strURL + '&page=' + $('#page').val();
		strURL = strURL + '&associated=' + $('#associated').is(':checked');
		strURL = strURL + '&filter=' + $('#filter').val();
		strURL = strURL + '&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	function clearFilter() {
		strURL = 'user_group_admin.php?action=edit&tab=permste&id=<?php print get_request_var_request('id');?>&clearf=true'
		strURL = strURL + '&header=false';
		$.get(strURL, function(data) {
		$('#main').html(data);
		applySkin();
		});
	}

	$(function(data) {
		$('#forms').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box('<strong>Template Permission</strong> ' . htmlspecialchars($header_label), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='forms' method='post' action='user_group_admin.php'>
			<table cellpadding="2" cellspacing="0">
				<tr>
					<td  width='50'>
						Search
					</td>
					<td>
						<input type='text' id='filter' size='25' value="<?php print htmlspecialchars(get_request_var_request('filter'));?>" onChange='applyFilter()'>
					</td>
					<td>
						Templates
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var_request('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type='checkbox' name='associated' id='associated' onChange='applyFilter()' <?php print ($_REQUEST['associated'] == 'true' || $_REQUEST['associated'] == 'on' ? 'checked':'');?>>
					</td>
					<td>
						<label style='white-space:nowrap;' for='associated'>Show Exceptions</label>
					</td>
					<td>
						<input type='button' value='Go' onClick='applyFilter()' title='Set/Refresh Filters'>
					</td>
					<td>
						<input type='button' name="clearf" value='Clear' onClick='clearFilter()' title='Clear Filters'>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' value='<?php print $_REQUEST['page'];?>'>
			<input type='hidden' name='action' value='edit'>
			<input type='hidden' name='tab' value='permste'>
			<input type='hidden' name='id' value='<?php print get_request_var_request('id');?>'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();
}

function tree_filter($header_label) {
	global $config, $item_rows;

	?>
	<script type="text/javascript">
	function applyFilter() {
		strURL = 'user_group_admin.php?action=edit&tab=permstr&id=<?php print get_request_var_request('id');?>'
		strURL = strURL + '&rows=' + $('#rows').val();
		strURL = strURL + '&page=' + $('#page').val();
		strURL = strURL + '&associated=' + $('#associated').is(':checked');
		strURL = strURL + '&filter=' + $('#filter').val();
		strURL = strURL + '&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	function clearFilter() {
		strURL = 'user_group_admin.php?action=edit&tab=permstr&id=<?php print get_request_var_request('id');?>&clearf=true'
		strURL = strURL + '&header=false';
		$.get(strURL, function(data) {
		$('#main').html(data);
		applySkin();
		});
	}

	$(function(data) {
		$('#forms').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box('<strong>Tree Permission</strong> ' . htmlspecialchars($header_label), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='forms' method='post' action='user_group_admin.php'>
			<table cellpadding="2" cellspacing="0">
				<tr>
					<td width='50'>
						Search
					</td>
					<td>
						<input type='text' name='filter' id='filter' size='25' value="<?php print htmlspecialchars(get_request_var_request('filter'));?>" onChange='applyFilter()'>
					</td>
					<td>
						Trees
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var_request('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type='checkbox' name='associated' id='associated' onChange='applyFilter()' <?php print ($_REQUEST['associated'] == 'true' || $_REQUEST['associated'] == 'on' ? 'checked':'');?>>
					</td>
					<td>
						<label style='white-space:nowrap;' for='associated'>Show Exceptions</label>
					</td>
					<td>
						<input type='button' value='Go' onClick='applyFilter()' title='Set/Refresh Filters'>
					</td>
					<td>
						<input type='button' name="clearf" value='Clear' onClick='clearFilter()' title='Clear Filters'>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' value='<?php print $_REQUEST['page'];?>'>
			<input type='hidden' name='action' value='edit'>
			<input type='hidden' name='tab' value='permstr'>
			<input type='hidden' name='id' value='<?php print get_request_var_request('id');?>'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();
}

function member_filter($header_label) {
	global $config, $item_rows;

	?>
	<script type="text/javascript">
	function applyFilter() {
		strURL = 'user_group_admin.php?action=edit&tab=members&id=<?php print get_request_var_request('id');?>'
		strURL = strURL + '&rows=' + $('#rows').val();
		strURL = strURL + '&page=' + $('#page').val();
		strURL = strURL + '&associated=' + $('#associated').is(':checked');
		strURL = strURL + '&filter=' + $('#filter').val();
		strURL = strURL + '&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	function clearFilter() {
		strURL = 'user_group_admin.php?action=edit&tab=members&id=<?php print get_request_var_request('id');?>&clearf=true'
		strURL = strURL + '&header=false';
		$.get(strURL, function(data) {
		$('#main').html(data);
		applySkin();
		});
	}

	$(function(data) {
		$('#forms').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box('<strong>User Membership</strong> ' . htmlspecialchars($header_label), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='forms' method='post' action='user_group_admin.php'>
			<table cellpadding="2" cellspacing="0">
				<tr>
					<td width='50'>
						Search
					</td>
					<td>
						<input type='text' id='filter' name='filter' size='25' value="<?php print htmlspecialchars(get_request_var_request('filter'));?>" onChange='applyFilter()'>
					</td>
					<td>
						Users
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var_request('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type='checkbox' name='associated' id='associated' onChange='applyFilter()' <?php print ($_REQUEST['associated'] == 'true' || $_REQUEST['associated'] == 'on' ? 'checked':'');?>>
					</td>
					<td>
						<label style='white-space:nowrap;' for='associated'>Show Members</label>
					</td>
					<td>
						<input type='button' value='Go' onClick='applyFilter()' title='Set/Refresh Filters'>
					</td>
					<td>
						<input type='button' name="clearf" value='Clear' onClick='clearFilter()' title='Clear Filters'>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' value='<?php print $_REQUEST['page'];?>'>
			<input type='hidden' name='action' value='edit'>
			<input type='hidden' name='tab' value='members'>
			<input type='hidden' name='id' value='<?php print get_request_var_request('id');?>'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();
}

