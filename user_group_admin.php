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

set_default_action();

$group_actions = [
	1 => __('Delete'),
	2 => __('Copy'),
	3 => __('Enable'),
	4 => __('Disable')
];

$href_options = [
	3 => [
		'radio_value'   => '4',
		'radio_caption' => __('Defer to the Users Setting')
		],
	0 => [
		'radio_value'   => '1',
		'radio_caption' => __('Show the Page that the User pointed their browser to')
		],
	1 => [
		'radio_value'   => '2',
		'radio_caption' => __('Show the Console')
		],
	2 => [
		'radio_value'   => '3',
		'radio_caption' => __('Show the default Graph Screen')
		]
];

$gperm_options = [
	0 => [
		'radio_value'   => '1',
		'radio_caption' => __('Defer to the Users Setting')
		],
	1 => [
		'radio_value'   => '2',
		'radio_caption' => __('Grant Access')
		],
	2 => [
		'radio_value'   => '3',
		'radio_caption' => __('Restrict Access')
		]
];

$fields_user_group_edit = [
	'name' => [
		'method'        => 'textbox',
		'friendly_name' => __('Group Name'),
		'description'   => __('The name of this Group.'),
		'value'         => '|arg1:name|',
		'max_length'    => '255'
		],
	'description' => [
		'method'        => 'textbox',
		'friendly_name' => __('Group Description'),
		'description'   => __('A more descriptive name for this group, that can include spaces or special characters.'),
		'value'         => '|arg1:description|',
		'max_length'    => '255'
		],
	'enabled' => [
		'method'        => 'checkbox',
		'friendly_name' => __('Enabled'),
		'description'   => __('Determines if user is able to login.'),
		'value'         => '|arg1:enabled|',
		'default'       => ''
		],
	'grp1' => [
		'friendly_name' => __('General Group Options'),
		'method'        => 'checkbox_group',
		'description'   => __('Set any user account-specific options here.'),
		'items'         => [
			'graph_settings' => [
				'value'         => '|arg1:graph_settings|',
				'friendly_name' => __('Allow Users of this Group to keep custom User Settings'),
				'form_id'       => '|arg1:id|',
				'default'       => 'on'
				]
			]
		],
	'show_tree' => [
		'friendly_name' => __('Tree Rights'),
		'method'        => 'radio',
		'description'   => __('Should Users of this Group have access to the Tree?'),
		'value'         => '|arg1:show_tree|',
		'default'       => '1',
		'items'         => $gperm_options
		],
	'show_list' => [
		'friendly_name' => __('Graph List Rights'),
		'method'        => 'radio',
		'description'   => __('Should Users of this Group have access to the Graph List?'),
		'value'         => '|arg1:show_list|',
		'default'       => '1',
		'items'         => $gperm_options
		],
	'show_preview' => [
		'friendly_name' => __('Graph Preview Rights'),
		'method'        => 'radio',
		'description'   => __('Should Users of this Group have access to the Graph Preview?'),
		'value'         => '|arg1:show_preview|',
		'default'       => '1',
		'items'         => $gperm_options
		],
	'login_opts' => [
		'friendly_name' => __('Login Options'),
		'method'        => 'radio',
		'default'       => '1',
		'description'   => __('What to do when a User from this User Group logs in.'),
		'value'         => '|arg1:login_opts|',
		'items'         => $href_options
		],
	'id' => [
		'method' => 'hidden_zero',
		'value'  => '|arg1:id|'
		],
	'save_component_group' => [
		'method' => 'hidden',
		'value'  => '1'
		]
];

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
		case 'edit':
			top_header();
			group_edit();
			bottom_footer();

			break;
		default:
			if (!api_plugin_hook_function('user_group_admin_action', grv('action'))) {
				top_header();
				user_group();
				bottom_footer();
			}

			break;
	}
}

function user_group_disable(int $id) : void {
	db_execute_prepared("UPDATE user_auth_group SET enabled = '' WHERE id = ?", [$id]);

	reset_group_perms($id);
}

function user_group_enable(int $id) : void {
	db_execute_prepared("UPDATE user_auth_group SET enabled = 'on' WHERE id = ?", [$id]);

	reset_group_perms($id);
}

function user_group_remove(int $id) : void {
	db_execute_prepared('DELETE FROM user_auth_group WHERE id = ?', [$id]);
	db_execute_prepared('DELETE FROM user_auth_group_members WHERE group_id = ?', [$id]);
	db_execute_prepared('DELETE FROM user_auth_group_realm WHERE group_id = ?', [$id]);
	db_execute_prepared('DELETE FROM user_auth_group_perms WHERE group_id = ?', [$id]);
}

