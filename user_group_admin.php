<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

include('./include/auth.php');

set_default_action();

$group_actions = array(
	1 => __('Delete'),
	2 => __('Copy'),
	3 => __('Enable'),
	4 => __('Disable')
);

$href_options = array(
	3 => array(
		'radio_value' => '4',
		'radio_caption' => __('Defer to the Users Setting')
		),
	0 => array(
		'radio_value' => '1',
		'radio_caption' => __('Show the Page that the User pointed their browser to')
		),
	1 => array(
		'radio_value' => '2',
		'radio_caption' => __('Show the Console')
		),
	2 => array(
		'radio_value' => '3',
		'radio_caption' => __('Show the default Graph Screen')
		)
);

$gperm_options = array(
	0 => array(
		'radio_value' => '1',
		'radio_caption' => __('Defer to the Users Setting')
		),
	1 => array(
		'radio_value' => '2',
		'radio_caption' => __('Grant Access')
		),
	2 => array(
		'radio_value' => '3',
		'radio_caption' => __('Restrict Access')
		)
);

$fields_user_group_edit = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Group Name'),
		'description' => __('The name of this Group.'),
		'value' => '|arg1:name|',
		'max_length' => '255'
		),
	'description' => array(
		'method' => 'textbox',
		'friendly_name' => __('Group Description'),
		'description' => __('A more descriptive name for this group, that can include spaces or special characters.'),
		'value' => '|arg1:description|',
		'max_length' => '255'
		),
	'enabled' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Enabled'),
		'description' => __('Determines if user is able to login.'),
		'value' => '|arg1:enabled|',
		'default' => ''
		),
	'grp1' => array(
		'friendly_name' => __('General Group Options'),
		'method' => 'checkbox_group',
		'description' => __('Set any user account-specific options here.'),
		'items' => array(
			'graph_settings' => array(
				'value' => '|arg1:graph_settings|',
				'friendly_name' => __('Allow Users of this Group to keep custom User Settings'),
				'form_id' => '|arg1:id|',
				'default' => 'on'
				)
			)
		),
	'show_tree' => array(
		'friendly_name' => __('Tree Rights'),
		'method' => 'radio',
		'description' => __('Should Users of this Group have access to the Tree?'),
		'value' => '|arg1:show_tree|',
		'default' => '1',
		'items' => $gperm_options
		),
	'show_list' => array(
		'friendly_name' => __('Graph List Rights'),
		'method' => 'radio',
		'description' => __('Should Users of this Group have access to the Graph List?'),
		'value' => '|arg1:show_list|',
		'default' => '1',
		'items' => $gperm_options
		),
	'show_preview' => array(
		'friendly_name' => __('Graph Preview Rights'),
		'method' => 'radio',
		'description' => __('Should Users of this Group have access to the Graph Preview?'),
		'value' => '|arg1:show_preview|',
		'default' => '1',
		'items' => $gperm_options
		),
	'login_opts' => array(
		'friendly_name' => __('Login Options'),
		'method' => 'radio',
		'default' => '1',
		'description' => __('What to do when a User from this User Group logs in.'),
		'value' => '|arg1:login_opts|',
		'items' => $href_options
		),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
		),
	'save_component_group' => array(
		'method' => 'hidden',
		'value' => '1'
		)
);

