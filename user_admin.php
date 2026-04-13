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

require('./include/auth.php');

$actions = [
	1 => __('Delete'),
	2 => __('Copy'),
	3 => __('Enable'),
	4 => __('Disable'),
	5 => __('Batch Copy')
];

if (read_config_option('secpass_2fa_enabled') == 'on') {
	$actions[6] = __('Reset 2FA');
}

set_default_action();

if (isrv('update_policy')) {
	update_policies();
} else {
	switch (grv('action')) {
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
			$error = secpass_check_pass(gnrv('password'));

			if ($error == '') {
				print $error;
			} else {
				print 'ok';
			}

			break;
		default:
			if (!api_plugin_hook_function('user_admin_action', grv('action'))) {
				top_header();
				user();
				bottom_footer();
			}

			break;
	}
}

function update_policies() : void {
	$policies = ['policy_graphs', 'policy_trees', 'policy_hosts', 'policy_graph_templates'];

	foreach ($policies as $p) {
		if (isrv($p)) {
			db_execute_prepared("UPDATE `user_auth` SET `$p` = ? WHERE `id` = ?", [gfrv($p), gfrv('id')]);
		}
	}

	header('Location: user_admin.php?action=user_edit&tab=' . gnrv('tab') . '&id=' . gnrv('id'));

	exit;
}

function form_actions() : void {
	global $actions, $auth_realms;

	// if we are to save this form, instead of display it
	if (isrv('associate_host')) {
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				if (gnrv('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_perms
						(user_id, item_id, type)
						VALUES (?, ?, 3)',
						[gnrv('id'), $matches[1]]);
				} else {
					db_execute_prepared('DELETE FROM user_auth_perms
						WHERE user_id = ?
						AND item_id = ?
						AND type = 3',
						[gnrv('id'), $matches[1]]);
				}
			}
		}

		header('Location: user_admin.php?action=user_edit&tab=permsd&id=' . gnrv('id'));

		exit;
	}

	if (isrv('associate_graph')) {
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				if (gnrv('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_perms
						(user_id, item_id, type)
						VALUES (?, ?, 1)',
						[gnrv('id'), $matches[1]]);
				} else {
					db_execute_prepared('DELETE FROM user_auth_perms
						WHERE user_id = ?
						AND item_id = ?
						AND type = 1',
						[gnrv('id'), $matches[1]]);
				}
			}
		}

		header('Location: user_admin.php?action=user_edit&tab=permsg&id=' . gnrv('id'));

		exit;
	}

	if (isrv('associate_template')) {
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				if (gnrv('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_perms
						(user_id, item_id, type)
						VALUES (?, ?, 4)',
						[gnrv('id'), $matches[1]]);
				} else {
					db_execute_prepared('DELETE FROM user_auth_perms
						WHERE user_id = ?
						AND item_id = ?
						AND type = 4',
						[gnrv('id'), $matches[1]]);
				}
			}
		}

		header('Location: user_admin.php?action=user_edit&tab=permste&id=' . gnrv('id'));

		exit;
	}

	if (isrv('associate_groups')) {
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				if (gnrv('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_group_members
						(user_id, group_id)
						VALUES (?, ?)',
						[gnrv('id'), $matches[1]]);
				} else {
					db_execute_prepared('DELETE FROM user_auth_group_members
						WHERE user_id = ?
						AND group_id = ?',
						[gnrv('id'), $matches[1]]);
				}
			}
		}

		header('Location: user_admin.php?action=user_edit&tab=permsgr&id=' . gnrv('id'));

		exit;
	}

	if (isrv('associate_tree')) {
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				if (gnrv('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_perms
						(user_id, item_id, type)
						VALUES (?, ?, 2)',
						[gnrv('id'), $matches[1]]);
				} else {
					db_execute_prepared('DELETE FROM user_auth_perms
						WHERE user_id = ?
						AND item_id = ?
						AND type = 2',
						[gnrv('id'), $matches[1]]);
				}
			}
		}

		header('Location: user_admin.php?action=user_edit&tab=permstr&id=' . gnrv('id'));

		exit;
	}

	if (isrv('selected_items')) {
		if (gnrv('drp_action') == '2') { // copy
			// ================= input validation =================
			gfrv('selected_items');
			gfrv('new_realm');
			// ====================================================

			$new_username  = gnrv('new_username');
			$new_realm     = gnrv('new_realm', 0);

			$template_user = db_fetch_row_prepared('SELECT username, realm
				FROM user_auth
				WHERE id = ?',
				[gnrv('selected_items')]);

			$overwrite     = [ 'full_name' => gnrv('new_fullname') ];

			if ($new_username != '') {
				if (cacti_sizeof(db_fetch_assoc_prepared('SELECT username FROM user_auth WHERE username = ? AND realm = ?', [$new_username, $new_realm]))) {
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
			$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

			if ($selected_items != false) {
				if (gnrv('drp_action') == '1') { // delete
					for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
						if ($_SESSION[SESS_USER_ID] != $selected_items[$i]) {
							user_remove($selected_items[$i]);
						} else {
							raise_message('attempt current', __('You are not allowed to delete the current login account'), MESSAGE_LEVEL_ERROR);
						}
					}
				} elseif (gnrv('drp_action') == '3') { // enable
					for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
						user_enable($selected_items[$i]);
					}
				} elseif (gnrv('drp_action') == '4') { // disable
					for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
						if ($_SESSION[SESS_USER_ID] != $selected_items[$i]) {
							user_disable($selected_items[$i]);
						} else {
							raise_message('attempt current', __('You are not allowed to disable the current login account'), MESSAGE_LEVEL_ERROR);
						}
					}
				} elseif (gnrv('drp_action') == '5') { // batch copy
					// ================= input validation =================
					gfrv('template_user');
					// ====================================================

					$copy_error = false;
					$template   = db_fetch_row_prepared('SELECT username, realm
						FROM user_auth
						WHERE id = ?',
						[gnrv('template_user')]);

					for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
						$user = db_fetch_row_prepared('SELECT username, realm
							FROM user_auth
							WHERE id = ?',
							[$selected_items[$i]]);

						if (cacti_sizeof($user) && cacti_sizeof($template)) {
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
				} elseif (gnrv('drp_action') == '6') { // reset 2FA token and status
					foreach ($selected_items as $user_id) {
						disable_2fa($user_id);
					}
				}
			}
		}

		header('Location: user_admin.php');

		exit;
	} else {
		$ilist  = '';
		$iarray = [];

		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				if (gnrv('drp_action') != '2') {
					$ilist .= '<li>' . htmle(db_fetch_cell_prepared('SELECT username FROM user_auth WHERE id = ?', [$matches[1]])) . '</li>';
				}

				$iarray[] = $matches[1];
			}
		}

		if (cacti_sizeof($iarray)) {
			$user_id    = $iarray[0];
			$user_realm = db_fetch_cell_prepared('SELECT realm FROM user_auth WHERE id = ?', [$user_id]);
			$template   = htmle(db_fetch_cell_prepared('SELECT username FROM user_auth WHERE id = ?', [$user_id]));
			$usernames  = db_fetch_assoc('SELECT id, username FROM user_auth WHERE realm = 0 ORDER BY username');
		} else {
			$user_id    = null;
			$user_realm = null;
			$template   = null;
			$usernames  = null;
		}

		$form_data = [
			'general' => [
				'page'       => 'user_admin.php',
				'actions'    => $actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			],
			'options' => [
				1 => [
					'smessage' => __('Click \'Continue\' to Delete the following User.'),
					'pmessage' => __('Click \'Continue\' to Delete following Users.'),
					'scont'    => __('Delete User'),
					'pcont'    => __('Delete Users')
				],
				2 => [
					'message'  => __('Click \'Continue\' to Copy the following User.'),
					'cont'     => __('Copy User'),
					'extra'    => [
						'template_username' => [
							'method'  => 'other',
							'title'   => __('Template Username'),
							'default' => $template
						],
						'new_username' => [
							'method'  => 'textbox',
							'title'   => __('Username'),
							'default' => '',
							'width'   => 25
						],
						'new_fullname' => [
							'method'  => 'textbox',
							'title'   => __('Full Name'),
							'default' => '',
							'width'   => 35
						],
						'new_realm' => [
							'method'  => 'drop_array',
							'title'   => __('Realm'),
							'array'   => $auth_realms,
							'default' => $user_realm
						]
					]
				],
				3 => [
					'smessage' => __('Click \'Continue\' to Enable the following User.'),
					'pmessage' => __('Click \'Continue\' to Enable following Users.'),
					'scont'    => __('Enable User'),
					'pcont'    => __('Enable Users')
				],
				4 => [
					'smessage' => __('Click \'Continue\' to Disable the following User.'),
					'pmessage' => __('Click \'Continue\' to Disable following Users.'),
					'scont'    => __('Disable User'),
					'pcont'    => __('Disable Users')
				],
				5 => [
					'smessage' => __('Click \'Continue\' to Overwrite the User settings with the selected Template User settings and permissions.  The original User Full Name, Password, Realm and Enable status will be retained, all other fields will be overwritten from the Template User.'),
					'pmessage' => __('Click \'Continue\' to Overwrite the Users settings with the selected Template User settings and permissions.  The original Users Full Name, Password, Realm and Enable status will be retained, all other fields will be overwritten from the Template User.'),
					'scont'    => __('Replace User Settings for User'),
					'pcont'    => __('Replace User Settings for Users'),
					'extra'    => [
						'new_realm' => [
							'method'   => 'drop_array',
							'title'    => __('Template User'),
							'array'    => $usernames,
							'variable' => 'username',
							'id'       => 'id'
						]
					]
				],
				6 => [
					'smessage' => __('Click \'Continue\' to Reset 2FA for User.'),
					'pmessage' => __('Click \'Continue\' to Reset 2FA for following Users.'),
					'scont'    => __('Reset 2FA for User'),
					'pcont'    => __('Reset 2FA for Users')
				],
			]
		];

		form_continue_confirmation($form_data);
	}
}

