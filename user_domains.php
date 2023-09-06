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
include_once('./lib/poller.php');
include_once('./lib/utility.php');

$actions = array(
	1 => __('Delete'),
	2 => __('Disable'),
	3 => __('Enable'),
	4 => __('Default')
);

/* set default action */
set_default_action();

switch (get_request_var('action')) {
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

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	global $registered_cacti_names;

	if (isset_request_var('save_component_domain_ldap')) {
		/* ================= input validation ================= */
		get_filter_request_var('domain_id');
		get_filter_request_var('type');
		get_filter_request_var('user_id');
		/* ==================================================== */

		$save['domain_id']   = get_nfilter_request_var('domain_id');
		$save['type']        = get_nfilter_request_var('type');
		$save['user_id']     = get_nfilter_request_var('user_id');
		$save['domain_name'] = form_input_validate(get_nfilter_request_var('domain_name'), 'domain_name', '', false, 3);
		$save['enabled']     = (isset_request_var('enabled') ? form_input_validate(get_nfilter_request_var('enabled'), 'enabled', '', true,  3):'');

		if (!is_error_message()) {
			$domain_id = sql_save($save, 'user_domains', 'domain_id');

			if ($domain_id) {
				// Disable template user from logging in
				db_execute_prepared('UPDATE user_auth
					SET enabled=""
					WHERE id = ?', array($save['user_id']));

				raise_message(1);
			} else {
				raise_message(2);
			}

			if (!is_error_message()) {
				/* ================= input validation ================= */
				get_filter_request_var('domain_id');
				get_filter_request_var('port');
				get_filter_request_var('port_ssl');
				get_filter_request_var('proto_version');
				get_filter_request_var('encryption');
				get_filter_request_var('referrals');
				get_filter_request_var('mode');
				get_filter_request_var('group_member_type');
				/* ==================================================== */

				$save                      = array();
				$save['domain_id']         = $domain_id;
				$save['server']            = form_input_validate(get_nfilter_request_var('server'), 'server', '', false, 3);
				$save['port']              = get_nfilter_request_var('port');
				$save['port_ssl']          = get_nfilter_request_var('port_ssl');
				$save['proto_version']     = get_nfilter_request_var('proto_version');
				$save['encryption']        = get_nfilter_request_var('encryption');
				$save['referrals']         = get_nfilter_request_var('referrals');
				$save['mode']              = get_nfilter_request_var('mode');
				$save['group_member_type'] = get_nfilter_request_var('group_member_type');
				$save['dn']                = form_input_validate(get_nfilter_request_var('dn'),                'dn',              '', true, 3);
				$save['group_require']     = isset_request_var('group_require') ? 'on':'';
				$save['group_dn']          = form_input_validate(get_nfilter_request_var('group_dn'),          'group_dn',        '', true, 3);
				$save['group_attrib']      = form_input_validate(get_nfilter_request_var('group_attrib'),      'group_attrib',    '', true, 3);
				$save['search_base']       = form_input_validate(get_nfilter_request_var('search_base'),       'search_base',     '', true, 3);
				$save['search_filter']     = form_input_validate(get_nfilter_request_var('search_filter'),     'search_filter',   '', true, 3);
				$save['specific_dn']         = form_input_validate(get_nfilter_request_var('specific_dn'),         'specific_dn',       '', true, 3);
				$save['specific_password']   = form_input_validate(get_nfilter_request_var('specific_password'),   'specific_password', '', true, 3);
                                $save['cn_full_name']        = get_nfilter_request_var('cn_full_name');
                                $save['cn_email']            = get_nfilter_request_var('cn_email');

				if (!is_error_message()) {
					$insert_id = sql_save($save, 'user_domains_ldap', 'domain_id', false);

					if ($insert_id) {
						raise_message(1);
					} else {
						raise_message(2);
					}
				}
			}
		}
	} elseif (isset_request_var('save_component_domain')) {
		/* ================= input validation ================= */
		get_filter_request_var('domain_id');
		get_filter_request_var('type');
		get_filter_request_var('user_id');
		/* ==================================================== */

		$save['domain_id']   = get_nfilter_request_var('domain_id');
		$save['domain_name'] = form_input_validate(get_nfilter_request_var('domain_name'), 'domain_name', '', false, 3);
		$save['type']        = get_nfilter_request_var('type');
		$save['user_id']     = get_nfilter_request_var('user_id');
		$save['enabled']     = (isset_request_var('enabled') ? form_input_validate(get_nfilter_request_var('enabled'), 'enabled', '', true,  3):'');

		if (!is_error_message()) {
			$domain_id = sql_save($save, 'user_domains', 'domain_id');

			if ($domain_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}
	}

	header('Location: user_domains.php?header=false&action=edit&domain_id=' . (empty($domain_id) ? get_nfilter_request_var('domain_id') : $domain_id));
}

function form_actions() {
	global $actions;

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') { // delete
				for ($i=0;($i<cacti_count($selected_items));$i++) {
					domain_remove($selected_items[$i]);
				}
			} elseif (get_nfilter_request_var('drp_action') == '2') { // disable
				for ($i=0;($i<cacti_count($selected_items));$i++) {
					domain_disable($selected_items[$i]);
				}
			} elseif (get_nfilter_request_var('drp_action') == '3') { // enable
				for ($i=0;($i<cacti_count($selected_items));$i++) {
					domain_enable($selected_items[$i]);
				}
			} elseif (get_nfilter_request_var('drp_action') == '4') { // default
				if (cacti_sizeof($selected_items) > 1) {
					/* error message */
				} else {
					for ($i=0;($i<cacti_count($selected_items));$i++) {
						domain_default($selected_items[$i]);
					}
				}
			}
		}

		header('Location: user_domains.php?header=false');
		exit;
	}

	/* setup some variables */
	$d_list = '';
	$d_array = array();

	/* loop through each of the data queries and process them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$d_list .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT domain_name FROM user_domains WHERE domain_id = ?', array($matches[1]))) . '</li>';
			$d_array[] = $matches[1];
		}
	}

	top_header();

	form_start('user_domains.php');

	html_start_box($actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (isset($d_array) && cacti_sizeof($d_array)) {
		if (get_nfilter_request_var('drp_action') == '1') { // delete
			print "<tr>
				<td class='textArea'>
					<p>" . __n('Click \'Continue\' to delete the following User Domain.', 'Click \'Continue\' to delete following User Domains.', cacti_sizeof($d_array)) . "</p>
					<div class='itemlist'><ul>$d_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Delete User Domain', 'Delete User Domains', cacti_sizeof($d_array)) . "'>";
		}else if (get_nfilter_request_var('drp_action') == '2') { // disable
			print "<tr>
				<td class='textArea'>
					<p>" . __n('Click \'Continue\' to disable the following User Domain.', 'Click \'Continue\' to disable following User Domains.', cacti_sizeof($d_array)) . "</p>
					<div class='itemlist'><ul>$d_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Disable User Domain', 'Disable User Domains', cacti_sizeof($d_array)) . "'>";
		}else if (get_nfilter_request_var('drp_action') == '3') { // enable
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to enable the following User Domain.', 'Click \'Continue\' to enable following User Domains.', cacti_sizeof($d_array)) . "</p>
					<div class='itemlist'><ul>$d_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __n('Enabled User Domain', 'Enable User Domains', cacti_sizeof($d_array)) . "'>";
		}else if (get_nfilter_request_var('drp_action') == '4') { // default
			print "<tr>
				<td class='textArea'>
					<p>" . __('Click \'Continue\' to make the following the following User Domain the default one.') . "</p>
					<div class='itemlist'><ul>$d_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='" . __esc('Make Selected Domain Default') . "'>";
		}
	} else {
		raise_message(40);
		header('Location: user_domains.php?header=false');
		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($d_array) ? serialize($d_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

/* -----------------------
    Domain Functions
   ----------------------- */

