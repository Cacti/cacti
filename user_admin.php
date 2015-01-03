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

include('./include/auth.php');

define('MAX_DISPLAY_PAGES', 21);

$user_actions = array(
	1 => 'Delete',
	2 => 'Copy',
	3 => 'Enable',
	4 => 'Disable',
	5 => 'Batch Copy'
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

	case 'user_edit':
		top_header();
		user_edit();
		bottom_footer();
		break;

	default:
		if (!api_plugin_hook_function('user_admin_action', get_request_var_request('action'))) {
			top_header();
			user();
			bottom_footer();
		}
		break;
	}
}

/* --------------------------
    Actions Function
   -------------------------- */

function update_policies() {
	$set = '';

	$set .= isset($_POST['policy_graphs']) ? 'policy_graphs=' . get_request_var_post('policy_graphs'):'';
	$set .= isset($_POST['policy_trees']) ? (strlen($set) ? ',':'') . 'policy_trees=' . get_request_var_post('policy_trees'):'';
	$set .= isset($_POST['policy_hosts']) ? (strlen($set) ? ',':'') . 'policy_hosts=' . get_request_var_post('policy_hosts'):'';
	$set .= isset($_POST['policy_graph_templates']) ? (strlen($set) ? ',':'') . 'policy_graph_templates=' . get_request_var_post('policy_graph_templates'):'';

	if (strlen($set)) {
		db_execute_prepared("UPDATE user_auth SET $set WHERE id = ?", array(get_request_var_post('id')));
	}

	header('Location: user_admin.php?action=user_edit&tab=' .  get_request_var_post('tab') . '&id=' . get_request_var_post('id'));
	exit;
}