if (isset_request_var('update_policy')) {
	update_policies();
} else {
	switch (get_request_var('action')) {
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
		if (!api_plugin_hook_function('user_group_admin_action', get_request_var('action'))) {
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

	reset_group_perms($id);
}

function user_group_enable($id) {
	db_execute_prepared("UPDATE user_auth_group SET enabled = 'on' WHERE id = ?", array($id));

	reset_group_perms($id);
}

function user_group_remove($id) {
	db_execute_prepared('DELETE FROM user_auth_group WHERE id = ?', array($id));
	db_execute_prepared('DELETE FROM user_auth_group_members WHERE group_id = ?', array($id));
	db_execute_prepared('DELETE FROM user_auth_group_realm WHERE group_id = ?', array($id));
	db_execute_prepared('DELETE FROM user_auth_group_perms WHERE group_id = ?', array($id));
}

function user_group_copy($id, $prefix = 'New Group') {
	static $count = 1;

	$name = $prefix . ' ' . $count;

	db_execute_prepared('INSERT INTO user_auth_group
		(name, description, graph_settings, login_opts, show_tree, show_list, show_preview,
		policy_graphs, policy_trees, policy_hosts, policy_graph_templates, enabled)
		SELECT ' . db_qstr($name) . ', description, graph_settings, login_opts, show_tree, show_list, show_preview,
		policy_graphs, policy_trees, policy_hosts, policy_graph_templates, enabled
		FROM user_auth_group WHERE id = ?', array($id));

	$id = db_fetch_insert_id();

	if (!empty($id)) {
		$perms = db_fetch_assoc_prepared('SELECT *
			FROM user_auth_group_perms
			WHERE group_id = ?',
			array($id));

		if (cacti_sizeof($perms)) {
			foreach($perms as $p) {
				db_execute_prepared('INSERT INTO user_auth_group_perms
					(group_id, item_id, type)
					VALUES (?, ?, ?)',
					array($id, $p['item_id'], $p['type']));
			}
		}

		$realms = db_fetch_assoc_prepared('SELECT *
			FROM user_auth_group_realm
			WHERE group_id = ?',
			array($id));

		if (cacti_sizeof($realms)) {
			foreach($realms as $r) {
				db_execute_prepared('INSERT INTO user_auth_group_realm
					(group_id, realm_id)
					VALUES (?, ?)',
					array($id, $r['realm_id']));
			}
		}
	}

	$count++;
}

function update_policies() {
	$policies = array('policy_graphs', 'policy_trees', 'policy_hosts', 'policy_graph_templates');

	foreach ($policies as $p) {
		if (isset_request_var($p)) {
			db_execute_prepared("UPDATE `user_auth_group` SET `$p` = ? WHERE `id` = ?", array(get_filter_request_var($p), get_filter_request_var('id')));
		}
	}

	header('Location: user_group_admin.php?action=edit&header=false&tab=' .  get_nfilter_request_var('tab') . '&id=' . get_filter_request_var('id'));
	exit;
}

function form_actions() {
	global $group_actions, $user_auth_realms;

	/* if we are to save this form, instead of display it */
	if (isset_request_var('associate_host')) {
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				if (get_nfilter_request_var('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_group_perms
						(group_id, item_id, type)
						VALUES (?, ?, 3)',
						array(get_nfilter_request_var('id'), $matches[1]));
				} else {
					db_execute_prepared('DELETE FROM user_auth_group_perms
						WHERE group_id = ?
						AND item_id = ?
						AND type = 3',
						array(get_nfilter_request_var('id'), $matches[1]));
				}
			}
		}

		header('Location: user_group_admin.php?action=edit&header=false&tab=permsd&id=' . get_nfilter_request_var('id'));
		exit;
	} elseif (isset_request_var('associate_graph')) {
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				if (get_nfilter_request_var('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_group_perms
						(group_id, item_id, type)
						VALUES (?, ?, 1)',
						array(get_nfilter_request_var('id'), $matches[1]));
				} else {
					db_execute_prepared('DELETE FROM user_auth_group_perms
						WHERE group_id = ?
						AND item_id = ?
						AND type = 1',
						array(get_nfilter_request_var('id'), $matches[1]));
				}
			}
		}

		header('Location: user_group_admin.php?action=edit&header=false&tab=permsg&id=' . get_nfilter_request_var('id'));
		exit;
	} elseif (isset_request_var('associate_template')) {
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				if (get_nfilter_request_var('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_group_perms
						(group_id, item_id, type)
						VALUES (?, ?, 4)',
						array(get_nfilter_request_var('id'), $matches[1]));
				} else {
					db_execute_prepared('DELETE FROM user_auth_group_perms
						WHERE group_id = ?
						AND item_id = ?
						AND type = 4',
						array(get_nfilter_request_var('id'), $matches[1]));
				}
			}
		}

		header('Location: user_group_admin.php?action=edit&header=false&tab=permste&id=' . get_nfilter_request_var('id'));
		exit;
	} elseif (isset_request_var('associate_tree')) {
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				if (get_nfilter_request_var('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_group_perms
						(group_id, item_id, type)
						VALUES (?, ?, 2)',
						array(get_nfilter_request_var('id'), $matches[1]));
				} else {
					db_execute_prepared('DELETE FROM user_auth_group_perms
						WHERE group_id = ?
						AND item_id = ?
						AND type = 2',
						array(get_nfilter_request_var('id'), $matches[1]));
				}
			}
		}

		header('Location: user_group_admin.php?action=edit&header=false&tab=permstr&id=' . get_nfilter_request_var('id'));
		exit;
	} elseif (isset_request_var('associate_member')) {
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				if (get_nfilter_request_var('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_group_members
						(group_id, user_id)
						VALUES (?, ?)',
						array(get_nfilter_request_var('id'), $matches[1]));
				} else {
					db_execute_prepared('DELETE FROM user_auth_group_members
						WHERE group_id = ?
						AND user_id = ?',
						array(get_nfilter_request_var('id'), $matches[1]));
				}
			}
		}

		header('Location: user_group_admin.php?action=edit&header=false&tab=members&id=' . get_nfilter_request_var('id'));
		exit;
	} elseif (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') { /* delete */
				for ($i=0;($i<cacti_count($selected_items));$i++) {
					user_group_remove($selected_items[$i]);

					api_plugin_hook_function('user_group_remove', $selected_items[$i]);
				}
			} elseif (get_nfilter_request_var('drp_action') == '2') { /* copy */
				for ($i=0;($i<cacti_count($selected_items));$i++) {
					user_group_copy($selected_items[$i], get_nfilter_request_var('group_prefix'));
				}
			} elseif (get_nfilter_request_var('drp_action') == '3') { /* enable */
				for ($i=0;($i<cacti_count($selected_items));$i++) {
					user_group_enable($selected_items[$i]);
				}
			} elseif (get_nfilter_request_var('drp_action') == '4') { /* disable */
				for ($i=0;($i<cacti_count($selected_items));$i++) {
					user_group_disable($selected_items[$i]);
				}
			}
		}

		header('Location: user_group_admin.php?header=false');
		exit;
	}

	/* loop through each of the users and process them */
	$group_list = '';
	$group_array = array();
	$i = 0;
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			if (get_nfilter_request_var('drp_action') != '2') {
				$group_list .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT name FROM user_auth_group WHERE id = ?', array($matches[1]))) . '</li>';
			}
			$group_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('user_group_admin.php');

	html_start_box($group_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (isset($group_array) && cacti_sizeof($group_array)) {
		if ((get_nfilter_request_var('drp_action') == '1') && (cacti_sizeof($group_array))) { /* delete */
			print "<tr>
				<td class='textArea'>
					<p>" . __n('Click \'Continue\' to delete the following User Group', 'Click \'Continue\' to delete following User Groups', cacti_sizeof($group_array)) . "</p>
					<div class='itemlist'><ul>$group_list</ul></div>
				</td>
			</tr>";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Delete User Group', 'Delete User Groups', cacti_sizeof($group_array)) . "'>";
		}

		$group_id = '';

		if ((get_nfilter_request_var('drp_action') == '2') && (cacti_sizeof($group_array))) { /* copy */
			print "<tr>
				<td class='textArea'>
					<p>" . __n('Click \'Continue\' to Copy the following User Group to a new User Group.', 'Click \'Continue\' to Copy following User Groups to new User Groups.', cacti_sizeof($group_array)) . "</p>
					<div class='itemlist'><ul>$group_list</ul></div>
				</td>
			</tr>
			<tr>
				<td class='textArea'>
					<p>" . __('Group Prefix:') . " ";
			print form_text_box('group_prefix', __('New Group'), '', 25);
			print "</p></td>
				</tr>";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Copy User Group', 'Copy User Groups', cacti_sizeof($group_array)) . "'>";
		}

		if ((get_nfilter_request_var('drp_action') == '3') && (cacti_sizeof($group_array))) { /* enable */
			print "<tr>
				<td class='textArea'>
					<p>" . __n('Click \'Continue\' to enable the following User Group.', 'Click \'Continue\' to enable following User Groups.', cacti_sizeof($group_array)) . "</p>
					<div class='itemlist'><ul>$group_list</ul></div>
				</td>
			</tr>";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Enable User Group', 'Enable User Groups', cacti_sizeof($group_array)) . "'>";
		}

		if ((get_nfilter_request_var('drp_action') == '4') && (cacti_sizeof($group_array))) { /* disable */
			print "<tr>
				<td class='textArea'>
					<p>" . __n('Click \'Continue\' to disable the following User Group.', 'Click \'Continue\' to disable following User Groups.', cacti_sizeof($group_array)) . "</p>
					<div class='itemlist'><ul>$group_list</ul></div>
				</td>
			</tr>";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Disable User Group', 'Disable User Groups', cacti_sizeof($group_array)) . "'>";
		}
	} else {
		raise_message(40);
		header('Location: user_group_admin.php?header=false');
		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>";

	print "<input type='hidden' name='selected_items' value='" . (isset($group_array) ? serialize($group_array) : '') . "'>";
	print "<input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>
		$save_html
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

/* --------------------------
    Save Function
   -------------------------- */

function form_save() {
	global $settings_user;

	if (isset_request_var('save_component_group')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		get_filter_request_var('realm');
		/* ==================================================== */

		/* check duplicate group */
		if (cacti_sizeof(db_fetch_row_prepared('SELECT * FROM user_auth_group WHERE name = ? AND id != ?', array(get_nfilter_request_var('name'), get_nfilter_request_var('id'))))) {
			raise_message(12);
		}

		$save['id']             = get_nfilter_request_var('id');
		$save['name']           = form_input_validate(get_nfilter_request_var('name'), 'name', "^[A-Za-z0-9\._\\\@\ -]+$", false, 3);
		$save['description']    = form_input_validate(get_nfilter_request_var('description'), 'description', '', true, 3);
		$save['show_tree']      = form_input_validate(get_nfilter_request_var('show_tree', ''), 'show_tree', '', true, 3);
		$save['show_list']      = form_input_validate(get_nfilter_request_var('show_list', ''), 'show_list', '', true, 3);
		$save['show_preview']   = form_input_validate(get_nfilter_request_var('show_preview', ''), 'show_preview', '', true, 3);
		$save['graph_settings'] = form_input_validate(get_nfilter_request_var('graph_settings', ''), 'graph_settings', '', true, 3);
		$save['login_opts']     = form_input_validate(get_nfilter_request_var('login_opts'), 'login_opts', '', true, 3);
		$save['enabled']        = form_input_validate(get_nfilter_request_var('enabled', ''), 'enabled', '', true, 3);

		$save = api_plugin_hook_function('user_group_admin_setup_sql_save', $save);

		if (!is_error_message()) {
			$group_id = sql_save($save, 'user_auth_group');

			if ($group_id) {
				reset_group_perms($group_id);
				raise_message(1);
			} else {
				raise_message(2);
			}

		}

		header('Location: user_group_admin.php?action=edit&header=false&tab=general&id=' . (isset($group_id) && $group_id > 0 ? $group_id : get_nfilter_request_var('id')));
		exit;
	} elseif (isset_request_var('save_component_realm_perms')) {
		db_execute_prepared('DELETE FROM user_auth_group_realm WHERE group_id = ?', array(get_filter_request_var('id')));

		foreach ($_POST as $var => $val) {
			if (preg_match('/^[section]/i', $var)) {
				if (substr($var, 0, 7) == 'section') {
				    db_execute_prepared('REPLACE INTO user_auth_group_realm (group_id, realm_id) VALUES (?, ?)', array(get_request_var('id'), substr($var, 7)));
				}
			}
		}

		reset_group_perms(get_request_var('id'));

		raise_message(1);

		header('Location: user_group_admin.php?action=edit&header=false&tab=realms&id=' . get_request_var('id'));
		exit;
	} elseif (isset_request_var('save_component_graph_settings')) {
		foreach ($settings_user as $tab_short_name => $tab_fields) {
			foreach ($tab_fields as $field_name => $field_array) {
				if ((isset($field_array['items'])) && (is_array($field_array['items']))) {
					foreach ($field_array['items'] as $sub_field_name => $sub_field_array) {
						db_execute_prepared('REPLACE INTO settings_user_group (group_id, name, value) VALUES (?, ?, ?)', array(get_filter_request_var('id'), $sub_field_name, get_nfilter_request_var($sub_field_name, '')));
					}
				} else {
					db_execute_prepared('REPLACE INTO settings_user_group (group_id, name, value) VALUES (?, ?, ?)', array(get_request_var('id'), $field_name, get_nfilter_request_var($field_name)));
				}
			}
		}

		kill_session_var('sess_user_config_array');

		reset_group_perms(get_request_var('id'));

		raise_message(1);

		header('Location: user_group_admin.php?action=edit&header=false&tab=settings&id=' . get_nfilter_request_var('id'));
		exit;
	} else {
		api_plugin_hook('user_group_admin_save');
	}

	/* redirect to the appropriate page */
	header('Location: user_group_admin.php?action=edit&header=false&tab=general&id=' .  get_nfilter_request_var('id'));
}

/* --------------------------
    Graph Permissions
   -------------------------- */

function perm_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('group_id');
	/* ==================================================== */

	if (get_request_var('type') == 'graph') {
		db_execute_prepared('DELETE FROM user_auth_group_perms WHERE type=1 AND group_id = ? AND item_id = ?', array(get_request_var('group_id'), get_request_var('id')));
	} elseif (get_request_var('type') == 'tree') {
		db_execute_prepared('DELETE FROM user_auth_group_perms WHERE type=2 AND group_id = ? AND item_id = ?', array(get_request_var('group_id'), get_request_var('id')));
	} elseif (get_request_var('type') == 'host') {
		db_execute_prepared('DELETE FROM user_auth_group_perms WHERE type=3 AND group_id = ? AND item_id = ?', array(get_request_var('group_id'), get_request_var('id')));
	} elseif (get_request_var('type') == 'graph_template') {
		db_execute_prepared('DELETE FROM user_auth_group_perms WHERE type=4 AND group_id = ? AND item_id = ?', array(get_request_var('group_id'), get_request_var('id')));
	}

	header('Location: user_group_admin.php?action=edit&header=false&tab=gperms&id=' . get_request_var('group_id'));
}

function user_group_members_edit($header_label) {
	global $config, $auth_realms;

	process_member_request_vars();

	member_filter($header_label);

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = 'WHERE (
			username LIKE '     . db_qstr('%' . get_request_var('filter') . '%') . '
			OR full_name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

	if (get_request_var('associated') != 'false') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' (user_auth_group_members.group_id=' . get_request_var('id', 0) . ')';
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
		LIMIT " . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$members = db_fetch_assoc($sql_query);

	$nav = html_nav_bar('user_group_admin.php?action=edit&tab=members&id=' . get_request_var('id'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 7, __('Users'), 'page', 'main');

	form_start('user_group_admin.php?tab=members&id=' . get_request_var('id'), 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array( __('Login Name'), __('Full Name'), __('ID'), __('Membership'), __('Enabled'), __('Realm'));

	html_header_checkbox($display_text, false);

	if (cacti_sizeof($members)) {
		foreach ($members as $g) {
			form_alternate_row('line' . $g['id'], true);
			form_selectable_cell(filter_value($g['username'], get_request_var('filter'), 'user_admin.php?action=user_edit&id=' . $g['id']), $g['id']);
			form_selectable_cell(filter_value($g['full_name'], get_request_var('filter')), $g['id']);
			form_selectable_cell($g['id'], $g['id']);
			if (user_group_is_member($g['id'], get_request_var('id'))) {
				form_selectable_cell('<span class="accessGranted">' . __('Group Member') . '</span>', $g['id']);
			} else {
				form_selectable_cell('<span class="accessRestricted">' . __('Non Member') . '</span>', $g['id']);
			}
			form_selectable_cell(($g['enabled'] == 'on' ? __('Enabled'):__('Disabled') ), $g['id']);
			form_selectable_cell((isset($auth_realms[$g['realm']]) ? $auth_realms[$g['realm']]:'Unknown'), $g['id']);
			form_checkbox_cell($g['full_name'], $g['id']);
			form_end_row();
		}
	} else {
		print '<tr><td colspan="' . (cacti_sizeof($display_text)+1) . '"><em>' . __('No Matching Group Members Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($members)) {
		print $nav;
	}

	form_hidden_box('tab', 'members', '');
	form_hidden_box('id', get_request_var('id'), '');
	form_hidden_box('associate_member', '1', '');

	$assoc_actions = array(
		1 => __('Add to Group'),
		2 => __('Remove from Group')
	);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($assoc_actions);

	form_end();
}

function user_group_graph_perms_edit($tab, $header_label) {
	global $config, $assoc_actions;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$policy_array = array(
		1 => __('Allow'),
		2 => __('Deny')
	);

	if (!isempty_request_var('id')) {
		$policy = db_fetch_row_prepared('SELECT policy_graphs, policy_trees, policy_hosts, policy_graph_templates
			FROM user_auth_group
			WHERE id = ?',
			array(get_request_var('id')));
	}

	switch($tab) {
	case 'permsg':
		process_graph_request_vars();

		$graph_auth_method = read_config_option('graph_auth_method');

		if ($graph_auth_method == 1) {
			$policy_note = __('<b>Note:</b> System Graph Policy is \'Permissive\' meaning the User must have access to at least one of Graph, Device, or Graph Template to gain access to the Graph');
		} elseif ($graph_auth_method == 2) {
			$policy_note = __('<b>Note:</b> System Graph Policy is \'Restrictive\' meaning the User must have access to either the Graph or the Device and Graph Template to gain access to the Graph');
		} elseif ($graph_auth_method == 3) {
			$policy_note = __('<b>Note:</b> System Graph Policy is \'Device\' meaning the User must have access to the Graph or Device to gain access to the Graph');
		} else {
			$policy_note = __('<b>Note:</b> System Graph Policy is \'Graph Template\' meaning the User must have access to the Graph or Graph Template to gain access to the Graph');
		}

		graph_filter($header_label);

		form_start('user_group_admin.php', 'policy');

		/* box: device permissions */
		html_start_box( __('Default Graph Policy'), '100%', '', '3', 'center', '');

		?>
		<tr class='even'>
			<td><table><tr>
			<td class='nowrap'><?php print __('Default Graph Policy for this User Group');?></td>
			<td>
				<?php form_dropdown('policy_graphs', $policy_array, '', '', $policy['policy_graphs'], '', ''); ?>
			</td>
			<td>
				<input type='submit' class='ui-button ui-corner-all ui-widget' name='update_policy' value='<?php print __esc('Update');?>'>
				<input type='hidden' name='tab' value='<?php print $tab;?>'>
				<input type='hidden' name='id' value='<?php print get_request_var('id');?>'>
				<input type="hidden" name='update_policy' value='1'>
			</td>
			</tr></table></td>
		</tr>
		<tr class='even'>
			<td><br><?php print $policy_note;?></td>
		</tr>
		<?php

		html_end_box();

		form_end();

		/* if the number of rows is -1, set it to the default */
		if (get_request_var('rows') == -1) {
			$rows = read_config_option('num_rows_table');
		} else {
			$rows = get_request_var('rows');
		}

		$sql_limit = 'LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;
		$sql_where = '';

		/* form the 'where' clause for our main sql query */
		if (get_request_var('filter') != '') {
			$sql_where .= 'WHERE (
				gtg.title_cache LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . '
				AND gtg.local_graph_id > 0)';
		} else {
			$sql_where .= 'WHERE (gtg.local_graph_id > 0)';
		}

		if (get_request_var('graph_template_id') == '-1') {
			/* Show all items */
		} elseif (get_request_var('graph_template_id') == '0') {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' gtg.graph_template_id=0';
		} elseif (!isempty_request_var('graph_template_id')) {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' gtg.graph_template_id=' . get_request_var('graph_template_id');
		}

		$policies = db_fetch_assoc_prepared("SELECT uag.id, 'group' AS type, uag.name,
			uag.policy_graphs, uag.policy_hosts, uag.policy_graph_templates
			FROM user_auth_group AS uag
			WHERE uag.enabled = 'on'
			AND uag.id = ?",
			array(get_request_var('id')));

		/**
		 * if viewing just the graphs that the user has access to
		 * we use a custom crafted sql_where clause to calculate
		 * permissions due to the inefficient nature of the HAVING
		 * SQL clause.
		 */
		if (get_request_var('associated') == 'false') {
			$sql_where = get_policy_where($graph_auth_method, $policies, $sql_where);
		}

		/**
		 * get the sql join and select to display the policy information
         * this includes the four graph permission types
		 */
		$details = get_policy_join_select($policies);

		if (cacti_sizeof($details)) {
			$sql_select = $details['sql_select'];
			$sql_join   = $details['sql_join'];
		} else {
			$sql_select = '';
			$sql_join   = '';
		}

		$graphs = db_fetch_assoc("SELECT gtg.local_graph_id, h.description,
			h.disabled, h.deleted, gt.name AS template_name,
			gtg.title_cache, gtg.width, gtg.height, gl.snmp_index, gl.snmp_query_id, $sql_select
			FROM graph_templates_graph AS gtg
			INNER JOIN graph_local AS gl
			ON gl.id = gtg.local_graph_id
			LEFT JOIN graph_templates AS gt
			ON gt.id = gl.graph_template_id
			LEFT JOIN host AS h
			ON h.id = gl.host_id
			$sql_join
			$sql_where
			ORDER BY gtg.title_cache
			$sql_limit");

		$total_rows = db_fetch_cell("SELECT COUNT(DISTINCT gl.id)
			FROM graph_templates_graph AS gtg
			INNER JOIN graph_local AS gl
			ON gl.id = gtg.local_graph_id
			LEFT JOIN graph_templates AS gt
			ON gt.id = gl.graph_template_id
			LEFT JOIN host AS h
			ON h.id = gl.host_id
			$sql_where");

		$nav = html_nav_bar('user_group_admin.php?action=edit&tab=permsg&id=' . get_request_var('id'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 7, __('Graphs'), 'page', 'main');

		form_start('user_group_admin.php?tab=permsg&id=' . get_request_var('id'), 'chk');

		print $nav;

		html_start_box('', '100%', '', '3', 'center', '');

		$display_text = array( __('Graph Title'), __('ID'), __('Effective Policy'));

		html_header_checkbox($display_text, false);

		if (cacti_sizeof($graphs)) {
			foreach ($graphs as $g) {
				form_alternate_row('line' . $g['local_graph_id'], true);
				form_selectable_cell(filter_value($g['title_cache'], get_request_var('filter')), $g['local_graph_id']);
				form_selectable_cell($g['local_graph_id'], $g['local_graph_id']);
				form_selectable_cell(get_permission_string($g, $policies), $g['local_graph_id']);
				form_checkbox_cell($g['title_cache'], $g['local_graph_id']);
				form_end_row();
			}
		} else {
			print '<tr><td colspan="' . (cacti_sizeof($display_text)+1) . '"><em>' . __('No Matching Graphs Found') . '</em></td></tr>';
		}

		html_end_box(false);

		if (cacti_sizeof($graphs)) {
			print $nav;
		}

		form_hidden_box('tab',$tab,'');
		form_hidden_box('id', get_request_var('id'), '');
		form_hidden_box('associate_graph', '1', '');

		if ($policy['policy_graphs'] == 1) {
			$assoc_actions = array(
				1 => __('Revoke Access'),
				2 => __('Grant Access')
			);
		} else {
			$assoc_actions = array(
				1 => __('Grant Access'),
				2 => __('Revoke Access')
			);
		}

		?>
		<script type='text/javascript'>
		$(function() {
			$(document).tooltip({
				items: '[data-tooltip]',
				content: function() {
					return $(this).attr('data-tooltip');
				}
			});
		});
		</script>
		<?php

		/* draw the dropdown containing a list of available actions for this form */
		draw_actions_dropdown($assoc_actions);

		form_end();

		break;
	case 'permsd':
		process_device_request_vars();

		device_filter($header_label);

		form_start('user_group_admin.php', 'policy');

		/* box: device permissions */
		html_start_box( __('Default Device Policy'), '100%', '', '3', 'center', '');

		?>
		<tr class='even'>
			<td><table><tr>
			<td class='nowrap'><?php print __('Default Graph Policy for this User Group');?></td>
			<td>
				<?php form_dropdown('policy_hosts',$policy_array,'','',$policy['policy_hosts'],'',''); ?>
			</td>
			<td>
				<input type='submit' class='ui-button ui-corner-all ui-widget' name='update_policy' value='<?php print __esc('Update');?>'>
				<input type='hidden' name='tab' value='<?php print $tab;?>'>
				<input type='hidden' name='id' value='<?php print get_request_var('id');?>'>
				<input type="hidden" name='update_policy' value='1'>
			</td>
			</tr></table></td>
		</tr>
		<?php

		html_end_box();

		form_end();

		/* if the number of rows is -1, set it to the default */
		if (get_request_var('rows') == -1) {
			$rows = read_config_option('num_rows_table');
		} else {
			$rows = get_request_var('rows');
		}

		/* form the 'where' clause for our main sql query */
		/* form the 'where' clause for our main sql query */
		if (get_request_var('filter') != '') {
			$sql_where = 'WHERE host.deleted = "" AND (
				host.hostname LIKE '       . db_qstr('%' . get_request_var('filter') . '%') . '
				OR host.description LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
		} else {
			$sql_where = "WHERE host.deleted = ''";
		}

		if (get_request_var('host_template_id') == '-1') {
			/* Show all items */
		} elseif (get_request_var('host_template_id') == '0') {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' host.host_template_id=0';
		} elseif (!isempty_request_var('host_template_id')) {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' host.host_template_id=' . get_request_var('host_template_id');
		}

		if (get_request_var('associated') != 'false') {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' user_auth_group_perms.group_id=' . get_request_var('id', 0);
		}

		$total_rows = db_fetch_cell("SELECT
			COUNT(DISTINCT host.id)
			FROM host
			LEFT JOIN user_auth_group_perms
			ON (host.id = user_auth_group_perms.item_id AND user_auth_group_perms.type = 3 AND user_auth_group_perms.group_id = " . get_request_var('id') . ")
			$sql_where");

		$host_graphs       = array_rekey(db_fetch_assoc('SELECT host_id, count(*) AS graphs FROM graph_local GROUP BY host_id'), 'host_id', 'graphs');
		$host_data_sources = array_rekey(db_fetch_assoc('SELECT host_id, count(*) AS data_sources FROM data_local GROUP BY host_id'), 'host_id', 'data_sources');

		$sql_query = "SELECT host.*, user_auth_group_perms.group_id
			FROM host
			LEFT JOIN user_auth_group_perms
			ON (host.id=user_auth_group_perms.item_id AND user_auth_group_perms.type = 3 AND user_auth_group_perms.group_id = " . get_request_var('id') . ")
			$sql_where
			ORDER BY description
			LIMIT " . ($rows*(get_request_var('page')-1)) . ',' . $rows;

		$hosts = db_fetch_assoc($sql_query);

		$nav = html_nav_bar('user_group_admin.php?action=edit&tab=permsd&id=' . get_request_var('id'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, __('Devices'), 'page', 'main');

		form_start('user_group_admin.php?tab=permsd&id=' . get_request_var('id'), 'chk');

		print $nav;

		html_start_box('', '100%', '', '3', 'center', '');

		$display_text = array( __('Description'), __('ID'), __('Effective Policy'), __('Graphs'), __('Data Sources'), __('Status'), __('Hostname') );

		html_header_checkbox($display_text, false);

		if (cacti_sizeof($hosts)) {
			foreach ($hosts as $host) {
				form_alternate_row('line' . $host['id'], true);
				form_selectable_cell(filter_value($host['description'], get_request_var('filter')), $host['id']);
				form_selectable_cell($host['id'], $host['id']);
				if (empty($host['group_id']) || $host['group_id'] == NULL) {
					if ($policy['policy_hosts'] == 1) {
						form_selectable_cell('<span class="accessGranted">' . __('Access Granted') . '</span>', $host['id']);
					} else {
						form_selectable_cell('<span class="accessRestricted">' . __('Access Restricted') . '</span>', $host['id']);
					}
				} else {
					if ($policy['policy_hosts'] == 1) {
						form_selectable_cell('<span class="accessRestricted">' . __('Access Restricted') . '</span>', $host['id']);
					} else {
						form_selectable_cell('<span class="accessGranted">' . __('Access Granted') . '</span>', $host['id']);
					}
				}
				form_selectable_cell((isset($host_graphs[$host['id']]) ? $host_graphs[$host['id']] : 0), $host['id']);
				form_selectable_cell((isset($host_data_sources[$host['id']]) ? $host_data_sources[$host['id']] : 0), $host['id']);
				form_selectable_cell(get_colored_device_status(($host['disabled'] == 'on' ? true : false), $host['status']), $host['id']);
				form_selectable_cell(filter_value($host['hostname'], get_request_var('filter')), $host['id']);
				form_checkbox_cell($host['description'], $host['id']);
				form_end_row();
			}
		} else {
			print '<tr><td colspan="' . (cacti_sizeof($display_text)+1) . '"><em>' . __('No Matching Devices Found') . '</em></td></tr>';
		}
		html_end_box(false);

		if (cacti_sizeof($hosts)) {
			print $nav;
		}

		form_hidden_box('tab',$tab,'');
		form_hidden_box('id', get_request_var('id'), '');
		form_hidden_box('associate_host', '1', '');

		if ($policy['policy_hosts'] == 1) {
			$assoc_actions = array(
				1 => __('Revoke Access'),
				2 => __('Grant Access')
			);
		} else {
			$assoc_actions = array(
				1 => __('Grant Access'),
				2 => __('Revoke Access')
			);
		}

		/* draw the dropdown containing a list of available actions for this form */
		draw_actions_dropdown($assoc_actions);

		form_end();

		break;
	case 'permste':
		process_template_request_vars();

		template_filter($header_label);

		form_start('user_group_admin.php', 'policy');

		/* box: device permissions */
		html_start_box( __('Default Graph Template Policy'), '100%', '', '3', 'center', '');

		?>
		<tr class='even'>
			<td><table><tr>
			<td class='nowrap'><?php print __('Default Graph Template Policy for this User Group');?></td>
			<td>
				<?php form_dropdown('policy_graph_templates',$policy_array,'','',$policy['policy_graph_templates'],'',''); ?>
			</td>
			<td>
				<input type='submit' class='ui-button ui-corner-all ui-widget' name='update_policy' value='<?php print __esc('Update');?>'>
				<input type='hidden' name='tab' value='<?php print $tab;?>'>
				<input type='hidden' name='id' value='<?php print get_request_var('id');?>'>
				<input type="hidden" name='update_policy' value='1'>
			</td>
			</tr></table></td>
		</tr>
		<?php

		html_end_box();

		form_end();

		/* if the number of rows is -1, set it to the default */
		if (get_request_var('rows') == -1) {
			$rows = read_config_option('num_rows_table');
		} else {
			$rows = get_request_var('rows');
		}

		/* form the 'where' clause for our main sql query */
		/* form the 'where' clause for our main sql query */
		if (get_request_var('filter') != '') {
			$sql_where = 'WHERE gt.name LIKE ' . db_qstr('%' . get_request_var('filter') . '%');
		} else {
			$sql_where = '';
		}

		if (get_request_var('associated') != 'false') {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' (user_auth_group_perms.type = 4 AND user_auth_group_perms.group_id=' . get_request_var('id', 0) . ')';
		}

		$total_rows = db_fetch_cell_prepared("SELECT
			COUNT(DISTINCT gt.id)
			FROM graph_templates AS gt
			LEFT JOIN graph_local AS gl
			ON gt.id = gl.graph_template_id
			LEFT JOIN user_auth_group_perms
			ON gt.id = user_auth_group_perms.item_id
			AND user_auth_group_perms.type = 4
			AND user_auth_group_perms.group_id = ?
			$sql_where",
			array(get_request_var('id')));

		$sql_query = "SELECT gt.id, gt.name, COUNT(DISTINCT gl.id) AS totals, user_auth_group_perms.group_id
			FROM graph_templates AS gt
			LEFT JOIN graph_local AS gl
			ON gt.id = gl.graph_template_id
			LEFT JOIN user_auth_group_perms
			ON gt.id = user_auth_group_perms.item_id
			AND user_auth_group_perms.type = 4
			AND user_auth_group_perms.group_id = ?
			$sql_where
			GROUP BY gt.id
			ORDER BY name
			LIMIT " . ($rows*(get_request_var('page')-1)) . ',' . $rows;

		$graphs = db_fetch_assoc_prepared($sql_query, array(get_request_var('id')));

		$nav = html_nav_bar('user_group_admin.php?action=edit&tab=permste&id=' . get_request_var('id'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, __('Graph Templates'), 'page', 'main');

		form_start('user_group_admin.php?tab=permste&id=' . get_request_var('id'), 'chk');

		print $nav;

		html_start_box('', '100%', '', '3', 'center', '');

		$display_text = array( __('Template Name'), __('ID'), __('Effective Policy'), __('Total Graphs'));

		html_header_checkbox($display_text, false);

		if (cacti_sizeof($graphs)) {
			foreach ($graphs as $g) {
				form_alternate_row('line' . $g['id'], true);
				form_selectable_cell(filter_value($g['name'], get_request_var('filter')), $g['id']);
				form_selectable_cell($g['id'], $g['id']);
				if (empty($g['group_id']) || $g['group_id'] == NULL) {
					if ($policy['policy_graph_templates'] == 1) {
						form_selectable_cell('<span class="accessGranted">' . __('Access Granted') . '</span>', $g['id']);
					} else {
						form_selectable_cell('<span class="accessRestricted">' . __('Access Restricted') . '</span>', $g['id']);
					}
				} else {
					if ($policy['policy_graph_templates'] == 1) {
						form_selectable_cell('<span class="accessRestricted">' . __('Access Restricted') . '</span>', $g['id']);
					} else {
						form_selectable_cell('<span class="accessGranted">' . __('Access Granted') . '</span>', $g['id']);
					}
				}
				form_selectable_cell($g['totals'], $g['id']);
				form_checkbox_cell($g['name'], $g['id']);
				form_end_row();
			}
		} else {
			print '<tr><td colspan="' . (cacti_sizeof($display_text)+1) . '"><em>' . __('No Matching Graph Templates Found') . '</em></td></tr>';
		}

		html_end_box(false);

		if (cacti_sizeof($graphs)) {
			print $nav;
		}

		form_hidden_box('tab',$tab,'');
		form_hidden_box('id', get_request_var('id'), '');
		form_hidden_box('associate_template', '1', '');

		if ($policy['policy_graph_templates'] == 1) {
			$assoc_actions = array(
				1 => __('Revoke Access'),
				2 => __('Grant Access')
			);
		} else {
			$assoc_actions = array(
				1 => __('Grant Access'),
				2 => __('Revoke Access')
			);
		}

		/* draw the dropdown containing a list of available actions for this form */
		draw_actions_dropdown($assoc_actions);

		form_end();

		break;
	case 'permstr':
		process_tree_request_vars();

		tree_filter($header_label);

		form_start('user_group_admin.php', 'policy');

		/* box: device permissions */
		html_start_box( __('Default Tree Policy'), '100%', '', '3', 'center', '');

		?>
		<tr class='even'>
			<td><table><tr>
			<td class='nowrap'><?php print __('Default Tree Policy for this User Group');?></td>
			<td>
				<?php form_dropdown('policy_trees',$policy_array,'','',$policy['policy_trees'],'',''); ?>
			</td>
			<td>
				<input type='submit' class='ui-button ui-corner-all ui-widget' name='update_policy' value='<?php print __esc('Update');?>'>
				<input type='hidden' name='tab' value='<?php print $tab;?>'>
				<input type='hidden' name='id' value='<?php print get_request_var('id');?>'>
				<input type='hidden' name='update_policy' value='1'>
			</td>
			</tr></table></td>
		</tr>
		<?php

		html_end_box();

		form_end();

		/* if the number of rows is -1, set it to the default */
		if (get_request_var('rows') == -1) {
			$rows = read_config_option('num_rows_table');
		} else {
			$rows = get_request_var('rows');
		}

		/* form the 'where' clause for our main sql query */
		if (get_request_var('filter') != '') {
			$sql_where = 'WHERE gt.name LIKE ' . db_qstr('%' . get_request_var('filter') . '%');
		} else {
			$sql_where = '';
		}

		if (get_request_var('associated') != 'false') {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' (user_auth_group_perms.type = 2 AND user_auth_group_perms.group_id=' . get_request_var('id', 0) . ')';
		}

		$total_rows = db_fetch_cell("SELECT
			COUNT(DISTINCT gt.id)
			FROM graph_tree AS gt
			LEFT JOIN user_auth_group_perms
			ON (gt.id = user_auth_group_perms.item_id AND user_auth_group_perms.type = 2 AND user_auth_group_perms.group_id = " . get_request_var('id') . ")
			$sql_where");

		$sql_query = "SELECT gt.id, gt.name, user_auth_group_perms.group_id
			FROM graph_tree AS gt
			LEFT JOIN user_auth_group_perms
			ON (gt.id = user_auth_group_perms.item_id AND user_auth_group_perms.type = 2 AND user_auth_group_perms.group_id = " . get_request_var('id') . ")
			$sql_where
			ORDER BY name
			LIMIT " . ($rows*(get_request_var('page')-1)) . ',' . $rows;

		$trees = db_fetch_assoc($sql_query);

		$nav = html_nav_bar('user_group_admin.php?action=edit&tab=permstr&id=' . get_request_var('id'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, __('Trees'), 'page', 'main');

		form_start('user_group_admin.php?tab=permstr&id=' . get_request_var('id'), 'chk');

		print $nav;

		html_start_box('', '100%', '', '3', 'center', '');

		$display_text = array( __('Tree Name'), __('ID'), __('Effective Policy'));

		html_header_checkbox($display_text, false);

		if (cacti_sizeof($trees)) {
			foreach ($trees as $t) {
				form_alternate_row('line' . $t['id'], true);
				form_selectable_cell(filter_value($t['name'], get_request_var('filter')), $t['id']);
				form_selectable_cell($t['id'], $t['id']);
				if (empty($t['group_id']) || $t['group_id'] == NULL) {
					if ($policy['policy_trees'] == 1) {
						form_selectable_cell('<span class="accessGranted">' . __('Access Granted') . '</span>', $t['id']);
					} else {
						form_selectable_cell('<span class="accessRestricted">' . __('Access Restricted') . '</span>', $t['id']);
					}
				} else {
					if ($policy['policy_trees'] == 1) {
						form_selectable_cell('<span class="accessRestricted">' . __('Access Restricted') . '</span>', $t['id']);
					} else {
						form_selectable_cell('<span class="accessGranted">' . __('Access Granted') . '</span>', $t['id']);
					}
				}
				form_checkbox_cell($t['name'], $t['id']);
				form_end_row();
			}
		} else {
			print '<tr><td colspan="' . (cacti_sizeof($display_text)+1) . '"><em>' . __('No Matching Trees Found') . '</em></td></tr>';
		}
		html_end_box(false);

		if (cacti_sizeof($trees)) {
			print $nav;
		}

		form_hidden_box('tab',$tab,'');
		form_hidden_box('id', get_request_var('id'), '');
		form_hidden_box('associate_tree', '1', '');

		if ($policy['policy_trees'] == 1) {
			$assoc_actions = array(
				1 => __('Revoke Access'),
				2 => __('Grant Access')
			);
		} else {
			$assoc_actions = array(
				1 => __('Grant Access'),
				2 => __('Revoke Access')
			);
		}

		/* draw the dropdown containing a list of available actions for this form */
		draw_actions_dropdown($assoc_actions);

		form_end();

		break;
	}
}

function user_group_is_member($user_id, $group_id) {
	return db_fetch_cell_prepared('SELECT COUNT(*)
		FROM user_auth_group_members
		WHERE user_id = ?
		AND group_id = ?',
		array($user_id, $group_id));
}

function user_group_realms_edit($header_label) {
	global $user_auth_realms, $user_auth_roles;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	print "<div class='cactiTable' style='width:100%;text-align:left;'>
		<div>
			<div class='cactiTableTitle'><span style='padding:3px;'>" . __('User Permissions') . ' ' . html_escape($header_label) . "</span></div>
			<div class='cactiTableButton'><span style='padding:3px;'><input class='checkbox' type='checkbox' id='all' name='all' title='" . __esc('Select All') . "' onClick='selectAllRealms(this.checked)'><label class='formCheckboxLabel' title='" . __esc('Select All') . "' for='all'></label></a></span></div>
		</div>
	</div>";

	form_start('user_group_admin.php', 'chk');

	$all_realms = $user_auth_realms;

	html_start_box('', '100%', '', '3', 'center', '');

	/* do cacti realms first */
	foreach($user_auth_roles as $role_name => $perms) {
        print "<tr class='tableHeader'><th colspan='2'>" . html_escape($role_name) . "</th></tr>";
        print "<tr class='odd'><td colspan='2'><div class='flexContainer'>";

		foreach($perms as $realm) {
			if (isset($user_auth_realms[$realm])) {
				$set = db_fetch_cell_prepared('SELECT realm_id
					FROM user_auth_group_realm
					WHERE group_id = ?
					AND realm_id = ?',
					array(get_request_var('id', 0), $realm));

				if ($set) {
					$old_value = 'on';
				} else {
					$old_value = '';
				}

				if ($realm != 101) {
					$display = trim(str_replace(array('Plugin ->', 'Plugin ', 'Configure '), '', $user_auth_realms[$realm]));
				} else {
					$display = trim($user_auth_realms[$realm]);
				}
				$display = trim(str_replace(array('View ', 'Management'), array('', 'Administration'), $display));

				unset($all_realms[$realm]);

				print '<div class="flexChild">';
				form_checkbox('section' . $realm, $old_value, $display, '', '', '', (!isempty_request_var('id') ? 1 : 0), $display, true);
				print '</div>';
			}
		}

		print '</div></td></tr>';
	}

	/* external links */
	$links  = db_fetch_assoc('SELECT * FROM external_links ORDER BY sortorder');

	$style_translate = array(
		'CONSOLE'    => __('Console'),
		'TAB'        => __('Top Tab'),
		'FRONT'      => __('Bottom Console'),
		'FRONTTOP'   => __('Top Console')
	);

	if (cacti_sizeof($links)) {
		print "<tr class='tableHeader'><th colspan='2'>" . __('External Link Permissions') . "</th></tr>";
		print "<tr class='odd'><td class='left' colspan='2'><div class='flexContainer'>";

		foreach($links as $r) {
			$realm = $r['id'] + 10000;

			$set = db_fetch_cell_prepared('SELECT realm_id
				FROM user_auth_group_realm
				WHERE group_id = ?
				AND realm_id = ?',
				array(get_request_var('id', 0), $realm));

			if ($set) {
				$old_value = 'on';
			} else {
				$old_value = '';
			}

			unset($all_realms[$realm]);

			switch($r['style']) {
			case 'CONSOLE':
				$description = $style_translate[$r['style']] . ': ' . ($r['extendedstyle'] == '' ? __('External Links') : $r['extendedstyle']) . '/' . $r['title'];
				break;
			default:
				$description = $style_translate[$r['style']] . ': ' . ucfirst($r['title']);
				break;
			}

			print '<div class="flexChild">';
			form_checkbox('section' . $realm, $old_value, $description, '', '', '', (!isempty_request_var('id') ? 1 : 0), $description, true);
			print '</div>';
		}

		print '</div></td></tr>';
	}

	/* do plugin realms */
	$realms = db_fetch_assoc('SELECT pc.name, pr.id AS realm_id, pr.display
		FROM plugin_config AS pc
		INNER JOIN plugin_realms AS pr
		ON pc.directory = pr.plugin
		ORDER BY pc.name, pr.display');

	$i = 0;

	if (cacti_sizeof($realms)) {
		foreach($realms as $r) {
			$realm = $r['realm_id'] + 100;

			// Skip already set realms
			foreach($user_auth_roles as $role => $rrealms) {
				foreach($rrealms as $realm_id) {
					if ($realm == $realm_id) {
						unset($all_realms[$realm]);
						continue 3;
					}
				}
			}

			$set = db_fetch_cell_prepared('SELECT realm_id
				FROM user_auth_group_realm
				WHERE group_id = ?
				AND realm_id = ?',
				array(get_request_var('id', 0), $realm));

			if ($set) {
				$old_value = 'on';
			} else {
				$old_value = '';
			}

			unset($all_realms[$realm]);

			$pos = (strpos($user_auth_realms[$realm], '->') !== false ? strpos($user_auth_realms[$realm], '->')+2:0);

			if ($i == 0) {
				print "<tr class='tableHeader'><th colspan='2'>" . __('Plugin Permissions') . "</th></tr>";
				print "<tr class='odd'><td colspan='2'><div class='flexContainer'>";
				$i++;
			}

			print '<div class="flexChild">';
			form_checkbox('section' . $realm, $old_value, substr($user_auth_realms[$realm], $pos), '', '', '', (!isempty_request_var('id') ? 1 : 0), $r['display'], true);
			print '</div>';
		}

		if ($i > 0) {
			print '</div></td></tr>';
		}
	}

	/* get the old PIA 1.x realms */
	if (cacti_sizeof($all_realms)) {
		print "<tr class='tableHeader'><th class='left' colspan='2'>" . __('Legacy Permissions') . "</th></tr>";
		print "<tr class='odd'><td colspan='2'><div class='flexContainer'>";

		foreach($all_realms as $realm => $name) {
			$set = db_fetch_cell_prepared('SELECT realm_id
				FROM user_auth_group_realm
				WHERE group_id = ? AND
				realm_id = ?',
				array(get_request_var('id', 0), $realm));

			if ($set) {
				$old_value = 'on';
			} else {
				$old_value = '';
			}

			$pos = (strpos($user_auth_realms[$realm], '->') !== false ? strpos($user_auth_realms[$realm], '->')+2:0);

			print '<div class="flexChild">';
			form_checkbox('section' . $realm, $old_value, substr($user_auth_realms[$realm], $pos), '', '', '', (!isempty_request_var('id') ? 1 : 0), $r['display'], true);
			print '</div>';

		}

		print '</div></td></tr>';
	}

	print '</table></td></tr>';
	?>
	<script type='text/javascript'>
	function selectAllRealms(checked) {
		if (checked) {
			$('input[id^=\"section\"]').prop('checked', true);
		} else {
			$('input[id^=\"section\"]').prop('checked', false);
		}
	}

	$(function() {
		$('input[type="checkbox"]').each(function() {
			$(this).addClass($(this).attr('id'));
		});
	});
	</script>
	<?php

	html_end_box();

	form_hidden_box('save_component_realm_perms','1','');
	form_hidden_box('tab','realms','');
	form_hidden_box('id',get_request_var('id'),'');

	form_save_button('user_group_admin.php', 'return');
}

function user_group_settings_edit($header_label) {
	global $settings_user, $tabs_graphs, $graph_views;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	form_start('user_group_admin.php', 'chk');

	html_start_box(__esc('User Settings %s', $header_label), '100%', true, '3', 'center', '');

	foreach ($settings_user as $tab_short_name => $tab_fields) {
		$collapsible = true;

		print "<div class='spacer formHeader" . ($collapsible ? ' collapsible':'') . "' id='row_$tab_short_name'><div style='cursor:pointer;' class='tableSubHeaderColumn'>" . $tabs_graphs[$tab_short_name] . ($collapsible ? "<div style='float:right;padding-right:4px;'><i class='fa fa-angle-double-up'></i></div>":"") . "</div></div>";

		$form_array = array();

		foreach ($tab_fields as $field_name => $field_array) {
			$form_array += array($field_name => $tab_fields[$field_name]);

			if ((isset($field_array['items'])) && (is_array($field_array['items']))) {
				foreach ($field_array['items'] as $sub_field_name => $sub_field_array) {
					if (graph_config_value_exists($sub_field_name, get_request_var('id'))) {
						$form_array[$field_name]['items'][$sub_field_name]['form_id'] = 1;
					}

					$form_array[$field_name]['items'][$sub_field_name]['value'] =  db_fetch_cell_prepared('SELECT value FROM settings_user_group WHERE name = ? AND group_id = ?', array($sub_field_name, get_request_var('id')));
				}
			} else {
				if (graph_config_value_exists($field_name, get_request_var('id'))) {
					$form_array[$field_name]['form_id'] = 1;
				}

				$form_array[$field_name]['value'] = db_fetch_cell_prepared('SELECT value FROM settings_user_group WHERE name = ? AND group_id = ?', array($field_name, get_request_var('id')));
			}
		}

		draw_edit_form(
			array(
				'config' => array('no_form_tag' => true),
				'fields' => $form_array
			)
		);
	}

	html_end_box(true, true);

	form_hidden_box('save_component_graph_settings','1','');
	form_hidden_box('tab','settings','');
	form_hidden_box('id',get_request_var('id'),'');

	form_save_button('user_group_admin.php', 'return');

	?>
	<script type='text/javascript'>

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
		} else {
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

	</script>
	<?php
}

/* --------------------------
    User Administration
   -------------------------- */

function group_edit() {
	global $config, $fields_user_group_edit;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('tab', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-z_A-Z]+)$/')));
	/* ==================================================== */

	/* present a tabbed interface */
	$tabs = array(
		'general'  => __('General'),
		'members'  => __('Members'),
		'realms'   => __('Permissions'),
		'permsg'   => __('Graph Perms'),
		'permsd'   => __('Device Perms'),
		'permste'  => __('Template Perms'),
		'permstr'  => __('Tree Perms'),
		'settings' => __('User Settings')
	);

	$permission_model = read_config_option('graph_auth_method');

	if ($permission_model == 3) { // Device Based
		unset($tabs['permste']);
	} elseif ($permission_model == 4) { // Graph Template Based
		unset($tabs['permsd']);
	}

	if (!isempty_request_var('id')) {
		$group = db_fetch_row_prepared('SELECT * FROM user_auth_group WHERE id = ?', array(get_request_var('id')));
		$header_label = __esc('User Group Management [edit: %s]', $group['name']);
	} else {
		$header_label = __('User Group Management [new]');
	}

    /* set the default tab */
    load_current_session_value('tab', 'sess_ugroup_tab', 'general');
    $current_tab = get_nfilter_request_var('tab');

	if (cacti_sizeof($tabs) && !isempty_request_var('id')) {
		/* draw the tabs */
		print "<div class='tabs'><nav><ul role='tablist'>";

		foreach (array_keys($tabs) as $tab_short_name) {
			print "<li class='subTab'><a class='tab" . (($tab_short_name == $current_tab) ? " selected'" : "'") .
				" href='" . html_escape($config['url_path'] .
				'user_group_admin.php?action=edit&id=' . get_request_var('id') .
				'&tab=' . $tab_short_name) .
				"'>" . $tabs[$tab_short_name] . "</a></li>";
		}

		api_plugin_hook('user_group_admin_tab');

		print "</ul></nav></div>";
	}

	switch(get_request_var('tab')) {
	case 'general':
		api_plugin_hook_function('user_group_admin_edit', (isset($group) ? get_request_var('id') : 0));

		form_start('user_group_admin.php');

		html_start_box($header_label, '100%', true, '3', 'center', '');

		draw_edit_form(array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($fields_user_group_edit, (isset($group) ? $group : array()))
		));

		html_end_box(true, true);

		form_save_button('user_group_admin.php', 'return');

		?>
		<script type='text/javascript'>
		var consoleAllowed=<?php print is_user_group_realm_allowed(8, (isset($group) ? $group['id'] : 0)) ? 'true':'false';?>;

		$(function() {
			if (!consoleAllowed) {
				if ($('#login_opts_2').is(':checked')) {
					$('#login_opts_2').prop('checked', false);
					$('#login_opts_3').prop('checked', true);
				}

				$('#login_opts_2').prop('disabled', true);
			}
		});
		</script>
		<?php

		break;
	case 'settings':
		if (isempty_request_var('id')) {
			header('Location: user_group_admin.php&header=false');
		}

		user_group_settings_edit($header_label);

		break;
	case 'realms':
		if (isempty_request_var('id')) {
			header('Location: user_group_admin.php&header=false');
		}

		user_group_realms_edit($header_label);

		break;
	case 'permsg':
	case 'permsd':
	case 'permste':
	case 'permstr':
		if (isempty_request_var('id')) {
			header('Location: user_group_admin.php&header=false');
		}

		user_group_graph_perms_edit(get_request_var('tab'), $header_label);

		break;
	case 'members':
		if (isempty_request_var('id')) {
			header('Location: user_group_admin.php&header=false');
		}

		user_group_members_edit($header_label);

		break;
	default:
		if (api_plugin_hook_function('user_group_admin_run_action', get_request_var('tab'))) {
			user_group_realms_edit($header_label);
		}
		break;
	}
}

function is_user_group_realm_allowed($realm_id, $group_id) {
	return db_fetch_cell_prepared('SELECT COUNT(*)
		FROM user_auth_group_realm
		WHERE group_id = ?
		AND realm_id = ?',
		array($group_id, $realm_id));
}

function user_group() {
	global $group_actions, $item_rows;

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
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
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

	validate_store_request_vars($filters, 'sess_ugad');
	/* ================= input validation ================= */

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL  = 'user_group_admin.php'
		strURL += '?rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'user_group_admin.php?clear=1';
		strURL = strURL + '&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#associated').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#rows').change(function() {
			applyFilter();
		});

		$('#forms').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php

	html_start_box( __('User Group Management'), '100%', '', '3', 'center', 'user_group_admin.php?action=edit&tab=general');

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	?>
	<tr class='even'>
		<td>
		<form id='forms' action='user_group_admin.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Groups');?>
					</td>
					<td>
						<select id='rows'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . "</option>";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='go' value='<?php print __x('filter: use', 'Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __x('filter: reset', 'Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = 'WHERE (
			name LIKE '           . db_qstr('%' . get_request_var('filter') . '%') . '
			OR description LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

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
		ORDER BY " . get_request_var('sort_column') . ' ' . get_request_var('sort_direction') .
		' LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows);

	$nav = html_nav_bar('user_group_admin.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 8, __('Groups'), 'page', 'main');

	form_start('user_group_admin.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'name'                   => array(__('Group Name'), 'ASC'),
		'members'                => array(__('Members'), 'ASC'),
		'description'            => array(__('Description'), 'ASC'),
		'policy_graphs'          => array(__('Graph Policy'), 'ASC'),
		'policy_hosts'           => array(__('Device Policy'), 'ASC'),
		'policy_graph_templates' => array(__('Template Policy'), 'ASC'),
		'enabled'                => array(__('Enabled'), 'ASC')
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (cacti_sizeof($group_list)) {
		foreach ($group_list as $group) {
			if ($group['enabled'] == 'on') {
				$enabled = __('Yes');
			} else {
				$enabled = __('No');
			}

			form_alternate_row('line' . $group['id'], true);
			form_selectable_cell(filter_value($group['name'], get_request_var('filter'), 'user_group_admin.php?action=edit&tab=general&id=' . $group['id']), $group['id']);
			form_selectable_cell(($group['members'] > 0 ? number_format_i18n($group['members'], 0):'None'), $group['id']);
			form_selectable_cell(filter_value($group['description'], get_request_var('filter')), $group['id']);
			form_selectable_cell(($group['policy_graphs'] == 1 ? __('ALLOW') : __('DENY') ), $group['id']);
			form_selectable_cell(($group['policy_hosts'] == 1 ? __('ALLOW') : __('DENY') ), $group['id']);
			form_selectable_cell(($group['policy_graph_templates'] == 1 ? __('ALLOW') : __('DENY') ), $group['id']);
			form_selectable_cell($enabled, $group['id']);
			form_checkbox_cell($group['name'], $group['id']);
			form_end_row();
		}
	} else {
		print '<tr><td colspan="' . (cacti_sizeof($display_text)+1) . '"><em>' . __('No User Groups Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($group_list)) {
		print $nav;
	}

	draw_actions_dropdown($group_actions);

	form_end();
}

function process_graph_request_vars() {
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
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'associated' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => 'true'
			),
		'graph_template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			)
	);

	validate_store_request_vars($filters, 'sess_ugg');
	/* ================= input validation ================= */
}

function process_device_request_vars() {
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
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'associated' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => 'true'
			),
		'host_template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			)
	);

	validate_store_request_vars($filters, 'sess_ugd');
	/* ================= input validation ================= */
}

function process_template_request_vars() {
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
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'associated' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => 'true'
			),
		'host_template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			)
	);

	validate_store_request_vars($filters, 'sess_ugte');
	/* ================= input validation ================= */
}

function process_tree_request_vars() {
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
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'associated' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => 'true'
			)
	);

	validate_store_request_vars($filters, 'sess_ugtr');
	/* ================= input validation ================= */
}

function process_member_request_vars() {
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
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'associated' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => 'true'
			)
	);

	validate_store_request_vars($filters, 'sess_ugm');
	/* ================= input validation ================= */
}

function graph_filter($header_label) {
	global $config, $item_rows;

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL  = 'user_group_admin.php?action=edit&tab=permsg&id=<?php print get_request_var('id');?>'
		strURL += '&rows=' + $('#rows').val();
		strURL += '&graph_template_id=' + $('#graph_template_id').val();
		strURL += '&associated=' + $('#associated').is(':checked');
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'user_group_admin.php?action=edit&tab=permsg&id=<?php print get_request_var('id');?>&clear=true'
		strURL = strURL + '&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#associated').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#rows, #graph_template_id').change(function() {
			applyFilter();
		});

		$('#forms').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php

	html_start_box(__('Graph Permissions %s', $header_label), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='forms' action='user_group_admin.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Template');?>
					</td>
					<td>
						<select id='graph_template_id'>
							<option value='-1'<?php if (get_request_var('graph_template_id') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<option value='0'<?php if (get_request_var('graph_template_id') == '0') {?> selected<?php }?>><?php print __('None');?></option>
							<?php
							$graph_templates = db_fetch_assoc('SELECT DISTINCT gt.id, gt.name
								FROM graph_templates AS gt
								INNER JOIN graph_local AS gl
								ON gl.graph_template_id = gt.id
								ORDER BY name');

							if (cacti_sizeof($graph_templates)) {
								foreach ($graph_templates as $gt) {
									print "<option value='" . $gt['id'] . "'"; if (get_request_var('graph_template_id') == $gt['id']) { print ' selected'; } print '>' . html_escape($gt['name']) . "</option>";
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Graphs');?>
					</td>
					<td>
						<select id='rows'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . "</option>";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' id='associated' <?php print (get_request_var('associated') == 'true' || get_request_var('associated') == 'on' ? 'checked':'');?>>
							<label for='associated'><?php print __('Only Show Exceptions');?></label>
						</span>
					</td>
					<td>
						<span>
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='go' value='<?php print __x('filter: use','Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __x('filter: reset','Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
			<input type='hidden' name='action' value='edit'>
			<input type='hidden' name='tab' value='permsg'>
			<input type='hidden' name='id' value='<?php print get_request_var('id');?>'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();
}

function device_filter($header_label) {
	global $config, $item_rows;

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL  = 'user_group_admin.php?action=edit&tab=permsd&id=<?php print get_request_var('id');?>'
		strURL += '&rows=' + $('#rows').val();
		strURL += '&host_template_id=' + $('#host_template_id').val();
		strURL += '&associated=' + $('#associated').is(':checked');
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'user_group_admin.php?action=edit&tab=permsd&id=<?php print get_request_var('id');?>&clear=true'
		strURL = strURL + '&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#associated').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#rows, #host_template_id').change(function() {
			applyFilter();
		});

		$('#forms').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php

	html_start_box(__('Devices Permission %s', $header_label), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='forms' action='user_group_admin.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Template');?>
					</td>
					<td>
						<select id='host_template_id'>
							<option value='-1'<?php if (get_request_var('host_template_id') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<option value='0'<?php if (get_request_var('host_template_id') == '0') {?> selected<?php }?>><?php print __('None');?></option>
							<?php
							$host_templates = db_fetch_assoc('SELECT id, name FROM host_template ORDER BY name');

							if (cacti_sizeof($host_templates) > 0) {
								foreach ($host_templates as $host_template) {
									print "<option value='" . $host_template['id'] . "'"; if (get_request_var('host_template_id') == $host_template['id']) { print ' selected'; } print '>' . html_escape($host_template['name']) . "</option>";
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Devices');?>
					</td>
					<td>
						<select id='rows'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . "</option>";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' id='associated' <?php print (get_request_var('associated') == 'true' || get_request_var('associated') == 'on' ? 'checked':'');?>>
							<label for='associated'><?php print __('Only Show Exceptions');?></label>
						</span>
					</td>
					<td>
						<span>
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='go' value='<?php print __x('filter: use', 'Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __x('filter: reset', 'Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
			<input type='hidden' name='action' value='edit'>
			<input type='hidden' name='tab' value='permsd'>
			<input type='hidden' name='id' value='<?php print get_request_var('id');?>'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();
}

function template_filter($header_label) {
	global $config, $item_rows;

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL  = 'user_group_admin.php?action=edit&tab=permste&id=<?php print get_request_var('id');?>'
		strURL += '&rows=' + $('#rows').val();
		strURL += '&associated=' + $('#associated').is(':checked');
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'user_group_admin.php?action=edit&tab=permste&id=<?php print get_request_var('id');?>&clear=true'
		strURL = strURL + '&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#associated').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#rows').change(function() {
			applyFilter();
		});

		$('#forms').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php

	html_start_box(__('Template Permission %s', $header_label), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='forms' action='user_group_admin.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Templates');?>
					</td>
					<td>
						<select id='rows'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . "</option>";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' id='associated' <?php print (get_request_var('associated') == 'true' || get_request_var('associated') == 'on' ? 'checked':'');?>>
							<label for='associated'><?php print __('Only Show Exceptions');?></label>
						</span>
					</td>
					<td>
						<span>
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='go' value='<?php print __x('filter: use', 'Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __x('filter: reset', 'Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
			<input type='hidden' name='action' value='edit'>
			<input type='hidden' name='tab' value='permste'>
			<input type='hidden' name='id' value='<?php print get_request_var('id');?>'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();
}

function tree_filter($header_label) {
	global $config, $item_rows;

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL  = 'user_group_admin.php?action=edit&tab=permstr&id=<?php print get_request_var('id');?>'
		strURL += '&rows=' + $('#rows').val();
		strURL += '&associated=' + $('#associated').is(':checked');
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'user_group_admin.php?action=edit&tab=permstr&id=<?php print get_request_var('id');?>&clear=true'
		strURL = strURL + '&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#associated').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#rows').change(function() {
			applyFilter();
		});

		$('#forms').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php

	html_start_box(__('Tree Permission %s', $header_label), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='forms' action='user_group_admin.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Trees');?>
					</td>
					<td>
						<select id='rows'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . "</option>";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' id='associated' <?php print (get_request_var('associated') == 'true' || get_request_var('associated') == 'on' ? 'checked':'');?>>
							<label for='associated'><?php print __('Only Show Exceptions');?></label>
						</span>
					</td>
					<td>
						<span>
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='go' value='<?php print __x('filter: use', 'Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __x('filter: reset', 'Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
			<input type='hidden' name='action' value='edit'>
			<input type='hidden' name='tab' value='permstr'>
			<input type='hidden' name='id' value='<?php print get_request_var('id');?>'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();
}

function member_filter($header_label) {
	global $config, $item_rows;

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL  = 'user_group_admin.php?action=edit&tab=members&id=<?php print get_request_var('id');?>'
		strURL += '&rows=' + $('#rows').val();
		strURL += '&associated=' + $('#associated').is(':checked');
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL  = 'user_group_admin.php?action=edit&tab=members&id=<?php print get_request_var('id');?>&clear=true'
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#associated').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#rows').change(function() {
			applyFilter();
		});

		$('#forms').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php

	html_start_box($header_label, '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='forms' action='user_group_admin.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Users');?>
					</td>
					<td>
						<select id='rows'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . "</option>";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' id='associated' <?php print (get_request_var('associated') == 'true' || get_request_var('associated') == 'on' ? 'checked':'');?>>
							<label for='associated'><?php print __('Show Members');?></label>
						</span>
					</td>
					<td>
						<span>
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='go' value='<?php print __x('filter: use', 'Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __x('filter reset', 'Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
			<input type='hidden' name='action' value='edit'>
			<input type='hidden' name='tab' value='members'>
			<input type='hidden' name='id' value='<?php print get_request_var('id');?>'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();
}