function user_group_copy(int $id, string $prefix = 'New Group') : void {
	static $count = 1;

	$name = $prefix . ' ' . $count;

	db_execute_prepared('INSERT INTO user_auth_group
		(name, description, graph_settings, login_opts, show_tree, show_list, show_preview,
		policy_graphs, policy_trees, policy_hosts, policy_graph_templates, enabled)
		SELECT ' . db_qstr($name) . ', description, graph_settings, login_opts, show_tree, show_list, show_preview,
		policy_graphs, policy_trees, policy_hosts, policy_graph_templates, enabled
		FROM user_auth_group WHERE id = ?', [$id]);

	$id = db_fetch_insert_id();

	if (!empty($id)) {
		$perms = db_fetch_assoc_prepared('SELECT *
			FROM user_auth_group_perms
			WHERE group_id = ?',
			[$id]);

		if (cacti_sizeof($perms)) {
			foreach ($perms as $p) {
				db_execute_prepared('INSERT INTO user_auth_group_perms
					(group_id, item_id, type)
					VALUES (?, ?, ?)',
					[$id, $p['item_id'], $p['type']]);
			}
		}

		$realms = db_fetch_assoc_prepared('SELECT *
			FROM user_auth_group_realm
			WHERE group_id = ?',
			[$id]);

		if (cacti_sizeof($realms)) {
			foreach ($realms as $r) {
				db_execute_prepared('INSERT INTO user_auth_group_realm
					(group_id, realm_id)
					VALUES (?, ?)',
					[$id, $r['realm_id']]);
			}
		}
	}

	$count++;
}

function update_policies() : void {
	$policies = ['policy_graphs', 'policy_trees', 'policy_hosts', 'policy_graph_templates'];

	foreach ($policies as $p) {
		if (isrv($p)) {
			db_execute_prepared("UPDATE `user_auth_group` SET `$p` = ? WHERE `id` = ?", [gfrv($p), gfrv('id')]);
		}
	}

	header('Location: user_group_admin.php?action=edit&tab=' . gnrv('tab') . '&id=' . gnrv('id'));

	exit;
}

function form_actions() : void {
	global $group_actions, $user_auth_realms;

	// if we are to save this form, instead of display it
	if (isrv('associate_host')) {
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				if (gnrv('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_group_perms
						(group_id, item_id, type)
						VALUES (?, ?, 3)',
						[gnrv('id'), $matches[1]]);
				} else {
					db_execute_prepared('DELETE FROM user_auth_group_perms
						WHERE group_id = ?
						AND item_id = ?
						AND type = 3',
						[gnrv('id'), $matches[1]]);
				}
			}
		}

		header('Location: user_group_admin.php?action=edit&tab=permsd&id=' . gnrv('id'));

		exit;
	}

	if (isrv('associate_graph')) {
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				if (gnrv('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_group_perms
						(group_id, item_id, type)
						VALUES (?, ?, 1)',
						[gnrv('id'), $matches[1]]);
				} else {
					db_execute_prepared('DELETE FROM user_auth_group_perms
						WHERE group_id = ?
						AND item_id = ?
						AND type = 1',
						[gnrv('id'), $matches[1]]);
				}
			}
		}

		header('Location: user_group_admin.php?action=edit&tab=permsg&id=' . gnrv('id'));

		exit;
	}

	if (isrv('associate_template')) {
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				if (gnrv('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_group_perms
						(group_id, item_id, type)
						VALUES (?, ?, 4)',
						[gnrv('id'), $matches[1]]);
				} else {
					db_execute_prepared('DELETE FROM user_auth_group_perms
						WHERE group_id = ?
						AND item_id = ?
						AND type = 4',
						[gnrv('id'), $matches[1]]);
				}
			}
		}

		header('Location: user_group_admin.php?action=edit&tab=permste&id=' . gnrv('id'));

		exit;
	}

	if (isrv('associate_tree')) {
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				if (gnrv('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_group_perms
						(group_id, item_id, type)
						VALUES (?, ?, 2)',
						[gnrv('id'), $matches[1]]);
				} else {
					db_execute_prepared('DELETE FROM user_auth_group_perms
						WHERE group_id = ?
						AND item_id = ?
						AND type = 2',
						[gnrv('id'), $matches[1]]);
				}
			}
		}

		header('Location: user_group_admin.php?action=edit&tab=permstr&id=' . gnrv('id'));

		exit;
	}

	if (isrv('associate_member')) {
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				if (gnrv('drp_action') == '1') {
					db_execute_prepared('REPLACE INTO user_auth_group_members
						(group_id, user_id)
						VALUES (?, ?)',
						[gnrv('id'), $matches[1]]);
				} else {
					db_execute_prepared('DELETE FROM user_auth_group_members
						WHERE group_id = ?
						AND user_id = ?',
						[gnrv('id'), $matches[1]]);
				}
			}
		}

		header('Location: user_group_admin.php?action=edit&tab=members&id=' . gnrv('id'));

		exit;
	}

	if (isrv('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

		if ($selected_items != false) {
			if (gnrv('drp_action') == '1') { // delete
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					user_group_remove($selected_items[$i]);

					api_plugin_hook_function('user_group_remove', $selected_items[$i]);
				}
			} elseif (gnrv('drp_action') == '2') { // copy
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					user_group_copy($selected_items[$i], gnrv('group_prefix'));
				}
			} elseif (gnrv('drp_action') == '3') { // enable
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					user_group_enable($selected_items[$i]);
				}
			} elseif (gnrv('drp_action') == '4') { // disable
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					user_group_disable($selected_items[$i]);
				}
			}
		}

		header('Location: user_group_admin.php');

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
					$ilist .= '<li>' . htmle(db_fetch_cell_prepared('SELECT name FROM user_auth_group WHERE id = ?', [$matches[1]])) . '</li>';
				}

				$iarray[] = $matches[1];
			}
		}

		$form_data = [
			'general' => [
				'page'       => 'user_group_admin.php',
				'actions'    => $group_actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			],
			'options' => [
				1 => [
					'smessage' => __('Click \'Continue\' to Delete the following User Group.'),
					'pmessage' => __('Click \'Continue\' to Delete following User Groups.'),
					'scont'    => __('Delete User Group'),
					'pcont'    => __('Delete User Groups')
				],
				2 => [
					'smessage' => __('Click \'Continue\' to Copy the following User Group.'),
					'pmessage' => __('Click \'Continue\' to Copy following User Groups.'),
					'scont'    => __('Copy User Group'),
					'pcont'    => __('Copy User Groups'),
					'extra'    => [
						'group_prefix' => [
							'method'  => 'textbox',
							'title'   => __('Group Prefix'),
							'default' => __('New Group'),
							'width'   => 25
						]
					]
				],
				3 => [
					'smessage' => __('Click \'Continue\' to Enable the following User Group.'),
					'pmessage' => __('Click \'Continue\' to Enable following User Groups.'),
					'scont'    => __('Enable User Group'),
					'pcont'    => __('Enable User Groups')
				],
				4 => [
					'smessage' => __('Click \'Continue\' to Disable the following User Group.'),
					'pmessage' => __('Click \'Continue\' to Disable following User Groups.'),
					'scont'    => __('Disable User Group'),
					'pcont'    => __('Disable User Groups')
				]
			]
		];

		form_continue_confirmation($form_data);
	}
}