function form_save() : void {
	global $settings_user;

	// graph permissions
	if ((isrv('save_component_graph_perms')) && (!is_error_message())) {
		// ================= input validation =================
		gfrv('id');
		gfrv('perm_graphs');
		gfrv('perm_trees');
		gfrv('perm_hosts');
		gfrv('perm_graph_templates');
		gfrv('policy_graphs');
		gfrv('policy_trees');
		gfrv('policy_hosts');
		gfrv('policy_graph_templates');
		// ====================================================

		$add_button_clicked = false;

		if (isrv('add_graph_x')) {
			db_execute_prepared('REPLACE INTO user_auth_perms
				(user_id,item_id,type)
				VALUES (?, ?, 1)',
				[gnrv('id'), gnrv('perm_graphs')]);

			$add_button_clicked = true;
		} elseif (isrv('add_tree_x')) {
			db_execute_prepared('REPLACE INTO user_auth_perms
				(user_id,item_id,type)
				VALUES (?, ?, 2)',
				[gnrv('id'), gnrv('perm_trees')]);

			$add_button_clicked = true;
		} elseif (isrv('add_host_x')) {
			db_execute_prepared('REPLACE INTO user_auth_perms
				(user_id,item_id,type)
				VALUES (?, ?, 3)',
				[gnrv('id'), gnrv('perm_hosts')]);

			$add_button_clicked = true;
		} elseif (isrv('add_graph_template_x')) {
			db_execute_prepared('REPLACE INTO user_auth_perms
				(user_id,item_id,type)
				VALUES (?, ?, 4)',
				[gnrv('id'), gnrv('perm_graph_templates')]);

			$add_button_clicked = true;
		}

		if ($add_button_clicked == true) {
			header('Location: user_admin.php?action=user_edit&tab=graph_perms_edit&id=' . gnrv('id'));

			exit;
		}
	} elseif (isrv('save_component_user')) {
		// user management save
		// ================= input validation =================
		gfrv('id');
		gfrv('realm');
		gfrv('policy_hosts');
		gfrv('policy_graphs');
		gfrv('policy_trees');
		gfrv('policy_graph_templates');
		// ====================================================

		$old_password = db_fetch_cell_prepared('SELECT password
			FROM user_auth
			WHERE id = ?',
			[gnrv('id')]);

		if ((gnrv('password') == '') && (gnrv('password_confirm') == '')) {
			$password = $old_password;
		} else {
			$password = compat_password_hash(gnrv('password'), PASSWORD_DEFAULT);

			if ($password != $old_password) {
				db_execute_prepared('DELETE FROM user_auth_cache
					WHERE user_id = ?',
					[gnrv('id')]);
			}
		}

		// check duplicate username
		if (cacti_sizeof(db_fetch_row_prepared('SELECT * FROM user_auth WHERE realm = ? AND username = ? AND id != ?', [gnrv('realm'), gnrv('username'), gnrv('id')]))) {
			raise_message(12);
		}

		// check for guest or template user
		$username = db_fetch_cell_prepared('SELECT username FROM user_auth WHERE id = ?', [gnrv('id')]);
		$history  = db_fetch_cell_prepared('SELECT password_history FROM user_auth WHERE id = ?', [gnrv('id')]);

		// check to make sure the passwords match; if not error
		if (gnrv('password') != gnrv('password_confirm')) {
			raise_message(4);
		}

		if (gnrv('must_change_password') == 'on' && gnrv('password_change') != 'on') {
			raise_message('password_change');
		}

		$save['id']                   = gnrv('id');
		$save['username']             = form_input_validate(gnrv('username'), 'username', "^[A-Za-z0-9\._\\\@\ -]+$", false, 3);
		$save['full_name']            = form_input_validate(gnrv('full_name'), 'full_name', '', true, 3);
		$save['password']             = $password;
		$save['must_change_password'] = form_input_validate(gnrv('must_change_password', ''), 'must_change_password', '', true, 3);
		$save['password_change']      = form_input_validate(gnrv('password_change', ''), 'password_change', '', true, 3);
		$save['show_tree']            = form_input_validate(gnrv('show_tree', ''), 'show_tree', '', true, 3);
		$save['show_list']            = form_input_validate(gnrv('show_list', ''), 'show_list', '', true, 3);
		$save['show_preview']         = form_input_validate(gnrv('show_preview', ''), 'show_preview', '', true, 3);
		$save['graph_settings']       = form_input_validate(gnrv('graph_settings', ''), 'graph_settings', '', true, 3);
		$save['login_opts']           = form_input_validate(gnrv('login_opts'), 'login_opts', '', true, 3);
		$save['password_history']     = $history;

		// force enable/disable on template accounts
		if (read_config_option('admin_user') == gnrv('id')) {
			$save['enabled'] = 'on';
			$save['realm']   = gnrv('realm', 0);
		} elseif (is_template_account(gnrv('id'))) {
			$save['enabled'] = '';
			$save['realm']   = 0;
		} else {
			$save['enabled'] = form_input_validate(gnrv('enabled', ''), 'enabled', '', true, 3);
			$save['realm']   = gnrv('realm', 0);
		}

		$save['email_address']        = form_input_validate(gnrv('email_address', ''), 'email_address', '', true, 3);
		$save['locked']               = form_input_validate(gnrv('locked', ''), 'locked', '', true, 3);
		$save['reset_perms']          = mt_rand();

		if ($save['locked'] == '') {
			$save['failed_attempts'] = 0;
		}

		// remove any stored tokens in case of account take over
		if ($save['must_change_password'] == 'on') {
			db_execute_prepared('DELETE FROM user_auth_cache WHERE user_id = ?', [$save['id']]);
			db_execute_prepared('DELETE FROM sessions WHERE user_id = ?', [$save['id']]);
		}

		$save = api_plugin_hook_function('user_admin_setup_sql_save', $save);

		if (!is_error_message()) {
			$user_id = sql_save($save, 'user_auth');

			if ($user_id) {
				if (($save['id'] == 0 && read_config_option('secnotify_newuser') == 'on') ||
					($save['id'] > 0 && read_config_option('secnotify_chpass') == 'on')) {
					$hash = generate_hash();

					$replacement = [
						read_config_option('base_url') . CACTI_PATH_URL,
						$save['username'],
						read_config_option('base_url') . CACTI_PATH_URL . 'auth_resetpassword.php?action=formreset&hash=' . $hash
					];

					$search = ['<CACTIURL>', '<USERNAME>', '<PWDRESETLINK>'];

					db_execute_prepared('INSERT INTO user_auth_reset_hashes
						(user_id, hash, expiry)
						VALUES (?, ?, date_add(now(), interval ? minute))',
						[$user_id, $hash, read_config_option('secnotify_resetlink_timeout')]);
				} else {
					$search = $replacement = '';
				}

				if ($save['id'] == 0) {
					if ($save['email_address'] && read_config_option('secnotify_newuser') == 'on') {
						$body = str_replace($search, $replacement, read_config_option('secnotify_newuser_message'));

						send_mail($save['email_address'], null, read_config_option('secnotify_newuser_subject'), $body, [], [],  true);
					}

					cacti_log(sprintf('NOTE: New user created, username %s, created by %s', $save['email_address'], get_username()), false, 'SYSTEM');
				}

				if ($save['id'] > 0) {
					if ($save['email_address'] && read_config_option('secnotify_chpass') == 'on') {
						$body = str_replace($search, $replacement, read_config_option('secnotify_chpass_message'));

						send_mail($save['email_address'], null, read_config_option('secnotify_chpass_subject'), $body, [], [],  true);
					}

					cacti_log(sprintf('NOTE: Admin %s, changed password for user %s', get_username(), $save['email_address']), false, 'SYSTEM');
				}

				raise_message(1);
			} else {
				raise_message(2);
			}
		}
	} elseif (isrv('save_component_realm_perms')) {
		// ================= input validation =================
		gfrv('id');
		// ====================================================

		db_execute_prepared('DELETE FROM user_auth_realm
			WHERE user_id = ?',
			[gnrv('id')]);

		foreach ($_POST as $var => $val) {
			if (preg_match('/^[section]/i', $var)) {
				if (substr($var, 0, 7) == 'section') {
					db_execute_prepared('REPLACE INTO user_auth_realm
						(user_id, realm_id)
						VALUES (?, ?)',
						[gnrv('id'), substr($var, 7)]);
				}
			}
		}

		reset_user_perms(gnrv('id'));

		raise_message(1);
	} elseif (isrv('save_component_graph_settings')) {
		// ================= input validation =================
		gfrv('id');
		// ====================================================

		save_user_settings(grv('id'));

		// reset local settings cache so the user sees the new settings
		kill_session_var(OPTIONS_USER);

		reset_user_perms(grv('id'));

		raise_message(1);
	} elseif (isrv('save_component_graph_perms')) {
		// ================= input validation =================
		gfrv('id');
		gfrv('policy_hosts');
		gfrv('policy_graphs');
		gfrv('policy_trees');
		gfrv('policy_graph_templates');
		// ====================================================

		db_execute_prepared('UPDATE user_auth
			SET policy_graphs = ?,
			policy_trees = ?,
			policy_hosts = ?,
			policy_graph_templates = ?
			WHERE id = ?',
			[
				gnrv('policy_graphs'),
				gnrv('policy_trees'),
				gnrv('policy_hosts'),
				gnrv('policy_graph_templates'),
				gnrv('id')
			]
		);
	} else {
		api_plugin_hook('user_admin_user_save');

		reset_user_perms(gfrv('id'));
	}

	// redirect to the appropriate page
	header('Location: user_admin.php?action=user_edit&id=' . (empty($user_id) ? gfrv('id') : $user_id));
}

