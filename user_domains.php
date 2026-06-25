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
require_once(CACTI_PATH_LIBRARY . '/poller.php');
require_once(CACTI_PATH_LIBRARY . '/utility.php');

$actions = [
	1 => __('Delete'),
	2 => __('Disable'),
	3 => __('Enable'),
	4 => __('Default')
];

// set default action
set_default_action();

switch (grv('action')) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'edit':
		top_header();

		domain_edit();

		bottom_footer();

		break;
	default:
		top_header();

		domains();

		bottom_footer();

		break;
}

function form_save() : void {
	global $registered_cacti_names;

	if (isrv('save_component_domain_ldap')) {
		// ================= input validation =================
		gfrv('domain_id');
		gfrv('type');
		gfrv('user_id');
		// ====================================================

		$save['domain_id']   = gnrv('domain_id');
		$save['type']        = gnrv('type');
		$save['user_id']     = gnrv('user_id');
		$save['domain_name'] = form_input_validate(gnrv('domain_name'), 'domain_name', '', false, 3);
		$save['enabled']     = (isrv('enabled') ? form_input_validate(gnrv('enabled'), 'enabled', '', true,  3) : '');
		$save['debug']       = (isrv('debug') ? form_input_validate(gnrv('debug'), 'debug', '', true,  3) : '');

		if (is_error_message() === false) {
			$domain_id = sql_save($save, 'user_domains', 'domain_id');

			if ($domain_id) {
				// Disable template user from logging in
				db_execute_prepared('UPDATE user_auth
					SET enabled=""
					WHERE id = ?', [$save['user_id']]);

				raise_message(1);
			} else {
				raise_message(2);
			}

			if (is_error_message() === false) {
				// ================= input validation =================
				gfrv('domain_id');
				gfrv('port');
				gfrv('port_ssl');
				gfrv('proto_version');
				gfrv('encryption');
				gfrv('referrals');
				gfrv('mode');
				gfrv('group_member_type');
				// ====================================================

				$save                        = [];
				$save['domain_id']           = $domain_id;
				$save['server']              = form_input_validate(gnrv('server'), 'server', '', false, 3);
				$save['port']                = gnrv('port');
				$save['port_ssl']            = gnrv('port_ssl');
				$save['proto_version']       = gnrv('proto_version');
				$save['network_timeout']     = gnrv('network_timeout');
				$save['bind_timeout']        = gnrv('bind_timeout');
				$save['encryption']          = gnrv('encryption');
				$save['tls_certificate']     = gnrv('tls_certificate');
				$save['referrals']           = gnrv('referrals');
				$save['mode']                = gnrv('mode');
				$save['group_member_type']   = gnrv('group_member_type');
				$save['dn']                  = form_input_validate(gnrv('dn'),                'dn',              '', true, 3);
				$save['group_require']       = isrv('group_require') ? 'on' : '';
				$save['group_dn']            = form_input_validate(gnrv('group_dn'),          'group_dn',        '', true, 3);
				$save['group_attrib']        = form_input_validate(gnrv('group_attrib'),      'group_attrib',    '', true, 3);
				$save['search_base']         = form_input_validate(gnrv('search_base'),       'search_base',     '', true, 3);
				$save['search_filter']       = form_input_validate(gnrv('search_filter'),     'search_filter',   '', true, 3);
				$save['specific_dn']         = form_input_validate(gnrv('specific_dn'),         'specific_dn',       '', true, 3);
				$save['specific_password']   = form_input_validate(gnrv('specific_password'),   'specific_password', '', true, 3);
				$save['cn_full_name']        = gnrv('cn_full_name');
				$save['cn_email']            = gnrv('cn_email');

				if (is_error_message() === false) {
					$insert_id = sql_save($save, 'user_domains_ldap', 'domain_id', false);

					if ($insert_id) {
						raise_message(1);
					} else {
						raise_message(2);
					}
				}
			}
		}
	} elseif (isrv('save_component_domain')) {
		// ================= input validation =================
		gfrv('domain_id');
		gfrv('type');
		gfrv('user_id');
		// ====================================================

		$save['domain_id']   = gnrv('domain_id');
		$save['domain_name'] = form_input_validate(gnrv('domain_name'), 'domain_name', '', false, 3);
		$save['type']        = gnrv('type');
		$save['user_id']     = gnrv('user_id');
		$save['enabled']     = (isrv('enabled') ? form_input_validate(gnrv('enabled'), 'enabled', '', true,  3) : '');

		if (is_error_message() === false) {
			$domain_id = sql_save($save, 'user_domains', 'domain_id');

			if ($domain_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}
	}

	header('Location: user_domains.php?action=edit&domain_id=' . (empty($domain_id) ? gnrv('domain_id') : $domain_id));
}

function form_actions() : void {
	global $actions;

	// if we are to save this form, instead of display it
	if (isrv('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

		if ($selected_items != false) {
			if (gnrv('drp_action') == '1') { // delete
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					domain_remove($selected_items[$i]);
				}
			} elseif (gnrv('drp_action') == '2') { // disable
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					domain_disable($selected_items[$i]);
				}
			} elseif (gnrv('drp_action') == '3') { // enable
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					domain_enable($selected_items[$i]);
				}
			} elseif (gnrv('drp_action') == '4') { // default
				if (cacti_sizeof($selected_items) > 1) {
					// error message
				} else {
					for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
						domain_default($selected_items[$i]);
					}
				}
			}
		}

		header('Location: user_domains.php');

		exit;
	} else {
		$ilist  = '';
		$iarray = [];

		// loop through each of the data queries and process them
		foreach ($_POST as $var => $val) {
			if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
				// ================= input validation =================
				input_validate_input_number($matches[1], 'chk[1]');
				// ====================================================

				$ilist .= '<li>' . htmle(db_fetch_cell_prepared('SELECT domain_name FROM user_domains WHERE domain_id = ?', [$matches[1]])) . '</li>';
				$iarray[] = $matches[1];
			}
		}

		$form_data = [
			'general' => [
				'page'       => 'user_domains.php',
				'actions'    => $actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			],
			'options' => [
				1 => [
					'smessage' => __('Click \'Continue\' to Delete the following User Domain.'),
					'pmessage' => __('Click \'Continue\' to Delete following User Domains.'),
					'scont'    => __('Delete User Domain'),
					'pcont'    => __('Delete User Domains')
				],
				2 => [
					'smessage' => __('Click \'Continue\' to Disable the following User Domain.'),
					'pmessage' => __('Click \'Continue\' to Disable following User Domains.'),
					'scont'    => __('Disable User Domain'),
					'pcont'    => __('Disable User Domains')
				],
				3 => [
					'smessage' => __('Click \'Continue\' to Enable the following User Domain.'),
					'pmessage' => __('Click \'Continue\' to Enable following User Domains.'),
					'scont'    => __('Enable User Domain'),
					'pcont'    => __('Enable User Domains')
				],
				4 => [
					'message' => __('Click \'Continue\' to make the following the following User Domain the default one.'),
					'cont'    => __('Make Selected Domain Default')
				]
			]
		];

		form_continue_confirmation($form_data);
	}
}

