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
include_once('./lib/utility.php');

define('MAX_DISPLAY_PAGES', 21);

$actions = array(
	1 => 'Delete',
	2 => 'Disable',
	3 => 'Enable',
	4 => 'Default'
	);

/* set default action */
if (!isset($_REQUEST['action'])) { $_REQUEST['action'] = ''; }

switch ($_REQUEST['action']) {
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

	if (isset($_POST['save_component_domain_ldap'])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('domain_id'));
		input_validate_input_number(get_request_var_post('type'));
		input_validate_input_number(get_request_var_post('user_id'));
		/* ==================================================== */

		$save['domain_id']   = $_POST['domain_id'];
		$save['type']        = $_POST['type'];
		$save['user_id']     = $_POST['user_id'];
		$save['domain_name'] = form_input_validate($_POST['domain_name'], 'domain_name', '', false, 3);
		$save['enabled']     = form_input_validate($_POST['enabled'],     'enabled',     '', true,  3);

		if (!is_error_message()) {
			$domain_id = sql_save($save, 'user_domains', 'domain_id');

			if ($domain_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}

			if (!is_error_message()) {
				/* ================= input validation ================= */
				input_validate_input_number(get_request_var_post('domain_id'));
				input_validate_input_number(get_request_var_post('port'));
				input_validate_input_number(get_request_var_post('port_ssl'));
				input_validate_input_number(get_request_var_post('proto_version'));
				input_validate_input_number(get_request_var_post('encryption'));
				input_validate_input_number(get_request_var_post('referrals'));
				input_validate_input_number(get_request_var_post('mode'));
				input_validate_input_number(get_request_var_post('group_member_type'));
				/* ==================================================== */

				$save                      = array();
				$save['domain_id']         = $domain_id;
				$save['server']            = form_input_validate($_POST['server'], 'server', '', false, 3);
				$save['port']              = $_POST['port'];
				$save['port_ssl']          = $_POST['port_ssl'];
				$save['proto_version']     = $_POST['proto_version'];
				$save['encryption']        = $_POST['encryption'];
				$save['referrals']         = $_POST['referrals'];
				$save['mode']              = $_POST['mode'];
				$save['group_member_type'] = $_POST['group_member_type'];
				$save['dn']                = form_input_validate($_POST['dn'],                'dn',              '', true, 3);
				$save['group_require']     = isset($_POST['group_require']) ? 'on':'';
				$save['group_dn']          = form_input_validate($_POST['group_dn'],          'group_dn',        '', true, 3);
				$save['group_attrib']      = form_input_validate($_POST['group_attrib'],      'group_attrib',    '', true, 3);
				$save['search_base']       = form_input_validate($_POST['search_base'],       'search_base',     '', true, 3);
				$save['search_filter']     = form_input_validate($_POST['search_filter'],     'search_filter',   '', true, 3);
				$save['specific_dn']         = form_input_validate($_POST['specific_dn'],         'specific_dn',       '', true, 3);
				$save['specific_password']   = form_input_validate($_POST['specific_password'],   'specific_password', '', true, 3);

				if (!is_error_message()) {
					$insert_id = sql_save($save, 'user_domains_ldap', 'domain_id', false);

					if ($insert_id) {
						raise_message(1);
					}else{
						raise_message(2);
					}
				}
			}
		}
	}elseif (isset($_POST['save_component_domain'])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('domain_id'));
		input_validate_input_number(get_request_var_post('type'));
		input_validate_input_number(get_request_var_post('user_id'));
		/* ==================================================== */

		$save['domain_id']   = $_POST['domain_id'];
		$save['domain_name'] = form_input_validate($_POST['domain_name'], 'domain_name', '', false, 3);
		$save['type']        = $_POST['type'];
		$save['user_id']     = $_POST['user_id'];
		$save['enabled']     = form_input_validate($_POST['enabled'],     'enabled',     '', true,  3);

		if (!is_error_message()) {
			$domain_id = sql_save($save, 'user_domains', 'domain_id');

			if ($domain_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}
	}

	header('Location: user_domains.php?action=edit&domain_id=' . (empty($domain_id) ? $_POST['domain_id'] : $domain_id));
}