function perm_remove() : void {
	// ================= input validation =================
	gfrv('id');
	gfrv('user_id');
	// ====================================================

	if (grv('type') == 'graph') {
		db_execute_prepared('DELETE FROM user_auth_perms
			WHERE type = 1
			AND user_id = ?
			AND item_id = ?',
			[grv('user_id'), grv('id')]);
	} elseif (grv('type') == 'tree') {
		db_execute_prepared('DELETE FROM user_auth_perms
			WHERE type = 2
			AND user_id = ?
			AND item_id = ?',
			[grv('user_id'), grv('id')]);
	} elseif (grv('type') == 'host') {
		db_execute_prepared('DELETE FROM user_auth_perms
			WHERE type = 3
			AND user_id = ?
			AND item_id = ?',
			[grv('user_id'), grv('id')]);
	} elseif (grv('type') == 'graph_template') {
		db_execute_prepared('DELETE FROM user_auth_perms
			WHERE type = 4
			AND user_id = ?
			AND item_id = ?',
			[grv('user_id'), grv('id')]);
	}

	header('Location: user_admin.php?action=user_edit&tab=graph_perms_edit&id=' . grv('user_id'));
}

function graph_perms_edit(string $tab, string $header_label) : void {
	// ================= input validation =================
	gfrv('id');
	// ====================================================

	$sql_where  = '';
	$sql_join   = '';
	$limit      = '';
	$sql_having = '';

	$policy_array = [
		1 => __('Allow'),
		2 => __('Deny')
	];

	if (!ierv('id')) {
		$policy = db_fetch_row_prepared('SELECT policy_graphs, policy_trees, policy_hosts, policy_graph_templates
			FROM user_auth
			WHERE id = ?', [grv('id')]);
	} else {
		$policy = [
			'policy_graphs'          => '1',
			'policy_trees'           => '1',
			'policy_hosts'           => '1',
			'policy_graph_templates' => '1'
		];
	}

	switch($tab) {
		case 'permsg':
			if (ierv('id')) {
				header('Location: user_admin.php');
			}

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

			// box: device permissions
			html_start_box(__('Default Graph Policy'), '100%', false, 3, 'center', '');

			?>
			<tr class='even'>
				<td><table><tr>
				<td class='nowrap'><?php print __('Default Graph Policy for this User'); ?></td>
				<td>
					<?php form_dropdown('policy_graphs',$policy_array,'','',$policy['policy_graphs'],'',''); ?>
				</td>
				<td>
					<button type='submit' class='ui-button ui-corner-all ui-widget ui-state-active' name='update_policy'><?php print __esc('Update'); ?></button>
					<input type='hidden' name='tab' value='<?php print $tab; ?>'>
					<input type='hidden' name='id' value='<?php print grv('id'); ?>'>
					<input type='hidden' name='update_policy' value='1'>
				</td>
				</tr></table></td>
			</tr>
			<tr class='even'>
				<td><br><?php print $policy_note; ?></td>
			</tr>
			<?php

			html_end_box();

			form_end();

			// if the number of rows is -1, set it to the default
			if (grv('rows') == -1) {
				$rows = read_config_option('num_rows_table');
			} else {
				$rows = grv('rows');
			}

			$user_id  = gfrv('id');
			$policies = get_policies($user_id);

			$policies = array_reverse($policies);

			$limit    = 'LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;

			// form the 'where' clause for our main sql query
			if (grv('filter') != '') {
				$sql_where = 'WHERE (
				gtg.title_cache LIKE ' . db_qstr('%' . grv('filter') . '%') . '
				AND gtg.local_graph_id > 0)';
			} else {
				$sql_where = 'WHERE (gtg.local_graph_id > 0)';
			}

			if (grv('graph_template_id') == '-1') {
				// Show all items
			} elseif (grv('graph_template_id') == '0') {
				$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' gtg.graph_template_id=0';
			} elseif (!ierv('graph_template_id')) {
				$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' gtg.graph_template_id=' . grv('graph_template_id');
			}

			/**
			 * if viewing just the graphs that the user has access to
			 * we use a custom crafted sql_where clause to calculate
			 * permissions due to the inefficient nature of the HAVING
			 * SQL clause.
			 */
			if (grv('associated') == 'false') {
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

			$nav = html_nav_bar('user_admin.php?action=user_edit&tab=permsg&id=' . grv('id'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 11, __('Graphs'), 'page', 'main');

			form_start('user_admin.php?tab=permsg&id=' . grv('id'), 'chk');

			print $nav;

			html_start_box('', '100%', false, 3, 'center', '');

			$display_text = [__('Graph Title'), __('ID'), __('Effective Policy')];

			html_header_checkbox($display_text, false);

			if (cacti_sizeof($graphs)) {
				foreach ($graphs as $g) {
					form_alternate_row('line' . $g['local_graph_id'], true);

					form_selectable_cell(filter_value($g['title_cache'], grv('filter')), $g['local_graph_id']);
					form_selectable_cell($g['local_graph_id'], $g['local_graph_id']);
					form_selectable_cell(get_permission_string($g, $policies), $g['local_graph_id']);

					form_checkbox_cell($g['title_cache'], $g['local_graph_id']);

					form_end_row();
				}
			} else {
				print '<tr class="tableRow odd"><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No Matching Graphs Found') . '</em></td></tr>';
			}

			html_end_box(false);

			if (cacti_sizeof($graphs)) {
				print $nav;
			}

			form_hidden_box('tab',$tab,'');
			form_hidden_box('id', grv('id'), '');
			form_hidden_box('associate_graph', '1', '');

			if ($policy['policy_graphs'] == 1) {
				$assoc_actions = [
					1 => __('Revoke Access'),
					2 => __('Grant Access')
				];
			} else {
				$assoc_actions = [
					1 => __('Grant Access'),
					2 => __('Revoke Access')
				];
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

			// draw the dropdown containing a list of available actions for this form
			draw_actions_dropdown($assoc_actions);

			form_end();

			break;
		case 'permsgr':
			if (ierv('id')) {
				header('Location: user_admin.php');
			}

			group_filter($header_label);

			// if the number of rows is -1, set it to the default
			if (grv('rows') == -1) {
				$rows = read_config_option('num_rows_table');
			} else {
				$rows = grv('rows');
			}

			// form the 'where' clause for our main sql query
			if (grv('filter') != '') {
				$sql_where = 'WHERE (
				uag.name LIKE ' . db_qstr('%' . grv('filter') . '%') . '
				OR uag.description LIKE ' . db_qstr('%' . grv('filter') . '%') . ')';
			} else {
				$sql_where = '';
			}

			if (grv('associated') != 'false') {
				// Show all items
			} else {
				$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' uagm.user_id=' . grv('id');
			}

			$total_rows = db_fetch_cell('SELECT COUNT(DISTINCT uag.id)
				FROM user_auth_group AS uag
				LEFT JOIN (
					SELECT user_id, group_id
					FROM user_auth_group_members
					WHERE user_id=' . grv('id') . "
				) AS uagm
				ON uag.id = uagm.group_id
			$sql_where");

			$sql_query = 'SELECT DISTINCT uag.*, uagm.user_id
				FROM user_auth_group AS uag
				LEFT JOIN (
					SELECT user_id, group_id
					FROM user_auth_group_members
					WHERE user_id=' . grv('id') . "
				) AS uagm
				ON uag.id = uagm.group_id
				$sql_where
				ORDER BY name
				LIMIT " . ($rows * (grv('page') - 1)) . ',' . $rows;

			$groups = db_fetch_assoc($sql_query);

			$nav = html_nav_bar('user_admin.php?action=user_edit&tab=permsgr&id=' . grv('id'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 11, __('Groups'), 'page', 'main');

			form_start('user_admin.php?tab=permsd&id=' . grv('id'), 'chk');

			print $nav;

			html_start_box('', '100%', false, 3, 'center', '');

			$display_text = [__('Name'), __('Description'), __('Member'), __('ID'), __('Policies (Graph/Device/Template)'), __('Enabled')];

			html_header_checkbox($display_text, false);

			if (cacti_sizeof($groups)) {
				foreach ($groups as $g) {
					form_alternate_row('line' . $g['id'], true);

					form_selectable_cell(filter_value($g['name'], grv('filter'), 'user_group_admin.php?action=edit&id=' . $g['id']), $g['id']);
					form_selectable_cell(filter_value($g['description'], grv('filter')), $g['id']);
					form_selectable_cell($g['user_id'] > 0 ? __('Member') : __('Non Member'), $g['id']);
					form_selectable_cell(($g['id']), $g['id']);
					form_selectable_cell(($g['policy_graphs'] == 1 ? __('ALLOW') : __('DENY')) . '/' . ($g['policy_hosts'] == 1 ? __('ALLOW') : __('DENY')) . '/' . ($g['policy_graph_templates'] == 1 ? __('ALLOW') : __('DENY')), $g['id']);
					form_selectable_cell($g['enabled'] == 'on' ? __('Enabled') : __('Disabled'), $g['id']);

					form_checkbox_cell($g['name'], $g['id']);

					form_end_row();
				}
			} else {
				print '<tr class="tableRow odd"><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No Matching User Groups Found') . '</em></td></tr>';
			}

			html_end_box(false);

			if (cacti_sizeof($groups)) {
				print $nav;
			}

			form_hidden_box('tab',$tab,'');
			form_hidden_box('id', grv('id'), '');
			form_hidden_box('associate_groups', '1', '');

			$assoc_actions = [
				1 => __('Assign Membership'),
				2 => __('Remove Membership')
			];

			// draw the dropdown containing a list of available actions for this form
			draw_actions_dropdown($assoc_actions);

			form_end();

			break;
		case 'permsd':
			if (ierv('id')) {
				header('Location: user_admin.php');
			}

			device_filter($header_label);

			form_start('user_admin.php', 'policy');

			html_start_box(__('Default Device Policy'), '100%', false, 3, 'center', '');

			?>
			<tr class='even'>
				<td><table><tr>
				<td class='nowrap'><?php print __('Default Device Policy for this User'); ?></td>
				<td>
					<?php form_dropdown('policy_hosts',$policy_array,'','',$policy['policy_hosts'],'',''); ?>
				</td>
				<td>
					<button type='submit' class='ui-button ui-corner-all ui-widget ui-state-active' name='update_policy'><?php print __esc('Update'); ?></button>
					<input type='hidden' name='tab' value='<?php print $tab; ?>'>
					<input type='hidden' name='id' value='<?php print grv('id'); ?>'>
					<input type='hidden' name='update_policy' value='1'>
				</td>
				</tr></table></td>
			</tr>
			<?php

			html_end_box();

			form_end();

			// if the number of rows is -1, set it to the default
			if (grv('rows') == -1) {
				$rows = read_config_option('num_rows_table');
			} else {
				$rows = grv('rows');
			}

			$sql_where    = '';
			$sql_params   = [];
			$sql_params[] = grv('id');

			if (grv('filter') != '') {
				$sql_where    = 'WHERE h.deleted = "" AND (h.hostname LIKE ? OR h.description LIKE ?)';
				$sql_params[] = '%' . grv('filter') . '%';
				$sql_params[] = '%' . grv('filter') . '%';
			} else {
				$sql_where = 'WHERE h.deleted = ""';
			}

			if (grv('host_template_id') == '0') {
				$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' h.host_template_id = 0';
			} elseif (grv('host_template_id') > 0) {
				$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' h.host_template_id = ?';
				$sql_params[] = grv('host_template_id');
			}

			if (grv('associated') != 'false') {
				$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' uap.user_id=' . grv('id', 0);
			}

			$total_rows = db_fetch_cell_prepared("SELECT COUNT(h.id)
				FROM host AS h
				LEFT JOIN user_auth_perms AS uap
				ON h.id = uap.item_id
				AND uap.type = 3
				AND uap.user_id = ?
				$sql_where",
				$sql_params);

			$host_graphs = array_rekey(
				db_fetch_assoc('SELECT host_id, count(*) AS graphs
					FROM graph_local
					GROUP BY host_id'),
				'host_id', 'graphs'
			);

			$host_data_sources = array_rekey(
				db_fetch_assoc('SELECT host_id, count(*) AS data_sources
					FROM data_local
					GROUP BY host_id'),
				'host_id', 'data_sources'
			);

			$sql_query = "SELECT h.*, uap.user_id
				FROM host AS h
				LEFT JOIN user_auth_perms AS uap
				ON h.id = uap.item_id
				AND uap.type = 3
				AND uap.user_id = ?
				$sql_where
				ORDER BY description
				LIMIT " . ($rows * (grv('page') - 1)) . ',' . $rows;

			$hosts = db_fetch_assoc_prepared($sql_query, $sql_params);

			$nav = html_nav_bar('user_admin.php?action=user_edit&tab=permsd&id=' . grv('id'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 11, __('Devices'), 'page', 'main');

			form_start('user_admin.php?tab=permsd&id=' . grv('id'), 'chk');

			print $nav;

			html_start_box('', '100%', false, 3, 'center', '');

			$display_text = [__('Description'), __('ID'), __('Effective Policy'), __('Graphs'), __('Data Sources'), __('Status'), __('Hostname')];

			html_header_checkbox($display_text, false);

			if (cacti_sizeof($hosts)) {
				foreach ($hosts as $host) {
					form_alternate_row('line' . $host['id'], true);

					form_selectable_cell(filter_value($host['description'], grv('filter')), $host['id']);
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
					form_selectable_cell(filter_value($host['hostname'], grv('filter')), $host['id']);

					form_checkbox_cell($host['description'], $host['id']);

					form_end_row();
				}
			} else {
				print '<tr class="tableRow odd"><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No Matching Devices Found') . '</em></td></tr>';
			}

			html_end_box(false);

			if (cacti_sizeof($hosts)) {
				print $nav;
			}

			form_hidden_box('tab',$tab,'');
			form_hidden_box('id', grv('id'), '');
			form_hidden_box('associate_host', '1', '');

			if ($policy['policy_hosts'] == 1) {
				$assoc_actions = [
					1 => __('Revoke Access'),
					2 => __('Grant Access')
				];
			} else {
				$assoc_actions = [
					1 => __('Grant Access'),
					2 => __('Revoke Access')
				];
			}

			// draw the dropdown containing a list of available actions for this form
			draw_actions_dropdown($assoc_actions);

			form_end();

			break;
		case 'permste':
			if (ierv('id')) {
				header('Location: user_admin.php');
			}

			template_filter($header_label);

			form_start('user_admin.php', 'policy');

			html_start_box(__('Default Graph Template Policy'), '100%', false, 3, 'center', '');

			?>
			<tr class='even'>
				<td><table><tr>
				<td class='nowrap'><?php print __('Default Graph Template Policy for this User'); ?></td>
				<td>
					<?php form_dropdown('policy_graph_templates',$policy_array,'','',$policy['policy_graph_templates'],'',''); ?>
				</td>
				<td>
					<button type='submit' class='ui-button ui-corner-all ui-widget ui-state-active' name='update_policy'><?php print __esc('Update'); ?></button>
					<input type='hidden' name='tab' value='<?php print $tab; ?>'>
					<input type='hidden' name='id' value='<?php print grv('id'); ?>'>
					<input type='hidden' name='update_policy' value='1'>
				</td>
				</tr></table></td>
			</tr>
			<?php

			html_end_box();

			form_end();

			// if the number of rows is -1, set it to the default
			if (grv('rows') == -1) {
				$rows = read_config_option('num_rows_table');
			} else {
				$rows = grv('rows');
			}

			$sql_where    = '';
			$sql_params   = [];
			$sql_params[] = grv('id');

			// form the 'where' clause for our main sql query
			if (grv('filter') != '') {
				$sql_where    = 'WHERE gt.name LIKE ?';
				$sql_params[] = '%' . grv('filter') . '%';
			}

			if (grv('associated') != 'false') {
				$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' (user_auth_perms.type = 4 AND user_auth_perms.user_id = ?)';
				$sql_params[] = grv('id');
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
				$sql_params);

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
				LIMIT " . ($rows * (grv('page') - 1)) . ',' . $rows;

			$graphs = db_fetch_assoc_prepared($sql_query, $sql_params);

			$display_text = [
				__('Template Name'),
				__('ID'),
				__('Effective Policy'),
				__('Total Graphs')
			];

			$nav = html_nav_bar('user_admin.php?action=user_edit&tab=permste&id=' . grv('id'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, cacti_sizeof($display_text) + 1, __('Graph Templates'), 'page', 'main');

			form_start('user_admin.php?tab=permste&id=' . grv('id'), 'chk');

			print $nav;

			html_start_box('', '100%', false, 3, 'center', '');

			html_header_checkbox($display_text, false);

			if (cacti_sizeof($graphs)) {
				foreach ($graphs as $g) {
					form_alternate_row('line' . $g['id'], true);

					form_selectable_cell(filter_value($g['name'], grv('filter')), $g['id']);
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
				print '<tr class="tableRow odd"><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No Matching Graph Templates Found') . '</em></td></tr>';
			}

			html_end_box(false);

			if (cacti_sizeof($graphs)) {
				print $nav;
			}

			form_hidden_box('tab',$tab,'');
			form_hidden_box('id', grv('id'), '');
			form_hidden_box('associate_template', '1', '');

			if ($policy['policy_graph_templates'] == 1) {
				$assoc_actions = [
					1 => __('Revoke Access'),
					2 => __('Grant Access')
				];
			} else {
				$assoc_actions = [
					1 => __('Grant Access'),
					2 => __('Revoke Access')
				];
			}

			// draw the dropdown containing a list of available actions for this form
			draw_actions_dropdown($assoc_actions);

			form_end();

			break;
		case 'permstr':
			if (ierv('id')) {
				header('Location: user_admin.php');
			}

			tree_filter($header_label);

			form_start('user_admin.php', 'policy');

			html_start_box(__('Default Tree Policy'), '100%', false, 3, 'center', '');

			?>
			<tr class='even'>
				<td><table><tr>
				<td class='nowrap'><?php print __('Default Tree Policy for this User'); ?></td>
				<td>
					<?php form_dropdown('policy_trees',$policy_array,'','',$policy['policy_trees'],'',''); ?>
				</td>
				<td>
					<button type='submit' class='ui-button ui-corner-all ui-widget ui-state-active' name='update_policy'><?php print __esc('Update'); ?></button>
					<input type='hidden' name='tab' value='<?php print $tab; ?>'>
					<input type='hidden' name='id' value='<?php print grv('id'); ?>'>
					<input type='hidden' name='update_policy' value='1'>
				</td>
			</tr></table></td>
			</tr>
			<?php

			html_end_box();

			form_end();

			// if the number of rows is -1, set it to the default
			if (grv('rows') == -1) {
				$rows = read_config_option('num_rows_table');
			} else {
				$rows = grv('rows');
			}

			// form the 'where' clause for our main sql query
			if (grv('filter') != '') {
				$sql_where = 'WHERE gt.name LIKE ' . db_qstr('%' . grv('filter') . '%');
			} else {
				$sql_where = '';
			}

			if (grv('associated') != 'false') {
				$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' (user_auth_perms.type=2 AND user_auth_perms.user_id=' . grv('id', 0) . ')';
			}

			$total_rows = db_fetch_cell('SELECT COUNT(DISTINCT gt.id)
				FROM graph_tree AS gt
				LEFT JOIN user_auth_perms
				ON gt.id = user_auth_perms.item_id
				AND user_auth_perms.type = 2
				AND user_auth_perms.user_id = ' . grv('id') . "
				$sql_where");

			$sql_query = 'SELECT gt.id, gt.name, user_auth_perms.user_id
				FROM graph_tree AS gt
				LEFT JOIN user_auth_perms
				ON gt.id = user_auth_perms.item_id
				AND user_auth_perms.type = 2
				AND user_auth_perms.user_id = ' . grv('id') . "
				$sql_where
				ORDER BY name
				LIMIT " . ($rows * (grv('page') - 1)) . ',' . $rows;

			$trees = db_fetch_assoc($sql_query);

			$display_text = [
				__('Tree Name'),
				__('ID'),
				__('Effective Policy')
			];

			html_header_checkbox($display_text, false);

			$nav = html_nav_bar('user_admin.php?action=user_edit&tab=permstr&id=' . grv('id'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, cacti_sizeof($display_text) + 1, __('Trees'), 'page', 'main');

			form_start('user_admin.php?tab=permstr&id=' . grv('id'), 'chk');

			print $nav;

			html_start_box('', '100%', false, 3, 'center', '');

			if (cacti_sizeof($trees)) {
				foreach ($trees as $t) {
					form_alternate_row('line' . $t['id'], true);

					form_selectable_cell(filter_value($t['name'], grv('filter')), $t['id']);
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
				print '<tr class="tableRow odd"><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No Matching Trees Found') . '</em></td></tr>';
			}

			html_end_box(false);

			if (cacti_sizeof($trees)) {
				print $nav;
			}

			form_hidden_box('tab',$tab,'');
			form_hidden_box('id', grv('id'), '');
			form_hidden_box('associate_tree', '1', '');

			if ($policy['policy_trees'] == 1) {
				$assoc_actions = [
					1 => __('Revoke Access'),
					2 => __('Grant Access')
				];
			} else {
				$assoc_actions = [
					1 => __('Grant Access'),
					2 => __('Revoke Access')
				];
			}

			// draw the dropdown containing a list of available actions for this form
			draw_actions_dropdown($assoc_actions);

			form_end();

			break;
	}
}

function user_realms_edit(string $header_label) : void {
	global $user_auth_realms, $user_auth_roles;

	// ================= input validation =================
	gfrv('id');
	// ====================================================

	$all_realms = $user_auth_realms;

	print "<div class='cactiTable' style='width:100%;text-align:left;'>
		<div class='cactiTableTitleRow'>
			<div class='cactiTableTitle'><span style='padding:3px;'>" . __('User Permissions') . ' ' . htmle($header_label) . "</span></div>
			<div class='cactiTableButton'><span style='padding:3px;'><input class='checkbox' type='checkbox' id='all' name='all' title='" . __esc('Select All') . "' onClick='selectAllRealms(this.checked)'></a><label class='formCheckboxLabel' title='" . __esc('Select All') . "' for='all'></label></span></div>
		</div>
	</div>";

	form_start('user_admin.php', 'chk');

	html_start_box('', '100%', false, 3, 'center', '');

	// do cacti realms first
	foreach ($user_auth_roles as $role_name => $perms) {
		print "<tr class='tableHeader'><th colspan='2'>" . htmle($role_name) . '</th></tr>';
		print "<tr class='odd'><td colspan='2'><div class='flexContainer'>";

		foreach ($perms as $realm) {
			if (isset($user_auth_realms[$realm])) {
				$set = db_fetch_cell_prepared('SELECT realm_id
					FROM user_auth_realm
					WHERE user_id = ?
					AND realm_id = ?',
					[grv('id', 0), $realm]);

				if ($set) {
					$old_value = 'on';
				} else {
					$old_value = '';
				}

				if ($realm != 101) {
					$display = trim(str_replace(['Plugin ->', 'Plugin ', 'Configure '], '', $user_auth_realms[$realm]));
				} else {
					$display = trim($user_auth_realms[$realm]);
				}
				$display = trim(str_replace(['View ', 'Management'], ['', 'Administration'], $display));

				unset($all_realms[$realm]);

				print '<div class="flexChild">';
				form_checkbox('section' . $realm, $old_value, $display, '', '', '', '', $display, true);
				print '</div>';
			}
		}

		print '</div></td></tr>';
	}

	// external links
	$links  = db_fetch_assoc('SELECT * FROM external_links ORDER BY sortorder');

	$style_translate = [
		'CONSOLE'    => __('Console'),
		'TAB'        => __('Top Tab'),
		'FRONT'      => __('Bottom Console'),
		'FRONTTOP'   => __('Top Console')
	];

	if (cacti_sizeof($links)) {
		print "<tr class='tableHeader'><th class='left' colspan='2'>" . __('External Link Permissions') . '</th></tr>';
		print "<tr class='odd'><td class='left' colspan='2'><div class='flexContainer'>";

		foreach ($links as $r) {
			$realm = $r['id'] + 10000;

			$set = db_fetch_cell_prepared('SELECT realm_id
				FROM user_auth_realm
				WHERE user_id = ?
				AND realm_id = ?',
				[grv('id', 0), $realm]);

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
			form_checkbox('section' . $realm, $old_value, $description, '', '', '', '', $description, true);
			print '</div>';
		}

		print '</div></td></tr>';
	}

	// do plugin realms
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
				[grv('id', 0), $realm]);

			if ($set) {
				$old_value = 'on';
			} else {
				$old_value = '';
			}

			unset($all_realms[$realm]);

			$local_user_auth_realms = __($user_auth_realms[$realm], $r['directory']);

			$pos = (str_contains($local_user_auth_realms, '->') ? strpos($local_user_auth_realms, '->') + 2 : 0);

			if ($i == 0) {
				print "<tr class='tableHeader'><th class='left' colspan='2'>" . __('Plugin Permissions') . '</th></tr>';
				print "<tr class='odd'><td colspan='2'><div class='flexContainer'>";
				$i++;
			}

			print '<div class="flexChild">';
			form_checkbox('section' . $realm, $old_value, trim(substr($local_user_auth_realms, $pos)), '', '', '', '', $r['display'], true);
			print '</div>';
		}

		if ($i > 0) {
			print '</div></td></tr>';
		}
	}

	// get the old PIA 1.x realms
	if (cacti_sizeof($all_realms)) {
		print "<tr class='tableHeader'><th class='left' colspan='2'>" . __('Legacy Permissions') . '</th></tr>';

		foreach ($all_realms as $realm => $name) {
			$set = db_fetch_cell_prepared('SELECT realm_id
				FROM user_auth_realm
				WHERE user_id = ?
				AND realm_id = ?',
				[grv('id', 0), $realm]);

			if ($set) {
				$old_value = 'on';
			} else {
				$old_value = '';
			}

			$pos = (str_contains($user_auth_realms[$realm], '->') ? strpos($user_auth_realms[$realm], '->') + 2 : 0);

			print '<div class="flexChild">';
			form_checkbox('section' . $realm, $old_value, substr($user_auth_realms[$realm], $pos), '', '', '', '', $name, true);
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

	form_hidden_box('id', grv('id'), '');
	form_hidden_box('tab', 'realms', '');
	form_hidden_box('save_component_realm_perms', '1', '');

	form_save_button('user_admin.php', 'return');
}

function settings_edit(string $header_label) : void {
	global $settings_user, $tabs_graphs, $graph_views;

	// ================= input validation =================
	gfrv('id');
	// ====================================================

	form_start('user_admin.php');

	html_start_box(__esc('User Settings %s', $header_label), '100%', true, 3, 'center', '');

	foreach ($settings_user as $tab_short_name => $tab_fields) {
		print "<div class='spacer formHeader collapsible' id='row_$tab_short_name'><div style='cursor:pointer;' class='tableSubHeaderColumn'>" . $tabs_graphs[$tab_short_name] . "<div style='float:right;padding-right:4px;'><i class='ti ti-chevrons-up'></i></div></div></div>";

		$form_array = [];

		foreach ($tab_fields as $field_name => $field_array) {
			$form_array += [$field_name => $tab_fields[$field_name]];

			if ((isset($field_array['items'])) && (is_array($field_array['items']))) {
				foreach ($field_array['items'] as $sub_field_name => $sub_field_array) {
					if (user_setting_exists($sub_field_name, grv('id'))) {
						$form_array[$field_name]['items'][$sub_field_name]['form_id'] = 1;
					}

					$form_array[$field_name]['items'][$sub_field_name]['value'] =  db_fetch_cell_prepared('SELECT value FROM settings_user WHERE name = ? AND user_id = ?', [$sub_field_name, grv('id')]);
				}
			} else {
				if (user_setting_exists($field_name, grv('id'))) {
					$form_array[$field_name]['form_id'] = 1;
				}

				$form_array[$field_name]['value'] = db_fetch_cell_prepared('SELECT value FROM settings_user WHERE name = ? and user_id = ?', [$field_name, grv('id')]);
			}
		}

		draw_edit_form(
			[
				'config' => ['no_form_tag' => true],
				'fields' => $form_array
			]
		);
	}

	html_end_box(true, true);

	form_hidden_box('id', grv('id'), '');
	form_hidden_box('tab', 'settings', '');
	form_hidden_box('save_component_graph_settings','1','');

	form_save_button('user_admin.php', 'return');

	?>
	<script type='text/javascript'>

	var themeFonts=<?php print read_config_option('font_method'); ?>;

	function graphSettings() {
		var custom_fonts = $('#custom_fonts').is(':checked');
		var fields = {
			fonts: (themeFonts == 1),
			custom_fonts: themeFonts != 1,
			title_size: themeFonts != 1 && custom_fonts,
			title_font: themeFonts != 1 && custom_fonts,
			legend_size: themeFonts != 1 && custom_fonts,
			legend_font: themeFonts != 1 && custom_fonts,
			axis_size: themeFonts != 1 && custom_fonts,
			axis_font: themeFonts != 1 && custom_fonts,
			unit_size: themeFonts != 1 && custom_fonts,
			unit_font: themeFonts != 1 && custom_fonts,
		}

		toggleFields(fields);
	}

	$(function() {
		graphSettings();
	});

	</script>
	<?php
}

function user_edit() : void {
	global $fields_user_edit;

	// ================= input validation =================
	gfrv('id');
	gfrv('tab', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^([a-z_A-Z]+)$/']]);
	// ====================================================

	// present a tabbed interface
	$tabs = [
		'general'  => __('General'),
		'realms'   => __('Permissions'),
		'permsgr'  => __('Group Membership'),
		'permsg'   => __('Graph Perms'),
		'permsd'   => __('Device Perms'),
		'permste'  => __('Template Perms'),
		'permstr'  => __('Tree Perms'),
		'settings' => __('User Settings')
	];

	$permission_model = read_config_option('graph_auth_method');

	if ($permission_model == 3) { // Device Based
		unset($tabs['permste']);
	} elseif ($permission_model == 4) { // Graph Template Based
		unset($tabs['permsd']);
	}

	// set the default tab
	load_current_session_value('tab', 'sess_user_admin_tab', 'general');
	$current_tab = gnrv('tab');

	if (!ierv('id')) {
		$user         = db_fetch_row_prepared('SELECT * FROM user_auth WHERE id = ?', [grv('id')]);
		$header_label = __('[edit: %s]', $user['username']);
	} else {
		$header_label = __('[new]');
	}

	if (cacti_sizeof($tabs) && !ierv('id')) {
		$i = 0;

		// draw the tabs
		print "<div class='tabs'><nav><ul role='tablist'>";

		foreach (array_keys($tabs) as $tab_short_name) {
			print "<li class='subTab'><a class='tab" . (($tab_short_name == $current_tab) ? " selected'" : "'") .
				" href='" . htmle(CACTI_PATH_URL .
				'user_admin.php?action=user_edit&id=' . grv('id') .
				'&tab=' . $tab_short_name) .
				"'>" . $tabs[$tab_short_name] . '</a></li>';

			$i++;
		}

		api_plugin_hook('user_admin_tab');

		print '</ul></nav></div>';
	}

	switch($current_tab) {
		case 'general':
			api_plugin_hook_function('user_admin_edit', (isset($user) ? grv('id') : 0));

			form_start('user_admin.php');

			html_start_box(__esc('User Management %s', $header_label), '100%', false, 3, 'center', '');

			draw_edit_form(
				[
					'config' => ['no_form_tag' => true],
					'fields' => inject_form_variables($fields_user_edit, (isset($user) ? $user : []))
				]
			);

			html_end_box();

			form_save_button('user_admin.php', 'return');

			?>
		<script type='text/javascript'>

		var templateAccount=<?php print is_template_account(gfrv('id')) ? 'true' : 'false'; ?>;
		var consoleAllowed=<?php print(isset($user['id']) ? (is_realm_allowed(8, $user['id']) ? 'true' : 'false') : 'false'); ?>;

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
			if (api_plugin_hook_function('user_admin_run_action', grv('tab'))) {
				user_realms_edit($header_label);
			}

			break;
	}
}

function create_user_filter() : array {
	global $item_rows;

	$all  = ['-1' => __('All')];
	$any  = ['-1' => __('Any')];
	$none = ['0'  => __('None')];

	$groups = array_rekey(
		db_fetch_assoc('SELECT id, description
			FROM user_auth_group
			ORDER BY description'),
		'id', 'description'
	);

	$groups = $all + $groups;

	$logins = [
		'0' => __esc('All'),
		'1' => __esc('< 1 Week Ago'),
		'2' => __esc('< 1 Month Ago'),
		'3' => __esc('> 1 Month Ago'),
		'4' => __esc('> 2 Months Ago'),
		'5' => __esc('> 4 Months Ago'),
		'6' => __esc('Never')
	];

	$realms = [
		'-1' => __('All'),
		'0'  => __('Local'),
		'2'  => __('Basic'),
		'3'  => __('LDAP/AD'),
		'4'  => __('Domain')
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
				'group' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Group'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $groups,
					'value'         => '-1'
				],
				'login' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Last Login'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '0',
					'pageset'       => true,
					'array'         => $logins,
					'value'         => '0'
				],
				'realm' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Realm'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '0',
					'pageset'       => true,
					'array'         => $realms,
					'value'         => '0'
				],
				'rows' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Users'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $item_rows,
					'value'         => '-1'
				]
			]
		],
		'buttons' => [
			'go' => [
				'method'  => 'submit',
				'display' => __('Go'),
				'title'   => __('Apply filter to table'),
			],
			'clear' => [
				'method'  => 'button',
				'display' => __('Clear'),
				'title'   => __('Reset filter to default values'),
			]
		],
		'sort' => [
			'sort_column'    => 'username',
			'sort_direction' => 'ASC'
		]
	];
}

function user() : void {
	global $auth_realms, $actions, $item_rows;

	$filters = create_user_filter();

	// create the page filter
	$pageFilter = new CactiTableFilter(__('User Management'), 'user_admin.php', 'form_user', 'sess_ua', 'user_admin.php?action=user_edit&tab=general');

	$pageFilter->rows_label       = __('Users');

	$pageFilter->set_filter_array($filters);
	$pageFilter->render();

	if (grv('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	$sql_where  = '';
	$sql_params = [];

	// form the 'where' clause for our main sql query
	if (grv('filter') != '') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . '(ua.username LIKE ? OR ua.full_name LIKE ?)';

		$sql_params[] = '%' . grv('filter') . '%';
		$sql_params[] = '%' . grv('filter') . '%';
	}

	if (grv('realm') >= 0) {
		if (grv('realm') < 4) {
			$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' ua.realm = ?';
			$sql_params[] = grv('realm');
		} else {
			$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' ua.realm > 3';
		}
	}

	if (grv('group') > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' ug.group_id = ?';
		$sql_params[] = grv('group');
	}

	if (grv('login') > 0) {
		if (grv('login') == 1) {
			$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' time > DATE_SUB(NOW(), INTERVAL 1 WEEK)';
		} elseif (grv('login') == 2) {
			$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' time > DATE_SUB(NOW(), INTERVAL 1 MONTH)';
		} elseif (grv('login') == 3) {
			$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' time < DATE_SUB(NOW(), INTERVAL 1 MONTH)';
		} elseif (grv('login') == 4) {
			$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' time < DATE_SUB(NOW(), INTERVAL 2 MONTH)';
		} elseif (grv('login') == 5) {
			$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' time < DATE_SUB(NOW(), INTERVAL 4 MONTH)';
		} elseif (grv('login') == 6) {
			$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' time IS NULL';
		}
	}

	$total_rows = db_fetch_cell_prepared("SELECT
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
		$sql_where",
		$sql_params);

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;

	$user_list = db_fetch_assoc_prepared("SELECT ua.id, ua.username, ua.full_name,
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
		$sql_limit",
		$sql_params);

	$display_text = [
		'username' => [
			'display' => __('User Name'),
			'sort'    => 'ASC'
		],
		'id' => [
			'display' => __('User ID'),
			'sort'    => 'ASC'
		],
		'full_name' => [
			'display' => __('Full Name'),
			'sort'    => 'ASC'
		],
		'enabled' => [
			'display' => __('Enabled'),
			'sort'    => 'ASC'
		],
		'realm' => [
			'display' => __('Realm'),
			'sort'    => 'ASC'
		],
		'policy_graphs' => [
			'display' => __('Graph Policy'),
			'sort'    => 'ASC'
		],
		'policy_hosts' => [
			'display' => __('Device Policy'),
			'sort'    => 'ASC'
		],
		'policy_graph_templates' => [
			'display' => __('Template Policy'),
			'sort'    => 'ASC'
		],
		'dtime' => [
			'display' => __('Last Login'),
			'sort'    => 'DESC'
		]
	];

	$nav = html_nav_bar('user_admin.php?filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 9, __('Users'), 'page', 'main');

	form_start('user_admin.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'), false);

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

			if (isset($auth_realms[$user['realm']]['name'])) {
				$realm = $auth_realms[$user['realm']]['name'];
			} elseif (isset($auth_realms[$user['realm']])) {
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

			form_selectable_cell(filter_value($user['username'], grv('filter'), CACTI_PATH_URL . 'user_admin.php?action=user_edit&tab=general&id=' . $user['id']), $user['id']);
			form_selectable_cell($user['id'], $user['id']);
			form_selectable_cell(filter_value($user['full_name'], grv('filter')), $user['id']);
			form_selectable_cell($enabled, $user['id']);
			form_selectable_cell($realm, $user['id']);
			form_selectable_cell(($user['policy_graphs'] == 1 ? __('ALLOW') : __('DENY')), $user['id']);
			form_selectable_cell(($user['policy_hosts'] == 1 ? __('ALLOW') : __('DENY')), $user['id']);
			form_selectable_cell(($user['policy_graph_templates'] == 1 ? __('ALLOW') : __('DENY')), $user['id']);
			form_selectable_cell($last_login, $user['id']);

			form_checkbox_cell($user['username'], $user['id'], $disabled);

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No Users Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($user_list)) {
		print $nav;
	}

	draw_actions_dropdown($actions);

	form_end();
}

function create_ugraphs_filter() : array {
	global $item_rows;

	$any  = ['-1' => __('Any')];
	$none = ['0'  => __('None')];

	$graph_templates = array_rekey(
		db_fetch_assoc('SELECT DISTINCT gt.id, gt.name
			FROM graph_templates AS gt
			INNER JOIN graph_local AS gl
			ON gl.graph_template_id = gt.id
			ORDER BY name'),
		'id', 'name'
	);

	$graph_templates = $any + $none + $graph_templates;

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
				'graph_template_id' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Template'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $graph_templates,
					'value'         => '0'
				],
				'rows' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Graphs'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $item_rows,
					'value'         => '-1'
				]
			]
		],
		'buttons' => [
			'go' => [
				'method'  => 'submit',
				'display' => __('Go'),
				'title'   => __('Apply filter to table'),
			],
			'clear' => [
				'method'  => 'button',
				'display' => __('Clear'),
				'title'   => __('Reset filter to default values'),
			]
		]
	];
}