function domain_remove($domain_id) {
	db_execute_prepared('DELETE FROM user_domains WHERE domain_id = ?', array($domain_id));
	db_execute_prepared('DELETE FROM user_domains_ldap WHERE domain_id = ?', array($domain_id));
}

function domain_disable($domain_id) {
	db_execute_prepared('UPDATE user_domains SET enabled = "" WHERE domain_id = ?', array($domain_id));
}

function domain_enable($domain_id) {
	db_execute_prepared('UPDATE user_domains SET enabled = "on" WHERE domain_id = ?', array($domain_id));
}

function domain_default($domain_id) {
	db_execute('UPDATE user_domains SET defdomain = 0');
	db_execute_prepared('UPDATE user_domains SET defdomain = 1 WHERE domain_id = ?', array($domain_id));
}

function domain_edit() {
	global $ldap_versions, $ldap_encryption, $ldap_modes, $domain_types;

	/* ================= input validation ================= */
	get_filter_request_var('domain_id');
	/* ==================================================== */

	if (!isempty_request_var('domain_id')) {
		$domain = db_fetch_row_prepared('SELECT * FROM user_domains WHERE domain_id = ?', array(get_request_var('domain_id')));
		$header_label = __esc('User Domain [edit: %s]', $domain['domain_name']);
	} else {
		$header_label = __('User Domain [new]');
	}

	/* file: data_input.php, action: edit */
	$fields_domain_edit = array(
		'domain_name' => array(
			'method' => 'textbox',
			'friendly_name' => __('Name'),
			'description' => __('Enter a meaningful name for this domain. This will be the name that appears in the Login Realm during login.'),
			'value' => '|arg1:domain_name|',
			'max_length' => '255',
			),
		'type' => array(
			'method' => 'drop_array',
			'friendly_name' => __('Domains Type'),
			'description' => __('Choose what type of domain this is.'),
			'value' => '|arg1:type|',
			'array' => $domain_types,
			'default' => '2'
			),
		'user_id' => array(
			'friendly_name' => __('User Template'),
			'description' => __('The name of the user that Cacti will use as a template for new user accounts.'),
			'method' => 'drop_sql',
			'value' => '|arg1:user_id|',
			'none_value' => __('No User'),
			'sql' => 'SELECT id AS id, username AS name FROM user_auth WHERE realm=0 ORDER BY username',
			'default' => '0'
			),
		'enabled' => array(
			'method' => 'checkbox',
			'friendly_name' => __('Enabled'),
			'description' => __('If this checkbox is checked, users will be able to login using this domain.'),
			'value' => '|arg1:enabled|',
			'default' => '',
			),
		'domain_id' => array(
			'method' => 'hidden_zero',
			'value' => '|arg1:domain_id|'
			),
		'save_component_domain' => array(
			'method' => 'hidden',
			'value' => '1'
			)
		);

	$fields_domain_ldap_edit = array(
		'server' => array(
			'friendly_name' => __('Server(s)'),
			'description' => __('A space delimited list of DNS hostnames or IP address of for valid LDAP servers.  Cacti will attempt to use the LDAP servers from left to right to authenticate a user.'),
			'method' => 'textbox',
			'value' => '|arg1:server|',
			'default' => read_config_option('ldap_server'),
			'size' => 80,
			'max_length' => '255'
			),
		'port' => array(
			'friendly_name' => __('Port Standard'),
			'description' => __('TCP/UDP port for Non SSL communications.'),
			'method' => 'textbox',
			'max_length' => '5',
			'value' => '|arg1:port|',
			'default' => read_config_option('ldap_port'),
			'size' => '5'
			),
		'port_ssl' => array(
			'friendly_name' => __('Port SSL'),
			'description' => __('TCP/UDP port for SSL communications.'),
			'method' => 'textbox',
			'max_length' => '5',
			'value' => '|arg1:port_ssl|',
			'default' => read_config_option('ldap_port_ssl'),
			'size' => '5'
			),
		'proto_version' => array(
			'friendly_name' => __('Protocol Version'),
			'description' => __('Protocol Version that the server supports.'),
			'method' => 'drop_array',
			'value' => '|arg1:proto_version|',
			'array' => $ldap_versions
			),
		'encryption' => array(
			'friendly_name' => __('Encryption'),
			'description' => __('Encryption that the server supports. TLS is only supported by Protocol Version 3.'),
			'method' => 'drop_array',
			'value' => '|arg1:encryption|',
			'array' => $ldap_encryption
			),
		'referrals' => array(
			'friendly_name' => __('Referrals'),
			'description' => __('Enable or Disable LDAP referrals.  If disabled, it may increase the speed of searches.'),
			'method' => 'drop_array',
			'value' => '|arg1:referrals|',
			'array' => array( '0' => __('Disabled'), '1' => __('Enable'))
			),
		'mode' => array(
			'friendly_name' => __('Mode'),
			'description' => __('Mode which cacti will attempt to authenticate against the LDAP server.<blockquote><i>No Searching</i> - No Distinguished Name (DN) searching occurs, just attempt to bind with the provided Distinguished Name (DN) format.<br><br><i>Anonymous Searching</i> - Attempts to search for username against LDAP directory via anonymous binding to locate the users Distinguished Name (DN).<br><br><i>Specific Searching</i> - Attempts search for username against LDAP directory via Specific Distinguished Name (DN) and Specific Password for binding to locate the users Distinguished Name (DN).'),
			'method' => 'drop_array',
			'value' => '|arg1:mode|',
			'array' => $ldap_modes
			),
		'dn' => array(
			'friendly_name' => __('Distinguished Name (DN)'),
			'description' => __('The "Distinguished Name" syntax, applicable for both OpenLDAP and Windows AD configurations, offers flexibility in defining user identity. For OpenLDAP, the format follows this structure: <i>"uid=&lt;username&gt;,ou=people,dc=domain,dc=local"</i>. Windows AD provides an alternative syntax: <i>"&lt;username&gt;@win2kdomain.local"</i>, commonly known as "userPrincipalName (UPN)". In this context, "&lt;username&gt;" represents the specific username provided during the login prompt. This is particularly pertinent when operating in "No Searching" mode, or "Require Group Membership" enabled.'),
			'method' => 'textbox',
			'value' => '|arg1:dn|',
			'max_length' => '255',
			'size' => 100
			),
		'group_require' => array(
			'friendly_name' => __('Require Group Membership'),
			'description' => __('Require user to be member of group to authenticate. Group settings must be set for this to work, enabling without proper group settings will cause authentication failure.'),
			'value' => '|arg1:group_require|',
			'method' => 'checkbox'
			),
		'group_header' => array(
			'friendly_name' => __('LDAP Group Settings'),
			'method' => 'spacer'
			),
		'group_dn' => array(
			'friendly_name' => __('Group Distinguished Name (DN)'),
			'description' => __('Distinguished Name of the group that user must have membership.'),
			'method' => 'textbox',
			'value' => '|arg1:group_dn|',
			'max_length' => '255'
			),
		'group_attrib' => array(
			'friendly_name' => __('Group Member Attribute'),
			'description' => __('This refers to the specific attribute within the LDAP directory that holds the usernames of group members. It is crucial to ensure that the attribute value aligns with the configuration specified in the "Distinguished Name" or that the actual attribute value is searchable using the settings outlined in the "Distinguished Name".'),
			'method' => 'textbox',
			'value' => '|arg1:group_attrib|',
			'max_length' => '255'
			),
		'group_member_type' => array(
			'friendly_name' => __('Group Member Type'),
			'description' => __('Defines if users use full Distinguished Name or just Username in the defined Group Member Attribute.'),
			'method' => 'drop_array',
			'value' => '|arg1:group_member_type|',
			'array' => array( 1 => 'Distinguished Name', 2 => 'Username' )
			),
		'search_base_header' => array(
			'friendly_name' => __('LDAP Specific Search Settings'),
			'method' => 'spacer'
			),
		'search_base' => array(
			'friendly_name' => __('Search Base'),
			'description' => __('Search base for searching the LDAP directory, such as <i>"dc=win2kdomain,dc=local"</i> or <i>"ou=people,dc=domain,dc=local"</i>.'),
			'method' => 'textbox',
			'value' => '|arg1:search_base|',
			'max_length' => '255'
			),
		'search_filter' => array(
			'friendly_name' => __('Search Filter'),
			'description' => __('Search filter to use to locate the user in the LDAP directory, such as for windows: <i>"(&amp;(objectclass=user)(objectcategory=user)(userPrincipalName=&lt;username&gt;*))"</i> or for OpenLDAP: <i>"(&(objectClass=account)(uid=&lt;username&gt))"</i>.  "&lt;username&gt" is replaced with the username that was supplied at the login prompt.'),
			'method' => 'textbox',
			'value' => '|arg1:search_filter|',
			'max_length' => '512'
			),
		'specific_dn' => array(
			'friendly_name' => __('Search Distinguished Name (DN)'),
			'description' => __('Distinguished Name for Specific Searching binding to the LDAP directory.'),
			'method' => 'textbox',
			'value' => '|arg1:specific_dn|',
			'max_length' => '255'
			),
		'specific_password' => array(
			'friendly_name' => __('Search Password'),
			'description' => __('Password for Specific Searching binding to the LDAP directory.'),
			'method' => 'textbox_password',
			'value' => '|arg1:specific_password|',
			'max_length' => '255'
			),
		'cn_header' => array(
			'friendly_name' => __('LDAP CN Settings'),
			'method' => 'spacer'
			),
		'cn_full_name' => array(
			'friendly_name' => __('Full Name'),
			'description' => __('Field that will replace the Full Name when creating a new user, taken from LDAP. (on windows: displayname) '),
			'method' => 'textbox',
			'value' => '|arg1:cn_full_name|',
			'max_length' => '255'
			),
		'cn_email' => array(
			'friendly_name' => __('eMail'),
			'description' => __('Field that will replace the email taken from LDAP. (on windows: mail) '),
			'method' => 'textbox',
			'value' => '|arg1:cn_email|',
			'max_length' => '255'
			),
		'save_component_domain_ldap' => array(
			'method' => 'hidden',
			'value' => '1'
			)
	);

	form_start('user_domains.php');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(array(
		'config' => array(),
		'fields' => inject_form_variables($fields_domain_edit, (isset($domain) ? $domain : array()))
		));

	html_end_box(true, true);

	if (!isempty_request_var('domain_id')) {
		$domain = db_fetch_row_prepared('SELECT * FROM user_domains_ldap WHERE domain_id = ?', array(get_request_var('domain_id')));

		html_start_box( __('Domain Properties'), '100%', true, '3', 'center', '');

		draw_edit_form(array(
			'config' => array(),
			'fields' => inject_form_variables($fields_domain_ldap_edit, (isset($domain) ? $domain : array()))
			));

		html_end_box(true, true);
	}

	?>
	<script type='text/javascript'>
	function initGroupMember() {
		if ($('#group_require').is(':checked')) {
			$('#row_group_header').show();
			$('#row_group_dn').show();
			$('#row_group_attrib').show();
			$('#row_group_member_type').show();
		} else {
			$('#row_group_header').hide();
			$('#row_group_dn').hide();
			$('#row_group_attrib').hide();
			$('#row_group_member_type').hide();
		}
	}

	function initSearch() {
		switch($('#mode').val()) {
		case '0':
			$('#row_search_base_header').hide();
			$('#row_search_base').hide();
			$('#row_search_filter').hide();
			$('#row_specific_dn').hide();
			$('#row_specific_password').hide();
			$('#row_cn_header').hide();
			$('#row_cn_full_name').hide();
			$('#row_cn_email').hide();
			break;
		case '1':
			$('#row_search_base_header').show();
			$('#row_search_base').show();
			$('#row_search_filter').show();
			$('#row_specific_dn').hide();
			$('#row_specific_password').hide();
			$('#row_cn_header').hide();
			$('#row_cn_full_name').hide();
			$('#row_cn_email').hide();
			break;
		case '2':
			$('#row_search_base_header').show();
			$('#row_search_base').show();
			$('#row_search_filter').show();
			$('#row_specific_dn').show();
			$('#row_specific_password').show();
			$('#row_cn_header').show();
			$('#row_cn_full_name').show();
			$('#row_cn_email').show();
			break;
		}
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

function domains() {
	global $domain_types, $actions, $item_rows;

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
			'default' => 'domain_name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_domains');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	html_start_box( __('User Domains'), '100%', '', '3', 'center', 'user_domains.php?action=edit');

	?>
	<tr class='even' class='noprint'>
		<td class='noprint'>
		<form id='form_domains' method='get' action='user_domains.php'>
			<table class='filterTable'>
				<tr class='noprint'>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Domains');?>
					</td>
					<td>
						<select id='rows' onChange="applyFilter()">
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __x('filter: use', 'Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
		</form>
		<script type='text/javascript'>
		function applyFilter() {
			strURL  = 'user_domains.php?rows=' + $('#rows').val();
			strURL += '&filter=' + $('#filter').val();
			strURL += '&header=false';
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL = 'user_domains.php?clear=1&header=false';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#refresh').click(function() {
				applyFilter();
			});

			$('#clear').click(function() {
				clearFilter();
			});

			$('#form_domains').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});
		</script>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = 'WHERE
			domain_name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . '
			OR type LIKE '     . db_qstr('%' . get_request_var('filter') . '%');
	} else {
		$sql_where = '';
	}

	$total_rows = db_fetch_cell("SELECT
		count(*)
		FROM user_domains
		$sql_where");

	$domains = db_fetch_assoc("SELECT *
		FROM user_domains
		$sql_where
		ORDER BY " . get_request_var('sort_column') . ' ' . get_request_var('sort_direction') . '
		LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows);

	$nav = html_nav_bar('user_user_domains.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 8, __('User Domains'), 'page', 'main');

	form_start('user_domains.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'domain_name'  => array(__('Domain Name'), 'ASC'),
		'type'         => array(__('Domain Type'), 'ASC'),
		'defdomain'    => array(__('Default'), 'ASC'),
		'user_id'      => array(__('Effective User'), 'ASC'),
		'cn_full_name' => array(__('CN FullName'), 'ASC'),
		'cn_email'     => array(__('CN eMail'), 'ASC'),
		'enabled'      => array(__('Enabled'), 'ASC'));

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;
	if (cacti_sizeof($domains)) {
		foreach ($domains as $domain) {
			/* hide system types */
			form_alternate_row('line' . $domain['domain_id'], true);
			form_selectable_cell(filter_value($domain['domain_name'], get_request_var('filter'), 'user_domains.php?action=edit&domain_id=' . $domain['domain_id']), $domain['domain_id']);
			form_selectable_cell($domain_types[$domain['type']], $domain['domain_id']);
			form_selectable_cell(($domain['defdomain'] == '0' ? '--': __('Yes') ), $domain['domain_id']);
			form_selectable_ecell(($domain['user_id'] == '0' ? __('None Selected') : db_fetch_cell_prepared('SELECT username FROM user_auth WHERE id = ?', array($domain['user_id']))), $domain['domain_id']);
			form_selectable_ecell(db_fetch_cell_prepared('SELECT cn_full_name FROM user_domains_ldap WHERE domain_id = ?', array($domain['domain_id'])), $domain['domain_id']);
			form_selectable_ecell(db_fetch_cell_prepared('SELECT cn_email FROM user_domains_ldap WHERE domain_id = ?', array($domain['domain_id'])), $domain['domain_id']);
			form_selectable_cell($domain['enabled'] == 'on' ? __('Yes'):__('No'), $domain['domain_id']);
			form_checkbox_cell($domain['domain_name'], $domain['domain_id']);
			form_end_row();
		}
	} else {
		print '<tr><td colspan="' . (cacti_sizeof($display_text)+1) . '"><em>' . __('No User Domains Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($domains)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($actions);

	form_end();
}