function form_actions() {
	global $user_actions, $auth_realms;

	/* if we are to save this form, instead of display it */
	if (isset($_POST['associate_host'])) {
		while (list($var,$val) = each($_POST)) {
			if (ereg('^chk_([0-9]+)$', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				if (get_request_var_post('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_perms (user_id, item_id, type) VALUES (?, ?, 3)', array(get_request_var_post('id'), $matches[1]));
				}else{
					db_execute_prepared('DELETE FROM user_auth_perms WHERE user_id = ? AND item_id = ? AND type = 3', array(get_request_var_post('id'), $matches[1]));
				}
			}
		}

		header('Location: user_admin.php?action=user_edit&tab=permsd&id=' . get_request_var_post('id'));
		exit;
	}elseif (isset($_POST['associate_graph'])) {
		while (list($var,$val) = each($_POST)) {
			if (ereg('^chk_([0-9]+)$', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				if (get_request_var_post('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_perms (user_id, item_id, type) VALUES (?, ?, 1)', array(get_request_var_post('id'), $matches[1]));
				}else{
					db_execute_prepared('DELETE FROM user_auth_perms WHERE user_id = ? AND item_id = ? AND type = 1', array(get_request_var_post('id'), $matches[1]));
				}
			}
		}

		header('Location: user_admin.php?action=user_edit&tab=permsg&id=' . get_request_var_post('id'));
		exit;
	}elseif (isset($_POST['associate_template'])) {
		while (list($var,$val) = each($_POST)) {
			if (ereg('^chk_([0-9]+)$', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				if (get_request_var_post('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_perms (user_id, item_id, type) VALUES (?, ?, 4)', array(get_request_var_post('id'), $matches[1]));
				}else{
					db_execute_prepared('DELETE FROM user_auth_perms WHERE user_id = ? AND item_id = ? AND type = 4', array(get_request_var_post('id'), $matches[1]));
				}
			}
		}

		header('Location: user_admin.php?action=user_edit&tab=permste&id=' . get_request_var_post('id'));
		exit;
	}elseif (isset($_POST['associate_groups'])) {
		while (list($var,$val) = each($_POST)) {
			if (ereg('^chk_([0-9]+)$', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				if (get_request_var_post('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_group_members (user_id, group_id) VALUES (?, ?)', array(get_request_var_post('id'), $matches[1]));
				}else{
					db_execute_prepared('DELETE FROM user_auth_group_members WHERE user_id = ? AND group_id = ?', array(get_request_var_post('id'), $matches[1]));
				}
			}
		}

		header('Location: user_admin.php?action=user_edit&tab=permsgr&id=' . get_request_var_post('id'));
		exit;
	}elseif (isset($_POST['associate_tree'])) {
		while (list($var,$val) = each($_POST)) {
			if (ereg('^chk_([0-9]+)$', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				if (get_request_var_post('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_perms (user_id, item_id, type) VALUES (?, ?, 2)', array(get_request_var_post('id'), $matches[1]));;
				}else{
					db_execute_prepared('DELETE FROM user_auth_perms WHERE user_id = ? AND item_id = ? AND type = 2', array(get_request_var_post('id'), $matches[1]));;
				}
			}
		}

		header('Location: user_admin.php?action=user_edit&tab=permstr&id=' . get_request_var_post('id'));
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

				user_remove($selected_items[$i]);

				api_plugin_hook_function('user_remove', $selected_items[$i]);
			}
		}

		if (get_request_var_post('drp_action') == '2') { /* copy */
			/* ================= input validation ================= */
			input_validate_input_number(get_request_var_post('selected_items'));
			input_validate_input_number(get_request_var_post('new_realm'));
			/* ==================================================== */

			$new_username  = get_request_var_post('new_username');
			$new_realm     = get_request_var_post('new_realm', 0);
			$template_user = db_fetch_row_prepared('SELECT username, realm FROM user_auth WHERE id = ?', array(get_request_var_post('selected_items')));
			$overwrite     = array( 'full_name' => get_request_var_post('new_fullname') );

			if (strlen($new_username)) {
				if (sizeof(db_fetch_assoc_prepared('SELECT username FROM user_auth WHERE username = ? AND realm = ?', array($new_username, $new_realm)))) {
					raise_message(19);
				} else {
					if (user_copy($template_user['username'], $new_username, $template_user['realm'], $new_realm, false, $overwrite) === false) {
						raise_message(2);
					} else {
						raise_message(1);
					}
				}
			}
		}

		if (get_request_var_post('drp_action') == '3') { /* enable */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				user_enable($selected_items[$i]);
			}
		}

		if (get_request_var_post('drp_action') == '4') { /* disable */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				user_disable($selected_items[$i]);
			}
		}

		if (get_request_var_post('drp_action') == '5') { /* batch copy */
			/* ================= input validation ================= */
			input_validate_input_number(get_request_var_post('template_user'));
			/* ==================================================== */

			$copy_error = false;
			$template = db_fetch_row_prepared('SELECT username, realm FROM user_auth WHERE id = ?', array(get_request_var_post('template_user')));
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				$user = db_fetch_row_prepared('SELECT username, realm FROM user_auth WHERE id = ?', array($selected_items[$i]));
				if ((isset($user)) && (isset($template))) {
					if (user_copy($template['username'], $user['username'], $template['realm'], $user['realm'], true) === false) {
						$copy_error = true;
					}
				}
			}
			if ($copy_error) {
				raise_message(2);
			} else {
				raise_message(1);
			}
		}

		header('Location: user_admin.php');
		exit;
	}

	/* loop through each of the users and process them */
	$user_list = '';
	$user_array = array();
	$i = 0;
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			if (get_request_var_post('drp_action') != '2') {
				$user_list .= '<li>' . db_fetch_cell_prepared('SELECT username FROM user_auth WHERE id = ?', array($matches[1])) . '</li>';
			}
			$user_array[$i] = $matches[1];

			$i++;
		}
	}

	/* Check for deleting of Graph Export User */
	if ((get_request_var_post('drp_action') == '1') && isset($user_array) && sizeof($user_array)) { /* delete */
		$exportuser = read_config_option('export_user_id');
		if (in_array($exportuser, $user_array)) {
			raise_message(22);
			header('Location: user_admin.php');
			exit;
		}
	}

	top_header();

	html_start_box('<strong>' . $user_actions[get_request_var_post('drp_action')] . '</strong>', '40%', '', '3', 'center', '');

	print "<form action='user_admin.php' method='post'>\n";

	if (isset($user_array) && sizeof($user_array)) {
		if ((get_request_var_post('drp_action') == '1') && (sizeof($user_array))) { /* delete */
			print "
				<tr>
					<td class='textArea'>
						<p>When you click \"Continue\", the selected User(s) will be deleted.</p>
						<p><ul>$user_list</ul></p>
					</td>
				</tr>\n";
			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Delete User(s)'>";
		}
		$user_id = '';

		if ((get_request_var_post('drp_action') == '2') && (sizeof($user_array))) { /* copy */
			$user_id = $user_array[0];
			$user_realm = db_fetch_cell_prepared('SELECT realm FROM user_auth WHERE id = ?', array($user_id));

			print "
				<tr>
					<td class='textArea'>
						When you click \"Continue\" the selected User will be copied to the new User below<br><br>
					</td>
				</tr>
				<tr>
					<td class='textArea'>
						Template Username: <i>" . db_fetch_cell_prepared('SELECT username FROM user_auth WHERE id = ?', array($user_id)) . "</i>
					</td>
				</tr>
				<tr>
					<td class='textArea'>
					Username: ";
			print form_text_box('new_username', '', '', 25);
			print "				</td>
				</tr>
				<tr>
					<td class='textArea'>
						Full Name: ";
			print form_text_box('new_fullname', '', '', 35);
			print "				</td>
				</tr>
				<tr>
					<td class='textArea'>
						Realm: \n";
			print form_dropdown('new_realm', $auth_realms, '', '', $user_realm, '', 0);
			print "				</td>

				</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Copy User'>";
		}

		if ((get_request_var_post('drp_action') == '3') && (sizeof($user_array))) { /* enable */
			print "
				<tr>
					<td class='textArea'>
						<p>When you click \"Continue\" the selected User(s) will be enabled.</p>
						<ul>$user_list</ul>
					</td>
				</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Enable User(s)'>";
		}

		if ((get_request_var_post('drp_action') == '4') && (sizeof($user_array))) { /* disable */
			print "
				<tr>
					<td class='textArea'>
						<p>When you click \"Continue\" the selected User(s) will be disabled.</p>
						<ul>$user_list</ul>
					</td>
				</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Disable User(s)'>";
		}

		if ((get_request_var_post('drp_action') == '5') && (sizeof($user_array))) { /* batch copy */
			$usernames = db_fetch_assoc('SELECT id, username FROM user_auth WHERE realm = 0 ORDER BY username');

			print "
				<tr>
					<td class='textArea'>When you click \"Continue\" you will overwrite selected the User(s) settings with the selected template User settings and permissions?  Original user Full Name, Password, Realm and Enable status will be retained, all other fields will be overwritten from Template User.<br><br></td>
				</tr><tr>
					<td class='textArea'>
						Template User: \n";
			print form_dropdown('template_user', $usernames, 'username', 'id', '', '', 0);
			print "		</td>
				</tr><tr>
					<td class='textArea'>
						<p>User(s) to update:
						<ul>$user_list</ul></p>
					</td>
				</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Reset User(s) Settings'>";
		}
	}else{
		print "<tr><td class='even'><span class='textError'>You must select at least one user.</span></td></tr>\n";

		$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
	}

	print " <tr>
			<td align='right' class='saveRow'>
				<input type='hidden' name='action' value='actions'>";
	if (get_request_var_post('drp_action') == '2') { /* copy */
		print "				<input type='hidden' name='selected_items' value='" . $user_id . "'>\n";
	}else{
		print "				<input type='hidden' name='selected_items' value='" . (isset($user_array) ? serialize($user_array) : '') . "'>\n";
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

	/* graph permissions */
	if ((isset($_POST['save_component_graph_perms'])) && (!is_error_message())) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('id'));
		input_validate_input_number(get_request_var_post('perm_graphs'));
		input_validate_input_number(get_request_var_post('perm_trees'));
		input_validate_input_number(get_request_var_post('perm_hosts'));
		input_validate_input_number(get_request_var_post('perm_graph_templates'));
		input_validate_input_number(get_request_var_post('policy_graphs'));
		input_validate_input_number(get_request_var_post('policy_trees'));
		input_validate_input_number(get_request_var_post('policy_hosts'));
		input_validate_input_number(get_request_var_post('policy_graph_templates'));
		/* ==================================================== */

		$add_button_clicked = false;

		if (isset($_POST['add_graph_x'])) {
			db_execute_prepared('REPLACE INTO user_auth_perms (user_id,item_id,type) VALUES (?, ?, 1)', array(get_request_var_post('id'), get_request_var_post('perm_graphs')));
			$add_button_clicked = true;
		}elseif (isset($_POST['add_tree_x'])) {
			db_execute_prepared('REPLACE INTO user_auth_perms (user_id,item_id,type) VALUES (?, ?, 2)', array(get_request_var_post('id'), get_request_var_post('perm_trees')));
			$add_button_clicked = true;
		}elseif (isset($_POST['add_host_x'])) {
			db_execute_prepared('REPLACE INTO user_auth_perms (user_id,item_id,type) VALUES (?, ?, 3)', array(get_request_var_post('id'), get_request_var_post('perm_hosts')));
			$add_button_clicked = true;
		}elseif (isset($_POST['add_graph_template_x'])) {
			db_execute_prepared('REPLACE INTO user_auth_perms (user_id,item_id,type) VALUES (?, ?, 4)', array(get_request_var_post('id'), get_request_var_post('perm_graph_templates')));
			$add_button_clicked = true;
		}

		if ($add_button_clicked == true) {
			header('Location: user_admin.php?action=user_edit&tab=graph_perms_edit&id=' . get_request_var_post('id'));
			exit;
		}
	}

	/* user management save */
	if (isset($_POST['save_component_user'])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('id'));
		input_validate_input_number(get_request_var_post('realm'));
		/* ==================================================== */

		if ((get_request_var_post('password') == '') && (get_request_var_post('password_confirm') == '')) {
			$password = db_fetch_cell_prepared('SELECT password FROM user_auth WHERE id = ?', array(get_request_var_post('id')));
		}else{
			$password = md5(get_request_var_post('password'));
		}

		/* check duplicate username */
		if (sizeof(db_fetch_row_prepared('SELECT * FROM user_auth WHERE realm = ? AND username = ? AND id != ?', array(get_request_var_post('realm'), get_request_var_post('username'), get_request_var_post('id'))))) {
			raise_message(12);
		}

		/* check for guest or template user */
		$username = db_fetch_cell_prepared('SELECT username FROM user_auth WHERE id = ?', array(get_request_var_post('id')));
		if ($username != '' && $username != get_request_var_post('username')) {
			$template_user = read_config_option('user_template');
			$guest_user    = read_config_option('guest_user');

			if ($username == $template_user) {
				raise_message(20);
			}

			if ($username == $guest_user) {
				raise_message(20);
			}
		}

		/* check to make sure the passwords match; if not error */
		if (get_request_var_post('password') != get_request_var_post('password_confirm')) {
			raise_message(4);
		}

		form_input_validate(get_request_var_post('password'), 'password', '' . preg_quote(get_request_var_post('password_confirm')) . '', true, 4);
		form_input_validate(get_request_var_post('password_confirm'), 'password_confirm', '' . preg_quote(get_request_var_post('password')) . '', true, 4);

		$save['id'] = get_request_var_post('id');
		$save['username'] = form_input_validate(get_request_var_post('username'), 'username', "^[A-Za-z0-9\._\\\@\ -]+$", false, 3);
		$save['full_name'] = form_input_validate(get_request_var_post('full_name'), 'full_name', '', true, 3);
		$save['password'] = $password;
		$save['must_change_password'] = form_input_validate(get_request_var_post('must_change_password', ''), 'must_change_password', '', true, 3);
		$save['show_tree'] = form_input_validate(get_request_var_post('show_tree', ''), 'show_tree', '', true, 3);
		$save['show_list'] = form_input_validate(get_request_var_post('show_list', ''), 'show_list', '', true, 3);
		$save['show_preview'] = form_input_validate(get_request_var_post('show_preview', ''), 'show_preview', '', true, 3);
		$save['graph_settings'] = form_input_validate(get_request_var_post('graph_settings', ''), 'graph_settings', '', true, 3);
		$save['login_opts'] = form_input_validate(get_request_var_post('login_opts'), 'login_opts', '', true, 3);
		$save['realm'] = get_request_var_post('realm', 0);
		$save['enabled'] = form_input_validate(get_request_var_post('enabled', ''), 'enabled', '', true, 3);
		$save['locked'] = form_input_validate(get_request_var_post('locked', ''), 'locked', '', true, 3);
		if ($save['locked'] == '') {
			$save['failed_attempts'] = 0;
		}		

		$save = api_plugin_hook_function('user_admin_setup_sql_save', $save);

		if (!is_error_message()) {
			$user_id = sql_save($save, 'user_auth');

			if ($user_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}
	}elseif (isset($_POST['save_component_realm_perms'])) {
		db_execute_prepared('DELETE FROM user_auth_realm WHERE user_id = ?', array(get_request_var_post('id')));

		while (list($var, $val) = each($_POST)) {
			if (preg_match('/^[section]/i', $var)) {
				if (substr($var, 0, 7) == 'section') {
					db_execute_prepared('REPLACE INTO user_auth_realm (user_id, realm_id) VALUES (?, ?)', array(get_request_var_post('id'), substr($var, 7)));
				}
			}
		}

		raise_message(1);
	}elseif (isset($_POST['save_component_graph_settings'])) {
		while (list($tab_short_name, $tab_fields) = each($settings_graphs)) {
			while (list($field_name, $field_array) = each($tab_fields)) {
				if ((isset($field_array['items'])) && (is_array($field_array['items']))) {
					while (list($sub_field_name, $sub_field_array) = each($field_array['items'])) {
						db_execute_prepared('REPLACE INTO settings_graphs (user_id, name, value) VALUES (?, ?, ?)', array((!empty($user_id) ? $user_id : get_request_var_post('id')), $sub_field_name, get_request_var_post($sub_field_name, '')));
					}
				}else{
					db_execute_prepared('REPLACE INTO settings_graphs (user_id, name, value) VALUES (?, ?, ?)', array((!empty($user_id) ? $user_id : $_POST['id']), $field_name, get_request_var_post($field_name)));
				}
			}
		}

		/* reset local settings cache so the user sees the new settings */
		kill_session_var('sess_graph_config_array');

		raise_message(1);
	}elseif (isset($_POST['save_component_graph_perms'])) {
		db_execute_prepared('UPDATE user_auth SET policy_graphs = ?, policy_trees = ?, policy_hosts = ?, policy_graph_templates = ? WHERE id = ?', 
			array(get_request_var_post('policy_graphs'), get_request_var_post('policy_trees'), get_request_var_post('policy_hosts'), get_request_var_post('policy_graph_templates'), get_request_var_post('id')));
	} else {
		api_plugin_hook('user_admin_user_save');
	}

	/* redirect to the appropriate page */
	header('Location: user_admin.php?action=user_edit&id=' . (empty($user_id) ? $_POST['id'] : $user_id));
}