function graph_filter(string $header_label) : void {
	$filters = create_ugraphs_filter();

	// create the page filter
	$pageFilter = new CactiTableFilter(__('Graph Permissions %s', $header_label), 'user_admin.php?action=user_edit&tab=permsg&id=' . grv('id'), 'form_template', 'sess_ua_d');

	$pageFilter->rows_label       = __('Graphs');
	$pageFilter->has_associated   = true;
	$pageFilter->associated_label = __('Only Show Exceptions');

	$pageFilter->set_filter_array($filters);
	$pageFilter->render();

	form_hidden_box('action', 'user_edit', '');
	form_hidden_box('tab', 'permsg', '');
	form_hidden_box('id', grv('id'), '');
}

function group_filter(string $header_label) : void {
	// create the page filter
	$pageFilter = new CactiTableFilter(__('Group Membership %s', $header_label), 'user_admin.php?action=user_edit&tab=permsgr&id=' . grv('id'), 'form_group', 'sess_ua_g');

	$pageFilter->rows_label       = __('Groups');
	$pageFilter->has_associated   = true;
	$pageFilter->associated_label = __('Show All');
	$pageFilter->render();

	form_hidden_box('action', 'user_edit', '');
	form_hidden_box('tab', 'permsgr', '');
	form_hidden_box('id', grv('id'), '');
}