function domain_remove(int $domain_id) : void {
	db_execute_prepared('DELETE FROM user_domains WHERE domain_id = ?', [$domain_id]);
	db_execute_prepared('DELETE FROM user_domains_ldap WHERE domain_id = ?', [$domain_id]);
}

function domain_disable(int $domain_id) : void {
	db_execute_prepared('UPDATE user_domains SET enabled = "" WHERE domain_id = ?', [$domain_id]);
}

function domain_enable(int $domain_id) : void {
	db_execute_prepared('UPDATE user_domains SET enabled = "on" WHERE domain_id = ?', [$domain_id]);
}

function domain_default(int $domain_id) : void {
	db_execute('UPDATE user_domains SET defdomain = 0');
	db_execute_prepared('UPDATE user_domains SET defdomain = 1 WHERE domain_id = ?', [$domain_id]);
}

function domain_edit() : void {
	global $ldap_versions, $ldap_encryption, $ldap_modes, $domain_types, $ldap_tls_cert_req;

	// ================= input validation =================
	gfrv('domain_id');
	// ====================================================

	if (!ierv('domain_id')) {
		$domain       = db_fetch_row_prepared('SELECT * FROM user_domains WHERE domain_id = ?', [grv('domain_id')]);
		$header_label = __esc('User Domain [edit: %s]', $domain['domain_name']);
	} else {
		$header_label = __('User Domain [new]');
	}

	// file: data_input.php, action: edit
	$fields_domain_edit = [
		'domain_name' => [
			'method'        => 'textbox',
			'friendly_name' => __('Name'),
			'description'   => __('Enter a meaningful name for this domain. This will be the name that appears in the Login Realm during login.'),
			'value'         => '|arg1:domain_name|',
			'max_length'    => '255',
		],
		'type' => [
			'method'        => 'drop_array',
			'friendly_name' => __('Domains Type'),
			'description'   => __('Choose what type of domain this is.'),
			'value'         => '|arg1:type|',
			'array'         => $domain_types,
			'default'       => '2'
		],
		'user_id' => [
			'friendly_name' => __('User Template'),
			'description'   => __('The name of the user that Cacti will use as a template for new user accounts.'),
			'method'        => 'drop_sql',
			'value'         => '|arg1:user_id|',
			'none_value'    => __('No User'),
			'sql'           => 'SELECT id AS id, username AS name FROM user_auth WHERE realm=0 ORDER BY username',
			'default'       => '0'
		],
		'enabled' => [
			'method'        => 'checkbox',
			'friendly_name' => __('Enabled'),
			'description'   => __('If this checkbox is checked, users will be able to login using this domain.'),
			'value'         => '|arg1:enabled|',
			'default'       => '',
		],
		'debug' => [
			'method'        => 'checkbox',
			'friendly_name' => __('Debug'),
			'description'   => __('If testing the LDAP connection and your desire more details in your Cacti log, check this box.'),
			'value'         => '|arg1:debug|',
			'default'       => '',
		],
		'domain_id' => [
			'method' => 'hidden_zero',
			'value'  => '|arg1:domain_id|'
		],
		'save_component_domain' => [
			'method' => 'hidden',
			'value'  => '1'
		]
	];

	$fields_domain_ldap_edit = [
		'server' => [
			'friendly_name' => __('Server(s)'),
			'description'   => __('A space delimited list of DNS hostnames or IP address of for valid LDAP servers.  Cacti will attempt to use the LDAP servers from left to right to authenticate a user.'),
			'method'        => 'textbox',
			'value'         => '|arg1:server|',
			'default'       => '',
			'size'          => 80,
			'max_length'    => '255'
		],
		'port' => [
			'friendly_name' => __('Port Standard'),
			'description'   => __('TCP/UDP port for Non SSL communications.'),
			'method'        => 'textbox',
			'max_length'    => '5',
			'value'         => '|arg1:port|',
			'default'       => 389,
			'size'          => '5'
		],
		'port_ssl' => [
			'friendly_name' => __('Port SSL'),
			'description'   => __('TCP/UDP port for SSL communications.'),
			'method'        => 'textbox',
			'max_length'    => '5',
			'value'         => '|arg1:port_ssl|',
			'default'       => 636,
			'size'          => '5'
		],
		'proto_version' => [
			'friendly_name' => __('Protocol Version'),
			'description'   => __('Protocol Version that the server supports.'),
			'method'        => 'drop_array',
			'value'         => '|arg1:proto_version|',
			'array'         => $ldap_versions
		],
		'network_timeout' => [
			'friendly_name' => __('Network Timeout'),
			'description'   => __('The timeout to connect to the LDAP server in seconds.'),
			'method'        => 'textbox',
			'max_length'    => '5',
			'value'         => '|arg1:network_timeout|',
			'default'       => 2,
			'size'          => '5'
		],
		'bind_timeout' => [
			'friendly_name' => __('Bind Timeout'),
			'description'   => __('The timeout to bind to the LDAP service in seconds.'),
			'method'        => 'textbox',
			'max_length'    => '5',
			'value'         => '|arg1:bind_timeout|',
			'default'       => 2,
			'size'          => '5'
		],
		'encryption' => [
			'friendly_name' => __('Encryption'),
			'description'   => __('Encryption that the server supports. TLS is only supported by Protocol Version 3.'),
			'method'        => 'drop_array',
			'value'         => '|arg1:encryption|',
			'array'         => $ldap_encryption
		],
		'tls_certificate' => [
			'friendly_name' => __('TLS Certificate Requirements'),
			'description'   => __('Should LDAP verify TLS Certificates when received by the Client.'),
			'method'        => 'drop_array',
			'value'         => '|arg1:tls_certificate|',
			'default'       => LDAP_OPT_X_TLS_NEVER,
			'array'         => $ldap_tls_cert_req
		],
		'referrals' => [
			'friendly_name' => __('Referrals'),
			'description'   => __('Enable or Disable LDAP referrals.  If disabled, it may increase the speed of searches.'),
			'method'        => 'drop_array',
			'value'         => '|arg1:referrals|',
			'array'         => ['0' => __('Disabled'), '1' => __('Enable')]
		],
		'mode' => [
			'friendly_name' => __('Mode'),
			'description'   => __('Mode which cacti will attempt to authenticate against the LDAP server.<blockquote><i>No Searching</i> - No Distinguished Name (DN) searching occurs, just attempt to bind with the provided Distinguished Name (DN) format.<br><br><i>Anonymous Searching</i> - Attempts to search for username against LDAP directory via anonymous binding to locate the users Distinguished Name (DN).<br><br><i>Specific Searching</i> - Attempts search for username against LDAP directory via Specific Distinguished Name (DN) and Specific Password for binding to locate the users Distinguished Name (DN).'),
			'method'        => 'drop_array',
			'value'         => '|arg1:mode|',
			'array'         => $ldap_modes
		],
		'dn' => [
			'friendly_name' => __('Distinguished Name (DN)'),
			'description'   => __('The "Distinguished Name" syntax, applicable for both OpenLDAP and Windows AD configurations, offers flexibility in defining user identity. For OpenLDAP, the format follows this structure: <i>"uid=&lt;username&gt;,ou=people,dc=domain,dc=local"</i>. Windows AD provides an alternative syntax: <i>"&lt;username&gt;@win2kdomain.local"</i>, commonly known as "userPrincipalName (UPN)". In this context, "&lt;username&gt;" represents the specific username provided during the login prompt. This is particularly pertinent when operating in "No Searching" mode, or "Require Group Membership" enabled.'),
			'method'        => 'textbox',
			'value'         => '|arg1:dn|',
			'max_length'    => '255',
			'size'          => 100
			],
		'group_require' => [
			'friendly_name' => __('Require Group Membership'),
			'description'   => __('Require user to be member of group to authenticate. Group settings must be set for this to work, enabling without proper group settings will cause authentication failure.'),
			'value'         => '|arg1:group_require|',
			'method'        => 'checkbox'
		],
		'group_header' => [
			'friendly_name' => __('LDAP Group Settings'),
			'method'        => 'spacer'
		],
		'group_dn' => [
			'friendly_name' => __('Group Distinguished Name (DN)'),
			'description'   => __('Distinguished Name of the group that user must have membership.'),
			'method'        => 'textbox',
			'value'         => '|arg1:group_dn|',
			'max_length'    => '255'
		],
		'group_attrib' => [
			'friendly_name' => __('Group Member Attribute'),
			'description'   => __('This refers to the specific attribute within the LDAP directory that holds the usernames of group members. It is crucial to ensure that the attribute value aligns with the configuration specified in the "Distinguished Name" or that the actual attribute value is searchable using the settings outlined in the "Distinguished Name".'),
			'method'        => 'textbox',
			'value'         => '|arg1:group_attrib|',
			'max_length'    => '255'
			],
		'group_member_type' => [
			'friendly_name' => __('Group Member Type'),
			'description'   => __('Defines if users use full Distinguished Name or just Username in the defined Group Member Attribute.'),
			'method'        => 'drop_array',
			'value'         => '|arg1:group_member_type|',
			'array'         => [1 => 'Distinguished Name', 2 => 'Username']
		],
		'search_base_header' => [
			'friendly_name' => __('LDAP Specific Search Settings'),
			'method'        => 'spacer'
		],
		'search_base' => [
			'friendly_name' => __('Search Base'),
			'description'   => __('Search base for searching the LDAP directory, such as <i>"dc=win2kdomain,dc=local"</i> or <i>"ou=people,dc=domain,dc=local"</i>.'),
			'method'        => 'textbox',
			'value'         => '|arg1:search_base|',
			'max_length'    => '255'
		],
		'search_filter' => [
			'friendly_name' => __('Search Filter'),
			'description'   => __('Search filter to use to locate the user in the LDAP directory, such as for windows: <i>"(&amp;(objectclass=user)(objectcategory=user)(userPrincipalName=&lt;username&gt;*))"</i> or for OpenLDAP: <i>"(&(objectClass=account)(uid=&lt;username&gt))"</i>.  "&lt;username&gt" is replaced with the username that was supplied at the login prompt.'),
			'method'        => 'textbox',
			'value'         => '|arg1:search_filter|',
			'max_length'    => '512'
		],
		'specific_dn' => [
			'friendly_name' => __('Search Distinguished Name (DN)'),
			'description'   => __('Distinguished Name for Specific Searching binding to the LDAP directory.'),
			'method'        => 'textbox',
			'value'         => '|arg1:specific_dn|',
			'max_length'    => '255'
		],
		'specific_password' => [
			'friendly_name' => __('Search Password'),
			'description'   => __('Password for Specific Searching binding to the LDAP directory.'),
			'method'        => 'textbox_password',
			'value'         => '|arg1:specific_password|',
			'max_length'    => '255'
		],
		'cn_header' => [
			'friendly_name' => __('LDAP CN Settings'),
			'method'        => 'spacer'
		],
		'cn_full_name' => [
			'friendly_name' => __('Full Name'),
			'description'   => __('Field that will replace the Full Name when creating a new user, taken from LDAP. (on windows: displayname) '),
			'method'        => 'textbox',
			'value'         => '|arg1:cn_full_name|',
			'max_length'    => '255'
		],
		'cn_email' => [
			'friendly_name' => __('eMail'),
			'description'   => __('Field that will replace the email taken from LDAP. (on windows: mail) '),
			'method'        => 'textbox',
			'value'         => '|arg1:cn_email|',
			'max_length'    => '255'
		],
		'save_component_domain_ldap' => [
			'method' => 'hidden',
			'value'  => '1'
		]
	];

	form_start('user_domains.php');

	html_start_box($header_label, '100%', true, 3, 'center', '');

	draw_edit_form([
		'config' => [],
		'fields' => inject_form_variables($fields_domain_edit, (isset($domain) ? $domain : []))
	]);

	html_end_box(true, true);

	if (!ierv('domain_id')) {
		$domain = db_fetch_row_prepared('SELECT * FROM user_domains_ldap WHERE domain_id = ?', [grv('domain_id')]);

		html_start_box(__('Domain Properties'), '100%', true, 3, 'center', '');

		draw_edit_form([
			'config' => [],
			'fields' => inject_form_variables($fields_domain_ldap_edit, $domain)
		]);

		html_end_box(true, true);
	}

	?>
	<script type='text/javascript'>
		function initGroupMember() {
			toggleFields({
				group_header: $('#group_require').is(':checked'),
				group_dn: $('#group_require').is(':checked'),
				group_attrib: $('#group_require').is(':checked'),
				group_member_type: $('#group_require').is(':checked'),
			});
		}

		function initSearch() {
			var mode = $('#mode').val();
			toggleFields({
				search_base_header: mode > 0,
				search_base: mode > 0,
				search_filter: mode > 0,
				specific_dn: mode > 1,
				specific_password: mode > 1,
				cn_header: mode > 1,
				cn_full_name: mode > 1,
				cn_email: mode > 1,
			});
		}

		$(function() {
			initSearch();
			initGroupMember();

			$('#mode').change(function() {
				initSearch();
			});

			$('#group_require').change(function() {
				initGroupMember();
			});
		});
	</script>
<?php

		form_save_button('user_domains.php', 'return', 'domain_id');
}

