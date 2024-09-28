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

include('./include/auth.php');

$actions = array(
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

function update_policies() {
	$policies = array('policy_graphs', 'policy_trees', 'policy_hosts', 'policy_graph_templates');

	foreach ($policies as $p) {
		if (isset_request_var($p)) {
			db_execute_prepared("UPDATE `user_auth` SET `$p` = ? WHERE `id` = ?", array(get_filter_request_var($p), get_filter_request_var('id')));
		}
	}

	header('Location: user_admin.php?action=user_edit&tab=' .  get_nfilter_request_var('tab') . '&id=' . get_nfilter_request_var('id'));

	exit;
}

function form_actions() {
	global $actions, $auth_realms;

	/* if we are to save this form, instead of display it */
	if (isset_request_var('associate_host')) {
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1], 'chk[1]');
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

		header('Location: user_admin.php?action=user_edit&tab=permsd&id=' . get_nfilter_request_var('id'));

		exit;
	}

	if (isset_request_var('associate_graph')) {
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1], 'chk[1]');
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

		header('Location: user_admin.php?action=user_edit&tab=permsg&id=' . get_nfilter_request_var('id'));

		exit;
	}

	if (isset_request_var('associate_template')) {
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1], 'chk[1]');
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

		header('Location: user_admin.php?action=user_edit&tab=permste&id=' . get_nfilter_request_var('id'));

		exit;
	}

	if (isset_request_var('associate_groups')) {
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1], 'chk[1]');
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

		header('Location: user_admin.php?action=user_edit&tab=permsgr&id=' . get_nfilter_request_var('id'));

		exit;
	}

	if (isset_request_var('associate_tree')) {
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1], 'chk[1]');
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

		header('Location: user_admin.php?action=user_edit&tab=permstr&id=' . get_nfilter_request_var('id'));

		exit;
	}

	if (isset_request_var('selected_items')) {
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
				if (cacti_sizeof(db_fetch_assoc_prepared('SELECT username FROM user_auth WHERE username = ? AND realm = ?', array($new_username, $new_realm)))) {
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
					for ($i=0;($i < cacti_count($selected_items));$i++) {
						if ($_SESSION[SESS_USER_ID] != $selected_items[$i]) {
							user_remove($selected_items[$i]);
						} else {
							raise_message('attempt current', __('You are not allowed to delete the current login account'), MESSAGE_LEVEL_ERROR);
						}
					}
				} elseif (get_nfilter_request_var('drp_action') == '3') { // enable
					for ($i=0;($i < cacti_count($selected_items));$i++) {
						user_enable($selected_items[$i]);
					}
				} elseif (get_nfilter_request_var('drp_action') == '4') { // disable
					for ($i=0;($i < cacti_count($selected_items));$i++) {
						if ($_SESSION[SESS_USER_ID] != $selected_items[$i]) {
							user_disable($selected_items[$i]);
						} else {
							raise_message('attempt current', __('You are not allowed to disable the current login account'), MESSAGE_LEVEL_ERROR);
						}
					}
				} elseif (get_nfilter_request_var('drp_action') == '5') { // batch copy
					/* ================= input validation ================= */
					get_filter_request_var('template_user');
					/* ==================================================== */

					$copy_error = false;
					$template   = db_fetch_row_prepared('SELECT username, realm
						FROM user_auth
						WHERE id = ?',
						array(get_nfilter_request_var('template_user')));

					for ($i=0;($i < cacti_count($selected_items));$i++) {
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

		header('Location: user_admin.php');

		exit;
	} else {
		$ilist  = '';
		$iarray = array();

		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1], 'chk[1]');
				/* ==================================================== */

				if (get_nfilter_request_var('drp_action') != '2') {
					$ilist .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT username FROM user_auth WHERE id = ?', array($matches[1]))) . '</li>';
				}

				$iarray[] = $matches[1];
			}
		}

		if (cacti_sizeof($iarray)) {
			$user_id    = $iarray[0];
			$user_realm = db_fetch_cell_prepared('SELECT realm FROM user_auth WHERE id = ?', array($user_id));
			$template   = html_escape(db_fetch_cell_prepared('SELECT username FROM user_auth WHERE id = ?', array($user_id)));
			$usernames  = db_fetch_assoc('SELECT id, username FROM user_auth WHERE realm = 0 ORDER BY username');
		} else {
			$user_id    = null;
			$user_realm = null;
			$template   = null;
			$usernames  = null;
		}

		$form_data = array(
			'general' => array(
				'page'       => 'user_admin.php',
				'actions'    => $actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			),
			'options' => array(
				1 => array(
					'smessage' => __('Click \'Continue\' to Delete the following User.'),
					'pmessage' => __('Click \'Continue\' to Delete following Users.'),
					'scont'    => __('Delete User'),
					'pcont'    => __('Delete Users')
				),
				2 => array(
					'message' => __('Click \'Continue\' to Copy the following User.'),
					'cont'    => __('Copy User'),
					'extra'    => array(
						'template_username' => array(
							'method'  => 'other',
							'title'   => __('Template Username:'),
							'default' => $template
						),
						'new_username' => array(
							'method'  => 'textbox',
							'title'   => __('Username:'),
							'default' => '',
							'width'   => 25
						),
						'new_fullname' => array(
							'method'  => 'textbox',
							'title'   => __('Full Name:'),
							'default' => '',
							'width'   => 35
						),
						'new_realm' => array(
							'method'  => 'drop_array',
							'title'   => __('Realm:'),
							'array'   => $auth_realms,
							'default' => $user_realm
						)
					)
				),
				3 => array(
					'smessage' => __('Click \'Continue\' to Enable the following User.'),
					'pmessage' => __('Click \'Continue\' to Enable following Users.'),
					'scont'    => __('Enable User'),
					'pcont'    => __('Enable Users')
				),
				4 => array(
					'smessage' => __('Click \'Continue\' to Disable the following User.'),
					'pmessage' => __('Click \'Continue\' to Disable following Users.'),
					'scont'    => __('Disable User'),
					'pcont'    => __('Disable Users')
				),
				5 => array(
					'smessage' => __('Click \'Continue\' to Overwrite the User settings with the selected Template User settings and permissions.  The original User Full Name, Password, Realm and Enable status will be retained, all other fields will be overwritten from the Template User.'),
					'pmessage' => __('Click \'Continue\' to Overwrite the Users settings with the selected Template User settings and permissions.  The original Users Full Name, Password, Realm and Enable status will be retained, all other fields will be overwritten from the Template User.'),
					'scont'    => __('Replace User Settings for User'),
					'pcont'    => __('Replace User Settings for Users'),
					'extra'    => array(
						'new_realm' => array(
							'method'  => 'drop_array',
							'title'   => __('Template User:'),
							'array'   => $usernames,
							'variable' => 'username',
							'id'       => 'id'
						)
					)
				)
			)
		);

		form_continue_confirmation($form_data);
	}
}

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
			header('Location: user_admin.php?action=user_edit&tab=graph_perms_edit&id=' . get_nfilter_request_var('id'));

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

		$old_password = db_fetch_cell_prepared('SELECT password
			FROM user_auth
			WHERE id = ?',
			array(get_nfilter_request_var('id')));

		if ((get_nfilter_request_var('password') == '') && (get_nfilter_request_var('password_confirm') == '')) {
			$password = $old_password;
		} else {
			$password = compat_password_hash(get_nfilter_request_var('password'), PASSWORD_DEFAULT);

			if ($password != $old_password) {
				db_execute_prepared('DELETE FROM user_auth_cache
					WHERE user_id = ?',
					array(get_nfilter_request_var('id')));
			}
		}

		/* check duplicate username */
		if (cacti_sizeof(db_fetch_row_prepared('SELECT * FROM user_auth WHERE realm = ? AND username = ? AND id != ?', array(get_nfilter_request_var('realm'), get_nfilter_request_var('username'), get_nfilter_request_var('id'))))) {
			raise_message(12);
		}

		/* check for guest or template user */
		$username = db_fetch_cell_prepared('SELECT username FROM user_auth WHERE id = ?', array(get_nfilter_request_var('id')));
		$history  = db_fetch_cell_prepared('SELECT password_history FROM user_auth WHERE id = ?', array(get_nfilter_request_var('id')));

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
		$save['password_history']     = $history;

		/* force enable/disable on template accounts */
		if (read_config_option('admin_user') == get_nfilter_request_var('id')) {
			$save['enabled'] = 'on';
			$save['realm']   = get_nfilter_request_var('realm', 0);
		} elseif (is_template_account(get_nfilter_request_var('id'))) {
			$save['enabled'] = '';
			$save['realm']   = 0;
		} else {
			$save['enabled'] = form_input_validate(get_nfilter_request_var('enabled', ''), 'enabled', '', true, 3);
			$save['realm']   = get_nfilter_request_var('realm', 0);
		}

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
		/* ================= input validation ================= */
		get_filter_request_var('id');
		/* ==================================================== */

		db_execute_prepared('DELETE FROM user_auth_realm
			WHERE user_id = ?',
			array(get_nfilter_request_var('id')));

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
		/* ================= input validation ================= */
		get_filter_request_var('id');
		/* ==================================================== */

		save_user_settings(get_request_var('id'));

		/* reset local settings cache so the user sees the new settings */
		kill_session_var(OPTIONS_USER);

		reset_user_perms(get_request_var('id'));

		raise_message(1);
	} elseif (isset_request_var('save_component_graph_perms')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		get_filter_request_var('policy_hosts');
		get_filter_request_var('policy_graphs');
		get_filter_request_var('policy_trees');
		get_filter_request_var('policy_graph_templates');
		/* ==================================================== */

		db_execute_prepared('UPDATE user_auth
			SET policy_graphs = ?,
			policy_trees = ?,
			policy_hosts = ?,
			policy_graph_templates = ?
			WHERE id = ?',
			array(
				get_nfilter_request_var('policy_graphs'),
				get_nfilter_request_var('policy_trees'),
				get_nfilter_request_var('policy_hosts'),
				get_nfilter_request_var('policy_graph_templates'),
				get_nfilter_request_var('id')
			)
		);
	} else {
		api_plugin_hook('user_admin_user_save');

		reset_user_perms(get_filter_request_var('id'));
	}

	/* redirect to the appropriate page */
	header('Location: user_admin.php?action=user_edit&id=' . (empty($user_id) ? get_filter_request_var('id') : $user_id));
}

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

	header('Location: user_admin.php?action=user_edit&tab=graph_perms_edit&id=' . get_request_var('user_id'));
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
			'policy_graphs'          => '1',
			'policy_trees'           => '1',
			'policy_hosts'           => '1',
			'policy_graph_templates' => '1'
		);
	}

	switch($tab) {
		case 'permsg':
			if (isempty_request_var('id')) {
				header('Location: user_admin.php');
			}

			process_graph_request_vars();

			graph_filter($header_label);

			form_start('user_admin.php', 'policy');

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
				<input type='submit' class='ui-button ui-corner-all ui-widget' name='update_policy' value='<?php print __esc('Update');?>'>
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

			$user_id  = get_filter_request_var('id');
			$policies = get_policies($user_id);

			$policies = array_reverse($policies);

			$limit    = 'LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

			/* form the 'where' clause for our main sql query */
			if (get_request_var('filter') != '') {
				$sql_where = 'WHERE (
				gtg.title_cache LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . '
				AND gtg.local_graph_id > 0)';
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
				$sql_join   = $details['sql_join'];
				$sql_select = $details['sql_select'];
			} else {
				$sql_join   = '';
				$sql_select = '';
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
			$limit");

			$total_rows = db_fetch_cell("SELECT COUNT(DISTINCT gl.id)
			FROM graph_templates_graph AS gtg
			INNER JOIN graph_local AS gl
			ON gl.id = gtg.local_graph_id
			LEFT JOIN graph_templates AS gt
			ON gt.id = gl.graph_template_id
			LEFT JOIN host AS h
			ON h.id = gl.host_id
			$sql_where");

			$nav = html_nav_bar('user_admin.php?action=user_edit&tab=permsg&id=' . get_request_var('id'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, __('Graphs'), 'page', 'main');

			form_start('user_admin.php?tab=permsg&id=' . get_request_var('id'), 'chk');

			print $nav;

			html_start_box('', '100%', '', '3', 'center', '');

			$display_text = array(__('Graph Title'), __('ID'), __('Effective Policy'));

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
				print '<tr><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No Matching Graphs Found') . '</em></td></tr>';
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
		case 'permsgr':
			if (isempty_request_var('id')) {
				header('Location: user_admin.php');
			}

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
				$sql_where = 'WHERE (
				uag.name LIKE '		   . db_qstr('%' . get_request_var('filter') . '%') . '
				OR uag.description LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
			} else {
				$sql_where = '';
			}

			if (get_request_var('associated') != 'false') {
				/* Show all items */
			} else {
				$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' uagm.user_id=' . get_request_var('id');
			}

			$total_rows = db_fetch_cell('SELECT
			COUNT(DISTINCT uag.id)
			FROM user_auth_group AS uag
			LEFT JOIN (SELECT user_id, group_id FROM user_auth_group_members WHERE user_id=' . get_request_var('id') . ") AS uagm
			ON uag.id = uagm.group_id
			$sql_where");

			$sql_query = 'SELECT DISTINCT uag.*, uagm.user_id
			FROM user_auth_group AS uag
			LEFT JOIN (SELECT user_id, group_id FROM user_auth_group_members WHERE user_id=' . get_request_var('id') . ") AS uagm
			ON uag.id = uagm.group_id
			$sql_where
			ORDER BY name
			LIMIT " . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

			$groups = db_fetch_assoc($sql_query);

			$nav = html_nav_bar('user_admin.php?action=user_edit&tab=permsgr&id=' . get_request_var('id'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, __('Groups'), 'page', 'main');

			form_start('user_admin.php?tab=permsd&id=' . get_request_var('id'), 'chk');

			print $nav;

			html_start_box('', '100%', '', '3', 'center', '');

			$display_text = array(__('Name'), __('Description'), __('Member'), __('ID'), __('Policies (Graph/Device/Template)'), __('Enabled'));

			html_header_checkbox($display_text, false);

			if (cacti_sizeof($groups)) {
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
				print '<tr><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No Matching User Groups Found') . '</em></td></tr>';
			}

			html_end_box(false);

			if (cacti_sizeof($groups)) {
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
			if (isempty_request_var('id')) {
				header('Location: user_admin.php');
			}

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
			/* form the 'where' clause for our main sql query */
			if (get_request_var('filter') != '') {
				$sql_where = 'WHERE host.deleted = "" AND (
				host.hostname LIKE '	   . db_qstr('%' . get_request_var('filter') . '%') . '
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
				$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' user_auth_perms.user_id=' . get_request_var('id', 0);
			}

			$total_rows = db_fetch_cell('SELECT
			COUNT(host.id)
			FROM host
			LEFT JOIN user_auth_perms
			ON host.id = user_auth_perms.item_id
			AND user_auth_perms.type = 3
			AND user_auth_perms.user_id = ' . get_request_var('id') . "
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

			$sql_query = 'SELECT host.*, user_auth_perms.user_id
			FROM host
			LEFT JOIN user_auth_perms
			ON host.id = user_auth_perms.item_id
			AND user_auth_perms.type = 3
			AND user_auth_perms.user_id = ' . get_request_var('id') . "
			$sql_where
			ORDER BY description
			LIMIT " . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

			$hosts = db_fetch_assoc($sql_query);

			$nav = html_nav_bar('user_admin.php?action=user_edit&tab=permsd&id=' . get_request_var('id'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, __('Devices'), 'page', 'main');

			form_start('user_admin.php?tab=permsd&id=' . get_request_var('id'), 'chk');

			print $nav;

			html_start_box('', '100%', '', '3', 'center', '');

			$display_text = array(__('Description'), __('ID'), __('Effective Policy'), __('Graphs'), __('Data Sources'), __('Status'), __('Hostname'));

			html_header_checkbox($display_text, false);

			if (cacti_sizeof($hosts)) {
				foreach ($hosts as $host) {
					form_alternate_row('line' . $host['id'], true);
					form_selectable_cell(filter_value($host['description'], get_request_var('filter')), $host['id']);
					form_selectable_cell($host['id'], $host['id']);

					if (empty($host['user_id']) || $host['user_id'] == null) {
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
				print '<tr><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No Matching Devices Found') . '</em></td></tr>';
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
			if (isempty_request_var('id')) {
				header('Location: user_admin.php');
			}

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
				$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' (user_auth_perms.type=4 AND user_auth_perms.user_id=' . get_request_var('id', 0) . ')';
			}

			$total_rows = db_fetch_cell_prepared("SELECT COUNT(DISTINCT gt.id)
			FROM graph_templates AS gt
			LEFT JOIN graph_local AS gl
			ON gt.id = gl.graph_template_id
			LEFT JOIN user_auth_perms
			ON gt.id = user_auth_perms.item_id
			AND user_auth_perms.type = 4
			AND user_auth_perms.user_id = ?
			$sql_where",
				array(get_request_var('id')));

			$sql_query = "SELECT gt.id, gt.name, COUNT(DISTINCT gl.id) AS totals, user_auth_perms.user_id
			FROM graph_templates AS gt
			LEFT JOIN graph_local AS gl
			ON gt.id = gl.graph_template_id
			LEFT JOIN user_auth_perms
			ON gt.id = user_auth_perms.item_id
			AND user_auth_perms.type = 4
			AND user_auth_perms.user_id = ?
			$sql_where
			GROUP BY gt.id
			ORDER BY name
			LIMIT " . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

			$graphs = db_fetch_assoc_prepared($sql_query, array(get_request_var('id')));

			$nav = html_nav_bar('user_admin.php?action=user_edit&tab=permste&id=' . get_request_var('id'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, __('Graph Templates'), 'page', 'main');

			form_start('user_admin.php?tab=permste&id=' . get_request_var('id'), 'chk');

			print $nav;

			html_start_box('', '100%', '', '3', 'center', '');

			$display_text = array(__('Template Name'), __('ID'), __('Effective Policy'), __('Total Graphs'));

			html_header_checkbox($display_text, false);

			if (cacti_sizeof($graphs)) {
				foreach ($graphs as $g) {
					form_alternate_row('line' . $g['id'], true);
					form_selectable_cell(filter_value($g['name'], get_request_var('filter')), $g['id']);
					form_selectable_cell($g['id'], $g['id']);

					if (empty($g['user_id']) || $g['user_id'] == null) {
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
				print '<tr><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No Matching Graph Templates Found') . '</em></td></tr>';
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
			if (isempty_request_var('id')) {
				header('Location: user_admin.php');
			}

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
				$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' (user_auth_perms.type=2 AND user_auth_perms.user_id=' . get_request_var('id', 0) . ')';
			}

			$total_rows = db_fetch_cell('SELECT
			COUNT(DISTINCT gt.id)
			FROM graph_tree AS gt
			LEFT JOIN user_auth_perms
			ON gt.id = user_auth_perms.item_id
			AND user_auth_perms.type = 2
			AND user_auth_perms.user_id = ' . get_request_var('id') . "
			$sql_where");

			$sql_query = 'SELECT gt.id, gt.name, user_auth_perms.user_id
			FROM graph_tree AS gt
			LEFT JOIN user_auth_perms
			ON gt.id = user_auth_perms.item_id
			AND user_auth_perms.type = 2
			AND user_auth_perms.user_id = ' . get_request_var('id') . "
			$sql_where
			ORDER BY name
			LIMIT " . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

			$trees = db_fetch_assoc($sql_query);

			$nav = html_nav_bar('user_admin.php?action=user_edit&tab=permstr&id=' . get_request_var('id'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, __('Trees'), 'page', 'main');

			form_start('user_admin.php?tab=permstr&id=' . get_request_var('id'), 'chk');

			print $nav;

			html_start_box('', '100%', '', '3', 'center', '');

			$display_text = array(__('Tree Name'), __('ID'), __('Effective Policy'));

			html_header_checkbox($display_text, false);

			if (cacti_sizeof($trees)) {
				foreach ($trees as $t) {
					form_alternate_row('line' . $t['id'], true);
					form_selectable_cell(filter_value($t['name'], get_request_var('filter')), $t['id']);
					form_selectable_cell($t['id'], $t['id']);

					if (empty($t['user_id']) || $t['user_id'] == null) {
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
				print '<tr><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No Matching Trees Found') . '</em></td></tr>';
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

function user_realms_edit($header_label) {
	global $user_auth_realms, $user_auth_roles;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$all_realms = $user_auth_realms;

	print "<div class='cactiTable' style='width:100%;text-align:left;'>
		<div>
			<div class='cactiTableTitle'><span style='padding:3px;'>" . __('User Permissions') . ' ' . html_escape($header_label) . "</span></div>
			<div class='cactiTableButton'><span style='padding:3px;'><input class='checkbox' type='checkbox' id='all' name='all' title='" . __esc('Select All') . "' onClick='selectAllRealms(this.checked)'></a><label class='formCheckboxLabel' title='" . __esc('Select All') . "' for='all'></label></span></div>
		</div>
	</div>";

	form_start('user_admin.php', 'chk');

	html_start_box('', '100%', '', '3', 'center', '');

	/* do cacti realms first */
	foreach ($user_auth_roles as $role_name => $perms) {
		print "<tr class='tableHeader'><th colspan='2'>" . html_escape($role_name) . '</th></tr>';
		print "<tr class='odd'><td colspan='2'><div class='flexContainer'>";

		foreach ($perms as $realm) {
			if (isset($user_auth_realms[$realm])) {
				$set = db_fetch_cell_prepared('SELECT realm_id
					FROM user_auth_realm
					WHERE user_id = ?
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
		print "<tr class='tableHeader'><th class='left' colspan='2'>" . __('External Link Permissions') . '</th></tr>';
		print "<tr class='odd'><td class='left' colspan='2'><div class='flexContainer'>";

		foreach ($links as $r) {
			$realm = $r['id'] + 10000;

			$set = db_fetch_cell_prepared('SELECT realm_id
				FROM user_auth_realm
				WHERE user_id = ?
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
	$realms = db_fetch_assoc('SELECT pc.directory, pc.name, pr.id AS realm_id, pr.display
		FROM plugin_config AS pc
		INNER JOIN plugin_realms AS pr
		ON pc.directory = pr.plugin
		ORDER BY pc.name, pr.display');

	$i = 0;

	if (cacti_sizeof($realms)) {
		foreach ($realms as $r) {
			$realm = $r['realm_id'] + 100;

			// Skip already set realms
			foreach ($user_auth_roles as $role => $rrealms) {
				foreach ($rrealms as $realm_id) {
					if ($realm == $realm_id) {
						unset($all_realms[$realm]);

						continue 3;
					}
				}
			}

			$set = db_fetch_cell_prepared('SELECT realm_id
				FROM user_auth_realm
				WHERE user_id = ?
				AND realm_id = ?',
				array(get_request_var('id', 0), $realm));

			if ($set) {
				$old_value = 'on';
			} else {
				$old_value = '';
			}

			unset($all_realms[$realm]);

			$local_user_auth_realms = __($user_auth_realms[$realm], $r['directory']);

			$pos = (strpos($local_user_auth_realms, '->') !== false ? strpos($local_user_auth_realms, '->') + 2:0);

			if ($i == 0) {
				print "<tr class='tableHeader'><th class='left' colspan='2'>" . __('Plugin Permissions') . '</th></tr>';
				print "<tr class='odd'><td colspan='2'><div class='flexContainer'>";
				$i++;
			}

			print '<div class="flexChild">';
			form_checkbox('section' . $realm, $old_value, trim(substr($local_user_auth_realms, $pos)), '', '', '', (!isempty_request_var('id') ? 1 : 0), $r['display'], true);
			print '</div>';
		}

		if ($i > 0) {
			print '</div></td></tr>';
		}
	}

	/* get the old PIA 1.x realms */
	if (cacti_sizeof($all_realms)) {
		print "<tr class='tableHeader'><th class='left' colspan='2'>" . __('Legacy Permissions') . '</th></tr>';

		foreach ($all_realms as $realm => $name) {
			$set = db_fetch_cell_prepared('SELECT realm_id
				FROM user_auth_realm
				WHERE user_id = ?
				AND realm_id = ?',
				array(get_request_var('id', 0), $realm));

			if ($set) {
				$old_value = 'on';
			} else {
				$old_value = '';
			}

			$pos = (strpos($user_auth_realms[$realm], '->') !== false ? strpos($user_auth_realms[$realm], '->') + 2:0);

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

	html_start_box(__esc('User Settings %s', $header_label), '100%', true, '3', 'center', '');

	foreach ($settings_user as $tab_short_name => $tab_fields) {
		$collapsible = true;

		print "<div class='spacer formHeader" . ($collapsible ? ' collapsible':'') . "' id='row_$tab_short_name'><div style='cursor:pointer;' class='tableSubHeaderColumn'>" . $tabs_graphs[$tab_short_name] . ($collapsible ? "<div style='float:right;padding-right:4px;'><i class='fa fa-angle-double-up'></i></div>":'') . '</div></div>';

		$form_array = array();

		foreach ($tab_fields as $field_name => $field_array) {
			$form_array += array($field_name => $tab_fields[$field_name]);

			if ((isset($field_array['items'])) && (is_array($field_array['items']))) {
				foreach ($field_array['items'] as $sub_field_name => $sub_field_array) {
					if (user_setting_exists($sub_field_name, get_request_var('id'))) {
						$form_array[$field_name]['items'][$sub_field_name]['form_id'] = 1;
					}

					$form_array[$field_name]['items'][$sub_field_name]['value'] =  db_fetch_cell_prepared('SELECT value FROM settings_user WHERE name = ? AND user_id = ?', array($sub_field_name, get_request_var('id')));
				}
			} else {
				if (user_setting_exists($field_name, get_request_var('id'))) {
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
		var custom_fonts = $('#custom_fonts').is(':checked');
		var fields = {
			fonts: (themeFonts == 1),
			custom_fonts: themeFonts != 1 && custom_fonts,
			title_size: themeFonts != 1 && custom_fonts,
			title_font: themeFonts != 1 && custom_fonts,
			legend_size: themeFonts != 1 && custom_fonts,
			legend_font: themeFonts != 1 && custom_fonts,
			axis_size: themeFonts != 1 && custom_fonts,
			axis_font: themeFonts != 1 && custom_fonts,
			unit_size: themeFonts != 1 && custom_fonts,
			unit_font: themeFonts != 1 && custom_fonts,
		}
	}

	$(function() {
		graphSettings();
	});

	</script>
	<?php
}

function user_edit() {
	global $config, $fields_user_user_edit_host;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('tab', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-z_A-Z]+)$/')));
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

	$permission_model = read_config_option('graph_auth_method');

	if ($permission_model == 3) { // Device Based
		unset($tabs['permste']);
	} elseif ($permission_model == 4) { // Graph Template Based
		unset($tabs['permsd']);
	}

	/* set the default tab */
	load_current_session_value('tab', 'sess_user_admin_tab', 'general');
	$current_tab = get_nfilter_request_var('tab');

	if (!isempty_request_var('id')) {
		$user         = db_fetch_row_prepared('SELECT * FROM user_auth WHERE id = ?', array(get_request_var('id')));
		$header_label = __('[edit: %s]', $user['username']);
	} else {
		$header_label = __('[new]');
	}

	if (cacti_sizeof($tabs) && !isempty_request_var('id')) {
		$i = 0;

		/* draw the tabs */
		print "<div class='tabs'><nav><ul role='tablist'>";

		foreach (array_keys($tabs) as $tab_short_name) {
			print "<li class='subTab'><a class='tab" . (($tab_short_name == $current_tab) ? " selected'" : "'") .
				" href='" . html_escape(CACTI_PATH_URL .
				'user_admin.php?action=user_edit&id=' . get_request_var('id') .
				'&tab=' . $tab_short_name) .
				"'>" . $tabs[$tab_short_name] . '</a></li>';

			$i++;
		}

		api_plugin_hook('user_admin_tab');

		print '</ul></nav></div>';
	}

	switch($current_tab) {
		case 'general':
			api_plugin_hook_function('user_admin_edit', (isset($user) ? get_request_var('id') : 0));

			form_start('user_admin.php');

			html_start_box(__esc('User Management %s', $header_label), '100%', '', '3', 'center', '');

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

		var templateAccount=<?php print is_template_account(get_filter_request_var('id')) ? 'true':'false';?>;
		var consoleAllowed=<?php print(isset($user['id']) ? (is_realm_allowed(8, (isset($user) ? $user['id'] : 0)) ? 'true':'false'):'false');?>;

		function changeRealm() {
			if ($('#realm').val() != 0) {
				$('#password_change').prop('disabled', true);
			} else {
				$('#password_change').prop('disabled', false);
			}
		}

		var password_change = $('#password_change').is(':checked');

		$(function() {
			changeRealm();

			/* clear passwords */
			$('#password').val('');
			$('#password_confirm').val('');

            $('#realm').change(function () {
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

			if (templateAccount == true) {
				$('#realm').prop('disabled', true);
				$('#enabled').prop('disabled', true);

				if ($('#realm').selectmenu('instance')) {
					$('#realm').selectmenu('disable');
				}
			}

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
				user_realms_edit($header_label);
			}

			break;
	}
}

function user() {
	global $config, $auth_realms, $actions, $item_rows;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'realm' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'page' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
		),
		'login' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '0'
		),
		'filter' => array(
			'filter'  => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
		),
		'group' => array(
			'filter' => FILTER_CALLBACK,
			'default' => '-1',
			'pageset' => true,
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_column' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'username',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_direction' => array(
			'filter'  => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
		)
	);

	validate_store_request_vars($filters, 'sess_usera');
	/* ================= input validation ================= */

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL  = 'user_admin.php?rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&group=' + $('#group').val();
		strURL += '&realm=' + $('#realm').val();
		strURL += '&login=' + $('#login').val();
		loadUrl({url:strURL})
	}

	function clearFilter() {
		strURL = 'user_admin.php?clear=1';
		loadUrl({url:strURL})
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#realm, #rows, #group, #login').change(function() {
			applyFilter();
		});

		$('#forms').submit(function(event) {
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
		<form id='forms' action='user_admin.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Group');?>
					</td>
					<td>
						<select id='group' data-defaultLabel='<?php print __('Group');?>'>
							<option value='-1'<?php print (get_request_var('group') == '-1' ? ' selected>':'>') . __('All');?></option>
							<?php
							$groups = array_rekey(
								db_fetch_assoc('SELECT id, description
									FROM user_auth_group
									ORDER BY description'),
								'id', 'description'
							);

							if (cacti_sizeof($groups)) {
								foreach ($groups as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('group') == $key) { print ' selected'; } print '>' . html_escape($value) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Last Login');?>
					</td>
					<td>
						<select id='login' data-defaultLabel='<?php print __('Last Login');?>'>
							<option value='0'<?php print (get_request_var('login') == '0' ? ' selected>':'>') . __esc('All');?></option>
							<option value='1'<?php print (get_request_var('login') == '1' ? ' selected>':'>') . __esc('< 1 Week Ago');?></option>
							<option value='2'<?php print (get_request_var('login') == '2' ? ' selected>':'>') . __esc('< 1 Month Ago');?></option>
							<option value='3'<?php print (get_request_var('login') == '3' ? ' selected>':'>') . __esc('> 1 Month Ago');?></option>
							<option value='4'<?php print (get_request_var('login') == '4' ? ' selected>':'>') . __esc('> 2 Months Ago');?></option>
							<option value='5'<?php print (get_request_var('login') == '5' ? ' selected>':'>') . __esc('> 4 Months Ago');?></option>
							<option value='6'<?php print (get_request_var('login') == '6' ? ' selected>':'>') . __esc('Never');?></option>
						</select>
					</td>
					<td>
						<?php print __('Realm');?>
					</td>
					<td>
						<select id='realm' data-defaultLabel='<?php print __('Realm');?>'>
							<option value='-1'<?php print(get_request_var('realm') == '-1' ? ' selected>':'>') . __('All');?></option>
							<option value='0'<?php print(get_request_var('realm') == '0' ? ' selected>':'>') . __('Local');?></option>
							<option value='2'<?php print(get_request_var('realm') == '2' ? ' selected>':'>') . __('Basic');?></option>
							<option value='3'<?php print(get_request_var('realm') == '3' ? ' selected>':'>') . __('LDAP/AD');?></option>
							<option value='4'<?php print(get_request_var('realm') == '4' ? ' selected>':'>') . __('Domain');?></option>
						</select>
					</td>
					<td>
						<?php print __('Users');?>
					</td>
					<td>
						<select id='rows' data-defaultLabel='<?php print __('Users');?>'>
							<option value='-1'<?php print(get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'";

									if (get_request_var('rows') == $key) {
										print ' selected';
									} print '>' . html_escape($value) . '</option>';
								}
							}
	?>
						</select>
					</td>
					<td>
						<span>
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='go' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
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
		$sql_where = 'WHERE (
			ua.username LIKE '     . db_qstr('%' . get_request_var('filter') . '%') . '
			OR ua.full_name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	} else {
		$sql_where = '';
	}

	if (get_request_var('realm') >= 0) {
		if (get_request_var('realm') < 4) {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' ua.realm = ' . get_request_var('realm');
		} else {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' ua.realm > 3';
		}
	}

	if (get_request_var('group') > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' ug.group_id = ' . get_request_var('group');
	}

	if (get_request_var('login') > 0) {
		if (get_request_var('login') == 1) {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' time > DATE_SUB(NOW(), INTERVAL 1 WEEK)';
		} elseif (get_request_var('login') == 2) {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' time > DATE_SUB(NOW(), INTERVAL 1 MONTH)';
		} elseif (get_request_var('login') == 3) {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' time < DATE_SUB(NOW(), INTERVAL 1 MONTH)';
		} elseif (get_request_var('login') == 4) {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' time < DATE_SUB(NOW(), INTERVAL 2 MONTH)';
		} elseif (get_request_var('login') == 5) {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' time < DATE_SUB(NOW(), INTERVAL 4 MONTH)';
		} elseif (get_request_var('login') == 6) {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' time IS NULL';
		}
	}

	$total_rows = db_fetch_cell("SELECT
		COUNT(DISTINCT ua.id)
		FROM user_auth AS ua
		LEFT JOIN (
			SELECT user_id, MAX(time) AS time
			FROM user_log
			GROUP BY user_id
		) AS ul
		ON ua.id = ul.user_id
		LEFT JOIN user_auth_group_members AS ug
		ON ua.id = ug.user_id
		$sql_where");

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;

	$user_list = db_fetch_assoc("SELECT ua.id, ua.username, ua.full_name,
		ua.realm, ua.enabled, ua.policy_graphs, ua.policy_hosts, ua.policy_graph_templates,
		time, MAX(UNIX_TIMESTAMP(time)) as dtime
		FROM user_auth AS ua
		LEFT JOIN (
			SELECT user_id, MAX(time) AS time
			FROM user_log
			GROUP BY user_id
		) AS ul
		ON ua.id = ul.user_id
		LEFT JOIN user_auth_group_members AS ug
		ON ua.id = ug.user_id
		$sql_where
		GROUP BY ua.id
		$sql_order
		$sql_limit");

	$nav = html_nav_bar('user_admin.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 9, __('Users'), 'page', 'main');

	form_start('user_admin.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'username'               => array(__('User Name'), 'ASC'),
		'id'                     => array(__('User ID'), 'ASC'),
		'full_name'              => array(__('Full Name'), 'ASC'),
		'enabled'                => array(__('Enabled'), 'ASC'),
		'realm'                  => array(__('Realm'), 'ASC'),
		'policy_graphs'          => array(__('Graph Policy'), 'ASC'),
		'policy_hosts'           => array(__('Device Policy'), 'ASC'),
		'policy_graph_templates' => array(__('Template Policy'), 'ASC'),
		'dtime'                  => array(__('Last Login'), 'DESC')
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (cacti_sizeof($user_list)) {
		foreach ($user_list as $user) {
			if (empty($user['dtime']) || $user['dtime'] <= 10000) {
				$last_login = __('N/A');
			} else {
				$last_login = date('l, F d, Y H:i:s ', $user['dtime']);
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

			// Check for a disabled removal
			$disabled = is_template_account($user['id']);

			if ($_SESSION[SESS_USER_ID] == $user['id']) {
				$disabled = true;
			}

			form_alternate_row('line' . $user['id'], true, $disabled);

			form_selectable_cell(filter_value($user['username'], get_request_var('filter'), CACTI_PATH_URL . 'user_admin.php?action=user_edit&tab=general&id=' . $user['id']), $user['id']);
			form_selectable_cell($user['id'], $user['id']);
			form_selectable_cell(filter_value($user['full_name'], get_request_var('filter')), $user['id']);
			form_selectable_cell($enabled, $user['id']);
			form_selectable_cell($realm, $user['id']);
			form_selectable_cell(($user['policy_graphs'] == 1 ? __('ALLOW'):__('DENY')), $user['id']);
			form_selectable_cell(($user['policy_hosts'] == 1 ? __('ALLOW'):__('DENY')), $user['id']);
			form_selectable_cell(($user['policy_graph_templates'] == 1 ? __('ALLOW'):__('DENY')), $user['id']);
			form_selectable_cell($last_login, $user['id']);
			form_checkbox_cell($user['username'], $user['id'], $disabled);

			form_end_row();
		}
	} else {
		print '<tr><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No Users Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($user_list)) {
		print $nav;
	}

	draw_actions_dropdown($actions);

	form_end();
}

function process_graph_request_vars() {
	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => read_config_option('num_rows_table')
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
		'graph_template_id' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1',
		),
		'associated' => array(
			'filter'  => FILTER_VALIDATE_REGEXP,
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
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => read_config_option('num_rows_table')
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
		'associated' => array(
			'filter'  => FILTER_VALIDATE_REGEXP,
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
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => read_config_option('num_rows_table')
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
		'host_template_id' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1',
		),
		'associated' => array(
			'filter'  => FILTER_VALIDATE_REGEXP,
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
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => read_config_option('num_rows_table')
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
		'graph_template_id' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1',
		),
		'associated' => array(
			'filter'  => FILTER_VALIDATE_REGEXP,
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
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => read_config_option('num_rows_table')
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
		'graph_template_id' => array(
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1',
		),
		'associated' => array(
			'filter'  => FILTER_VALIDATE_REGEXP,
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
		loadUrl({url:strURL})
	}

	function clearFilter(objForm) {
		strURL = 'user_admin.php?action=user_edit&tab=permsg&id=<?php print get_request_var('id');?>&clear=true'
		loadUrl({url:strURL})
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
		})

		$('#forms').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php

	html_start_box(__esc('Graph Permissions %s', $header_label), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='forms' action='user_admin.php'>
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
			print "<option value='" . $gt['id'] . "'";

			if (get_request_var('graph_template_id') == $gt['id']) {
				print ' selected';
			} print '>' . html_escape($gt['name']) . '</option>';
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
							<option value='-1'<?php print(get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
	if (cacti_sizeof($item_rows)) {
		foreach ($item_rows as $key => $value) {
			print "<option value='" . $key . "'";

			if (get_request_var('rows') == $key) {
				print ' selected';
			} print '>' . html_escape($value) . '</option>';
		}
	}
	?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' id='associated' <?php print(get_request_var('associated') == 'true' || get_request_var('associated') == 'on' ? 'checked':'');?>>
							<label for='associated'><?php print __('Show All');?></label>
						</span>
					</td>
					<td>
						<span>
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='go' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
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
		loadUrl({url:strURL})
	}

	function clearFilter() {
		strURL = 'user_admin.php?action=user_edit&tab=permsgr&id=<?php print get_request_var('id');?>&clear=true'
		loadUrl({url:strURL})
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

	html_start_box(__esc('Group Membership %s', $header_label), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='forms' action='user_admin.php'>
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
							<option value='-1'<?php print(get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'";

									if (get_request_var('rows') == $key) {
										print ' selected';
									} print '>' . html_escape($value) . '</option>';
								}
							}
	?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' id='associated' <?php print(get_request_var('associated') == 'true' || get_request_var('associated') == 'on' ? 'checked':'');?>>
							<label for='associated'><?php print __('Show All');?></label>
						</span>
					</td>
					<td>
						<span>
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='go' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
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
		loadUrl({url:strURL})
	}

	function clearFilter(objForm) {
		strURL = 'user_admin.php?action=user_edit&tab=permsd&id=<?php print get_request_var('id');?>&clear=true'
		loadUrl({url:strURL})
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

	html_start_box(__esc('Devices Permission %s', $header_label), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='forms' action='user_admin.php'>
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
						<?php print __('Devices');?>
					</td>
					<td>
						<select id='rows'>
							<option value='-1'<?php print(get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
	if (cacti_sizeof($item_rows)) {
		foreach ($item_rows as $key => $value) {
			print "<option value='" . $key . "'";

			if (get_request_var('rows') == $key) {
				print ' selected';
			} print '>' . html_escape($value) . '</option>';
		}
	}
	?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' id='associated' <?php print(get_request_var('associated') == 'true' || get_request_var('associated') == 'on' ? 'checked':'');?>>
							<label for='associated'><?php print __('Only Show Exceptions');?></label>
						</span>
					</td>
					<td>
						<span>
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='go' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
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
		loadUrl({url:strURL})
	}

	function clearFilter() {
		strURL = 'user_admin.php?action=user_edit&tab=permste&id=<?php print get_request_var('id');?>&clear=true'
		loadUrl({url:strURL})
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

	html_start_box(__esc('Template Permission %s', $header_label), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='forms' action='user_admin.php'>
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
							<option value='-1'<?php print(get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'";

									if (get_request_var('rows') == $key) {
										print ' selected';
									} print '>' . html_escape($value) . '</option>';
								}
							}
	?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' id='associated' <?php print(get_request_var('associated') == 'true' || get_request_var('associated') == 'on' ? 'checked':'');?>>
							<label for='associated'><?php print __('Only Show Exceptions');?></label>
						</span>
					</td>
					<td>
						<span>
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='go' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
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
		loadUrl({url:strURL})
	}

	function clearFilter() {
		strURL = 'user_admin.php?action=user_edit&tab=permstr&id=<?php print get_request_var('id');?>&clear=true'
		loadUrl({url:strURL})
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

	html_start_box(__esc('Tree Permission %s', $header_label), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='forms' action='user_admin.php'>
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
							<option value='-1'<?php print(get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'";

									if (get_request_var('rows') == $key) {
										print ' selected';
									} print '>' . html_escape($value) . '</option>';
								}
							}
	?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' id='associated' <?php print(get_request_var('associated') == 'true' || get_request_var('associated') == 'on' ? 'checked':'');?>>
							<label for='associated'><?php print __('Only Show Exceptions');?></label>
						</span>
					</td>
					<td>
						<span>
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='go' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
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
		loadUrl({url:strURL})
	}

	function clearFilter(objForm) {
		strURL = 'user_admin.php?action=user_edit&tab=members&id=<?php print get_request_var('id');?>&clear=true'
		loadUrl({url:strURL})
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

	html_start_box(__esc('Tree Permission %s', $header_label), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='forms' action='user_admin.php'>
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
							<option value='-1'<?php print(get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'";

									if (get_request_var('rows') == $key) {
										print ' selected';
									} print '>' . html_escape($value) . '</option>';
								}
							}
	?>
						</select>
					</td>
					<td>
						<span>
							<input type='checkbox' id='associated' <?php print(get_request_var('associated') == 'true' || get_request_var('associated') == 'on' ? 'checked':'');?>>
							<label for='associated'><?php print __('Only Show Exceptions');?></label>
						</span>
					</td>
					<td>
						<span>
							<input type='submit' class='ui-button ui-corner-all ui-widget' id='go' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
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
