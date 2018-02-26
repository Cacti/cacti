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

include('./include/auth.php');

$user_actions = array(
	1 => __('Delete'),
	2 => __('Copy'),
	3 => __('Enable'),
	4 => __('Disable'),
	5 => __('Batch Copy')
);

set_default_action();

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
	case 'user_edit':
		top_header();
		user_edit();
		bottom_footer();

		break;
	case 'checkpass':
		$error = secpass_check_pass(get_nfilter_request_var('password'));

		if ($error == '') {
			print $error;
		} else {
			print 'ok';
		}

		break;
	default:
		if (!api_plugin_hook_function('user_admin_action', get_request_var('action'))) {
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

	$set .= isset_request_var('policy_graphs') ? 'policy_graphs=' . get_nfilter_request_var('policy_graphs'):'';
	$set .= isset_request_var('policy_trees') ? ($set != '' ? ',':'') . 'policy_trees=' . get_nfilter_request_var('policy_trees'):'';
	$set .= isset_request_var('policy_hosts') ? ($set != '' ? ',':'') . 'policy_hosts=' . get_nfilter_request_var('policy_hosts'):'';
	$set .= isset_request_var('policy_graph_templates') ? ($set != '' ? ',':'') . 'policy_graph_templates=' . get_nfilter_request_var('policy_graph_templates'):'';

	if ($set != '') {
		db_execute_prepared("UPDATE user_auth SET $set WHERE id = ?", array(get_nfilter_request_var('id')));
	}

	header('Location: user_admin.php?action=user_edit&header=false&tab=' .  get_nfilter_request_var('tab') . '&id=' . get_nfilter_request_var('id'));
	exit;
}