function form_save() : void {
	global $settings_user;

	if (isrv('save_component_group')) {
		// ================= input validation =================
		gfrv('id');
		gfrv('realm');
		// ====================================================

		// check duplicate group
		if (cacti_sizeof(db_fetch_row_prepared('SELECT * FROM user_auth_group WHERE name = ? AND id != ?', [gnrv('name'), gnrv('id')]))) {
			raise_message(12);
		}

		$save['id']             = gnrv('id');
		$save['name']           = form_input_validate(gnrv('name'), 'name', '', false, 3);
		$save['description']    = form_input_validate(gnrv('description'), 'description', '', true, 3);
		$save['show_tree']      = form_input_validate(gnrv('show_tree', ''), 'show_tree', '', true, 3);
		$save['show_list']      = form_input_validate(gnrv('show_list', ''), 'show_list', '', true, 3);
		$save['show_preview']   = form_input_validate(gnrv('show_preview', ''), 'show_preview', '', true, 3);
		$save['graph_settings'] = form_input_validate(gnrv('graph_settings', ''), 'graph_settings', '', true, 3);
		$save['login_opts']     = form_input_validate(gnrv('login_opts'), 'login_opts', '', true, 3);
		$save['enabled']        = form_input_validate(gnrv('enabled', ''), 'enabled', '', true, 3);

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

		header('Location: user_group_admin.php?action=edit&tab=general&id=' . (isset($group_id) && $group_id > 0 ? $group_id : gnrv('id')));

		exit;
	}

	if (isrv('save_component_realm_perms')) {
		db_execute_prepared('DELETE FROM user_auth_group_realm WHERE group_id = ?', [gfrv('id')]);

		foreach ($_POST as $var => $val) {
			if (preg_match('/^[section]/i', $var)) {
				if (substr($var, 0, 7) == 'section') {
					db_execute_prepared('REPLACE INTO user_auth_group_realm (group_id, realm_id) VALUES (?, ?)', [grv('id'), substr($var, 7)]);
				}
			}
		}

		reset_group_perms(grv('id'));

		raise_message(1);

		header('Location: user_group_admin.php?action=edit&tab=realms&id=' . grv('id'));

		exit;
	}

	if (isrv('save_component_graph_settings')) {
		foreach ($settings_user as $tab_short_name => $tab_fields) {
			foreach ($tab_fields as $field_name => $field_array) {
				if ((isset($field_array['items'])) && (is_array($field_array['items']))) {
					foreach ($field_array['items'] as $sub_field_name => $sub_field_array) {
						db_execute_prepared('REPLACE INTO settings_user_group (group_id, name, value) VALUES (?, ?, ?)', [gfrv('id'), $sub_field_name, gnrv($sub_field_name, '')]);
					}
				} else {
					db_execute_prepared('REPLACE INTO settings_user_group (group_id, name, value) VALUES (?, ?, ?)', [grv('id'), $field_name, gnrv($field_name)]);
				}
			}
		}

		kill_session_var(OPTIONS_USER);

		reset_group_perms(grv('id'));

		raise_message(1);

		header('Location: user_group_admin.php?action=edit&tab=settings&id=' . gnrv('id'));

		exit;
	} else {
		api_plugin_hook('user_group_admin_save');
	}

	// redirect to the appropriate page
	header('Location: user_group_admin.php?action=edit&tab=general&id=' . gnrv('id'));
}

function perm_remove() : void {
	// ================= input validation =================
	gfrv('id');
	gfrv('group_id');
	// ====================================================

	if (grv('type') == 'graph') {
		db_execute_prepared('DELETE FROM user_auth_group_perms WHERE type=1 AND group_id = ? AND item_id = ?', [grv('group_id'), grv('id')]);
	} elseif (grv('type') == 'tree') {
		db_execute_prepared('DELETE FROM user_auth_group_perms WHERE type=2 AND group_id = ? AND item_id = ?', [grv('group_id'), grv('id')]);
	} elseif (grv('type') == 'host') {
		db_execute_prepared('DELETE FROM user_auth_group_perms WHERE type=3 AND group_id = ? AND item_id = ?', [grv('group_id'), grv('id')]);
	} elseif (grv('type') == 'graph_template') {
		db_execute_prepared('DELETE FROM user_auth_group_perms WHERE type=4 AND group_id = ? AND item_id = ?', [grv('group_id'), grv('id')]);
	}

	header('Location: user_group_admin.php?action=edit&tab=gperms&id=' . grv('group_id'));
}