function domains() : void {
	global $domain_types, $actions, $item_rows;

	// create the page filter
	$pageFilter = new CactiTableFilter(__('User Domains'), 'user_domains.php', 'form_domain', 'sess_domain', 'user_domains.php?action=edit');

	$pageFilter->rows_label = __('Domains');
	$pageFilter->set_sort_array('domain_name', 'ASC');
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
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . '(domain_name LIKE ? OR type LIKE ?)';

		$sql_params[] = '%' . grv('filter') . '%';
		$sql_params[] = '%' . grv('filter') . '%';
	}

	$total_rows = db_fetch_cell_prepared("SELECT COUNT(*)
		FROM user_domains
		$sql_where",
		$sql_params);

	$domains = db_fetch_assoc_prepared("SELECT *
		FROM user_domains
		$sql_where
		ORDER BY " . sanitize_sql_column(grv('sort_column'), 'domain_name') . ' ' . (strtoupper(grv('sort_direction')) === 'DESC' ? 'DESC' : 'ASC') . '
		LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows,
		$sql_params);

	$display_text = [
		'domain_name'  => [
			'display' => __('Domain Name'),
			'sort'    => 'ASC'
		],
		'type'         => [
			'display' => __('Domain Type'),
			'sort'    => 'ASC'
		],
		'defdomain'    => [
			'display' => __('Default'),
			'sort'    => 'ASC'
		],
		'user_id'      => [
			'display' => __('Effective User'),
			'sort'    => 'ASC'
		],
		'cn_full_name' => [
			'display' => __('CN FullName'),
			'sort'    => 'ASC'
		],
		'cn_email'     => [
			'display' => __('CN eMail'),
			'sort'    => 'ASC'
		],
		'enabled'      => [
			'display' => __('Enabled'),
			'sort'    => 'ASC'
		]
	];

	$nav = html_nav_bar('user_user_domains.php?filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 8, __('User Domains'), 'page', 'main');

	form_start('user_domains.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'), false);

	$i = 0;

	if (cacti_sizeof($domains)) {
		foreach ($domains as $domain) {
			// hide system types
			form_alternate_row('line' . $domain['domain_id'], true);

			$effective_id = db_fetch_cell_prepared('SELECT username
				FROM user_auth
				WHERE id = ?',
				[$domain['user_id']]);

			$full_name_cn = db_fetch_cell_prepared('SELECT cn_full_name
				FROM user_domains_ldap
				WHERE domain_id = ?',
				[$domain['domain_id']]);

			$email_cn = db_fetch_cell_prepared('SELECT cn_email
				FROM user_domains_ldap
				WHERE domain_id = ?',
				[$domain['domain_id']]);

			form_selectable_cell(filter_value($domain['domain_name'], grv('filter'), 'user_domains.php?action=edit&domain_id=' . $domain['domain_id']), $domain['domain_id']);
			form_selectable_cell($domain_types[$domain['type']], $domain['domain_id']);
			form_selectable_cell(($domain['defdomain'] == '0' ? '--' : __('Yes')), $domain['domain_id']);
			form_selectable_ecell(($domain['user_id'] == '0' ? __('None Selected') : $effective_id), $domain['domain_id']);
			form_selectable_ecell($full_name_cn, $domain['domain_id']);
			form_selectable_ecell($email_cn, $domain['domain_id']);
			form_selectable_cell($domain['enabled'] == 'on' ? __('Yes') : __('No'), $domain['domain_id']);
			form_checkbox_cell($domain['domain_name'], $domain['domain_id']);

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No User Domains Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($domains)) {
		print $nav;
	}

	// draw the dropdown containing a list of available actions for this form
	draw_actions_dropdown($actions);

	form_end();
}