function form_actions() {
	global $user_actions, $auth_realms;

	/* if we are to save this form, instead of display it */
	if (isset_request_var('associate_host')) {
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				if (get_nfilter_request_var('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_perms
						(user_id, item_id, type)
						VALUES (?, ?, 3)',
						array(get_nfilter_request_var('id'), $matches[1]));
				} else {
					db_execute_prepared('DELETE FROM user_auth_perms
						WHERE user_id = ?
						AND item_id = ?
						AND type = 3',
						array(get_nfilter_request_var('id'), $matches[1]));
				}
			}
		}

		header('Location: user_admin.php?action=user_edit&header=false&tab=permsd&id=' . get_nfilter_request_var('id'));
		exit;
	} elseif (isset_request_var('associate_graph')) {
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				if (get_nfilter_request_var('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_perms
						(user_id, item_id, type)
						VALUES (?, ?, 1)',
						array(get_nfilter_request_var('id'), $matches[1]));
				} else {
					db_execute_prepared('DELETE FROM user_auth_perms
						WHERE user_id = ?
						AND item_id = ?
						AND type = 1',
						array(get_nfilter_request_var('id'), $matches[1]));
				}
			}
		}

		header('Location: user_admin.php?action=user_edit&header=false&tab=permsg&id=' . get_nfilter_request_var('id'));
		exit;
	} elseif (isset_request_var('associate_template')) {
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				if (get_nfilter_request_var('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_perms
						(user_id, item_id, type)
						VALUES (?, ?, 4)',
						array(get_nfilter_request_var('id'), $matches[1]));
				} else {
					db_execute_prepared('DELETE FROM user_auth_perms
						WHERE user_id = ?
						AND item_id = ?
						AND type = 4',
						array(get_nfilter_request_var('id'), $matches[1]));
				}
			}
		}

		header('Location: user_admin.php?action=user_edit&header=false&tab=permste&id=' . get_nfilter_request_var('id'));
		exit;
	} elseif (isset_request_var('associate_groups')) {
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				if (get_nfilter_request_var('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_group_members
						(user_id, group_id)
						VALUES (?, ?)',
						array(get_nfilter_request_var('id'), $matches[1]));
				} else {
					db_execute_prepared('DELETE FROM user_auth_group_members
						WHERE user_id = ?
						AND group_id = ?',
						array(get_nfilter_request_var('id'), $matches[1]));
				}
			}
		}

		header('Location: user_admin.php?action=user_edit&header=false&tab=permsgr&id=' . get_nfilter_request_var('id'));
		exit;
	} elseif (isset_request_var('associate_tree')) {
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */

				if (get_nfilter_request_var('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_perms
						(user_id, item_id, type)
						VALUES (?, ?, 2)',
						array(get_nfilter_request_var('id'), $matches[1]));
				} else {
					db_execute_prepared('DELETE FROM user_auth_perms
						WHERE user_id = ?
						AND item_id = ?
						AND type = 2',
						array(get_nfilter_request_var('id'), $matches[1]));
				}
			}
		}

		header('Location: user_admin.php?action=user_edit&header=false&tab=permstr&id=' . get_nfilter_request_var('id'));
		exit;
	} elseif (isset_request_var('selected_items')) {
		if (get_nfilter_request_var('drp_action') == '2') { /* copy */
			/* ================= input validation ================= */
			get_filter_request_var('selected_items');
			get_filter_request_var('new_realm');
			/* ==================================================== */

			$new_username  = get_nfilter_request_var('new_username');
			$new_realm     = get_nfilter_request_var('new_realm', 0);

			$template_user = db_fetch_row_prepared('SELECT username, realm
				FROM user_auth
				WHERE id = ?',
				array(get_nfilter_request_var('selected_items')));

			$overwrite     = array( 'full_name' => get_nfilter_request_var('new_fullname') );

			if ($new_username != '') {
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
		} else {
			$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

			if ($selected_items != false) {
				if (get_nfilter_request_var('drp_action') == '1') { // delete
					for ($i=0;($i<count($selected_items));$i++) {
						user_remove($selected_items[$i]);

						api_plugin_hook_function('user_remove', $selected_items[$i]);
					}
				} elseif (get_nfilter_request_var('drp_action') == '3') { // enable
					for ($i=0;($i<count($selected_items));$i++) {
						user_enable($selected_items[$i]);
					}
				} elseif (get_nfilter_request_var('drp_action') == '4') { // disable
					for ($i=0;($i<count($selected_items));$i++) {
						user_disable($selected_items[$i]);
					}
				} elseif (get_nfilter_request_var('drp_action') == '5') { // batch copy
					/* ================= input validation ================= */
					get_filter_request_var('template_user');
					/* ==================================================== */

					$copy_error = false;
					$template = db_fetch_row_prepared('SELECT username, realm
						FROM user_auth
						WHERE id = ?',
						array(get_nfilter_request_var('template_user')));

					for ($i=0;($i<count($selected_items));$i++) {
						$user = db_fetch_row_prepared('SELECT username, realm
							FROM user_auth
							WHERE id = ?',
							array($selected_items[$i]));

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
			}
		}

		header('Location: user_admin.php?header=false');
		exit;
	}

	/* loop through each of the users and process them */
	$user_list = '';
	$user_array = array();
	$i = 0;
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			if (get_nfilter_request_var('drp_action') != '2') {
				$user_list .= '<li>' . htmlspecialchars(db_fetch_cell_prepared('SELECT username FROM user_auth WHERE id = ?', array($matches[1]))) . '</li>';
			}
			$user_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('user_admin.php');

	html_start_box($user_actions[get_nfilter_request_var('drp_action')], '40%', '', '3', 'center', '');

	if (isset($user_array) && sizeof($user_array)) {
		if ((get_nfilter_request_var('drp_action') == '1') && (sizeof($user_array))) { // delete
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to delete the selected User(s).') . "</p>
					<div class='itemlist'><ul>$user_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'><input type='submit' value='" . __esc('Continue') . "' title='" . __esc('Delete User(s)') . "'>";
		}
		$user_id = '';

		if ((get_nfilter_request_var('drp_action') == '2') && (sizeof($user_array))) { // copy
			$user_id = $user_array[0];
			$user_realm = db_fetch_cell_prepared('SELECT realm FROM user_auth WHERE id = ?', array($user_id));

			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to copy the selected User to a new User below.') . "</p>
				</td>
			</tr>
			<tr>
				<td class='textArea'>
					<p>" . __('Template Username:') . " <i>" . htmlspecialchars(db_fetch_cell_prepared('SELECT username FROM user_auth WHERE id = ?', array($user_id))) . "</i></p>
				</td>
			</tr>
			<tr>
				<td class='textArea'>
					<p>" . __('Username:') . " ";
			print form_text_box('new_username', '', '', 25);

			print "</p></td>
				</tr>
				<tr>
					<td class='textArea'>
						<p>" . __('Full Name:') . " ";
			print form_text_box('new_fullname', '', '', 35);

			print "</p></td>
				</tr>
				<tr>
					<td class='textArea'>
						<p>" . __('Realm:') ." ";
			print form_dropdown('new_realm', $auth_realms, '', '', $user_realm, '', 0);

			print "</p></td>
				</tr>\n";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc('Copy User') . "'>";
		}

		if ((get_nfilter_request_var('drp_action') == '3') && (sizeof($user_array))) { // enable
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to enable the selected User(s).'). "</p>
					<div class='itemlist'><ul>$user_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc('Enable User(s)') . "'>";
		}

		if ((get_nfilter_request_var('drp_action') == '4') && (sizeof($user_array))) { // disable
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to disable the selected User(s).') . "</p>
					<div class='itemlist'><ul>$user_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc('Disable User(s)') . "'>";
		}

		if ((get_nfilter_request_var('drp_action') == '5') && (sizeof($user_array))) { // batch copy
			$usernames = db_fetch_assoc('SELECT id, username FROM user_auth WHERE realm = 0 ORDER BY username');

			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to overwrite the User(s) settings with the selected template User settings and permissions.  The original users Full Name, Password, Realm and Enable status will be retained, all other fields will be overwritten from Template User.') . "<br><br></td>
				</tr><tr>
					<td class='textArea'>
						<p>" . __('Template User:') . " ";
			print form_dropdown('template_user', $usernames, 'username', 'id', '', '', 0);

			print "</p></td>
				</tr><tr>
					<td class='textArea'>
						<p>" . __('User(s) to update:') . "</p>
						<p><ul>$user_list</ul></p>
					</td>
				</tr>\n";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc('Reset User(s) Settings') . "'>";
		}
	} else {
		print "<tr><td class='even'><span class='textError'>" . __('You must select at least one user.') . "</span></td></tr>\n";

		$save_html = "<input type='button' value='" . __esc('Return') . "' onClick='cactiReturnTo()'>";
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>";

	if (get_nfilter_request_var('drp_action') == '2') { // copy
		print "<input type='hidden' name='selected_items' value='" . $user_id . "'>\n";
	} else {
		print "<input type='hidden' name='selected_items' value='" . (isset($user_array) ? serialize($user_array) : '') . "'>\n";
	}

	print "<input type='hidden' name='drp_action' value='" . get_nfilter_request_var('drp_action') . "'>
		$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

/* --------------------------
    Save Function
   -------------------------- */

function form_save() {
	global $settings_user;

	/* graph permissions */
	if ((isset_request_var('save_component_graph_perms')) && (!is_error_message())) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		get_filter_request_var('perm_graphs');
		get_filter_request_var('perm_trees');
		get_filter_request_var('perm_hosts');
		get_filter_request_var('perm_graph_templates');
		get_filter_request_var('policy_graphs');
		get_filter_request_var('policy_trees');
		get_filter_request_var('policy_hosts');
		get_filter_request_var('policy_graph_templates');
		/* ==================================================== */

		$add_button_clicked = false;

		if (isset_request_var('add_graph_x')) {
			db_execute_prepared('REPLACE INTO user_auth_perms
				(user_id,item_id,type)
				VALUES (?, ?, 1)',
				array(get_nfilter_request_var('id'), get_nfilter_request_var('perm_graphs')));

			$add_button_clicked = true;
		} elseif (isset_request_var('add_tree_x')) {
			db_execute_prepared('REPLACE INTO user_auth_perms
				(user_id,item_id,type)
				VALUES (?, ?, 2)',
				array(get_nfilter_request_var('id'), get_nfilter_request_var('perm_trees')));

			$add_button_clicked = true;
		} elseif (isset_request_var('add_host_x')) {
			db_execute_prepared('REPLACE INTO user_auth_perms
				(user_id,item_id,type)
				VALUES (?, ?, 3)',
				array(get_nfilter_request_var('id'), get_nfilter_request_var('perm_hosts')));

			$add_button_clicked = true;
		} elseif (isset_request_var('add_graph_template_x')) {
			db_execute_prepared('REPLACE INTO user_auth_perms
				(user_id,item_id,type)
				VALUES (?, ?, 4)',
				array(get_nfilter_request_var('id'), get_nfilter_request_var('perm_graph_templates')));

			$add_button_clicked = true;
		}

		if ($add_button_clicked == true) {
			header('Location: user_admin.php?action=user_edit&header=false&tab=graph_perms_edit&id=' . get_nfilter_request_var('id'));
			exit;
		}
	} elseif (isset_request_var('save_component_user')) {
		/* user management save */
		/* ================= input validation ================= */
		get_filter_request_var('id');
		get_filter_request_var('realm');
		get_filter_request_var('policy_hosts');
		get_filter_request_var('policy_graphs');
		get_filter_request_var('policy_trees');
		get_filter_request_var('policy_graph_templates');
		/* ==================================================== */

		if ((get_nfilter_request_var('password') == '') && (get_nfilter_request_var('password_confirm') == '')) {
			$password = db_fetch_cell_prepared('SELECT password
				FROM user_auth
				WHERE id = ?',
				array(get_nfilter_request_var('id')));
		} else {
			$password = compat_password_hash(get_nfilter_request_var('password'), PASSWORD_DEFAULT);
		}

		/* check duplicate username */
		if (sizeof(db_fetch_row_prepared('SELECT * FROM user_auth WHERE realm = ? AND username = ? AND id != ?', array(get_nfilter_request_var('realm'), get_nfilter_request_var('username'), get_nfilter_request_var('id'))))) {
			raise_message(12);
		}

		/* check for guest or template user */
		$username = db_fetch_cell_prepared('SELECT username FROM user_auth WHERE id = ?', array(get_nfilter_request_var('id')));
		$history  = db_fetch_cell_prepared('SELECT password_history FROM user_auth WHERE id = ?', array(get_nfilter_request_var('id')));

		if ($username != '' && $username != get_nfilter_request_var('username')) {
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
		if (get_nfilter_request_var('password') != get_nfilter_request_var('password_confirm')) {
			raise_message(4);
		}

		if (get_nfilter_request_var('must_change_password') == 'on' && get_nfilter_request_var('password_change') != 'on') {
			raise_message('password_change');
		}

		$save['id']                   = get_nfilter_request_var('id');
		$save['username']             = form_input_validate(get_nfilter_request_var('username'), 'username', "^[A-Za-z0-9\._\\\@\ -]+$", false, 3);
		$save['full_name']            = form_input_validate(get_nfilter_request_var('full_name'), 'full_name', '', true, 3);
		$save['password']             = $password;
		$save['must_change_password'] = form_input_validate(get_nfilter_request_var('must_change_password', ''), 'must_change_password', '', true, 3);
		$save['password_change']      = form_input_validate(get_nfilter_request_var('password_change', ''), 'password_change', '', true, 3);
		$save['show_tree']            = form_input_validate(get_nfilter_request_var('show_tree', ''), 'show_tree', '', true, 3);
		$save['show_list']            = form_input_validate(get_nfilter_request_var('show_list', ''), 'show_list', '', true, 3);
		$save['show_preview']         = form_input_validate(get_nfilter_request_var('show_preview', ''), 'show_preview', '', true, 3);
		$save['graph_settings']       = form_input_validate(get_nfilter_request_var('graph_settings', ''), 'graph_settings', '', true, 3);
		$save['login_opts']           = form_input_validate(get_nfilter_request_var('login_opts'), 'login_opts', '', true, 3);
		$save['realm']                = get_nfilter_request_var('realm', 0);
		$save['password_history']     = $history;
		$save['enabled']              = form_input_validate(get_nfilter_request_var('enabled', ''), 'enabled', '', true, 3);
		$save['email_address']        = form_input_validate(get_nfilter_request_var('email_address', ''), 'email_address', '', true, 3);
		$save['locked']               = form_input_validate(get_nfilter_request_var('locked', ''), 'locked', '', true, 3);
		$save['reset_perms']          = mt_rand();
		if ($save['locked'] == '') {
			$save['failed_attempts'] = 0;
		}

		$save = api_plugin_hook_function('user_admin_setup_sql_save', $save);

		if (!is_error_message()) {
			$user_id = sql_save($save, 'user_auth');

			if ($user_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}
	} elseif (isset_request_var('save_component_realm_perms')) {
		db_execute_prepared('DELETE FROM user_auth_realm WHERE user_id = ?', array(get_nfilter_request_var('id')));

		foreach ($_POST as $var => $val) {
			if (preg_match('/^[section]/i', $var)) {
				if (substr($var, 0, 7) == 'section') {
					db_execute_prepared('REPLACE INTO user_auth_realm
						(user_id, realm_id)
						VALUES (?, ?)',
						array(get_nfilter_request_var('id'), substr($var, 7)));
				}
			}
		}

		reset_user_perms(get_nfilter_request_var('id'));

		raise_message(1);
	} elseif (isset_request_var('save_component_graph_settings')) {
		foreach ($settings_user as $tab_short_name => $tab_fields) {
			foreach ($tab_fields as $field_name => $field_array) {
				if ((isset($field_array['items'])) && (is_array($field_array['items']))) {
					foreach ($field_array['items'] as $sub_field_name => $sub_field_array) {
						db_execute_prepared('REPLACE INTO settings_user
							(user_id, name, value)
							VALUES (?, ?, ?)',
							array((!empty($user_id) ? $user_id : get_filter_request_var('id')), $sub_field_name, get_nfilter_request_var($sub_field_name, '')));
					}
				} else {
					db_execute_prepared('REPLACE INTO settings_user
						(user_id, name, value)
						VALUES (?, ?, ?)',
						array((!empty($user_id) ? $user_id : get_filter_request_var('id')), $field_name, get_nfilter_request_var($field_name)));
				}
			}
		}

		/* reset local settings cache so the user sees the new settings */
		kill_session_var('sess_user_config_array');

		reset_user_perms(get_request_var('id'));

		raise_message(1);
	} elseif (isset_request_var('save_component_graph_perms')) {
		db_execute_prepared('UPDATE user_auth
			SET policy_graphs = ?, policy_trees = ?, policy_hosts = ?, policy_graph_templates = ?
			WHERE id = ?',
			array(get_nfilter_request_var('policy_graphs'), get_nfilter_request_var('policy_trees'), get_nfilter_request_var('policy_hosts'), get_nfilter_request_var('policy_graph_templates'), get_nfilter_request_var('id')));
	} else {
		api_plugin_hook('user_admin_user_save');

		reset_user_perms(get_filter_request_var('id'));
	}

	/* redirect to the appropriate page */
	header('Location: user_admin.php?action=user_edit&header=false&id=' . (empty($user_id) ? get_filter_request_var('id') : $user_id));
}

/* --------------------------
    Graph Permissions
   -------------------------- */

function perm_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('user_id');
	/* ==================================================== */

	if (get_request_var('type') == 'graph') {
		db_execute_prepared('DELETE FROM user_auth_perms
			WHERE type = 1
			AND user_id = ?
			AND item_id = ?',
			array(get_request_var('user_id'), get_request_var('id')));
	} elseif (get_request_var('type') == 'tree') {
		db_execute_prepared('DELETE FROM user_auth_perms
			WHERE type = 2
			AND user_id = ?
			AND item_id = ?',
			array(get_request_var('user_id'), get_request_var('id')));
	} elseif (get_request_var('type') == 'host') {
		db_execute_prepared('DELETE FROM user_auth_perms
			WHERE type = 3
			AND user_id = ?
			AND item_id = ?',
			array(get_request_var('user_id'), get_request_var('id')));
	} elseif (get_request_var('type') == 'graph_template') {
		db_execute_prepared('DELETE FROM user_auth_perms
			WHERE type = 4
			AND user_id = ?
			AND item_id = ?',
			array(get_request_var('user_id'), get_request_var('id')));
	}

	header('Location: user_admin.php?action=user_edit&header=false&tab=graph_perms_edit&id=' . get_request_var('user_id'));
}

function get_permission_string(&$graph, &$policies) {
	$grantStr  = '';
	$rejectStr = '';

	if (read_config_option('graph_auth_method') == 1) {
		$method = 'loose';
	} else {
		$method = 'strong';
	}

	$i = 1;
	foreach($policies as $p) {
		$allowed  = 0;
		$rejected = 0;

		if ($p['policy_graphs'] == 1) {
			if ($graph["user$i"] == '') {
				$grantStr  = $grantStr . ($grantStr != '' ? ', ':'') . 'Graph:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
			} else {
				$rejectStr = $rejectStr . ($rejectStr != '' ? ', ':'') . 'Graph:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
			}
		} elseif ($graph["user$i"] != '') {
			$grantStr = $grantStr . ($grantStr != '' ? ', ':'') . 'Graph:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
		} elseif ($method == 'loose') {
			$rejected++;
		} else {
			$rejectStr = $rejectStr . ($rejectStr != '' ? ', ':'') . 'Graph:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
		}
		$i++;

		if ($p['policy_hosts'] == 1) {
			if ($graph["user$i"] == '') {
				if ($method == 'loose') {
					$grantStr = $grantStr . ($grantStr != '' ? ', ':'') . 'Device:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
				} else {
					$allowed++;
				}
			} else {
				$rejectStr = $rejectStr . ($rejectStr != '' ? ', ':'') . 'Device:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
			}
		} elseif ($graph["user$i"] != '') {
			if ($method == 'loose') {
				$grantStr = $grantStr . ($grantStr != '' ? ', ':'') . 'Device:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
			} else {
				$allowed++;
			}
		} elseif ($method == 'loose') {
			$rejected++;
		}
		$i++;

		if ($p['policy_graph_templates'] == 1) {
			if ($graph["user$i"] == '') {
				if ($method == 'loose') {
					$grantStr = $grantStr . ($grantStr != '' ? ', ':'') . 'Template:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
				} else {
					$allowed++;
				}
			} else {
				$rejectStr = $rejectStr . ($rejectStr != '' ? ', ':'') . 'Template:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
			}
		} elseif ($graph["user$i"] != '') {
			if ($method == 'loose') {
				$grantStr = $grantStr . ($grantStr != '' ? ', ':'') . 'Template:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
			} else {
				$allowed++;
			}
		} elseif ($method == 'loose') {
			$rejected++;
		}
		$i++;

		if ($method != 'loose') {
			if ($allowed == 2) {
				$grantStr = $grantStr . ($grantStr != '' ? ', ':'') . 'Device+Template:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
			} else {
				$rejectStr = $rejectStr . ($rejectStr != '' ? ', ':'') . 'Device+Template:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
			}
		} elseif ($rejected == 3) {
			$rejectStr = $rejectStr . ($rejectStr != '' ? ', ':'') . 'Graph+Device+Template:(' . ucfirst($p['type']) . ($p['type'] != 'user' ? '/' . $p['name'] . ')':')');
		}
	}

	$permStr = '';
	if ($grantStr != '') {
		$permStr = "<span class='accessGranted'>Granted:</span> <span class='accessGrantedItem'>" . trim($grantStr,',') . '</span>';
	}

	if ($rejectStr != '') {
		if ($grantStr == '') {
			$permStr = "<span class='accessRestricted'>Restricted:</span> <span class='accessRestrictedItem'>" . trim($rejectStr,',') . '</span>';
		} else {
			$permStr .= ", <span class='accessRestrictedItem'>" . trim($rejectStr,',') . '</span>';
		}
	}

	return $permStr;
}

function graph_perms_edit($tab, $header_label) {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$sql_where  = '';
	$sql_join   = '';
	$limit      = '';
	$sql_having = '';

	$policy_array = array(
		1 => __('Allow'),
		2 => __('Deny')
	);

	if (!isempty_request_var('id')) {
		$policy = db_fetch_row_prepared('SELECT policy_graphs, policy_trees, policy_hosts, policy_graph_templates
			FROM user_auth
			WHERE id = ?', array(get_request_var('id')));
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

		form_start('user_admin.php', 'policy');

		if (read_config_option('graph_auth_method') == 1) {
			$policy_note = __('<b>Note:</b> System Graph Policy is \'Permissive\' meaning the User must have access to at least one of Graph, Device, or Graph Template to gain access to the Graph');
		} else {
			$policy_note = __('<b>Note:</b> System Graph Policy is \'Restrictive\' meaning the User must have access to the Graph, Device, and Graph Template to gain access to the Graph');
		}

		/* box: device permissions */
		html_start_box(__('Default Graph Policy'), '100%', '', '3', 'center', '');

		?>
		<tr class='even'>
			<td><table><tr>
			<td class='nowrap'><?php print __('Default Graph Policy for this User');?></td>
			<td>
				<?php form_dropdown('policy_graphs',$policy_array,'','',$policy['policy_graphs'],'',''); ?>
			</td>
			<td>
				<input type='submit' name='update_policy' value='<?php print __esc('Update');?>'>
				<input type='hidden' name='tab' value='<?php print $tab;?>'>
				<input type='hidden' name='id' value='<?php print get_request_var('id');?>'>
				<input type='hidden' name='update_policy' value='1'>
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

		$user_id = get_request_var('id');

		if (read_config_option('graph_auth_method') == 1) {
			$sql_operator = 'OR';
		} else {
			$sql_operator = 'AND';
		}

		$limit = 'LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

		/* get policies for all groups and user */
		$policies   = db_fetch_assoc_prepared("SELECT uag.id, 'group' AS type, uag.name,
			policy_graphs, policy_hosts, policy_graph_templates
			FROM user_auth_group AS uag
			INNER JOIN user_auth_group_members AS uagm
			ON uag.id = uagm.group_id
			WHERE uag.enabled = 'on' AND uagm.user_id = ?",
			array($user_id));

		$policies[] = db_fetch_row_prepared("SELECT id, 'user' AS type, 'user' AS name,
			policy_graphs, policy_hosts, policy_graph_templates
			FROM user_auth WHERE id = ?",
			array($user_id));

		array_reverse($policies);

		/* form the 'where' clause for our main sql query */
		if (get_request_var('filter') != '') {
			$sql_where = "WHERE (gtg.title_cache LIKE '%" . get_request_var('filter') . "%' AND gtg.local_graph_id > 0)";
		} else {
			$sql_where = 'WHERE (gtg.local_graph_id > 0)';
		}

		if (get_request_var('graph_template_id') == '-1') {
			/* Show all items */
		} elseif (get_request_var('graph_template_id') == '0') {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' gtg.graph_template_id=0';
		} elseif (!isempty_request_var('graph_template_id')) {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' gtg.graph_template_id=' . get_request_var('graph_template_id');
		}

		$i = 1;
		$user_perm = '';
		$sql_select = '';
		foreach($policies as $policy) {
			if ($policy['type'] == 'user' && $user_perm == '') {
				$user_perm = $i;
			}

			if (get_request_var('associated') == 'false') {
				if ($policy['policy_graphs'] == 1) {
					$sql_having .= ($sql_having != '' ? ' OR ':'') . " (user$i IS NULL";
				} else {
					$sql_having .= ($sql_having != '' ? ' OR ':'') . " (user$i IS NOT NULL";
				}
			}

			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.id=uap$i.item_id AND uap$i.type=1 AND uap$i." . $policy['type'] . '_id=' . get_request_var('id') . ') ';
			$sql_select .= ($sql_select != '' ? ', ':'') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if (get_request_var('associated') == 'false') {
				if ($policy['policy_hosts'] == 1) {
					$sql_having .= " OR (user$i IS NULL";
				} else {
					$sql_having .= " OR (user$i IS NOT NULL";
				}
			}

			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.host_id=uap$i.item_id AND uap$i.type=3 AND uap$i." . $policy['type'] . '_id=' . get_request_var('id') . ') ';
			$sql_select .= ($sql_select != '' ? ', ':'') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if (get_request_var('associated') == 'false') {
				if ($policy['policy_graph_templates'] == 1) {
					$sql_having .= " $sql_operator user$i IS NULL))";
				} else {
					$sql_having .= " $sql_operator user$i IS NOT NULL))";
				}
			}

			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.graph_template_id=uap$i.item_id AND uap$i.type=4 AND uap$i." . $policy['type'] . '_id=' . get_request_var('id') . ') ';
			$sql_select .= ($sql_select != '' ? ', ':'') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;
		}

		if ($sql_having != '') {
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

		$nav = html_nav_bar('user_admin.php?action=user_edit&tab=permsg&id=' . get_request_var('id'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, __('Graphs'), 'page', 'main');

		form_start(htmlspecialchars('user_admin.php?tab=permsg&id=' . get_request_var('id')), 'chk');

		print $nav;

		html_start_box('', '100%', '', '3', 'center', '');

		$display_text = array(__('Graph Title'), __('ID'), __('Effective Policy'));

		html_header_checkbox($display_text, false);

		if (sizeof($graphs)) {
			foreach ($graphs as $g) {
				form_alternate_row('line' . $g['local_graph_id'], true);
				form_selectable_cell(filter_value($g['title_cache'], get_request_var('filter')), $g['local_graph_id']);
				form_selectable_cell($g['local_graph_id'], $g['local_graph_id']);
				form_selectable_cell(get_permission_string($g, $policies), $g['local_graph_id']);
				form_checkbox_cell($g['title_cache'], $g['local_graph_id']);
				form_end_row();
			}
		} else {
			print '<tr><td><em>' . __('No Matching Graphs Found') . '</em></td></tr>';
		}

		html_end_box(false);

		if (sizeof($graphs)) {
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

		/* draw the dropdown containing a list of available actions for this form */
		draw_actions_dropdown($assoc_actions);

		form_end();

		break;
	case 'permsgr':
		process_group_request_vars();

		group_filter($header_label);

		/* if the number of rows is -1, set it to the default */
		if (get_request_var('rows') == -1) {
			$rows = read_config_option('num_rows_table');
		} else {
			$rows = get_request_var('rows');
		}

		/* form the 'where' clause for our main sql query */
		if (get_request_var('filter') != '') {
			$sql_where = "WHERE (uag.name LIKE '%" . get_request_var('filter') . "%' OR uag.description LIKE '%" . get_request_var('filter') . "%')";
		} else {
			$sql_where = '';
		}

		if (get_request_var('associated') != 'false') {
			/* Show all items */
		} else {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' uagm.user_id=' . get_request_var('id');
		}

		$total_rows = db_fetch_cell("SELECT
			COUNT(DISTINCT uag.id)
			FROM user_auth_group AS uag
			LEFT JOIN (SELECT user_id, group_id FROM user_auth_group_members WHERE user_id=" . get_request_var('id') . ") AS uagm
			ON uag.id = uagm.group_id
			$sql_where");

		$sql_query = "SELECT DISTINCT uag.*, uagm.user_id
			FROM user_auth_group AS uag
			LEFT JOIN (SELECT user_id, group_id FROM user_auth_group_members WHERE user_id=" . get_request_var('id') . ") AS uagm
			ON uag.id = uagm.group_id
			$sql_where
			ORDER BY name
			LIMIT " . ($rows*(get_request_var('page')-1)) . ',' . $rows;

		$groups = db_fetch_assoc($sql_query);

		$nav = html_nav_bar('user_admin.php?action=user_edit&tab=permsgr&id=' . get_request_var('id'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, __('Groups'), 'page', 'main');

		form_start(htmlspecialchars('user_admin.php?tab=permsd&id=' . get_request_var('id')), 'chk');

		print $nav;

		html_start_box('', '100%', '', '3', 'center', '');

		$display_text = array(__('Name'), __('Description'), __('Member'), __('ID'), __('Policies (Graph/Device/Template)'), __('Enabled'));

		html_header_checkbox($display_text, false);

		if (sizeof($groups)) {
			foreach ($groups as $g) {
				form_alternate_row('line' . $g['id'], true);
				form_selectable_cell(filter_value($g['name'], get_request_var('filter'), 'user_group_admin.php?action=edit&id=' . $g['id']), $g['id']);
				form_selectable_cell(filter_value($g['description'], get_request_var('filter')), $g['id']);
				form_selectable_cell($g['user_id'] > 0 ? __('Member'):__('Non Member'), $g['id']);
				form_selectable_cell(($g['id']), $g['id']);
				form_selectable_cell(($g['policy_graphs'] == 1 ? __('ALLOW'):__('DENY')) . '/' . ($g['policy_hosts'] == 1 ? __('ALLOW'):__('DENY')) . '/' . ($g['policy_graph_templates'] == 1 ? __('ALLOW'):__('DENY')), $g['id']);
				form_selectable_cell($g['enabled'] == 'on' ? __('Enabled'):__('Disabled'), $g['id']);
				form_checkbox_cell($g['name'], $g['id']);
				form_end_row();
			}
		} else {
			print '<tr><td><em>' . __('No Matching User Groups Found') . '</em></td></tr>';
		}

		html_end_box(false);

		if (sizeof($groups)) {
			print $nav;
		}

		form_hidden_box('tab',$tab,'');
		form_hidden_box('id', get_request_var('id'), '');
		form_hidden_box('associate_groups', '1', '');

		$assoc_actions = array(
			1 => __('Assign Membership'),
			2 => __('Remove Membership')
		);

		/* draw the dropdown containing a list of available actions for this form */
		draw_actions_dropdown($assoc_actions);

		form_end();

		break;
	case 'permsd':
		process_device_request_vars();

		device_filter($header_label);

		form_start('user_admin.php', 'policy');

		html_start_box(__('Default Device Policy'), '100%', '', '3', 'center', '');

		?>
		<tr class='even'>
			<td><table><tr>
			<td class='nowrap'><?php print __('Default Device Policy for this User');?></td>
			<td>
				<?php form_dropdown('policy_hosts',$policy_array,'','',$policy['policy_hosts'],'',''); ?>
			</td>
			<td>
				<input type='submit' name='update_policy' value='<?php print __esc('Update');?>'>
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
		/* form the 'where' clause for our main sql query */
		if (get_request_var('filter') != '') {
			$sql_where = "WHERE (host.hostname LIKE '%" . get_request_var('filter') . "%' OR host.description LIKE '%" . get_request_var('filter') . "%')";
		} else {
			$sql_where = '';
		}

		if (get_request_var('host_template_id') == '-1') {
			/* Show all items */
		} elseif (get_request_var('host_template_id') == '0') {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' host.host_template_id=0';
		} elseif (!isempty_request_var('host_template_id')) {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' host.host_template_id=' . get_request_var('host_template_id');
		}

		if (get_request_var('associated') == 'false') {
			/* Show all items */
		} else {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' user_auth_perms.user_id=' . get_request_var('id', 0);
		}

		$total_rows = db_fetch_cell("SELECT
			COUNT(host.id)
			FROM host
			LEFT JOIN user_auth_perms
			ON host.id = user_auth_perms.item_id
			AND user_auth_perms.type = 3
			AND user_auth_perms.user_id = " . get_request_var('id') . "
			$sql_where");

		$host_graphs       = array_rekey(
			db_fetch_assoc('SELECT host_id, count(*) AS graphs
				FROM graph_local
				GROUP BY host_id'),
			'host_id', 'graphs');

		$host_data_sources = array_rekey(
			db_fetch_assoc('SELECT host_id, count(*) AS data_sources
				FROM data_local
				GROUP BY host_id'),
			'host_id', 'data_sources');

		$sql_query = "SELECT host.*, user_auth_perms.user_id
			FROM host
			LEFT JOIN user_auth_perms
			ON host.id = user_auth_perms.item_id
			AND user_auth_perms.type = 3
			AND user_auth_perms.user_id = " . get_request_var('id') . "
			$sql_where
			ORDER BY description
			LIMIT " . ($rows*(get_request_var('page')-1)) . ',' . $rows;

		$hosts = db_fetch_assoc($sql_query);

		$nav = html_nav_bar('user_admin.php?action=user_edit&tab=permsd&id=' . get_request_var('id'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, __('Devices'), 'page', 'main');

		form_start(htmlspecialchars('user_admin.php?tab=permsd&id=' . get_request_var('id')), 'chk');

		print $nav;

		html_start_box('', '100%', '', '3', 'center', '');

		$display_text = array(__('Description'), __('ID'), __('Effective Policy'), __('Graphs'), __('Data Sources'), __('Status'), __('Hostname'));

		html_header_checkbox($display_text, false);

		if (sizeof($hosts)) {
			foreach ($hosts as $host) {
				form_alternate_row('line' . $host['id'], true);
				form_selectable_cell(filter_value($host['description'], get_request_var('filter')), $host['id']);
				form_selectable_cell(round(($host['id']), 2), $host['id']);
				if (empty($host['user_id']) || $host['user_id'] == NULL) {
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
			print '<tr><td><em>' . __('No Matching Devices Found') . '</em></td></tr>';
		}

		html_end_box(false);

		if (sizeof($hosts)) {
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

		form_start('user_admin.php', 'policy');

		html_start_box(__('Default Graph Template Policy'), '100%', '', '3', 'center', '');

		?>
		<tr class='even'>
			<td><table><tr>
			<td class='nowrap'><?php print __('Default Graph Template Policy for this User');?></td>
			<td>
				<?php form_dropdown('policy_graph_templates',$policy_array,'','',$policy['policy_graph_templates'],'',''); ?>
			</td>
			<td>
				<input type='submit' name='update_policy' value='<?php print __esc('Update');?>'>
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
			$sql_where = "WHERE (gt.name LIKE '%" . get_request_var('filter') . "%')";
		} else {
			$sql_where = '';
		}

		if (get_request_var('associated') == 'false') {
			/* Show all items */
		} else {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' (user_auth_perms.type=4 AND user_auth_perms.user_id=' . get_request_var('id', 0) . ')';
		}

		$total_rows = db_fetch_cell("SELECT COUNT(rows)
			FROM (SELECT
				COUNT(DISTINCT gt.id) rows
				FROM graph_templates AS gt
				INNER JOIN graph_local AS gl
				ON gt.id = gl.graph_template_id
				LEFT JOIN user_auth_perms
				ON (gt.id = user_auth_perms.item_id AND user_auth_perms.type = 4 AND user_auth_perms.user_id = " . get_request_var('id') . ")
				$sql_where
				GROUP BY gl.graph_template_id
			) AS rs");

		$sql_query = "SELECT gt.id, gt.name, count(*) AS totals, user_auth_perms.user_id
			FROM graph_templates AS gt
			INNER JOIN graph_local AS gl
			ON gt.id = gl.graph_template_id
			LEFT JOIN user_auth_perms
			ON (gt.id = user_auth_perms.item_id AND user_auth_perms.type = 4 AND user_auth_perms.user_id = " . get_request_var('id') . ")
			$sql_where
			GROUP BY gl.graph_template_id
			ORDER BY name
			LIMIT " . ($rows*(get_request_var('page')-1)) . ',' . $rows;

		$graphs = db_fetch_assoc($sql_query);

		$nav = html_nav_bar('user_admin.php?action=user_edit&tab=permste&id=' . get_request_var('id'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, __('Graph Templates'), 'page', 'main');

		form_start(htmlspecialchars('user_admin.php?tab=permste&id=' . get_request_var('id')), 'chk');

		print $nav;

		html_start_box('', '100%', '', '3', 'center', '');

		$display_text = array(__('Template Name'), __('ID'), __('Effective Policy'), __('Total Graphs'));

		html_header_checkbox($display_text, false);

		if (sizeof($graphs)) {
			foreach ($graphs as $g) {
				form_alternate_row('line' . $g['id'], true);
				form_selectable_cell(filter_value($g['name'], get_request_var('filter')), $g['id']);
				form_selectable_cell($g['id'], $g['id']);
				if (empty($g['user_id']) || $g['user_id'] == NULL) {
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
			print '<tr><td><em>' . __('No Matching Graph Templates Found') . '</em></td></tr>';
		}

		html_end_box(false);

		if (sizeof($graphs)) {
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

		form_start('user_admin.php', 'policy');

		html_start_box(__('Default Tree Policy'), '100%', '', '3', 'center', '');

		?>
		<tr class='even'>
			<td><table><tr>
			<td class='nowrap'><?php print __('Default Tree Policy for this User');?></td>
			<td>
				<?php form_dropdown('policy_trees',$policy_array,'','',$policy['policy_trees'],'',''); ?>
			</td>
			<td>
				<input type='submit' name='update_policy' value='<?php print __esc('Update');?>'>
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
			$sql_where = "WHERE (gt.name LIKE '%" . get_request_var('filter') . "%')";
		} else {
			$sql_where = '';
		}

		if (get_request_var('associated') == 'false') {
			/* showing all rows */
		} else {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' (user_auth_perms.type=2 AND user_auth_perms.user_id=' . get_request_var('id', 0) . ')';
		}

		$total_rows = db_fetch_cell("SELECT
			COUNT(DISTINCT gt.id)
			FROM graph_tree AS gt
			LEFT JOIN user_auth_perms
			ON gt.id = user_auth_perms.item_id
			AND user_auth_perms.type = 2
			AND user_auth_perms.user_id = " . get_request_var('id') . "
			$sql_where");

		$sql_query = "SELECT gt.id, gt.name, user_auth_perms.user_id
			FROM graph_tree AS gt
			LEFT JOIN user_auth_perms
			ON gt.id = user_auth_perms.item_id
			AND user_auth_perms.type = 2
			AND user_auth_perms.user_id = " . get_request_var('id') . "
			$sql_where
			ORDER BY name
			LIMIT " . ($rows*(get_request_var('page')-1)) . ',' . $rows;

		$trees = db_fetch_assoc($sql_query);

		$nav = html_nav_bar('user_admin.php?action=user_edit&tab=permstr&id=' . get_request_var('id'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, __('Trees'), 'page', 'main');

		form_start(htmlspecialchars('user_admin.php?tab=permstr&id=' . get_request_var('id')), 'chk');

		print $nav;

		html_start_box('', '100%', '', '3', 'center', '');

		$display_text = array(__('Tree Name'), __('ID'), __('Effective Policy'));

		html_header_checkbox($display_text, false);

		if (sizeof($trees)) {
			foreach ($trees as $t) {
				form_alternate_row('line' . $t['id'], true);
				form_selectable_cell(filter_value($t['name'], get_request_var('filter')), $t['id']);
				form_selectable_cell($t['id'], $t['id']);
				if (empty($t['user_id']) || $t['user_id'] == NULL) {
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
			print '<tr><td><em>' . __('No Matching Trees Found') . '</em></td></tr>';
		}

		html_end_box(false);

		if (sizeof($trees)) {
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

function user_realms_edit($header_label) {
	global $user_auth_realms, $user_auth_roles;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$all_realms = $user_auth_realms;

	print "<div class='cactiTable' style='width:100%;text-align:left;'>
		<div>
			<div class='cactiTableTitle'><span style='padding:3px;'>" . __('User Permissions') . " $header_label</span></div>
			<div class='cactiTableButton'><span style='padding:3px;'><input class='checkbox' type='checkbox' id='all' name='all' title='" . __esc('Select All') . "' onClick='selectAllRealms(this.checked)'></a><label class='formCheckboxLabel' title='" . __esc('Select All') . "' for='all'></label></span></div>
		</div>
	</div>\n";

	form_start('user_admin.php', 'chk');

	html_start_box('', '100%', '', '3', 'center', '');

	/* do cacti realms first */
	$i = 1;
	foreach($user_auth_roles as $role_name => $perms) {
		$j = 1;

		print "<tr class='tableHeader'><th colspan='2'>" . $role_name . "</th></tr>\n";
		print "<tr class='odd'><td colspan='4' style='width:100%;'><table style='width:100%;'>\n";
		foreach($perms as $realm) {
			if ($j == 1) {
				print "<tr>\n";
			}

			print "<td class='realms'>\n";
			if (isset($user_auth_realms[$realm])) {
				$set = db_fetch_cell_prepared('SELECT realm_id
					FROM user_auth_realm
					WHERE user_id = ?
					AND realm_id = ?',
					array(get_request_var('id', 0), $realm));

				if (!empty($set)) {
					$old_value = 'on';
				} else {
					$old_value = '';
				}

				unset($all_realms[$realm]);

				form_checkbox('section' . $realm, $old_value, $user_auth_realms[$realm], '', '', '', (!isempty_request_var('id') ? 1 : 0)); print '<br>';
			}
			print "</td>\n";

			if ($j == 5) {
				print "</tr>\n";
				$j = 1;
			} else {
				$j++;
			}
		}

		if ($j > 1) {
			print "<td class='realms' colspan='" . (5-$j) . "'></td>\n";
			print "</tr>\n";
		}

		print "</table></td></tr>\n";
	}

	/* external links */
	$links  = db_fetch_assoc('SELECT * FROM external_links ORDER BY sortorder');

	$style_translate = array(
		'CONSOLE'    => __('Console'),
		'TAB'        => __('Top Tab'),
		'FRONT'      => __('Bottom Console'),
		'FRONTTOP'   => __('Top Console')
	);

	print "<tr class='tableHeader'><th colspan='2'>" . __('External Link Permissions') . "</th></tr>\n";
	print "<tr class='odd'><td colspan='4'><table style='width:100%;'><tr><td class='realms'>\n";
	if (sizeof($links)) {
		$j = 1;

		foreach($links as $r) {
			if ($j == 1) {
				print "<tr>\n";
			}

			$realm = $r['id'] + 10000;

			if (sizeof(db_fetch_assoc_prepared('SELECT realm_id FROM user_auth_realm WHERE user_id = ? AND realm_id = ?', array(get_request_var('id', 0), $realm))) > 0) {
				$old_value = 'on';
			} else {
				$old_value = '';
			}

			unset($all_realms[$realm]);

			print "<td class='realms'>\n";

			switch($r['style']) {
			case 'CONSOLE':
				$description = $style_translate[$r['style']] . ': ' . ($r['extendedstyle'] == '' ? 'External Links' : $r['extendedstyle']) . '/' . $r['title'];
				break;
			default:
				$description = $style_translate[$r['style']] . ': ' . ucfirst($r['title']);
				break;
			}

			form_checkbox('section' . $realm, $old_value, $description, '', '', '', (!isempty_request_var('id') ? 1 : 0)); print '<br>';

			print "</td>\n";

			if ($j == 5) {
				print "</tr>\n";
				$j = 1;
			} else {
				$j++;
			}
		}

		if ($j > 1) {
			print "<td class='realms' colspan='" . (5-$j) . "'></td>\n";
			print "</tr>\n";
		}
	}
	print "</tr></table></td></tr>\n";

	/* do plugin realms */
	$realms = db_fetch_assoc('SELECT pc.name, pr.id AS realm_id, pr.display
		FROM plugin_config AS pc
		INNER JOIN plugin_realms AS pr
		ON pc.directory = pr.plugin
		ORDER BY pc.name, pr.display');

	print "<tr class='tableHeader'><th colspan='2'>" . __('Plugin Permissions') . "</th></tr>\n";
	print "<tr class='odd'><td colspan='4'><table style='width:100%;'><tr><td class='realms'>\n";
	if (sizeof($realms)) {
		$last_plugin = 'none';
		$i = 1;
		$j = 1;

		foreach($realms as $r) {
			$break = false;

			if ($last_plugin != $r['name'] && $last_plugin != 'none') {
				$break = true;

				if ($j == 5) {
					print "</tr><tr>\n";
					$break = true;;
					$j = 1;
				} else {
					$j++;
				}
			}

			if ($break) {
				print "</td><td class='realms'>\n";
			}

			if ($break || $i == 1) {
				print "<i>" . $r['name'] . "</i><br>\n";
			}

			$realm = $r['realm_id'] + 100;

			if (sizeof(db_fetch_assoc_prepared('SELECT realm_id FROM user_auth_realm WHERE user_id = ? AND realm_id = ?', array(get_request_var('id', 0), $realm))) > 0) {
				$old_value = 'on';
			} else {
				$old_value = '';
			}

			unset($all_realms[$realm]);

			$pos = (strpos($user_auth_realms[$realm], '->') !== false ? strpos($user_auth_realms[$realm], '->')+2:0);

			form_checkbox('section' . $realm, $old_value, substr($user_auth_realms[$realm], $pos), '', '', '', (!isempty_request_var('id') ? 1 : 0)); print '<br>';

			$last_plugin = $r['name'];

			$i++;
		}
	}

	/* get the old PIA 1.x realms */
	if (sizeof($all_realms)) {
		if ($break) {
			print "</td><td class='realms'>\n";
		}

		print "<strong>" . __('Legacy 1.x Plugins') . "</strong><br>\n";
		foreach($all_realms as $realm => $name) {
			if (sizeof(db_fetch_assoc_prepared('SELECT realm_id FROM user_auth_realm WHERE user_id = ? AND realm_id = ?', array(get_request_var('id', 0), $realm))) > 0) {
				$old_value = 'on';
			} else {
				$old_value = '';
			}

			$pos = (strpos($user_auth_realms[$realm], '->') !== false ? strpos($user_auth_realms[$realm], '->')+2:0);

			form_checkbox('section' . $realm, $old_value, substr($user_auth_realms[$realm], $pos), '', '', '', (!isempty_request_var('id') ? 1 : 0)); print '<br>';
		}
	}

	print "</td></tr></table></td></tr>\n";
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

		$('label').click(function(event) {
			event.preventDefault();
			id = $(this).attr('for');
			$('.'+id).trigger('click');
		});
	});
	</script>
	<?php

	html_end_box();

	form_hidden_box('id', get_request_var('id'), '');
	form_hidden_box('tab', 'realms', '');
	form_hidden_box('save_component_realm_perms', '1', '');

	form_save_button('user_admin.php', 'return');
}

function settings_edit($header_label) {
	global $settings_user, $tabs_graphs, $graph_views;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	form_start('user_admin.php');

	html_start_box(__('User Settings %s', $header_label), '100%', true, '3', 'center', '');

	foreach ($settings_user as $tab_short_name => $tab_fields) {
		$collapsible = true;

        print "<div class='spacer tableHeader" . ($collapsible ? ' collapsible':'') . "' id='row_$tab_short_name'><div style='cursor:pointer;' class='tableSubHeaderColumn'>" . $tabs_graphs[$tab_short_name] . ($collapsible ? "<div style='float:right;padding-right:4px;'><i class='fa fa-angle-double-up'></i></div>":"") . "</div></div>\n";

		$form_array = array();

		foreach ($tab_fields as $field_name => $field_array) {
			$form_array += array($field_name => $tab_fields[$field_name]);

			if ((isset($field_array['items'])) && (is_array($field_array['items']))) {
				foreach ($field_array['items'] as $sub_field_name => $sub_field_array) {
					if (graph_config_value_exists($sub_field_name, get_request_var('id'))) {
						$form_array[$field_name]['items'][$sub_field_name]['form_id'] = 1;
					}

					$form_array[$field_name]['items'][$sub_field_name]['value'] =  db_fetch_cell_prepared('SELECT value FROM settings_user WHERE name = ? AND user_id = ?', array($sub_field_name, get_request_var('id')));
				}
			} else {
				if (graph_config_value_exists($field_name, get_request_var('id'))) {
					$form_array[$field_name]['form_id'] = 1;
				}

				$form_array[$field_name]['value'] = db_fetch_cell_prepared('SELECT value FROM settings_user WHERE name = ? and user_id = ?', array($field_name, get_request_var('id')));
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

	form_hidden_box('id', get_request_var('id'), '');
	form_hidden_box('tab', 'settings', '');
	form_hidden_box('save_component_graph_settings','1','');

	form_save_button('user_admin.php', 'return');

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

function user_edit() {
	global $config, $fields_user_user_edit_host;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('tab', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z]+)$/')));
	/* ==================================================== */

	/* present a tabbed interface */
	$tabs = array(
		'general'  => __('General'),
		'realms'   => __('Permissions'),
		'permsgr'  => __('Group Membership'),
		'permsg'   => __('Graph Perms'),
		'permsd'   => __('Device Perms'),
		'permste'  => __('Template Perms'),
		'permstr'  => __('Tree Perms'),
		'settings' => __('User Settings')
	);

	/* set the default tab */
	load_current_session_value('tab', 'sess_user_admin_tab', 'general');
	$current_tab = get_nfilter_request_var('tab');

	if (!isempty_request_var('id')) {
		$user = db_fetch_row_prepared('SELECT * FROM user_auth WHERE id = ?', array(get_request_var('id')));
		$header_label = __('[edit: %s]', $user['username']);
	} else {
		$header_label = __('[new]');
	}

	if (sizeof($tabs) && !isempty_request_var('id')) {
		$i = 0;

		/* draw the tabs */
		print "<div class='tabs'><nav><ul role='tablist'>\n";

		foreach (array_keys($tabs) as $tab_short_name) {
			print "<li class='subTab'><a class='tab" . (($tab_short_name == $current_tab) ? " selected'" : "'") .
				" href='" . htmlspecialchars($config['url_path'] .
				'user_admin.php?action=user_edit&id=' . get_request_var('id') .
				'&tab=' . $tab_short_name) .
				"'>" . $tabs[$tab_short_name] . "</a></li>\n";

			$i++;
		}

		api_plugin_hook('user_admin_tab');

		print "</ul></nav></div>\n";
	}

	switch($current_tab) {
	case 'general':
		api_plugin_hook_function('user_admin_edit', (isset($user) ? get_request_var('id') : 0));

		form_start('user_admin.php');

		html_start_box(__('User Management %s', $header_label), '100%', '', '3', 'center', '');

		draw_edit_form(
			array(
				'config' => array('no_form_tag' => true),
				'fields' => inject_form_variables($fields_user_user_edit_host, (isset($user) ? $user : array()))
			)
		);

		html_end_box();

		form_save_button('user_admin.php', 'return');

		?>
		<script type='text/javascript'>

		var minChars=<?php print read_config_option('secpass_minlen');?>;

		function changeRealm() {
			if ($('#realm').val() != 0) {
				$('#password_change').prop('disabled', true);
			} else {
				$('#password_change').prop('disabled', false);
			}
		}

		function checkPassword() {
			if ($('#password').val().length == 0) {
				$('#pass').remove();
				$('#passconfirm').remove();
			}else if ($('#password').val().length < minChars) {
				$('#pass').remove();
				$('#password').after('<span id="pass"><i class="badpassword fa fa-times"></i><span style="padding-left:4px;"><?php print __('Password Too Short')?></span></span>');
			} else {
				$.post('user_admin.php?action=checkpass', { password: $('#password').val(), password_confim: $('#password_confirm').val(), __csrf_magic: csrfMagicToken } ).done(function(data) {
					if (data == 'ok') {
						$('#pass').remove();
						$('#password').after('<span id="pass"><i class="goodpassword fa fa-check"></i><span style="padding-left:4px;"><?php print __('Password Validation Passes');?></span></span>');
						checkPasswordConfirm();
					} else {
						$('#pass').remove();
						$('#password').after('<span id="pass"><i class="badpassword fa fa-times"></i><span style="padding-left:4px;">'+data+'</span></span>');
					}
				});
			}
		}

		function checkPasswordConfirm() {
			if ($('#password_confirm').val().length > 0) {
				if ($('#password').val() != $('#password_confirm').val()) {
					$('#passconfirm').remove();
					$('#password_confirm').after('<span id="passconfirm"><i class="badpassword fa fa-times"></i><span style="padding-left:4px;"><?php print __('Passwords do Not Match');?></span></span>');
				} else {
					$('#passconfirm').remove();
					$('#password_confirm').after('<span id="passconfirm"><i class="goodpassword fa fa-check"></i><span style="padding-left:4px;"><?php print __('Passwords Match');?></span></span>');
				}
			} else {
				$('#passconfirm').remove();
			}
		}

		var password_change = $('#password_change').is(':checked');

		$(function() {
			changeRealm();

			/* clear passwords */
			$('#password').val('');
			$('#password_confirm').val('');

			$('#password').keyup(function() {
				checkPassword();
			});

			$('#password_confirm').keyup(function() {
				checkPasswordConfirm();
			});

			$('#realm').change(function() {
				changeRealm();
			});

			$('#password_change').click(function() {
				password_change = $('#password_change').is(':checked');

				if (!password_change && $('#must_change_password').is(':checked')) {
					button = ($('#must_change_password').button('instance') !== undefined);
					if (button) {
						$('#must_change_password').prop('checked', false).button('refresh');
					} else {
						$('#must_change_password').prop('checked', false);
					}
				}
			});

			$('#must_change_password').click(function() {
				if ($(this).is(':checked')) {
					button = ($('#must_change_password').button('instance') !== undefined);
					if (button) {
						$('#password_change').prop('checked', true);
						$('#password_change').button('refresh');
					} else {
						$('#password_change').prop('checked', true);
					}
				} else {
					button = ($('#must_change_password').button('instance') !== undefined);
					if (button) {
						$('#password_change').prop('checked', password_change).button('refresh');
					} else {
						$('#password_change').prop('checked', password_change);
					}
				}
			});
		});

		</script>
		<?php

		break;
	case 'settings':
		settings_edit($header_label);

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
		if (api_plugin_hook_function('user_admin_run_action', get_request_var('tab'))) {
			user_realms_edit();
		}
		break;
	}
}

function user() {
	global $config, $auth_realms, $user_actions, $item_rows;

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
			'default' => 'username',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
	);

	validate_store_request_vars($filters, 'sess_usera');
	/* ================= input validation ================= */

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL  = 'user_admin.php?rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'user_admin.php?clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
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

	html_start_box(__('User Management'), '100%', '', '3', 'center', 'user_admin.php?tab=general&action=user_edit');

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	?>
	<tr class='even'>
		<td>
		<form id='form_user_admin' action='user_admin.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input id='filter' type='text' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Users');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
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
	if (get_request_var('filter') != '') {
		$sql_where = "WHERE (user_auth.username LIKE '%" . get_request_var('filter') . "%' OR user_auth.full_name LIKE '%" . get_request_var('filter') . "%')";
	} else {
		$sql_where = '';
	}

	$total_rows = db_fetch_cell("SELECT
		COUNT(user_auth.id)
		FROM user_auth
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$user_list = db_fetch_assoc("SELECT id, user_auth.username, full_name,
		realm, enabled, policy_graphs, policy_hosts, policy_graph_templates,
		time, max(time) as dtime
		FROM user_auth
		LEFT JOIN user_log ON (user_auth.id = user_log.user_id)
		$sql_where
		GROUP BY id
		$sql_order
		$sql_limit");

	$nav = html_nav_bar('user_admin.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 9, __('Users'), 'page', 'main');

	form_start('user_admin.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'username'               => array(__('User Name'), 'ASC'),
		'full_name'              => array(__('Full Name'), 'ASC'),
		'enabled'                => array(__('Enabled'), 'ASC'),
		'realm'                  => array(__('Realm'), 'ASC'),
		'policy_graphs'          => array(__('Graph Policy'), 'ASC'),
		'policy_hosts'           => array(__('Device Policy'), 'ASC'),
		'policy_graph_templates' => array(__('Template Policy'), 'ASC'),
		'dtime'                  => array(__('Last Login'), 'DESC')
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (sizeof($user_list)) {
		foreach ($user_list as $user) {
			if (empty($user['dtime']) || ($user['dtime'] == '12/31/1969')) {
				$last_login = __('N/A');
			} else {
				$last_login = strftime('%A, %B %d, %Y %H:%M:%S ', strtotime($user['dtime']));;
			}
			if ($user['enabled'] == 'on') {
				$enabled = __('Yes');
			} else {
				$enabled = __('No');
			}

			if (isset($auth_realms[$user['realm']])) {
				$realm = $auth_realms[$user['realm']];
			} else {
				$realm = __('Unavailable');
			}

			form_alternate_row('line' . $user['id'], true);
			form_selectable_cell(filter_value($user['username'], get_request_var('filter'), $config['url_path'] . 'user_admin.php?action=user_edit&tab=general&id=' . $user['id']), $user['id']);
			form_selectable_cell(filter_value($user['full_name'], get_request_var('filter')), $user['id']);
			form_selectable_cell($enabled, $user['id']);
			form_selectable_cell($realm, $user['id']);
			form_selectable_cell(($user['policy_graphs'] == 1 ? __('ALLOW'):__('DENY')), $user['id']);
			form_selectable_cell(($user['policy_hosts'] == 1 ? __('ALLOW'):__('DENY')), $user['id']);
			form_selectable_cell(($user['policy_graph_templates'] == 1 ? __('ALLOW'):__('DENY')), $user['id']);
			form_selectable_cell($last_login, $user['id']);
			form_checkbox_cell($user['username'], $user['id']);
			form_end_row();
		}
	} else {
		print '<tr><td colspan="' . (sizeof($display_text)+1) . '"><em>' . __('No Users Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (sizeof($user_list)) {
		print $nav;
	}

	draw_actions_dropdown($user_actions);

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
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'graph_template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1',
			),
		'associated' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => 'true'
			)
	);

	validate_store_request_vars($filters, 'sess_uag');
	/* ================= input validation ================= */
}

function process_group_request_vars() {
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
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'associated' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => 'true'
			)
	);

	validate_store_request_vars($filters, 'sess_uagr');
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
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'host_template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1',
			),
		'associated' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => 'true'
			)
	);

	validate_store_request_vars($filters, 'sess_uad');
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
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'graph_template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1',
			),
		'associated' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => 'true'
			)
	);

	validate_store_request_vars($filters, 'sess_uate');
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
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'graph_template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1',
			),
		'associated' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => 'true'
			)
	);

	validate_store_request_vars($filters, 'sess_uatr');
	/* ================= input validation ================= */
}

function graph_filter($header_label) {
	global $config, $item_rows;

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL  = 'user_admin.php?action=user_edit&tab=permsg&id=<?php print get_request_var('id');?>'
		strURL += '&rows=' + $('#rows').val();
		strURL += '&graph_template_id=' + $('#graph_template_id').val();
		strURL += '&associated=' + $('#associated').is(':checked');
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter(objForm) {
		strURL = 'user_admin.php?action=user_edit&tab=permsg&id=<?php print get_request_var('id');?>&clear=true'
		strURL = strURL + '&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#forms').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php

	html_start_box(__('Graph Permissions %s', htmlspecialchars($header_label)), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='forms' method='post' action='user_admin.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input id='filter' type='text' size='25' value='<?php print html_escape_request_var('filter');?>' onChange='applyFilter()'>
					</td>
					<td>
						<?php print __('Template');?>
					</td>
					<td>
						<select id='graph_template_id' name='graph_template_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('graph_template_id') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<option value='0'<?php if (get_request_var('graph_template_id') == '0') {?> selected<?php }?>><?php print __('None');?></option>
							<?php
							$graph_templates = db_fetch_assoc('SELECT DISTINCT gt.id, gt.name
								FROM graph_templates AS gt
								INNER JOIN graph_local AS gl
								ON gl.graph_template_id = gt.id
								ORDER BY name');

							if (sizeof($graph_templates)) {
								foreach ($graph_templates as $gt) {
									print "<option value='" . $gt['id'] . "'"; if (get_request_var('graph_template_id') == $gt['id']) { print ' selected'; } print '>' . htmlspecialchars($gt['name']) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Graphs');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' name='associated' id='associated' onChange='applyFilter()' <?php print (get_request_var('associated') == 'true' || get_request_var('associated') == 'on' ? 'checked':'');?>>
							<label for='associated'><?php print __('Show All');?></label>
						</span>
					</td>
					<td>
						<span>
							<input type='button' id='go' value='<?php print __esc('Go');?>' onClick='applyFilter()' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' id='clear' value='<?php print __esc('Clear');?>' onClick='clearFilter()' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
			<input type='hidden' name='action' value='user_edit'>
			<input type='hidden' name='tab' value='permsg'>
			<input type='hidden' name='id' value='<?php print get_request_var('id');?>'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();
}

function group_filter($header_label) {
	global $config, $item_rows;

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL  = 'user_admin.php?action=user_edit&tab=permsgr&id=<?php print get_request_var('id');?>'
		strURL += '&rows=' + $('#rows').val();
		strURL += '&associated=' + $('#associated').is(':checked');
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'user_admin.php?action=user_edit&tab=permsgr&id=<?php print get_request_var('id');?>&clear=true'
		strURL = strURL + '&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#forms').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php

	html_start_box(__('Group Membership %s', htmlspecialchars($header_label)), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='forms' method='post' action='user_admin.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input id='filter' type='text' size='25' value='<?php print html_escape_request_var('filter');?>' onChange='applyFilter()'>
					</td>
					<td>
						<?php print __('Groups');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' name='associated' id='associated' onChange='applyFilter()' <?php print (get_request_var('associated') == 'true' || get_request_var('associated') == 'on' ? 'checked':'');?>>
							<label for='associated'><?php print __('Show All');?></label>
						</span>
					</td>
					<td>
						<span>
							<input type='button' value='<?php print __esc('Go');?>' onClick='applyFilter()' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' id='clear' value='<?php print __esc('Clear');?>' onClick='clearFilter()' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
			<input type='hidden' name='action' value='user_edit'>
			<input type='hidden' name='tab' value='permsgr'>
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
		strURL  = 'user_admin.php?action=user_edit&tab=permsd&id=<?php print get_request_var('id');?>'
		strURL += '&rows=' + $('#rows').val();
		strURL += '&host_template_id=' + $('#host_template_id').val();
		strURL += '&associated=' + $('#associated').is(':checked');
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter(objForm) {
		strURL = 'user_admin.php?action=user_edit&tab=permsd&id=<?php print get_request_var('id');?>&clear=true'
		strURL = strURL + '&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#forms').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php

	html_start_box(__('Devices Permission %s', htmlspecialchars($header_label)), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='forms' method='post' action='user_admin.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input id='filter' type='text' size='25' value='<?php print html_escape_request_var('filter');?>' onChange='applyFilter()'>
					</td>
					<td>
						<?php print __('Template');?>
					</td>
					<td>
						<select id='host_template_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('host_template_id') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
							<option value='0'<?php if (get_request_var('host_template_id') == '0') {?> selected<?php }?>><?php print __('None');?></option>
							<?php
							$host_templates = db_fetch_assoc('SELECT id, name FROM host_template ORDER BY name');

							if (sizeof($host_templates)) {
								foreach ($host_templates as $host_template) {
									print "<option value='" . $host_template['id'] . "'"; if (get_request_var('host_template_id') == $host_template['id']) { print ' selected'; } print '>' . htmlspecialchars($host_template['name']) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Devices');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' name='associated' id='associated' onChange='applyFilter()' <?php print (get_request_var('associated') == 'true' || get_request_var('associated') == 'on' ? 'checked':'');?>>
							<label for='associated'><?php print __('Show Exceptions');?></label>
						</span>
					</td>
					<td>
						<span>
							<input type='button' value='<?php print __esc('Go');?>' onClick='applyFilter()' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' id='clear' value='<?php print __esc('Clear');?>' onClick='clearFilter()' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
			<input type='hidden' name='action' value='user_edit'>
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
		strURL  = 'user_admin.php?action=user_edit&tab=permste&id=<?php print get_request_var('id');?>'
		strURL += '&rows=' + $('#rows').val();
		strURL += '&associated=' + $('#associated').is(':checked');
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'user_admin.php?action=user_edit&tab=permste&id=<?php print get_request_var('id');?>&clear=true'
		strURL = strURL + '&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#forms').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php

	html_start_box(__('Template Permission %s', htmlspecialchars($header_label)), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='forms' method='post' action='user_admin.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input id='filter' type='text' size='25' value='<?php print html_escape_request_var('filter');?>' onChange='applyFilter()'>
					</td>
					<td>
						<?php print __('Templates');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' name='associated' id='associated' onChange='applyFilter()' <?php print (get_request_var('associated') == 'true' || get_request_var('associated') == 'on' ? 'checked':'');?>>
							<label for='associated'><?php print __('Show Exceptions');?></label>
						</span>
					</td>
					<td>
						<span>
							<input type='button' value='<?php print __esc('Go');?>' onClick='applyFilter()' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' id='clear' value='<?php print __esc('Clear');?>' onClick='clearFilter()' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
			<input type='hidden' name='action' value='user_edit'>
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
		strURL  = 'user_admin.php?action=user_edit&tab=permstr&id=<?php print get_request_var('id');?>'
		strURL += '&rows=' + $('#rows').val();
		strURL += '&associated=' + $('#associated').is(':checked');
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'user_admin.php?action=user_edit&tab=permstr&id=<?php print get_request_var('id');?>&clear=true'
		strURL = strURL + '&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#forms').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php

	html_start_box(__('Tree Permission %s', htmlspecialchars($header_label)), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='forms' method='post' action='user_admin.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input id='filter' type='text' size='25' value='<?php print html_escape_request_var('filter');?>' onChange='applyFilter()'>
					</td>
					<td>
						<?php print __('Trees');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' name='associated' id='associated' onChange='applyFilter()' <?php print (get_request_var('associated') == 'true' || get_request_var('associated') == 'on' ? 'checked':'');?>>
							<label for='associated'><?php print __('Show Exceptions');?></label>
						</span>
					</td>
					<td>
						<span>
							<input type='button' id='refresh' value='<?php print __esc('Go');?>' onClick='applyFilter()' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' id='clear' value='<?php print __esc('Clear');?>' onClick='clearFilter()' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
			<input type='hidden' name='action' value='user_edit'>
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

	function applyFilter(objForm) {
		strURL  = 'user_admin.php?action=user_edit&tab=members&id=<?php print get_request_var('id');?>'
		strURL += '&rows=' + $('#rows').val();
		strURL += '&associated=' + $('#associated').is(':checked');
		strURL += '&filter=' + $('#filter').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter(objForm) {
		strURL = 'user_admin.php?action=user_edit&tab=members&id=<?php print get_request_var('id');?>&clear=true'
		strURL = strURL + '&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#forms').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php

	html_start_box(__('Tree Permission %s', htmlspecialchars($header_label)), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='forms' method='post' action='user_admin.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input id='filter' type='text' size='25' value='<?php print html_escape_request_var('filter');?>' onChange='applyFilter()'>
					</td>
					<td>
						<?php print __('Trees');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' name='associated' id='associated' onChange='applyFilter()' <?php print (get_request_var('associated') == 'true' || get_request_var('associated') == 'on' ? 'checked':'');?>>
							<label for='associated'><?php print __('Show Exceptions');?></label>
						</span>
					</td>
					<td>
						<span>
							<input type='button' id='refresh' value='<?php print __esc('Go');?>' onClick='applyFilter()' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' id='clear' value='<?php print __esc('Clear');?>' onClick='clearFilter()' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
			<input type='hidden' name='action' value='user_edit'>
			<input type='hidden' name='tab' value='members'>
			<input type='hidden' name='id' value='<?php print get_request_var('id');?>'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();
}