function user_group_members_edit(string $header_label) : void {
	global $auth_realms;

	member_filter($header_label);

	// if the number of rows is -1, set it to the default
	if (grv('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = grv('rows');
	}

	$sql_where  = '';
	$sql_params = [];

	// form the 'where' clause for our main sql query
	if (grv('filter') != '') {
		$sql_where = ($sql_where != '' ? ' AND ' : 'WHERE ') . '(username LIKE ? OR full_name LIKE ?)';

		$sql_params[] = '%' . grv('filter') . '%';
		$sql_params[] = '%' . grv('filter') . '%';
	}

	if (grv('associated') != 'false') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . 'user_auth_group_members.group_id = ?';
		$sql_params[] = grv('id', 0);
	}

	$total_rows = db_fetch_cell_prepared("SELECT COUNT(ua.id)
		FROM user_auth AS ua
		LEFT JOIN user_auth_group_members
		ON ua.id = user_auth_group_members.user_id
		$sql_where",
		$sql_params);

	$sql_query = "SELECT DISTINCT ua.id, ua.username, ua.full_name, ua.enabled, ua.realm
		FROM user_auth AS ua
		LEFT JOIN user_auth_group_members
		ON ua.id = user_auth_group_members.user_id
		$sql_where
		ORDER BY username, full_name
		LIMIT " . ($rows * (grv('page') - 1)) . ',' . $rows;

	$members = db_fetch_assoc_prepared($sql_query, $sql_params);

	$display_text = [
		__('Login Name'),
		__('Full Name'),
		__('ID'),
		__('Membership'),
		__('Enabled'),
		__('Realm')
	];

	$nav = html_nav_bar('user_group_admin.php?action=edit&tab=members&id=' . grv('id'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, cacti_sizeof($display_text) + 1, __('Users'), 'page', 'main');

	form_start('user_group_admin.php?tab=members&id=' . grv('id'), 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	html_header_checkbox($display_text, false);

	if (cacti_sizeof($members)) {
		foreach ($members as $g) {
			if (isset($auth_realms[$g['realm']]['name'])) {
				$realm = $auth_realms[$g['realm']]['name'];
			} elseif (isset($auth_realms[$g['realm']])) {
				$realm = $auth_realms[$g['realm']];
			} else {
				$realm = __('Unavailable');
			}

			form_alternate_row('line' . $g['id'], true);

			form_selectable_cell(filter_value($g['username'], grv('filter'), 'user_admin.php?action=user_edit&id=' . $g['id']), $g['id']);
			form_selectable_cell(filter_value($g['full_name'], grv('filter')), $g['id']);
			form_selectable_cell($g['id'], $g['id']);

			if (user_group_is_member($g['id'], grv('id'))) {
				form_selectable_cell('<span class="accessGranted">' . __('Group Member') . '</span>', $g['id']);
			} else {
				form_selectable_cell('<span class="accessRestricted">' . __('Non Member') . '</span>', $g['id']);
			}
			form_selectable_cell(($g['enabled'] == 'on' ? __('Enabled') : __('Disabled')), $g['id']);
			form_selectable_cell($realm, $g['id']);

			form_checkbox_cell($g['full_name'], $g['id']);

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No Matching Group Members Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($members)) {
		print $nav;
	}

	form_hidden_box('tab', 'members', '');
	form_hidden_box('id', grv('id'), '');
	form_hidden_box('associate_member', '1', '');

	$assoc_actions = [
		1 => __('Add to Group'),
		2 => __('Remove from Group')
	];

	// draw the dropdown containing a list of available actions for this form
	draw_actions_dropdown($assoc_actions);

	form_end();
}

function user_group_graph_perms_edit(string $tab, string $header_label) : void {
	global $assoc_actions;

	// ================= input validation =================
	gfrv('id');
	// ====================================================

	$policy_array = [
		1 => __('Allow'),
		2 => __('Deny')
	];

	$policy = [];

	if (!ierv('id')) {
		$policy = db_fetch_row_prepared('SELECT policy_graphs, policy_trees, policy_hosts, policy_graph_templates
			FROM user_auth_group
			WHERE id = ?',
			[grv('id')]);
	}

	switch($tab) {
		case 'permsg':
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

			// box: device permissions
			html_start_box(__('Default Graph Policy'), '100%', false, 3, 'center', '');

			?>
			<tr class='even'>
				<td><table><tr>
				<td class='nowrap'><?php print __('Default Graph Policy for this User Group'); ?></td>
				<td>
					<?php form_dropdown('policy_graphs', $policy_array, '', '', $policy['policy_graphs'], '', ''); ?>
				</td>
				<td>
					<button type='submit' class='ui-button ui-corner-all ui-widget ui-state-active' name='update_policy'><?php print __esc('Update'); ?></button>
					<input type='hidden' name='tab' value='<?php print $tab; ?>'>
					<input type='hidden' name='id' value='<?php print grv('id'); ?>'>
					<input type="hidden" name='update_policy' value='1'>
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

			$sql_limit = 'LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows;
			$sql_where = '';

			// form the 'where' clause for our main sql query
			if (grv('filter') != '') {
				$sql_where .= 'WHERE (
				gtg.title_cache LIKE ' . db_qstr('%' . grv('filter') . '%') . '
				AND gtg.local_graph_id > 0)';
			} else {
				$sql_where .= 'WHERE (gtg.local_graph_id > 0)';
			}

			if (grv('graph_template_id') == '-1') {
				// Show all items
			} elseif (grv('graph_template_id') == '0') {
				$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' gtg.graph_template_id=0';
			} elseif (!ierv('graph_template_id')) {
				$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' gtg.graph_template_id=' . grv('graph_template_id');
			}

			$policies = db_fetch_assoc_prepared("SELECT uag.id, 'group' AS type, uag.name,
				uag.policy_graphs, uag.policy_hosts, uag.policy_graph_templates
				FROM user_auth_group AS uag
				WHERE uag.enabled = 'on'
				AND uag.id = ?",
				[grv('id')]);

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

			$nav = html_nav_bar('user_group_admin.php?action=edit&tab=permsg&id=' . grv('id'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 7, __('Graphs'), 'page', 'main');

			form_start('user_group_admin.php?tab=permsg&id=' . grv('id'), 'chk');

			print $nav;

			html_start_box('', '100%', false, 3, 'center', '');

			$display_text = [ __('Graph Title'), __('ID'), __('Effective Policy')];

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
		case 'permsd':
			device_filter($header_label);

			form_start('user_group_admin.php', 'policy');

			// box: device permissions
			html_start_box(__('Default Device Policy'), '100%', false, 3, 'center', '');

			?>
			<tr class='even'>
				<td><table><tr>
				<td class='nowrap'><?php print __('Default Device Policy for this User Group'); ?></td>
				<td>
					<?php form_dropdown('policy_hosts',$policy_array,'','',$policy['policy_hosts'],'',''); ?>
				</td>
				<td>
					<button type='submit' class='ui-button ui-corner-all ui-widget ui-state-active' name='update_policy'><?php print __esc('Update'); ?></button>
					<input type='hidden' name='tab' value='<?php print $tab; ?>'>
					<input type='hidden' name='id' value='<?php print grv('id'); ?>'>
					<input type="hidden" name='update_policy' value='1'>
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
			// form the 'where' clause for our main sql query
			if (grv('filter') != '') {
				$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . 'h.deleted = "" AND (h.hostname LIKE ? OR h.description LIKE ?)';
				$sql_params[] = '%' . grv('filter') . '%';
				$sql_params[] = '%' . grv('filter') . '%';
			} else {
				$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' h.deleted = ""';
			}

			if (grv('host_template_id') == '0') {
				$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' h.host_template_id = 0';
			} elseif (grv('host_template_id') > 0) {
				$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' h.host_template_id = ?';
				$sql_params[] = grv('host_template_id');
			}

			if (grv('associated') != 'false') {
				$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' uagp.group_id = ?';
				$sql_params[] = grv('id', 0);
			}

			$total_rows = db_fetch_cell_prepared("SELECT COUNT(DISTINCT h.id)
				FROM host AS h
				LEFT JOIN user_auth_group_perms AS uagp
				ON h.id = uagp.item_id
				AND uagp.type = 3
				AND uagp.group_id = ?
				$sql_where",
				$sql_params);

			$host_graphs = array_rekey(
				db_fetch_assoc('SELECT host_id, COUNT(*) AS graphs
					FROM graph_local
					GROUP BY host_id'),
				'host_id', 'graphs'
			);

			$host_data_sources = array_rekey(
				db_fetch_assoc('SELECT host_id, COUNT(*) AS data_sources
					FROM data_local
					GROUP BY host_id'),
				'host_id', 'data_sources'
			);

			$sql_query = "SELECT h.*, uagp.group_id
				FROM host AS h
				LEFT JOIN user_auth_group_perms AS uagp
				ON h.id = uagp.item_id
				AND uagp.type = 3
				AND uagp.group_id = ?
				$sql_where
				ORDER BY description
				LIMIT " . ($rows * (grv('page') - 1)) . ',' . $rows;

			$hosts = db_fetch_assoc_prepared($sql_query, $sql_params);

			$display_text = [
				__('Description'),
				__('ID'),
				__('Effective Policy'),
				__('Graphs'),
				__('Data Sources'),
				__('Status'),
				__('Hostname')
			];

			$nav = html_nav_bar('user_group_admin.php?action=edit&tab=permsd&id=' . grv('id'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, cacti_sizeof($display_text) + 1, __('Devices'), 'page', 'main');

			form_start('user_group_admin.php?tab=permsd&id=' . grv('id'), 'chk');

			print $nav;

			html_start_box('', '100%', false, 3, 'center', '');

			html_header_checkbox($display_text, false);

			if (cacti_sizeof($hosts)) {
				foreach ($hosts as $host) {
					form_alternate_row('line' . $host['id'], true);

					form_selectable_cell(filter_value($host['description'], grv('filter')), $host['id']);
					form_selectable_cell($host['id'], $host['id']);

					if (empty($host['group_id']) || $host['group_id'] == null) {
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
			template_filter($header_label);

			form_start('user_group_admin.php', 'policy');

			// box: device permissions
			html_start_box(__('Default Graph Template Policy'), '100%', false, 3, 'center', '');

			?>
			<tr class='even'>
				<td><table><tr>
				<td class='nowrap'><?php print __('Default Graph Template Policy for this User Group'); ?></td>
				<td>
					<?php form_dropdown('policy_graph_templates',$policy_array,'','',$policy['policy_graph_templates'],'',''); ?>
				</td>
				<td>
					<button type='submit' class='ui-button ui-corner-all ui-widget ui-state-active' name='update_policy'><?php print __esc('Update'); ?></button>
					<input type='hidden' name='tab' value='<?php print $tab; ?>'>
					<input type='hidden' name='id' value='<?php print grv('id'); ?>'>
					<input type="hidden" name='update_policy' value='1'>
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
			// form the 'where' clause for our main sql query
			if (grv('filter') != '') {
				$sql_where = 'WHERE gt.name LIKE ' . db_qstr('%' . grv('filter') . '%');
			} else {
				$sql_where = '';
			}

			if (grv('associated') != 'false') {
				$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' (user_auth_group_perms.type = 4 AND user_auth_group_perms.group_id=' . grv('id', 0) . ')';
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
				[grv('id')]);

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
				LIMIT " . ($rows * (grv('page') - 1)) . ',' . $rows;

			$graphs = db_fetch_assoc_prepared($sql_query, [grv('id')]);

			$nav = html_nav_bar('user_group_admin.php?action=edit&tab=permste&id=' . grv('id'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 11, __('Graph Templates'), 'page', 'main');

			form_start('user_group_admin.php?tab=permste&id=' . grv('id'), 'chk');

			print $nav;

			html_start_box('', '100%', false, 3, 'center', '');

			$display_text = [ __('Template Name'), __('ID'), __('Effective Policy'), __('Total Graphs')];

			html_header_checkbox($display_text, false);

			if (cacti_sizeof($graphs)) {
				foreach ($graphs as $g) {
					form_alternate_row('line' . $g['id'], true);

					form_selectable_cell(filter_value($g['name'], grv('filter')), $g['id']);
					form_selectable_cell($g['id'], $g['id']);

					if (empty($g['group_id']) || $g['group_id'] == null) {
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
			tree_filter($header_label);

			form_start('user_group_admin.php', 'policy');

			// box: device permissions
			html_start_box(__('Default Tree Policy'), '100%', false, 3, 'center', '');

			?>
			<tr class='even'>
				<td><table><tr>
				<td class='nowrap'><?php print __('Default Tree Policy for this User Group'); ?></td>
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
				$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . ' (user_auth_group_perms.type = 2 AND user_auth_group_perms.group_id=' . grv('id', 0) . ')';
			}

			$total_rows = db_fetch_cell('SELECT
				COUNT(DISTINCT gt.id)
				FROM graph_tree AS gt
				LEFT JOIN user_auth_group_perms
				ON (gt.id = user_auth_group_perms.item_id AND user_auth_group_perms.type = 2 AND user_auth_group_perms.group_id = ' . grv('id') . ")
				$sql_where");

			$sql_query = 'SELECT gt.id, gt.name, user_auth_group_perms.group_id
				FROM graph_tree AS gt
				LEFT JOIN user_auth_group_perms
				ON (gt.id = user_auth_group_perms.item_id AND user_auth_group_perms.type = 2 AND user_auth_group_perms.group_id = ' . grv('id') . ")
				$sql_where
				ORDER BY name
				LIMIT " . ($rows * (grv('page') - 1)) . ',' . $rows;

			$trees = db_fetch_assoc($sql_query);

			$nav = html_nav_bar('user_group_admin.php?action=edit&tab=permstr&id=' . grv('id'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 11, __('Trees'), 'page', 'main');

			form_start('user_group_admin.php?tab=permstr&id=' . grv('id'), 'chk');

			print $nav;

			html_start_box('', '100%', false, 3, 'center', '');

			$display_text = [ __('Tree Name'), __('ID'), __('Effective Policy')];

			html_header_checkbox($display_text, false);

			if (cacti_sizeof($trees)) {
				foreach ($trees as $t) {
					form_alternate_row('line' . $t['id'], true);

					form_selectable_cell(filter_value($t['name'], grv('filter')), $t['id']);
					form_selectable_cell($t['id'], $t['id']);

					if (empty($t['group_id']) || $t['group_id'] == null) {
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

function user_group_is_member(int $user_id, int $group_id) : int {
	return db_fetch_cell_prepared('SELECT COUNT(*)
		FROM user_auth_group_members
		WHERE user_id = ?
		AND group_id = ?',
		[$user_id, $group_id]);
}

function user_group_realms_edit(string $header_label) : void {
	global $user_auth_realms, $user_auth_roles;

	// ================= input validation =================
	gfrv('id');
	// ====================================================

	print "<div class='cactiTable' style='width:100%;text-align:left;'>
		<div class='cactiTableTitleRow'>
			<div class='cactiTableTitle'><span style='padding:3px;'>" . __('User Permissions') . ' ' . htmle($header_label) . "</span></div>
			<div class='cactiTableButton'><span style='padding:3px;'><input class='checkbox' type='checkbox' id='all' name='all' title='" . __esc('Select All') . "' onClick='selectAllRealms(this.checked)'><label class='formCheckboxLabel' title='" . __esc('Select All') . "' for='all'></label></a></span></div>
		</div>
	</div>";

	form_start('user_group_admin.php', 'chk');

	$all_realms = $user_auth_realms;

	html_start_box('', '100%', false, 3, 'center', '');

	// do cacti realms first
	foreach ($user_auth_roles as $role_name => $perms) {
		print "<tr class='tableHeader'><th colspan='2'>" . htmle($role_name) . '</th></tr>';
		print "<tr class='odd'><td colspan='2'><div class='flexContainer'>";

		foreach ($perms as $realm) {
			if (isset($user_auth_realms[$realm])) {
				$set = db_fetch_cell_prepared('SELECT realm_id
					FROM user_auth_group_realm
					WHERE group_id = ?
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
		print "<tr class='tableHeader'><th colspan='2'>" . __('External Link Permissions') . '</th></tr>';
		print "<tr class='odd'><td class='left' colspan='2'><div class='flexContainer'>";

		foreach ($links as $r) {
			$realm = $r['id'] + 10000;

			$set = db_fetch_cell_prepared('SELECT realm_id
				FROM user_auth_group_realm
				WHERE group_id = ?
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
	$realms = db_fetch_assoc('SELECT pc.name, pr.id AS realm_id, pr.display
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
				FROM user_auth_group_realm
				WHERE group_id = ?
				AND realm_id = ?',
				[grv('id', 0), $realm]);

			if ($set) {
				$old_value = 'on';
			} else {
				$old_value = '';
			}

			unset($all_realms[$realm]);

			$pos = (str_contains($user_auth_realms[$realm], '->') ? strpos($user_auth_realms[$realm], '->') + 2 : 0);

			if ($i == 0) {
				print "<tr class='tableHeader'><th colspan='2'>" . __('Plugin Permissions') . '</th></tr>';
				print "<tr class='odd'><td colspan='2'><div class='flexContainer'>";
				$i++;
			}

			print '<div class="flexChild">';
			form_checkbox('section' . $realm, $old_value, substr($user_auth_realms[$realm], $pos), '', '', '', '', $r['display'], true);
			print '</div>';
		}

		if ($i > 0) {
			print '</div></td></tr>';
		}
	}

	// get the old PIA 1.x realms
	if (cacti_sizeof($all_realms)) {
		print "<tr class='tableHeader'><th class='left' colspan='2'>" . __('Legacy Permissions') . '</th></tr>';
		print "<tr class='odd'><td colspan='2'><div class='flexContainer'>";

		foreach ($all_realms as $realm => $name) {
			$set = db_fetch_cell_prepared('SELECT realm_id
				FROM user_auth_group_realm
				WHERE group_id = ? AND
				realm_id = ?',
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

	form_hidden_box('save_component_realm_perms','1','');
	form_hidden_box('tab','realms','');
	form_hidden_box('id',grv('id'),'');

	form_save_button('user_group_admin.php', 'return');
}

function user_group_settings_edit(string $header_label) : void {
	global $settings_user, $tabs_graphs, $graph_views;

	// ================= input validation =================
	gfrv('id');
	// ====================================================

	form_start('user_group_admin.php', 'chk');

	html_start_box(__esc('User Settings %s', $header_label), '100%', true, 3, 'center', '');

	foreach ($settings_user as $tab_short_name => $tab_fields) {
		print "<div class='spacer formHeader collapsible' id='row_$tab_short_name'><div style='cursor:pointer;' class='tableSubHeaderColumn'>" . $tabs_graphs[$tab_short_name] . "<div style='float:right;padding-right:4px;'><i class='ti ti-chevrons-up'></i></div></div></div>";

		$form_array = [];

		foreach ($tab_fields as $field_name => $field_array) {
			$form_array += [$field_name => $tab_fields[$field_name]];

			if ((isset($field_array['items'])) && (is_array($field_array['items']))) {
				foreach ($field_array['items'] as $sub_field_name => $sub_field_array) {
					if (graph_config_value_exists($sub_field_name, grv('id'))) {
						$form_array[$field_name]['items'][$sub_field_name]['form_id'] = 1;
					}

					$form_array[$field_name]['items'][$sub_field_name]['value'] =  db_fetch_cell_prepared('SELECT value FROM settings_user_group WHERE name = ? AND group_id = ?', [$sub_field_name, grv('id')]);
				}
			} else {
				if (graph_config_value_exists($field_name, grv('id'))) {
					$form_array[$field_name]['form_id'] = 1;
				}

				$form_array[$field_name]['value'] = db_fetch_cell_prepared('SELECT value FROM settings_user_group WHERE name = ? AND group_id = ?', [$field_name, grv('id')]);
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

	form_hidden_box('save_component_graph_settings','1','');
	form_hidden_box('tab','settings','');
	form_hidden_box('id',grv('id'),'');

	form_save_button('user_group_admin.php', 'return');

	?>
	<script type='text/javascript'>

	var themeFonts=<?php print read_config_option('font_method'); ?>;

	function graphSettings() {
		var showField = $('#custom_fonts').is(':checked');
		toggleFields({
			fonts: themeFonts == 1,
			title_size: showField,
			title_font: showField,
			legend_size: showField,
			legend_font: showField,
			axis_size: showField,
			axis_font: showField,
			unit_size: showField,
			unit_font: showField,
		});
	}

	$(function() {
		graphSettings();
	});

	</script>
	<?php
}

function group_edit() : void {
	global $fields_user_group_edit;

	// ================= input validation =================
	gfrv('id');
	gfrv('tab', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^([a-z_A-Z]+)$/']]);
	// ====================================================

	// present a tabbed interface
	$tabs = [
		'general'  => __('General'),
		'members'  => __('Members'),
		'realms'   => __('Permissions'),
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

	if (!ierv('id')) {
		$group        = db_fetch_row_prepared('SELECT * FROM user_auth_group WHERE id = ?', [grv('id')]);
		$header_label = __esc('User Group Management [edit: %s]', $group['name']);
	} else {
		$header_label = __('User Group Management [new]');
	}

	// set the default tab
	load_current_session_value('tab', 'sess_ugroup_tab', 'general');
	$current_tab = gnrv('tab');

	if (cacti_sizeof($tabs) && !ierv('id')) {
		// draw the tabs
		print "<div class='tabs'><nav><ul role='tablist'>";

		foreach ($tabs as $id => $name) {
			print "<li class='subTab'><a class='tab" . (($id == $current_tab) ? " selected'" : "'") .
				" href='" . htmle(CACTI_PATH_URL .
				'user_group_admin.php?action=edit&id=' . grv('id') .
				'&tab=' . $id) .
				"'>" . $name . '</a></li>';
		}

		api_plugin_hook('user_group_admin_tab');

		print '</ul></nav></div>';
	}

	switch(grv('tab')) {
		case 'general':
			api_plugin_hook_function('user_group_admin_edit', (isset($group) ? grv('id') : 0));

			form_start('user_group_admin.php');

			html_start_box($header_label, '100%', true, 3, 'center', '');

			draw_edit_form([
				'config' => ['no_form_tag' => true],
				'fields' => inject_form_variables($fields_user_group_edit, (isset($group) ? $group : []))
			]);

			html_end_box(true, true);

			form_save_button('user_group_admin.php', 'return');

			?>
			<script type='text/javascript'>
			var consoleAllowed=<?php print is_user_group_realm_allowed(8, (isset($group) ? $group['id'] : 0)) ? 'true' : 'false'; ?>;

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
			if (ierv('id')) {
				header('Location: user_group_admin.php');
			}

			user_group_settings_edit($header_label);

			break;
		case 'realms':
			if (ierv('id')) {
				header('Location: user_group_admin.php');
			}

			user_group_realms_edit($header_label);

			break;
		case 'permsg':
		case 'permsd':
		case 'permste':
		case 'permstr':
			if (ierv('id')) {
				header('Location: user_group_admin.php');
			}

			user_group_graph_perms_edit(grv('tab'), $header_label);

			break;
		case 'members':
			if (ierv('id')) {
				header('Location: user_group_admin.php');
			}

			user_group_members_edit($header_label);

			break;
		default:
			if (api_plugin_hook_function('user_group_admin_run_action', grv('tab'))) {
				user_group_realms_edit($header_label);
			}

			break;
	}
}

function is_user_group_realm_allowed(int $realm_id, int $group_id) : mixed {
	return db_fetch_cell_prepared('SELECT COUNT(*)
		FROM user_auth_group_realm
		WHERE group_id = ?
		AND realm_id = ?',
		[$group_id, $realm_id]);
}

function user_group() : void {
	global $group_actions, $item_rows;

	// create the page filter
	$pageFilter = new CactiTableFilter(__('User Group Management'), 'user_group_admin.php', 'forms', 'sess_ugad', 'user_group_admin.php?action=edit&tab=general');

	$pageFilter->rows_label = __('Groups');
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
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . '(name LIKE ? OR description LIKE ?)';
		$sql_params[] = '%' . grv('filter') . '%';
		$sql_params[] = '%' . grv('filter') . '%';
	} else {
		$sql_where = '';
	}

	$total_rows = db_fetch_cell_prepared("SELECT
		COUNT(*)
		FROM user_auth_group
		$sql_where",
		$sql_params);

	$group_list = db_fetch_assoc_prepared("SELECT uag.id, uag.name, uag.description,
		uag.policy_graphs, uag.policy_hosts, uag.policy_graph_templates,
		uag.enabled, count(uagm.group_id) AS members
		FROM user_auth_group AS uag
		LEFT JOIN user_auth_group_members AS uagm
		ON uag.id = uagm.group_id
		$sql_where
		GROUP BY uag.id
		ORDER BY " . sanitize_sql_column(grv('sort_column'), 'name') . ' ' . (strtoupper(grv('sort_direction')) === 'DESC' ? 'DESC' : 'ASC') .
		' LIMIT ' . ($rows * (grv('page') - 1) . ',' . $rows),
		$sql_params);

	$display_text = [
		'name' => [
			'display' => __('Group Name'),
			'sort'    => 'ASC'
		],
		'members' => [
			'display' => __('Members'),
			'sort'    => 'ASC'
		],
		'description' => [
			'display' => __('Description'),
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
		'enabled' => [
			'display' => __('Enabled'),
			'sort'    => 'ASC'
		]
	];

	$nav = html_nav_bar('user_group_admin.php?filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, cacti_sizeof($display_text) + 1, __('Groups'), 'page', 'main');

	form_start('user_group_admin.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'), false);

	if (cacti_sizeof($group_list)) {
		foreach ($group_list as $group) {
			if ($group['enabled'] == 'on') {
				$enabled = __('Yes');
			} else {
				$enabled = __('No');
			}

			form_alternate_row('line' . $group['id'], true);

			form_selectable_cell(filter_value($group['name'], grv('filter'), 'user_group_admin.php?action=edit&tab=general&id=' . $group['id']), $group['id']);
			form_selectable_cell(($group['members'] > 0 ? number_format_i18n($group['members'], 0) : 'None'), $group['id']);
			form_selectable_cell(filter_value($group['description'], grv('filter')), $group['id']);
			form_selectable_cell(($group['policy_graphs'] == 1 ? __('ALLOW') : __('DENY')), $group['id']);
			form_selectable_cell(($group['policy_hosts'] == 1 ? __('ALLOW') : __('DENY')), $group['id']);
			form_selectable_cell(($group['policy_graph_templates'] == 1 ? __('ALLOW') : __('DENY')), $group['id']);
			form_selectable_cell($enabled, $group['id']);

			form_checkbox_cell($group['name'], $group['id']);

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No User Groups Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($group_list)) {
		print $nav;
	}

	draw_actions_dropdown($group_actions);

	form_end();
}

function create_uggraphs_filter() : array {
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
	$filters = create_uggraphs_filter();

	// create the page filter
	$pageFilter = new CactiTableFilter(__('Graph Permissions %s', $header_label), 'user_group_admin.php?action=edit&tab=permsg&id=' . grv('id'), 'form_template', 'sess_ua_d');

	$pageFilter->rows_label       = __('Graphs');
	$pageFilter->has_associated   = true;
	$pageFilter->associated_label = __('Only Show Exceptions');

	$pageFilter->set_filter_array($filters);
	$pageFilter->render();

	form_hidden_box('action', 'edit', '');
	form_hidden_box('tab', 'permsg', '');
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
	$pageFilter = new CactiTableFilter(__('Device Permissions %s', $header_label), 'user_group_admin.php?action=edit&tab=permsd&id=' . grv('id'), 'form_template', 'sess_ug_d');

	$pageFilter->rows_label       = __('Devices');
	$pageFilter->has_associated   = true;
	$pageFilter->associated_label = __('Only Show Exceptions');

	$pageFilter->set_filter_array($filters);
	$pageFilter->render();

	form_hidden_box('action', 'edit', '');
	form_hidden_box('tab', 'permste', '');
	form_hidden_box('id', grv('id'), '');
}

function template_filter(string $header_label) : void {
	// create the page filter
	$pageFilter = new CactiTableFilter(__('Template Permissions %s', $header_label), 'user_group_admin.php?action=edit&tab=permste&id=' . grv('id'), 'form_template', 'sess_ug_te');

	$pageFilter->rows_label       = __('Templatee');
	$pageFilter->has_associated   = true;
	$pageFilter->associated_label = __('Only Show Exceptions');
	$pageFilter->render();

	form_hidden_box('action', 'edit', '');
	form_hidden_box('tab', 'permste', '');
	form_hidden_box('id', grv('id'), '');
}

function tree_filter(string $header_label) : void {
	// create the page filter
	$pageFilter = new CactiTableFilter(__('Tree Permissions %s', $header_label), 'user_group_admin.php?action=edit&tab=permstr&id=' . grv('id'), 'form_tree', 'sess_ug_tr');

	$pageFilter->rows_label       = __('Trees');
	$pageFilter->has_associated   = true;
	$pageFilter->associated_label = __('Only Show Exceptions');
	$pageFilter->render();

	form_hidden_box('action', 'edit', '');
	form_hidden_box('tab', 'permstr', '');
	form_hidden_box('id', grv('id'), '');
}

function member_filter(string $header_label) : void {
	// create the page filter
	$pageFilter = new CactiTableFilter($header_label, 'user_group_admin.php?action=edit&tab=members&id=' . grv('id'), 'forms', 'sess_ug_g');

	$pageFilter->rows_label       = __('Users');
	$pageFilter->has_associated   = true;
	$pageFilter->associated_label = __('Show Members');
	$pageFilter->render();

	form_hidden_box('action', 'edit', '');
	form_hidden_box('tab', 'members', '');
	form_hidden_box('id', grv('id'), '');
}