function create_device_filter() : array {
	global $item_rows;

	$any  = ['-1' => __('Any')];
	$none = ['0'  => __('None')];

	$host_templates = array_rekey(
		db_fetch_assoc('SELECT id, name
			FROM host_template
			ORDER BY name'),
		'id', 'name'
	);

	$host_templates = $any + $none + $host_templates;

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
				'host_template_id' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Template'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $host_templates,
					'value'         => '-1'
				],
				'rows' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Devices'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => '-1',
					'pageset'       => true,
					'array'         => $item_rows,
					'value'         => '-1'
				]
			]
		],
		'buttons' => [
			'go' => [
				'method'  => 'submit',
				'display' => __('Go'),
				'title'   => __('Apply filter to table'),
			],
			'clear' => [
				'method'  => 'button',
				'display' => __('Clear'),
				'title'   => __('Reset filter to default values'),
			]
		]
	];
}

function device_filter(string $header_label) : void {
	$filters = create_device_filter();

	// create the page filter
	$pageFilter = new CactiTableFilter(__('Device Permissions %s', $header_label), 'user_admin.php?action=user_edit&tab=permsd&id=' . grv('id'), 'form_template', 'sess_ua_d');

	$pageFilter->rows_label       = __('Devices');
	$pageFilter->has_associated   = true;
	$pageFilter->associated_label = __('Only Show Exceptions');

	$pageFilter->set_filter_array($filters);
	$pageFilter->render();

	form_hidden_box('action', 'user_edit', '');
	form_hidden_box('tab', 'permste', '');
	form_hidden_box('id', grv('id'), '');
}

function template_filter(string $header_label) : void {
	// create the page filter
	$pageFilter = new CactiTableFilter(__('Template Permissions %s', $header_label), 'user_admin.php?action=user_edit&tab=permste&id=' . grv('id'), 'form_template', 'sess_ua_te');

	$pageFilter->rows_label       = __('Templatee');
	$pageFilter->has_associated   = true;
	$pageFilter->associated_label = __('Only Show Exceptions');
	$pageFilter->render();

	form_hidden_box('action', 'user_edit', '');
	form_hidden_box('tab', 'permste', '');
	form_hidden_box('id', grv('id'), '');
}

function tree_filter(string $header_label) : void {
	// create the page filter
	$pageFilter = new CactiTableFilter(__('Tree Permissions %s', $header_label), 'user_admin.php?action=user_edit&tab=permstr&id=' . grv('id'), 'form_tree', 'sess_ua_tr');

	$pageFilter->rows_label       = __('Trees');
	$pageFilter->has_associated   = true;
	$pageFilter->associated_label = __('Only Show Exceptions');
	$pageFilter->render();

	form_hidden_box('action', 'user_edit', '');
	form_hidden_box('tab', 'permstr', '');
	form_hidden_box('id', grv('id'), '');
}