/* --------------------------
    Graph Permissions
   -------------------------- */

function perm_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	input_validate_input_number(get_request_var_request('user_id'));
	/* ==================================================== */

	if (get_request_var_request('type') == 'graph') {
		db_execute_prepared('DELETE FROM user_auth_perms WHERE type = 1 AND user_id = ? AND item_id = ?', array(get_request_var_request('user_id'), get_request_var_request('id')));
	}elseif (get_request_var_request('type') == 'tree') {
		db_execute_prepared('DELETE FROM user_auth_perms WHERE type = 2 AND user_id = ? AND item_id = ?', array(get_request_var_request('user_id'), get_request_var_request('id')));
	}elseif (get_request_var_request('type') == 'host') {
		db_execute_prepared('DELETE FROM user_auth_perms WHERE type = 3 AND user_id = ? AND item_id = ?', array(get_request_var_request('user_id'), get_request_var_request('id')));
	}elseif (get_request_var_request('type') == 'graph_template') {
		db_execute_prepared('DELETE FROM user_auth_perms WHERE type = 4 AND user_id = ? AND item_id = ?', array(get_request_var_request('user_id'), get_request_var_request('id')));
	}

	header('Location: user_admin.php?action=user_edit&tab=graph_perms_edit&id=' . get_request_var_request('user_id'));
}

function get_permission_string(&$graph, &$policies) {
	$grantStr  = '';
	$rejectStr = '';

	if (read_config_option('graph_auth_method') == 1) {
		$method = 'loose';
	}else{
		$method = 'strong';
	}

	$i = 1;
	foreach($policies as $p) {
		if ($p['policy_graphs'] == 1) {
			if ($graph["user$i"] == '') {
				$grantStr  = $grantStr . (strlen($grantStr) ? ', ':'') . 'Graph:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
			}else{
				$rejectStr = $rejectStr . (strlen($rejectStr) ? ', ':'') . 'Graph:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
			}
		}elseif ($graph["user$i"] != '') {
			$grantStr = $grantStr . (strlen($grantStr) ? ', ':'') . 'Graph:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
		}elseif ($method == 'loose') {
			$reject++;
		}else{
			$rejectStr = $rejectStr . (strlen($rejectStr) ? ', ':'') . 'Graph:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
		}
		$i++;

		$allowed = 0;
		$reject  = 0;
		if ($p['policy_hosts'] == 1) {
			if ($graph["user$i"] == '') {
				if ($method == 'loose') {
					$grantStr = $grantStr . (strlen($grantStr) ? ', ':'') . 'Device:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
				}else{
					$allowed++;
				}
			}else{
				$rejectStr = $rejectStr . (strlen($rejectStr) ? ', ':'') . 'Device:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
			}
		}elseif ($graph["user$i"] != '') {
			if ($method == 'loose') {
				$grantStr = $grantStr . (strlen($grantStr) ? ', ':'') . 'Device:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
			}else{
				$allowed++;
			}
		}elseif ($method == 'loose') {
			$reject++;
		}
		$i++;

		
		if ($p['policy_graph_templates'] == 1) {
			if ($graph["user$i"] == '') {
				if ($method == 'loose') {
					$grantStr = $grantStr . (strlen($grantStr) ? ', ':'') . 'Template:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
				}else{
					$allowed++;
				}
			}else{
				$rejectStr = $rejectStr . (strlen($rejectStr) ? ', ':'') . 'Template:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
			}
		}elseif ($graph["user$i"] != '') {
			if ($method == 'loose') {
				$grantStr = $grantStr . (strlen($grantStr) ? ', ':'') . 'Template:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
			}else{
				$allowed++;
			}
		}elseif ($method == 'loose') {
			$reject++;
		}
		$i++;

		if ($method != 'loose') {
			if ($allowed == 2) {
				$grantStr = $grantStr . (strlen($grantStr) ? ', ':'') . 'Device+Template:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
			}else{
				$rejectStr = $rejectStr . (strlen($rejectStr) ? ', ':'') . 'Device+Template:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
			}
		}elseif ($reject == 3) {
			$rejectStr = $rejectStr . (strlen($rejectStr) ? ', ':'') . 'Graph+Device+Template:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
		}
	}

	$permStr = '';
	if (strlen($grantStr)) {
		$permStr = "<span style='color:green;font-weight:bold;'>Granted:</span> <span style='color:green;'>" . trim($grantStr,',') . '</span>';
	}

	if (strlen($rejectStr)) {
		if ($grantStr == '') {
			$permStr = "<span style='color:red;font-weight:bold;'>Restricted:</span> <span style='color:red;'>" . trim($rejectStr,',') . '</span>';
		}else{
			$permStr .= ", <span style='color:red;'>" . trim($rejectStr,',') . '</span>';
		}
	}

	return $permStr;;
}