function form_actions() {
	global $actions;

	/* if we are to save this form, instead of display it */
	if (isset($_POST['selected_items'])) {
		$selected_items = unserialize(stripslashes($_POST['selected_items']));

		if ($_POST['drp_action'] == '1') { /* delete */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				domain_remove($selected_items[$i]);
			}
		}elseif ($_POST['drp_action'] == '2') { /* disable */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				domain_disable($selected_items[$i]);
			}
		}elseif ($_POST['drp_action'] == '3') { /* enable */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				domain_enable($selected_items[$i]);
			}
		}elseif ($_POST['drp_action'] == '4') { /* default */
			if (sizeof($selected_items) > 1) {
				/* error message */
			}else{
				for ($i=0;($i<count($selected_items));$i++) {
					/* ================= input validation ================= */
					input_validate_input_number($selected_items[$i]);
					/* ==================================================== */

					domain_default($selected_items[$i]);
				}
			}

		}

		header('Location: user_domains.php');
		exit;
	}

	/* setup some variables */
	$d_list = ''; $d_array = array();

	/* loop through each of the data queries and process them */
	while (list($var,$val) = each($_POST)) {
		if (ereg('^chk_([0-9]+)$', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$d_list .= '<li>' . db_fetch_cell('SELECT domain_name FROM user_domains WHERE domain_id="' . $matches[1] . '"') . '</li>';
			$d_array[] = $matches[1];
		}
	}

	top_header();

	html_start_box('<strong>' . $actions{$_POST['drp_action']} . '</strong>', '60%', '', '3', 'center', '');

	print "<form action='user_domains.php' method='post'>\n";

	if (isset($d_array) && sizeof($d_array)) {
		if ($_POST['drp_action'] == '1') { /* delete */
			print "
				<tr>
					<td class='textArea'>
						<p>When you click \"Continue\", the following User Domain(s) will be deleted.</p>
						<p><ul>$d_list</ul></p>
					</td>
				</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Delete User Domain(s)'>";
		}else if ($_POST['drp_action'] == '2') { /* disable */
			print "
				<tr>
					<td class='textArea'>
						<p>When you click \"Continue\", the following User Domain(s) will be disabled.</p>
						<p><ul>$d_list</ul></p>
					</td>
				</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Disable User Domain(s)'>";
		}else if ($_POST['drp_action'] == '3') { /* enable */
			print "
				<tr>
					<td class='textArea'>
						<p>When you click \"Continue\", the following User Domain(s) will be enabled.</p>
						<p><ul>$d_list</ul></p>
					</td>
				</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Enabled User Domain(s)'>";
		}else if ($_POST['drp_action'] == '4') { /* default */
			print "
				<tr>
					<td class='textArea'>
						<p>When you click \"Continue\", the following User Domain will become the default.</p>
						<p><ul>$d_list</ul></p>
					</td>
				</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='window.history.back()'>&nbsp;<input type='submit' value='Continue' title='Make Selected Domain Default'>";
		}
	}else{
		print "<tr><td class='even'><span class='textError'>You must select at least one data input method.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='window.history.back()'>";
	}

	print "	<tr>
			<td align='right' class='saveRow'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($d_array) ? serialize($d_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST['drp_action'] . "'>
				$save_html
			</td>
		</tr>";

	html_end_box();

	bottom_footer();
}

/* -----------------------
    Domain Functions
   ----------------------- */

function domain_remove($domain_id) {
	db_execute('DELETE FROM user_domains WHERE domain_id=' . $domain_id);
	db_execute('DELETE FROM user_domains_ldap WHERE domain_id=' . $domain_id);
}

function domain_disable($domain_id) {
	db_execute('UPDATE user_domains SET enabled="" WHERE domain_id=' . $domain_id);
}

function domain_enable($domain_id) {
	db_execute('UPDATE user_domains SET enabled="on" WHERE domain_id=' . $domain_id);
}

function domain_default($domain_id) {
	db_execute('UPDATE user_domains SET defdomain=0');
	db_execute('UPDATE user_domains SET defdomain=1 WHERE domain_id=' . $domain_id);
}

function domain_edit() {
	global $ldap_versions, $ldap_encryption, $ldap_modes, $domain_types;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('domain_id'));
	/* ==================================================== */

	if (!empty($_GET['domain_id'])) {
		$domain = db_fetch_row('SELECT * FROM user_domains WHERE domain_id=' . $_GET['domain_id']);
		$header_label = '[edit: ' . $domain['domain_name'] . ']';
	}else{
		$header_label = '[new]';
	}

	/* file: data_input.php, action: edit */
	$fields_domain_edit = array(
		'domain_name' => array(
			'method' => 'textbox',
			'friendly_name' => 'Name',
			'description' => 'Enter a meaningful name for this domain.  This will be the name that appears
			in the Login Realm during login.',
			'value' => '|arg1:domain_name|',
			'max_length' => '255',
			),
		'type' => array(
			'method' => 'drop_array',
			'friendly_name' => 'Domains Type',
			'description' => 'Choose what type of domain this is.',
			'value' => '|arg1:type|',
			'array' => $domain_types,
			'default' => '2'
			),
		'user_id' => array(
			'friendly_name' => 'User Template',
			'description' => 'The name of the user that Cacti will use as a template for new user accounts.',
			'method' => 'drop_sql',
			'value' => '|arg1:user_id|',
			'none_value' => 'No User',
			'sql' => 'SELECT id AS id, username AS name FROM user_auth WHERE realm=0 ORDER BY username',
			'default' => '0'
			),
		'enabled' => array(
			'method' => 'checkbox',
			'friendly_name' => 'Enabled',
			'description' => 'If this checkbox is checked, users will be able to login using this domain.',
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
			'friendly_name' => 'Server',
			'description' => 'The dns hostname or ip address of the server.',
			'method' => 'textbox',
			'value' => '|arg1:server|',
			'default' => read_config_option('ldap_server'),
			'max_length' => '255'
			),
		'port' => array(
			'friendly_name' => 'Port Standard',
			'description' => 'TCP/UDP port for Non SSL communications.',
			'method' => 'textbox',
			'max_length' => '5',
			'value' => '|arg1:port|',
			'default' => read_config_option('ldap_port'),
			'size' => '5'
			),
		'port_ssl' => array(
			'friendly_name' => 'Port SSL',
			'description' => 'TCP/UDP port for SSL communications.',
			'method' => 'textbox',
			'max_length' => '5',
			'value' => '|arg1:port_ssl|',
			'default' => read_config_option('ldap_port_ssl'),
			'size' => '5'
			),
		'proto_version' => array(
			'friendly_name' => 'Protocol Version',
			'description' => 'Protocol Version that the server supports.',
			'method' => 'drop_array',
			'value' => '|arg1:proto_version|',
			'array' => $ldap_versions
			),
		'encryption' => array(
			'friendly_name' => 'Encryption',
			'description' => 'Encryption that the server supports. TLS is only supported by Protocol Version 3.',
			'method' => 'drop_array',
			'value' => '|arg1:encryption|',
			'array' => $ldap_encryption
			),
		'referrals' => array(
			'friendly_name' => 'Referrals',
			'description' => 'Enable or Disable LDAP referrals.  If disabled, it may increase the speed of searches.',
			'method' => 'drop_array',
			'value' => '|arg1:referrals|',
			'array' => array( '0' => 'Disabled', '1' => 'Enable')
			),
		'mode' => array(
			'friendly_name' => 'Mode',
			'description' => 'Mode which cacti will attempt to authenicate against the LDAP server.<blockquote><i>No Searching</i> - No Distinguished Name (DN) searching occurs, just attempt to bind with the provided Distinguished Name (DN) format.<br><br><i>Anonymous Searching</i> - Attempts to search for username against LDAP directory via anonymous binding to locate the users Distinguished Name (DN).<br><br><i>Specific Searching</i> - Attempts search for username against LDAP directory via Specific Distinguished Name (DN) and Specific Password for binding to locate the users Distinguished Name (DN).',
			'method' => 'drop_array',
			'value' => '|arg1:mode|',
			'array' => $ldap_modes
			),
		'dn' => array(
			'friendly_name' => 'Distinguished Name (DN)',
			'description' => 'Distinguished Name syntax, such as for windows: <i>"&lt;username&gt;@win2kdomain.local"</i> or for OpenLDAP: <i>"uid=&lt;username&gt;,ou=people,dc=domain,dc=local"</i>.   "&lt;username&gt" is replaced with the username that was supplied at the login prompt.  This is only used when in "No Searching" mode.',
			'method' => 'textbox',
			'value' => '|arg1:dn|',
			'max_length' => '255'
			),
		'group_require' => array(
			'friendly_name' => 'Require Group Membership',
			'description' => 'Require user to be member of group to authenicate. Group settings must be set for this to work, enabling without proper group settings will cause authenication failure.',
			'value' => '|arg1:group_require|',
			'method' => 'checkbox'
			),
		'group_header' => array(
			'friendly_name' => 'LDAP Group Settings',
			'method' => 'spacer'
			),
		'group_dn' => array(
			'friendly_name' => 'Group Distingished Name (DN)',
			'description' => 'Distingished Name of the group that user must have membership.',
			'method' => 'textbox',
			'value' => '|arg1:group_dn|',
			'max_length' => '255'
			),
		'group_attrib' => array(
			'friendly_name' => 'Group Member Attribute',
			'description' => 'Name of the attribute that contains the usernames of the members.',
			'method' => 'textbox',
			'value' => '|arg1:group_attrib|',
			'max_length' => '255'
			),
		'group_member_type' => array(
			'friendly_name' => 'Group Member Type',
			'description' => 'Defines if users use full Distingished Name or just Username in the defined Group Member Attribute.',
			'method' => 'drop_array',
			'value' => '|arg1:group_member_type|',
			'array' => array( 1 => 'Distingished Name', 2 => 'Username' )
			),
		'search_base_header' => array(
			'friendly_name' => 'LDAP Specific Search Settings',
			'method' => 'spacer'
			),
		'search_base' => array(
			'friendly_name' => 'Search Base',
			'description' => 'Search base for searching the LDAP directory, such as <i>"dc=win2kdomain,dc=local"</i> or <i>"ou=people,dc=domain,dc=local"</i>.',
			'method' => 'textbox',
			'value' => '|arg1:search_base|',
			'max_length' => '255'
			),
		'search_filter' => array(
			'friendly_name' => 'Search Filter',
			'description' => 'Search filter to use to locate the user in the LDAP directory, such as for windows: <i>"(&amp;(objectclass=user)(objectcategory=user)(userPrincipalName=&lt;username&gt;*))"</i> or for OpenLDAP: <i>"(&(objectClass=account)(uid=&lt;username&gt))"</i>.  "&lt;username&gt" is replaced with the username that was supplied at the login prompt. ',
			'method' => 'textbox',
			'value' => '|arg1:search_filter|',
			'max_length' => '255'
			),
		'specific_dn' => array(
			'friendly_name' => 'Search Distingished Name (DN)',
			'description' => 'Distinguished Name for Specific Searching binding to the LDAP directory.',
			'method' => 'textbox',
			'value' => '|arg1:specific_dn|',
			'max_length' => '255'
			),
		'specific_password' => array(
			'friendly_name' => 'Search Password',
			'description' => 'Password for Specific Searching binding to the LDAP directory.',
			'method' => 'textbox_password',
			'value' => '|arg1:specific_password|',
			'max_length' => '255'
			),
		'save_component_domain_ldap' => array(
			'method' => 'hidden',
			'value' => '1'
			)
	);

	html_start_box('<strong>User Domain</strong> $header_label', '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array(),
		'fields' => inject_form_variables($fields_domain_edit, (isset($domain) ? $domain : array()))
		));

	html_end_box();

	if (!empty($_GET['domain_id'])) {
		$domain = db_fetch_row('SELECT * FROM user_domains_ldap WHERE domain_id=' . $_GET['domain_id']);

		html_start_box('<strong>Domain Properties</strong>', '100%', '', '3', 'center', '');

		draw_edit_form(array(
			'config' => array(),
			'fields' => inject_form_variables($fields_domain_ldap_edit, (isset($domain) ? $domain : array()))
			));

		html_end_box();
	}

	?>
	<script type='text/javascript'>
	function initGroupMember() {
		if ($('#group_require').is(':checked')) {
			$('#row_group_header').show();
			$('#row_group_dn').show();
			$('#row_group_attrib').show();
			$('#row_group_member_type').show();
		}else{
			$('#row_group_header').hide();
			$('#row_group_dn').hide();
			$('#row_group_attrib').hide();
			$('#row_group_member_type').hide();
		}
	}

	function initSearch() {
		switch($('#mode').val()) {
		case "0":
			$('#row_search_base_header').hide();
			$('#row_search_base').hide();
			$('#row_search_filter').hide();
			$('#row_specific_dn').hide();
			$('#row_specific_password').hide();
			break;
		case "1":
			$('#row_search_base_header').show();
			$('#row_search_base').show();
			$('#row_search_filter').show();
			$('#row_specific_dn').hide();
			$('#row_specific_password').hide();
			break;
		case "2":
			$('#row_search_base_header').show();
			$('#row_search_base').show();
			$('#row_search_filter').show();
			$('#row_specific_dn').show();
			$('#row_specific_password').show();
			break;
		}
	}

	$(function(data) {
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

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request('page'));
	input_validate_input_number(get_request_var_request('rows'));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var('filter'));
	}

	/* clean up sort_column */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var('sort_column'));
	}

	/* clean up search string */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var('sort_direction'));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear_x'])) {
		kill_session_var('sess_domains_filter');
		kill_session_var('sess_domains_rows');
		kill_session_var('sess_domains_sort_column');
		kill_session_var('sess_domains_sort_direction');

		unset($_REQUEST['page']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
		$_REQUEST['page'] = 1;
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('filter', 'sess_domains_filter', '');
	load_current_session_value('rows', 'sess_table_rows', read_config_option('num_rows_table'));
	load_current_session_value('sort_column', 'sess_domains_sort_column', 'domain_name');
	load_current_session_value('sort_direction', 'sess_domains_sort_direction', 'ASC');
	load_current_session_value('page', 'sess_domains_current_page', '1');

	html_start_box('<strong>User Domains</strong>', '100%', '', '3', 'center', 'user_domains.php?action=edit');

	?>
	<tr class='even' class='noprint'>
		<td class='noprint'>
		<form id='form_domains' method='get' action='user_domains.php'>
			<table cellpadding='2' cellspacing='0'>
				<tr class='noprint'>
					<td width='50'>
						Search:
					</td>
					<td>
						<input id='filter' type='text' name='filter' size='25' value='<?php print get_request_var_request('filter');?>'>
					</td>
					<td>
						Domains:
					</td>
					<td width="1">
						<select id='rows' name="rows" onChange="applyFilter()">
							<option value="-1"<?php if (get_request_var_request("rows") == "-1") {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var_request("rows") == $key) { print " selected"; } print ">" . htmlspecialchars($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input id='refresh' type='button' value='Go' title='Set/Refresh Filters'>
					</td>
					<td>
						<input id='clear' type='button' name='clear_x' value='Clear' title='Clear Filters'>
					</td>
				</tr>
			</table>
			<input type='hidden' name='page' value='<?php print $_REQUEST['page'];?>'>
		</form>
		<script type='text/javascript'>
		function applyFilter() {
			strURL = 'user_domains.php?rows=' + $('#rows').val();
			strURL = strURL + '&filter=' + $('#filter').val();
			strURL = strURL + '&page=' + $('#page').val();
			strURL = strURL + '&header=false';
			$.get(strURL, function(data) {
				$('#main').html(data);
				applySkin();
			});
		}

		function clearFilter() {
			strURL = 'user_domains.php?clear_x=1&header=false';
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

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='user_domains.php'>\n";

	html_start_box('', '100%', '', '3', 'center', '');

	/* form the 'where' clause for our main sql query */
	if ($_REQUEST['filter'] != '') {
		$sql_where = "WHERE (domain_name LIKE '%%" . get_request_var_request('filter') . "%%') ||
			(type LIKE '%%" . get_request_var_request('filter') . "%%')";
	}else{
		$sql_where = '';
	}

	$total_rows = db_fetch_cell("SELECT
		count(*)
		FROM user_domains
		$sql_where");

	$domains = db_fetch_assoc("SELECT *
		FROM user_domains
		$sql_where
		ORDER BY " . get_request_var_request('sort_column') . " " . get_request_var_request('sort_direction') . "
		LIMIT " . (get_request_var_request('rows')*(get_request_var_request('page')-1)) . ',' . get_request_var_request('rows'));

	$nav = html_nav_bar('user_user_domains.php?filter=' . get_request_var_request('filter'), MAX_DISPLAY_PAGES, get_request_var_request('page'), get_request_var_request('rows'), $total_rows, 6, 'User Domains', 'page', 'main');

	print $nav;

	$display_text = array(
		'domain_name' => array('Domain Name', 'ASC'),
		'type' => array('Domain Type', 'ASC'),
		'defdomain' => array('Default', 'ASC'),
		'user_id' => array('Effective User', 'ASC'),
		'enabled' => array('Enabled', 'ASC'));

	html_header_sort_checkbox($display_text, get_request_var_request('sort_column'), get_request_var_request('sort_direction'), false);

	$i = 0;
	if (sizeof($domains) > 0) {
		foreach ($domains as $domain) {
			/* hide system types */
			form_alternate_row('line' . $domain['domain_id'], true);
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars('user_domains.php?action=edit&domain_id=' . $domain['domain_id']) . "'>" . (strlen(get_request_var_request('filter')) ? eregi_replace('(' . preg_quote(get_request_var_request('filter')) . ')', "<span class='filteredValue>\\1</span>", $domain['domain_name']) : $domain['domain_name']) . '</a>', $domain['domain_id']);
			form_selectable_cell($domain_types{$domain['type']}, $domain['domain_id']);
			form_selectable_cell(($domain['defdomain'] == '0' ? '--':'Yes'), $domain['domain_id']);
			form_selectable_cell(($domain['user_id'] == '0' ? 'None Selected': db_fetch_cell('SELECT username FROM user_auth WHERE id=' . $domain['user_id'])), $domain['domain_id']);
			form_selectable_cell(($domain['enabled'] == 'on' ? 'Yes':'No'), $domain['domain_id']);
			form_checkbox_cell($domain['domain_name'], $domain['domain_id']);
			form_end_row();
		}

		print $nav;
	}else{
		print '<tr><td><em>No User Domains Defined</em></td></tr>';
	}

	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($actions);

	print "</form>\n";

}
?>