function graph_perms_edit($tab, $header_label) {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	/* ==================================================== */

	$sql_where  = '';
	$sql_join   = '';
	$limit      = '';
	$sql_having = '';

	$policy_array = array(
		1 => 'Allow',
		2 => 'Deny');

	if (!empty($_REQUEST['id'])) {
		$policy = db_fetch_row_prepared('SELECT policy_graphs, policy_trees, policy_hosts, policy_graph_templates 
			FROM user_auth 
			WHERE id = ?', array(get_request_var_request('id')));
	} else {
		$policy = array(
			'policy_graphs' => '1',
			'policy_trees'  => '1',
			'policy_hosts'  => '1',
			'policy_graph_templates' => '1'
		);
	}

	switch($tab) {
	case 'permsg':
		process_graph_request_vars();

		graph_filter($header_label);

		/* print checkbox form for validation */
		print "<form name='policy' method='post' action='user_admin.php'>\n";

		/* box: device permissions */
		html_start_box('<strong>Default Graph Policy</strong>', '100%', '', '3', 'center', '');

		?>
		<tr class='even'>
			<td><table cellspacing="0" cellpadding="2"><tr>
			<td style="white-space:nowrap;" width="120">Default Graph Policy for this User</td>
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

		/* print checkbox form for validation */
		print "<form name='chk' method='post' action='" . htmlspecialchars('user_admin.php?action=user_edit&tab=permsg&id=' . get_request_var_request('id')) . "'>\n";

		html_start_box('', '100%', '', '3', 'center', '');

		$user = $_SESSION['sess_user_id'];

		if (read_config_option('graph_auth_method') == 1) {
			$sql_operator = 'OR';
		}else{
			$sql_operator = 'AND';
		}

		$limit = 'LIMIT ' . ($rows*(get_request_var_request('page')-1)) . ',' . $rows;

		/* get policies for all groups and user */
		$policies   = db_fetch_assoc_prepared("SELECT uag.id, 'group' AS type, uag.name, 
			policy_graphs, policy_hosts, policy_graph_templates 
			FROM user_auth_group AS uag
			INNER JOIN user_auth_group_members AS uagm
			ON uag.id = uagm.group_id
			WHERE uag.enabled = 'on' AND uagm.user_id = ?", array($user));
		$policies[] = db_fetch_row_prepared("SELECT id, 'user' AS type, 'user' AS name, 
			policy_graphs, policy_hosts, policy_graph_templates 
			FROM user_auth WHERE id = ?", array($user));

		/* form the 'where' clause for our main sql query */
		if (strlen(get_request_var_request('filter'))) {
			$sql_where = "WHERE (gtg.title_cache LIKE '%%" . get_request_var_request('filter') . "%%' AND gtg.local_graph_id > 0)";
		} else {
			$sql_where = 'WHERE (gtg.local_graph_id > 0)';
		}

		if (get_request_var_request('graph_template_id') == '-1') {
			/* Show all items */
		}elseif (get_request_var_request('graph_template_id') == '0') {
			$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' gtg.graph_template_id=0';
		}elseif (!empty($_REQUEST['graph_template_id'])) {
			$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' gtg.graph_template_id=' . get_request_var_request('graph_template_id');
		}

		$i = 1;
		$user_perm = '';
		$sql_select = '';
		foreach($policies as $policy) {
			if ($policy['type'] == 'user' && $user_perm == '') {
				$user_perm = $i;
			}
			if (get_request_var_request('associated') == 'false') {
				if ($policy['policy_graphs'] == 1) {
					$sql_having .= (strlen($sql_having) ? ' OR':'') . " (user$i IS NULL";
				}else{
					$sql_having .= (strlen($sql_having) ? ' OR':'') . " (user$i=" . $policy['id'];
				}
			}
			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.id=uap$i.item_id AND uap$i.type=1) ";
			$sql_select .= (strlen($sql_select) ? ', ':'') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if (get_request_var_request('associated') == 'false') {
				if ($policy['policy_hosts'] == 1) {
					$sql_having .= " OR (user$i IS NULL";
				}else{
					$sql_having .= " OR (user$i=" . $policy['id'];
				}
			}
			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.host_id=uap$i.item_id AND uap$i.type=3) ";
			$sql_select .= (strlen($sql_select) ? ', ':'') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if (get_request_var_request('associated') == 'false') {
				if ($policy['policy_graph_templates'] == 1) {
					$sql_having .= " $sql_operator user$i IS NULL))";
				}else{
					$sql_having .= " $sql_operator user$i=" . $policy['id'] . '))';
				}
			}
			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.graph_template_id=uap$i.item_id AND uap$i.type=4) ";
			$sql_select .= (strlen($sql_select) ? ', ':'') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;
		}

		if (strlen($sql_having)) {
			$sql_having = 'HAVING ' . $sql_having;
		}

		$graphs = db_fetch_assoc("SELECT gtg.local_graph_id, h.description, gt.name AS template_name, 
			gtg.title_cache, gtg.width, gtg.height, gl.snmp_index, gl.snmp_query_id,
			$sql_select
			FROM graph_templates_graph AS gtg 
			INNER JOIN graph_local AS gl 
			ON gl.id = gtg.local_graph_id 
			LEFT JOIN graph_templates AS gt 
			ON gt.id = gl.graph_template_id 
			LEFT JOIN host AS h 
			ON h.id = gl.host_id 
			$sql_join
			$sql_where
			$sql_having
			ORDER BY gtg.title_cache
			$limit");

		$total_rows = db_fetch_cell("SELECT COUNT(*)
			FROM (
				SELECT $sql_select
				FROM graph_templates_graph AS gtg 
				INNER JOIN graph_local AS gl 
				ON gl.id = gtg.local_graph_id 
				LEFT JOIN graph_templates AS gt 
				ON gt.id = gl.graph_template_id 
				LEFT JOIN host AS h 
				ON h.id = gl.host_id 
				$sql_join
				$sql_where
				$sql_having
			) AS rows");

		//print '<pre>';print_r($graphs);print '</pre>';

		$nav = html_nav_bar('user_admin.php?action=user_edit&tab=permsg&id=' . get_request_var_request('id'), MAX_DISPLAY_PAGES, get_request_var_request('page'), $rows, $total_rows, 11, 'Graphs', 'page', 'main');

		print $nav;

		$display_text = array('Graph Title', 'ID', 'Effective Policy');

		html_header_checkbox($display_text, false);

		if (sizeof($graphs)) {
			foreach ($graphs as $g) {
				form_alternate_row('line' . $g['local_graph_id'], true);
				form_selectable_cell((strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($g['title_cache'])) : htmlspecialchars($g['title_cache'])), $g['local_graph_id']);
				form_selectable_cell($g['local_graph_id'], $g['local_graph_id']);
				form_selectable_cell(get_permission_string($g, $policies), $g['local_graph_id']);
				form_checkbox_cell($g['title_cache'], $g['local_graph_id']);
				form_end_row();
			}
	
			/* put the nav bar on the bottom as well */
			print $nav;
		} else {
			print '<tr><td><em>No Matching Graphs Found</em></td></tr>';
		}
		html_end_box(false);

		form_hidden_box('action', 'user_edit', '');
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
	case 'permsgr':
		process_group_request_vars();

		group_filter($header_label);

		/* if the number of rows is -1, set it to the default */
		if ($_REQUEST['rows'] == -1) {
			$rows = read_config_option('num_rows_table');
		}else{
			$rows = $_REQUEST['rows'];
		}

		/* form the 'where' clause for our main sql query */
		/* form the 'where' clause for our main sql query */
		if (strlen(get_request_var_request('filter'))) {
			$sql_where = "WHERE ((uag.name LIKE '%" . get_request_var_request('filter') . "%') OR (uag.description LIKE '%" . get_request_var_request('filter') . "%'))";
		} else {
			$sql_where = '';
		}

		if (get_request_var_request('associated') != 'false') {
			/* Show all items */
		} else {
			$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' uagm.user_id=' . get_request_var_request('id', 0);
		}

		/* print checkbox form for validation */
		print "<form name='chk' method='post' action='" . htmlspecialchars('user_admin.php?action=user_edit&tab=permsd&id=' . get_request_var_request('id')) . "'>\n";

		html_start_box('', '100%', '', '3', 'center', '');

		$total_rows = db_fetch_cell("SELECT
			COUNT(uag.id)
			FROM user_auth_group AS uag
			LEFT JOIN user_auth_group_members AS uagm
			ON uag.id = uagm.group_id
			$sql_where");

		$sql_query = "SELECT uag.*, uagm.user_id
			FROM user_auth_group AS uag
			LEFT JOIN user_auth_group_members AS uagm
			ON uag.id = uagm.group_id
			$sql_where 
			ORDER BY name
			LIMIT " . ($rows*(get_request_var_request('page')-1)) . ',' . $rows;

		$groups = db_fetch_assoc($sql_query);

		$nav = html_nav_bar('user_admin.php?action=user_edit&tab=permsgr&id=' . get_request_var_request('id'), MAX_DISPLAY_PAGES, get_request_var_request('page'), $rows, $total_rows, 11, 'Devices', 'page', 'main');
	
		print $nav;

		$display_text = array('Name', 'Description', 'Member', 'ID', 'Policies (Graph/Device/Template)', 'Enabled');

		html_header_checkbox($display_text, false);

		if (sizeof($groups)) {
			foreach ($groups as $g) {
				form_alternate_row('line' . $g['id'], true);
				form_selectable_cell("<a class='linkEditMain' href='user_group_admin.php?action=edit&id=" . $g['id'] . "'>" . (strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($g['name'])) : htmlspecialchars($g['name'])) . '</a>', $g['id']);
				form_selectable_cell((strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($g['description'])) : htmlspecialchars($g['description'])), $g['id']);
				form_selectable_cell($g['user_id'] > 0 ? 'Member':'Non Member', $g['id']);
				form_selectable_cell(($g['id']), $g['id']);
				form_selectable_cell(($g['policy_graphs'] == 1 ? 'ALLOW':'DENY') . '/' . ($g['policy_hosts'] == 1 ? 'ALLOW':'DENY') . '/' . ($g['policy_graph_templates'] == 1 ? 'ALLOW':'DENY'), $g['id']);
				form_selectable_cell($g['enabled'] == 'on' ? 'Enabled':'Disabled', $g['id']);
				form_checkbox_cell($g['name'], $g['id']);
				form_end_row();
			}
	
			/* put the nav bar on the bottom as well */
			print $nav;
		} else {
			print '<tr><td><em>No Matching User Groups Found</em></td></tr>';
		}
		html_end_box(false);

		form_hidden_box('action', 'user_edit', '');
		form_hidden_box('tab',$tab,'');
		form_hidden_box('id', get_request_var_request('id'), '');
		form_hidden_box('associate_groups', '1', '');

		$assoc_actions = array(
			1 => 'Assign Membership',
			2 => 'Remove Membership'
		);

		/* draw the dropdown containing a list of available actions for this form */
		draw_actions_dropdown($assoc_actions);

		print '</form>';

		break;
	case 'permsd':
		process_device_request_vars();

		device_filter($header_label);

		/* print checkbox form for validation */
		print "<form name='policy' method='post' action='user_admin.php'>\n";

		/* box: device permissions */
		html_start_box('<strong>Default Device Policy</strong>', '100%', '', '3', 'center', '');

		?>
		<tr class='even'>
			<td><table cellspacing="0" cellpadding="2"><tr>
			<td style="white-space:nowrap;" width="120">Default Device Policy for this User</td>
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
			$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' user_auth_perms.user_id=' . get_request_var_request('id', 0);
		}

		/* print checkbox form for validation */
		print "<form name='chk' method='post' action='" . htmlspecialchars('user_admin.php?action=user_edit&tab=permsd&id=' . get_request_var_request('id')) . "'>\n";

		html_start_box('', '100%', '', '3', 'center', '');

		$total_rows = db_fetch_cell("SELECT
			COUNT(host.id)
			FROM host
			LEFT JOIN user_auth_perms 
			ON (host.id = user_auth_perms.item_id AND user_auth_perms.type = 3)
			$sql_where");

		$host_graphs       = array_rekey(db_fetch_assoc('SELECT host_id, count(*) as graphs FROM graph_local GROUP BY host_id'), 'host_id', 'graphs');
		$host_data_sources = array_rekey(db_fetch_assoc('SELECT host_id, count(*) as data_sources FROM data_local GROUP BY host_id'), 'host_id', 'data_sources');

		$sql_query = "SELECT host.*, user_auth_perms.user_id
			FROM host 
			LEFT JOIN user_auth_perms 
			ON (host.id = user_auth_perms.item_id AND user_auth_perms.type = 3)
			$sql_where 
			ORDER BY description
			LIMIT " . ($rows*(get_request_var_request('page')-1)) . ',' . $rows;

		$hosts = db_fetch_assoc($sql_query);

		$nav = html_nav_bar('user_admin.php?action=user_edit&tab=permsd&id=' . get_request_var_request('id'), MAX_DISPLAY_PAGES, get_request_var_request('page'), $rows, $total_rows, 11, 'Devices', 'page', 'main');
	
		print $nav;

		$display_text = array('Description', 'ID', 'Effective Policy', 'Graphs', 'Data Sources', 'Status', 'Hostname');

		html_header_checkbox($display_text, false);

		if (sizeof($hosts)) {
			foreach ($hosts as $host) {
				form_alternate_row('line' . $host['id'], true);
				form_selectable_cell((strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($host['description'])) : htmlspecialchars($host['description'])), $host['id']);
				form_selectable_cell(round(($host['id']), 2), $host['id']);
				if (empty($host['user_id']) || $host['user_id'] == NULL) {
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

		form_hidden_box('action', 'user_edit', '');
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

		print '</form>';

		break;
	case 'permste':
		process_template_request_vars();

		template_filter($header_label);

		/* print checkbox form for validation */
		print "<form name='policy' method='post' action='user_admin.php'>\n";

		/* box: device permissions */
		html_start_box('<strong>Default Graph Template Policy</strong>', '100%', '', '3', 'center', '');

		?>
		<tr class='even'>
			<td><table cellspacing="0" cellpadding="2"><tr>
			<td style="white-space:nowrap;" width="120">Default Graph Template Policy for this User</td>
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
		if (strlen(get_request_var_request('filter'))) {
			$sql_where = "WHERE (gt.name LIKE '%%" . get_request_var_request('filter') . "%%')";
		} else {
			$sql_where = '';
		}

		if (get_request_var_request('associated') == 'false') {
			/* Show all items */
		} else {
			$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' (user_auth_perms.type=4 AND user_auth_perms.user_id=' . get_request_var_request('id', 0) . ')';
		}

		/* print checkbox form for validation */
		print "<form name='chk' method='post' action='" . htmlspecialchars('user_admin.php?action=user_edit&tab=permste&id=' . get_request_var_request('id')) . "'>\n";

		html_start_box('', '100%', '', '3', 'center', '');

		$total_rows = db_fetch_cell("SELECT
			COUNT(gt.id)
			FROM graph_templates AS gt
			INNER JOIN graph_local AS gl
			ON gt.id = gl.graph_template_id
			LEFT JOIN user_auth_perms 
			ON (gt.id = user_auth_perms.item_id AND user_auth_perms.type = 4)
			$sql_where
			GROUP BY gl.graph_template_id");

		$sql_query = "SELECT gt.id, gt.name, count(*) AS totals, user_auth_perms.user_id
			FROM graph_templates AS gt
			INNER JOIN graph_local AS gl
			ON gt.id = gl.graph_template_id
			LEFT JOIN user_auth_perms 
			ON (gt.id = user_auth_perms.item_id AND user_auth_perms.type = 4)
			$sql_where 
			GROUP BY gl.graph_template_id
			ORDER BY name
			LIMIT " . ($rows*(get_request_var_request('page')-1)) . ',' . $rows;

		$graphs = db_fetch_assoc($sql_query);

		$nav = html_nav_bar('user_admin.php?action=user_edit&tab=permste&id=' . get_request_var_request('id'), MAX_DISPLAY_PAGES, get_request_var_request('page'), $rows, $total_rows, 11, 'Graph Templates', 'page', 'main');

		print $nav;

		$display_text = array('Template Name', 'ID', 'Effective Policy', 'Total Graphs');

		html_header_checkbox($display_text, false);

		if (sizeof($graphs)) {
			foreach ($graphs as $g) {
				form_alternate_row('line' . $g['id'], true);
				form_selectable_cell((strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($g['name'])) : htmlspecialchars($g['name'])), $g['id']);
				form_selectable_cell($g['id'], $g['id']);
				if (empty($g['user_id']) || $g['user_id'] == NULL) {
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

		form_hidden_box('action', 'user_edit', '');
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
		print "<form name='policy' method='post' action='user_admin.php'>\n";

		/* box: device permissions */
		html_start_box('<strong>Default Tree Policy</strong>', '100%', '', '3', 'center', '');

		?>
		<tr class='even'>
			<td><table cellspacing="0" cellpadding="2"><tr>
			<td style="white-space:nowrap;" width="120">Default Tree Policy for this User</td>
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
			/* showing all rows */
		} else {
			$sql_where .= (strlen($sql_where) ? ' AND ':'WHERE ') . ' (user_auth_perms.type=2 AND user_auth_perms.user_id=' . get_request_var_request('id', 0) . ')';
		}

		/* print checkbox form for validation */
		print "<form name='chk' method='post' action='" . htmlspecialchars('user_admin.php?action=user_edit&tab=permstr&id=' . get_request_var_request('id')) . "'>\n";

		html_start_box('', '100%', '', '3', 'center', '');

		$total_rows = db_fetch_cell("SELECT
			COUNT(gt.id)
			FROM graph_tree AS gt
			LEFT JOIN user_auth_perms 
			ON (gt.id = user_auth_perms.item_id AND user_auth_perms.type = 2)
			$sql_where");

		$sql_query = "SELECT gt.id, gt.name, user_auth_perms.user_id
			FROM graph_tree AS gt
			LEFT JOIN user_auth_perms 
			ON (gt.id = user_auth_perms.item_id AND user_auth_perms.type = 2)
			$sql_where 
			ORDER BY name
			LIMIT " . ($rows*(get_request_var_request('page')-1)) . ',' . $rows;

		$trees = db_fetch_assoc($sql_query);

		$nav = html_nav_bar('user_admin.php?action=user_edit&tab=permstr&id=' . get_request_var_request('id'), MAX_DISPLAY_PAGES, get_request_var_request('page'), $rows, $total_rows, 11, 'Trees', 'page', 'main');

		print $nav;

		$display_text = array('Tree Name', 'ID', 'Effective Policy');

		html_header_checkbox($display_text, false);

		if (sizeof($trees)) {
			foreach ($trees as $t) {
				form_alternate_row('line' . $t['id'], true);
				form_selectable_cell((strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($t['name'])) : htmlspecialchars($t['name'])), $t['id']);
				form_selectable_cell($t['id'], $t['id']);
				if (empty($t['user_id']) || $t['user_id'] == NULL) {
					if ($policy['policy_graphs'] == 1) {
						form_selectable_cell('<span style="color:green;font-weight:bold;">Access Granted</span>', $t['id']);
					}else{
						form_selectable_cell('<span style="color:red;font-weight:bold;">Access Restricted</span>', $t['id']);
					}
				} else {
					if ($policy['policy_graphs'] == 1) {
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

		form_hidden_box('action', 'user_edit', '');
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

function user_realms_edit($header_label) {
	global $user_auth_realms;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	/* ==================================================== */

	print "<form name='chk' action='user_admin.php' method='post'>\n";

	html_start_box('', '100%', '', '3', 'center', '');

	$all_realms = $user_auth_realms;

	print "	<tr class='cactiTableTitle'>
			<td class='textHeaderDark'><strong>Realm Permissions</strong> $header_label</td>
			<td class='tableHeader' width='1%' align='center' style='" . get_checkbox_style() . "'><input type='checkbox' style='margin: 0px;' name='all' title='Select All' onClick='selectAllRealms(this.checked)'></td>\n
		</tr>\n";

	/* do cacti realms first */
	print "<tr class='tableHeader'><th colspan='2'>Base Permissions</th></tr>\n";
	print "<tr class='odd'><td colspan='4' width='100%'><table width='100%'><tr><td valign='top' style='white-space:nowrap;' width='20%'>\n";
	$i = 1;
	$j = 1;
	$base = array(7,8,15,1,2,3,4,5,6,9,10,11,12,13,14,16,17,18,19,101);
	foreach($base as $realm) {
		if (isset($user_auth_realms[$realm])) {
			$set = db_fetch_cell_prepared('SELECT realm_id 
				FROM user_auth_realm 
				WHERE user_id = ? 
				AND realm_id = ?', 
				array(get_request_var_request('id', 0), $realm));

			if (!empty($set)) {
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

	/* do plugin realms */
	$realms = db_fetch_assoc('SELECT pc.name, pr.id AS realm_id, pr.display
		FROM plugin_config AS pc
		INNER JOIN plugin_realms AS pr
		ON pc.directory = pr.plugin
		ORDER BY pc.name, pr.display');

	print "<tr class='tableHeader'><th colspan='2'>Plugin Permissions</th></tr>\n";
	print "<tr class='odd'><td colspan='4' width='100%'><table width='100%' cellpadding='0' cellspacing='0'><tr><td valign='top' width='20%' style='white-space:nowrap;'>\n";
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

			$set = db_fetch_cell_prepared('SELECT realm_id 
				FROM user_auth_realm 
				WHERE user_id = ? 
				AND realm_id = ?', 
				array(get_request_var_request('id', 0), $realm));

			if (!empty($set)) {
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
			if (sizeof(db_fetch_assoc_prepared('SELECT realm_id FROM user_auth_realm WHERE user_id = ? AND realm_id = ?', array(get_request_var_request('id', 0), $realm))) > 0) {
				$old_value = 'on';
			}else{
				$old_value = '';
			}

			$pos = (strpos($user_auth_realms[$realm], '->') !== false ? strpos($user_auth_realms[$realm], '->')+2:0);

			form_checkbox('section' . $realm, $old_value, substr($user_auth_realms[$realm], $pos), '', '', '', (!empty($_REQUEST['id']) ? 1 : 0)); print '<br>';


		}
	}

	print "</td></tr></table></td></tr>\n";
	print "<script type='text/javascript'>function selectAllRealms(checked) { if (checked) { $('input[id^=\"section\"]').prop('checked', true); } else { $('input[id^=\"section\"]').prop('checked', false); } }</script>\n";

	html_end_box();

	form_hidden_box('action', 'user_edit', '');
	form_hidden_box('id', get_request_var_request('id'), '');
	form_hidden_box('tab', 'realms', '');
	form_hidden_box('save_component_realm_perms', '1', '');

	form_save_button('user_admin.php', 'return');
}

function graph_settings_edit($header_label) {
	global $settings_graphs, $tabs_graphs, $graph_views;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	/* ==================================================== */

	print "<form name='chk' action='user_admin.php' method='post'>\n";

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

					$form_array[$field_name]['items'][$sub_field_name]['value'] =  db_fetch_cell_prepared('SELECT value FROM settings_graphs WHERE name = ? AND user_id = ?', array($sub_field_name, get_request_var_request('id')));
				}
			}else{
				if (graph_config_value_exists($field_name, $_REQUEST['id'])) {
					$form_array[$field_name]['form_id'] = 1;
				}

				$form_array[$field_name]['value'] = db_fetch_cell_prepared('SELECT value FROM settings_graphs WHERE name = ? and user_id = ?', array($field_name, $_REQUEST['id']));
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

	form_hidden_box('action', 'user_edit', '');
	form_hidden_box('id', get_request_var_request('id'), '');
	form_hidden_box('tab', 'settings', '');
	form_hidden_box('save_component_graph_settings','1','');

	form_save_button('user_admin.php', 'return');

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

function user_edit() {
	global $config, $fields_user_user_edit_host;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('id'));
	/* ==================================================== */

	/* present a tabbed interface */
	$tabs = array(
		'general' => 'General',
		'realms' => 'Realm Perms',
		'permsgr' => 'Group Membership',
		'permsg' => 'Graph Perms',
		'permsd' => 'Device Perms',
		'permste' => 'Template Perms',
		'permstr' => 'Tree Perms',
		'settings' => 'Graph Settings'
	);

	/* set the default tab */
	load_current_session_value('tab', 'sess_user_admin_tab', 'general');
	$current_tab = $_REQUEST['tab'];

	if (!empty($_REQUEST['id'])) {
		$user = db_fetch_row_prepared('SELECT * FROM user_auth WHERE id = ?', array(get_request_var_request('id')));
		$header_label = '[edit: ' . $user['username'] . ']';
	}else{
		$header_label = '[new]';
	}

	if (sizeof($tabs) && isset($_REQUEST['id'])) {
		/* draw the tabs */
		print "<div class='tabs'><nav><ul>\n";

		foreach (array_keys($tabs) as $tab_short_name) {
			print "<li class='subTab'><a " . (($tab_short_name == $current_tab) ? "class='selected'" : '') . 
				" href='" . htmlspecialchars($config['url_path'] .
				'user_admin.php?action=user_edit&id=' . get_request_var_request('id') .
				'&tab=' . $tab_short_name) .
				"'>$tabs[$tab_short_name]</a></li>\n";
		}

		api_plugin_hook('user_admin_tab');

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

	switch($current_tab) {
	case 'general':
		api_plugin_hook_function('user_admin_edit', (isset($user) ? get_request_var_request('id') : 0));

		html_start_box("<strong>User Management</strong> $header_label", '100%', '', '3', 'center', '');

		draw_edit_form(array(
			'config' => array('form_name' => 'chk'),
			'fields' => inject_form_variables($fields_user_user_edit_host, (isset($user) ? $user : array()))
			));

		html_end_box();

		form_save_button('user_admin.php', 'return');

		break;
	case 'settings':
		graph_settings_edit($header_label);

		break;
	case 'realms':
		user_realms_edit($header_label);

		break;
	case 'permsg':
	case 'permsd':
	case 'permsgr':
	case 'permste':
	case 'permstr':
		graph_perms_edit($current_tab, $header_label);
		break;
	default:
		if (api_plugin_hook_function('user_admin_run_action', get_request_var_request('tab'))) {
			user_realms_edit();
		}
		break;
	}
}

function user() {
	global $config, $auth_realms, $user_actions, $item_rows;

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
		kill_session_var('sess_user_admin_current_page');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_user_admin_filter');
		kill_session_var('sess_user_admin_sort_column');
		kill_session_var('sess_user_admin_sort_direction');

		unset($_REQUEST['page']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
	}else{
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_user_admin_current_page', '1');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));
	load_current_session_value('filter', 'sess_user_admin_filter', '');
	load_current_session_value('sort_column', 'sess_user_admin_sort_column', 'username');
	load_current_session_value('sort_direction', 'sess_user_admin_sort_direction', 'ASC');

	?>
	<script type="text/javascript">
	function applyFilter() {
		strURL = 'user_admin.php?rows=' + $('#rows').val();
		strURL = strURL + '&filter=' + $('#filter').val();
		strURL = strURL + '&page=' + $('#page').val();
		strURL = strURL + '&header=false';
		$.get(strURL, function(data) {
			$('#main').html(data);
			applySkin();
		});
	}

	function clearFilter() {
		strURL = 'user_admin.php?clear_x=1&header=false';
		$.get(strURL, function(data) {
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

		$('#form_user_admin').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box('<strong>User Management</strong>', '100%', '', '3', 'center', 'user_admin.php?tab=general&action=user_edit');

	if ($_REQUEST['rows'] == '-1') {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = $_REQUEST['rows'];
	}

	?>
	<tr class='even'>
		<td>
		<form id="form_user_admin" action="user_admin.php">
			<table cellpadding="2" cellspacing="0">
				<tr>
					<td width="50">
						Search
					</td>
					<td>
						<input id='filter' type="text" name="filter" size="25" value="<?php print htmlspecialchars(get_request_var_request('filter'));?>">
					</td>
					<td>
						Users
					</td>
					<td>
						<select id='rows' name="rows" onChange="applyFilter()">
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
						<input type="button" id='refresh' value="Go" title="Set/Refresh Filters">
					</td>
					<td>
						<input type="button" id='clear' name="clear_x" value="Clear" title="Clear Filters">
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' name='page' value='<?php print $_REQUEST['page'];?>'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var_request('filter'))) {
		$sql_where = "WHERE (user_auth.username LIKE '%" . get_request_var_request('filter') . "%' OR user_auth.full_name LIKE '%" . get_request_var_request('filter') . "%')";
	}else{
		$sql_where = '';
	}

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='user_admin.php'>\n";

	html_start_box('', '100%', '', '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT
		COUNT(user_auth.id)
		FROM user_auth
		$sql_where");

	$user_list = db_fetch_assoc("SELECT id, user_auth.username, full_name,
		realm, enabled, policy_graphs, policy_hosts, policy_graph_templates,
		time, max(time) as dtime
		FROM user_auth
		LEFT JOIN user_log ON (user_auth.id = user_log.user_id)
		$sql_where
		GROUP BY id
		ORDER BY " . get_request_var_request('sort_column') . ' ' . get_request_var_request('sort_direction') .
		' LIMIT ' . ($rows * (get_request_var_request('page') - 1)) . ',' . $rows);

	$nav = html_nav_bar('user_admin.php?filter=' . get_request_var_request('filter'), MAX_DISPLAY_PAGES, get_request_var_request('page'), $rows, $total_rows, 9, 'Users', 'page', 'main');

	print $nav;

	$display_text = array(
		'username' => array('User Name', 'ASC'),
		'full_name' => array('Full Name', 'ASC'),
		'enabled' => array('Enabled', 'ASC'),
		'realm' => array('Realm', 'ASC'),
		'policy_graphs' => array('Graph Policy', 'ASC'),
		'policy_hosts' => array('Device Policy', 'ASC'),
		'policy_graph_templates' => array('Template Policy', 'ASC'),
		'dtime' => array('Last Login', 'DESC'));

	html_header_sort_checkbox($display_text, get_request_var_request('sort_column'), get_request_var_request('sort_direction'), false);

	if (sizeof($user_list) > 0) {
		foreach ($user_list as $user) {
			if (empty($user['dtime']) || ($user['dtime'] == '12/31/1969')) {
				$last_login = 'N/A';
			}else{
				$last_login = strftime('%A, %B %d, %Y %H:%M:%S ', strtotime($user['dtime']));;
			}
			if ($user['enabled'] == 'on') {
				$enabled = 'Yes';
			}else{
				$enabled = 'No';
			}

			form_alternate_row('line' . $user['id'], true);
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars($config['url_path'] . 'user_admin.php?action=user_edit&tab=general&id=' . $user['id']) . "'>" .
			(strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>",  htmlspecialchars($user['username'])) : htmlspecialchars($user['username']))
			, $user['id']);
			form_selectable_cell((strlen(get_request_var_request('filter')) ? preg_replace('/(' . preg_quote(get_request_var_request('filter')) . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($user['full_name'])) : htmlspecialchars($user['full_name'])), $user['id']);
			form_selectable_cell($enabled, $user['id']);
			form_selectable_cell($auth_realms[$user['realm']], $user['id']);
			form_selectable_cell(($user['policy_graphs'] == 1 ? 'ALLOW':'DENY'), $user['id']);
			form_selectable_cell(($user['policy_hosts'] == 1 ? 'ALLOW':'DENY'), $user['id']);
			form_selectable_cell(($user['policy_graph_templates'] == 1 ? 'ALLOW':'DENY'), $user['id']);
			form_selectable_cell($last_login, $user['id']);
			form_checkbox_cell($user['username'], $user['id']);
			form_end_row();
		}

		print $nav;
	}else{
		print '<tr><td><em>No Users</em></td></tr>';
	}
	html_end_box(false);

	draw_actions_dropdown($user_actions);

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
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clearf'])) {
		kill_session_var('sess_uag_page');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_uag_filter');
		kill_session_var('sess_uag_graph_template_id');
		kill_session_var('sess_uag_associated');
		kill_session_var('sess_uag_sort_column');
		kill_session_var('sess_uag_sort_direction');

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
		$changed += check_changed('filter', 'sess_uag_filter');
		$changed += check_changed('graph_template_id', 'sess_uag_graph_template_id');
		$changed += check_changed('associated', 'sess_uag_associated');
		$changed += check_changed('sort_column', 'sess_uag_sort_column');
		$changed += check_changed('sort_direction', 'sess_uag_sort_direction');
		if ($changed) {
			$_REQUEST['page'] = '1';
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_uag_page', '1');
	load_current_session_value('filter', 'sess_uag_filter', '');
	load_current_session_value('associated', 'sess_uag_associated', 'true');
	load_current_session_value('graph_template_id', 'sess_uag_graph_template_id', '-1');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));
}

function process_group_request_vars() {
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
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clearf'])) {
		kill_session_var('sess_uagr_page');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_uagr_filter');
		kill_session_var('sess_uagr_associated');
		kill_session_var('sess_uagr_sort_column');
		kill_session_var('sess_uagr_sort_direction');

		unset($_REQUEST['page']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['associated']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
	}else{
		$changed = 0;
		$changed += check_changed('rows', 'sess_default_rows');
		$changed += check_changed('filter', 'sess_uagr_filter');
		$changed += check_changed('associated', 'sess_uagr_associated');
		$changed += check_changed('sort_column', 'sess_uagr_sort_column');
		$changed += check_changed('sort_direction', 'sess_uagr_sort_direction');
		if ($changed) {
			$_REQUEST['page'] = '1';
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_uagr_page', '1');
	load_current_session_value('filter', 'sess_uagr_filter', '');
	load_current_session_value('associated', 'sess_uagr_associated', 'true');
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
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clearf'])) {
		kill_session_var('sess_uad_page');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_uad_filter');
		kill_session_var('sess_uad_host_template_id');
		kill_session_var('sess_uad_associated');
		kill_session_var('sess_uad_sort_column');
		kill_session_var('sess_uad_sort_direction');

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
		$changed += check_changed('filter', 'sess_uad_filter');
		$changed += check_changed('host_template_id', 'sess_uad_host_template_id');
		$changed += check_changed('associated', 'sess_uad_associated');
		$changed += check_changed('sort_column', 'sess_uad_sort_column');
		$changed += check_changed('sort_direction', 'sess_uad_sort_direction');
		if ($changed) {
			$_REQUEST['page'] = '1';
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_uad_page', '1');
	load_current_session_value('filter', 'sess_uad_filter', '');
	load_current_session_value('associated', 'sess_uad_associated', 'true');
	load_current_session_value('host_template_id', 'sess_uad_host_template_id', '-1');
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
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clearf'])) {
		kill_session_var('sess_uate_page');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_uate_filter');
		kill_session_var('sess_uate_associated');
		kill_session_var('sess_uate_sort_column');
		kill_session_var('sess_uate_sort_direction');

		unset($_REQUEST['page']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['associated']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
	}else{
		$changed = 0;
		$changed += check_changed('rows', 'sess_default_rows');
		$changed += check_changed('filter', 'sess_uate_filter');
		$changed += check_changed('associated', 'sess_uate_associated');
		$changed += check_changed('sort_column', 'sess_uate_sort_column');
		$changed += check_changed('sort_direction', 'sess_uate_sort_direction');
		if ($changed) {
			$_REQUEST['page'] = '1';
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_uate_page', '1');
	load_current_session_value('filter', 'sess_uate_filter', '');
	load_current_session_value('associated', 'sess_uate_associated', 'true');
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
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clearf'])) {
		kill_session_var('sess_uatr_page');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_uatr_filter');
		kill_session_var('sess_uatr_associated');
		kill_session_var('sess_uatr_sort_column');
		kill_session_var('sess_uatr_sort_direction');

		unset($_REQUEST['page']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['associated']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
	}else{
		$changed = 0;
		$changed += check_changed('rows', 'sess_default_rows');
		$changed += check_changed('filter', 'sess_uatr_filter');
		$changed += check_changed('associated', 'sess_uatr_associated');
		$changed += check_changed('sort_column', 'sess_uatr_sort_column');
		$changed += check_changed('sort_direction', 'sess_uatr_sort_direction');
		if ($changed) {
			$_REQUEST['page'] = '1';
		}

		$reset_multi = false;
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_uatr_page', '1');
	load_current_session_value('filter', 'sess_uatr_filter', '');
	load_current_session_value('associated', 'sess_uatr_associated', 'true');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));
}

function graph_filter($header_label) {
	global $config, $item_rows;

	?>
	<script type="text/javascript">
	<!--

	function applyFilter() {
		strURL = 'user_admin.php?action=user_edit&tab=permsg&id=<?php print get_request_var_request('id');?>'
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

	function clearFilter(objForm) {
		strURL = 'user_admin.php?action=user_edit&tab=permsg&id=<?php print get_request_var_request('id');?>&clearf=true'
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
	-->
	</script>
	<?php

	html_start_box('<strong>Graph Permissions</strong> ' . htmlspecialchars($header_label), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id="forms" method="post" action="user_admin.php">
			<table cellpadding="2" cellspacing="0">
				<tr>
					<td>
						Template
					</td>
					<td>
						<select id='graph_template_id' name="graph_template_id" onChange="applyFilter()">
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
						<input id='filter' type="text" name="filter" size="25" value="<?php print htmlspecialchars(get_request_var_request('filter'));?>" onChange="applyFilter()">
					</td>
					<td>
						Graphs
					</td>
					<td>
						<select id='rows' name="rows" onChange="applyFilter()">
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
						<label style='white-space:nowrap;' for='associated'>Show All</label>
					</td>
					<td nowrap>
						<input type="button" value="Go" onClick='applyFilter()' title="Set/Refresh Filters">
					</td>
					<td nowrap>
						<input type="button" name="clearf" value="Clear" onClick='clearFilter()' title="Clear Filters">
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' name='page' value='<?php print $_REQUEST['page'];?>'>
			<input type='hidden' name='action' value='user_edit'>
			<input type='hidden' name='tab' value='permsg'>
			<input type='hidden' name='id' value='<?php print get_request_var_request('id');?>'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();
}

function group_filter($header_label) {
	global $config, $item_rows;

	?>
	<script type="text/javascript">
	function applyFilter() {
		strURL = 'user_admin.php?action=user_edit&tab=permsgr&id=<?php print get_request_var_request('id');?>'
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
		strURL = 'user_admin.php?action=user_edit&tab=permsgr&id=<?php print get_request_var_request('id');?>&clearf=true'
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

	html_start_box('<strong>Group Membership</strong> ' . htmlspecialchars($header_label), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id="forms" method="post" action="user_admin.php">
			<table cellpadding="2" cellspacing="0" border="0">
				<tr>
					<td>
						Search
					</td>
					<td>
						<input id='filter' type="text" name="filter" size="25" value="<?php print htmlspecialchars(get_request_var_request('filter'));?>" onChange="applyFilter()">
					</td>
					<td>
						Groups
					</td>
					<td>
						<select id='rows' name="rows" onChange="applyFilter()">
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
						<label style='white-space:nowrap;' for='associated'>Show All</label>
					</td>
					<td>
						<input type="button" value="Go" onClick='applyFilter()' title="Set/Refresh Filters">
					</td>
					<td>
						<input type="button" name="clearf" value="Clear" onClick='clearFilter()' title="Clear Filters">
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' name='page' value='<?php print $_REQUEST['page'];?>'>
			<input type='hidden' name='action' value='user_edit'>
			<input type='hidden' name='tab' value='permsgr'>
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
		strURL = 'user_admin.php?action=user_edit&tab=permsd&id=<?php print get_request_var_request('id');?>'
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

	function clearFilter(objForm) {
		strURL = 'user_admin.php?action=user_edit&tab=permsd&id=<?php print get_request_var_request('id');?>&clearf=true'
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
		<form id="forms" method="post" action="user_admin.php">
			<table cellpadding="2" cellspacing="0" border="0">
				<tr>
					<td>
						Template
					</td>
					<td>
						<select id='host_template_id' name="host_template_id" onChange="applyFilter()">
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
						<input id='filter' type="text" name="filter" size="25" value="<?php print htmlspecialchars(get_request_var_request('filter'));?>" onChange="applyFilter()">
					</td>
					<td>
						Devices
					</td>
					<td>
						<select id='rows' name="rows" onChange="applyFilter()">
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
						<input type="button" value="Go" onClick='applyFilter()' title="Set/Refresh Filters">
					</td>
					<td>
						<input type="button" name="clearf" value="Clear" onClick='clearFilter()' title="Clear Filters">
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' name='page' value='<?php print $_REQUEST['page'];?>'>
			<input type='hidden' name='action' value='user_edit'>
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
		strURL = 'user_admin.php?action=user_edit&tab=permste&id=<?php print get_request_var_request('id');?>'
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
		strURL = 'user_admin.php?action=user_edit&tab=permste&id=<?php print get_request_var_request('id');?>&clearf=true'
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
		<form id="forms" method="post" action="user_admin.php">
			<table cellpadding="2" cellspacing="0">
				<tr>
					<td width='50'>
						Search
					</td>
					<td>
						<input id='filter' type="text" name="filter" size="25" value="<?php print htmlspecialchars(get_request_var_request('filter'));?>" onChange="applyFilter()">
					</td>
					<td>
						Templates
					</td>
					<td>
						<select id='rows' name="rows" onChange="applyFilter()">
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
						<input type="button" value="Go" onClick='applyFilter()' title="Set/Refresh Filters">
					</td>
					<td>
						<input type="button" name="clearf" value="Clear" onClick='clearFilter()' title="Clear Filters">
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' name='page' value='<?php print $_REQUEST['page'];?>'>
			<input type='hidden' name='action' value='user_edit'>
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
		strURL = 'user_admin.php?action=user_edit&tab=permstr&id=<?php print get_request_var_request('id');?>'
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
		strURL = 'user_admin.php?action=user_edit&tab=permstr&id=<?php print get_request_var_request('id');?>&clearf=true'
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
		<form id="forms" method="post" action="user_admin.php">
			<table cellpadding="2" cellspacing="0">
				<tr>
					<td width='50'>
						Search
					</td>
					<td>
						<input id='filter' type="text" name="filter" size="25" value="<?php print htmlspecialchars(get_request_var_request('filter'));?>" onChange="applyFilter()">
					</td>
					<td>
						Trees
					</td>
					<td>
						<select id='rows' name="rows" onChange="applyFilter()">
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
						<input type="button" id='refresh' value="Go" onClick='applyFilter()' title="Set/Refresh Filters">
					</td>
					<td>
						<input type="button" id='clear' name="clearf" value="Clear" onClick='clearFilter()' title="Clear Filters">
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' name='page' value='<?php print $_REQUEST['page'];?>'>
			<input type='hidden' name='action' value='user_edit'>
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
	function applyFilter(objForm) {
		strURL = 'user_admin.php?action=user_edit&tab=members&id=<?php print get_request_var_request('id');?>'
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

	function clearFilter(objForm) {
		strURL = 'user_admin.php?action=user_edit&tab=members&id=<?php print get_request_var_request('id');?>&clearf=true'
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
		<form id="forms" method="post" action="user_admin.php">
			<table cellpadding="2" cellspacing="0" border="0">
				<tr>
					<td width='50'>
						Search
					</td>
					<td>
						<input id='filter' type="text" name="filter" size="25" value="<?php print htmlspecialchars(get_request_var_request('filter'));?>" onChange="applyFilter()">
					</td>
					<td>
						Trees
					</td>
					<td>
						<select id='rows' name="rows" onChange="applyFilter()">
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
						<input type="button" id='refresh' value="Go" onClick='applyFilter()' title="Set/Refresh Filters">
					</td>
					<td>
						<input type="button" id='clear' name="clearf" value="Clear" onClick='clearFilter()' title="Clear Filters">
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' name='page' value='<?php print $_REQUEST['page'];?>'>
			<input type='hidden' name='action' value='user_edit'>
			<input type='hidden' name='tab' value='members'>
			<input type='hidden' name='id' value='<?php print get_request_var_request('id');?>'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();
}

