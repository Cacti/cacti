<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
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

use Cacti\Auth\AbstractLoginProvider;
use Cacti\Auth\LoginProviderFactory;

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

		provider_edit();

		bottom_footer();

		break;
	default:
		top_header();

		login_providers();

		bottom_footer();

		break;
}

/* --------------------------
	The Save Function
   -------------------------- */

function form_save() : void {
	if (isrv('save_component_provider')) {
		// ================= input validation =================
		gfrv('id');
		gfrv('type');
		gfrv('user_id');
		// ====================================================

		$save                       = [];
		$save['id']                 = gnrv('id');
		// no-validation: admin-chosen display name, free text
		$save['name']               = form_input_validate(gnrv('name'), 'name', '', false, 3);
		// no-validation: admin-chosen free-text description
		$save['description']        = form_input_validate(gnrv('description'), 'description', '', true, 3);
		$save['type']               = gnrv('type');
		// no-validation: admin-chosen SSO login button text, free text
		$save['button_label']       = form_input_validate(gnrv('button_label'), 'button_label', '', true, 3);
		$save['user_id']            = gnrv('user_id');
		// no-validation: checkbox values are constrained to 'on'/'' by the isrv() guard, not a text field
		$save['enabled']            = (isrv('enabled') ? form_input_validate(gnrv('enabled'), 'enabled', '', true, 3) : '');
		// no-validation: checkbox values are constrained to 'on'/'' by the isrv() guard, not a text field
		$save['debug']              = (isrv('debug') ? form_input_validate(gnrv('debug'), 'debug', '', true, 3) : '');
		// no-validation: checkbox values are constrained to 'on'/'' by the isrv() guard, not a text field
		$save['allow_auth_cookies'] = (isrv('allow_auth_cookies') ? form_input_validate(gnrv('allow_auth_cookies'), 'allow_auth_cookies', '', true, 3) : '');

		$parameters = LoginProviderFactory::collectParameters((int) $save['type']);

		if (is_error_message() === false) {
			$id = AbstractLoginProvider::persist($save, $parameters);

			if ($id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		header('Location: login_providers.php?action=edit&id=' . (empty($id) ? gnrv('id') : $id));

		return;
	}

	header('Location: login_providers.php');
}

function form_actions() : void {
	global $actions;

	// if we are to save this form, instead of display it
	if (isrv('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(gnrv('selected_items'));

		if ($selected_items != false) {
			if (gnrv('drp_action') == '1') { // delete
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					AbstractLoginProvider::deleteById((int) $selected_items[$i]);
				}
			} elseif (gnrv('drp_action') == '2') { // disable
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					AbstractLoginProvider::disableById((int) $selected_items[$i]);
				}
			} elseif (gnrv('drp_action') == '3') { // enable
				for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
					AbstractLoginProvider::enableById((int) $selected_items[$i]);
				}
			} elseif (gnrv('drp_action') == '4') { // default
				if (cacti_sizeof($selected_items) > 1) {
					// error message
				} else {
					for ($i = 0; ($i < cacti_count($selected_items)); $i++) {
						AbstractLoginProvider::makeDefaultById((int) $selected_items[$i]);
					}
				}
			}
		}

		header('Location: login_providers.php');

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

				$ilist .= '<li>' . htmle(db_fetch_cell_prepared('SELECT name FROM login_providers WHERE id = ?', [$matches[1]])) . '</li>';
				$iarray[] = $matches[1];
			}
		}

		$form_data = [
			'general' => [
				'page'       => 'login_providers.php',
				'actions'    => $actions,
				'optvar'     => 'drp_action',
				'item_array' => $iarray,
				'item_list'  => $ilist
			],
			'options' => [
				1 => [
					'smessage' => __('Click \'Continue\' to Delete the following Login Provider.'),
					'pmessage' => __('Click \'Continue\' to Delete following Login Providers.'),
					'scont'    => __('Delete Login Provider'),
					'pcont'    => __('Delete Login Providers')
				],
				2 => [
					'smessage' => __('Click \'Continue\' to Disable the following Login Provider.'),
					'pmessage' => __('Click \'Continue\' to Disable following Login Providers.'),
					'scont'    => __('Disable Login Provider'),
					'pcont'    => __('Disable Login Providers')
				],
				3 => [
					'smessage' => __('Click \'Continue\' to Enable the following Login Provider.'),
					'pmessage' => __('Click \'Continue\' to Enable following Login Providers.'),
					'scont'    => __('Enable Login Provider'),
					'pcont'    => __('Enable Login Providers')
				],
				4 => [
					'message' => __('Click \'Continue\' to make the following Login Provider the default one.'),
					'cont'    => __('Make Selected Login Provider Default')
				]
			]
		];

		form_continue_confirmation($form_data);
	}
}

function provider_edit() : void {
	global $ldap_versions, $ldap_encryption, $ldap_modes, $provider_types, $ldap_tls_cert_req;

	// ================= input validation =================
	gfrv('id');
	// ====================================================

	$provider  = [];
	$type      = PROVIDER_TYPE_LDAP;

	if (!ierv('id')) {
		$provider = db_fetch_row_prepared('SELECT * FROM login_providers WHERE id = ?', [grv('id')]);
		$provider = is_array($provider) ? $provider : [];

		if (cacti_sizeof($provider)) {
			$type = (int) $provider['type'];

			$parameters = json_decode((string) $provider['parameters'], true);

			if (is_array($parameters)) {
				$provider += $parameters;
			}
		}

		$header_label = __esc('Login Provider [edit: %s]', $provider['name']);
	} else {
		$header_label = __('Login Provider [new]');
	}

	$fields_general = [
		'general_header' => [
			'friendly_name' => __('General'),
			'method'        => 'spacer',
		],
		'name' => [
			'method'        => 'textbox',
			'friendly_name' => __('Name'),
			'description'   => __('Enter a meaningful name for this Login Provider. This will be the name that appears in the Login Realm during login.'),
			'value'         => '|arg1:name|',
			'max_length'    => '64',
		],
		'description' => [
			'method'        => 'textbox',
			'friendly_name' => __('Description'),
			'description'   => __('An optional longer description of this Login Provider, for the benefit of other administrators.'),
			'value'         => '|arg1:description|',
			'max_length'    => '255',
		],
		'type' => [
			'method'        => 'drop_array',
			'friendly_name' => __('Provider Type'),
			'description'   => __('Choose the authentication method for this Login Provider.'),
			'value'         => '|arg1:type|',
			'array'         => $provider_types,
			'default'       => PROVIDER_TYPE_LDAP,
			'on_change'     => 'initProviderType(true)',
		],
		'button_label' => [
			'method'        => 'textbox',
			'friendly_name' => __('Login Button Label'),
			'description'   => __('For SAML2 and OpenID Providers, the text shown on the login page button, e.g. "Login with Azure AD". Ignored for LDAP/Active Directory. Defaults to the Name above when left blank.'),
			'value'         => '|arg1:button_label|',
			'max_length'    => '50',
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
			'description'   => __('If this checkbox is checked, users will be able to login using this Provider.'),
			'value'         => '|arg1:enabled|',
			'default'       => '',
		],
		'allow_auth_cookies' => [
			'method'        => 'checkbox',
			'friendly_name' => __('Allow "Remember Me" Cookies'),
			'description'   => __('If unchecked, users authenticated through this Provider will never be offered a "remember me" auth cookie, even when the global setting is enabled.'),
			'value'         => '|arg1:allow_auth_cookies|',
			'default'       => 'on',
		],
		'debug' => [
			'method'        => 'checkbox',
			'friendly_name' => __('Debug'),
			'description'   => __('If testing the connection and you desire more details in your Cacti log, check this box.'),
			'value'         => '|arg1:debug|',
			'default'       => '',
		],
		'id' => [
			'method' => 'hidden_zero',
			'value'  => '|arg1:id|'
		],
		'save_component_provider' => [
			'method' => 'hidden',
			'value'  => '1'
		]
	];

	$fields_ldap = [
		'ldap_conn_header' => [
			'friendly_name' => __('Connection'),
			'method'        => 'spacer',
		],
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
			'default'       => LDAP_OPT_X_TLS_DEMAND,
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
		'group_header' => [
			'friendly_name' => __('Group Membership'),
			'method'        => 'spacer'
		],
		'group_dn' => [
			'friendly_name' => __('Group Distinguished Name (DN)'),
			'description'   => __('Distinguished Name of the group that the user must be a member of in order to login. Leave blank to allow any successfully authenticated user to login.'),
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
			'friendly_name' => __('Search Settings'),
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
		'ldap_claims_header' => [
			'friendly_name' => __('Claims'),
			'method'        => 'spacer'
		],
		'claim_full_name' => [
			'friendly_name' => __('Full Name Attribute'),
			'description'   => __('The LDAP attribute that will populate a new user\'s Full Name, e.g. "displayName" on Active Directory or "cn" on most OpenLDAP servers.'),
			'method'        => 'textbox',
			'value'         => '|arg1:claim_full_name|',
			'default'       => $type == PROVIDER_TYPE_AD ? 'displayName' : 'cn',
			'max_length'    => '255'
		],
		'claim_email' => [
			'friendly_name' => __('Email Attribute'),
			'description'   => __('The LDAP attribute that will populate a new user\'s Email Address, e.g. "mail" on both Active Directory and most OpenLDAP servers.'),
			'method'        => 'textbox',
			'value'         => '|arg1:claim_email|',
			'default'       => 'mail',
			'max_length'    => '255'
		],
	];

	$fields_saml = [
		'sp_header' => [
			'friendly_name' => __('Service Provider (SP)'),
			'description'   => __('Settings describing Cacti itself, the Service Provider, as it will be known to the Identity Provider.'),
			'method'        => 'spacer'
		],
		'sp_entity_id' => [
			'friendly_name' => __('SP Entity ID'),
			'description'   => __('Uniquely identifies this Cacti installation to the Identity Provider. Leave blank to default to the Metadata URL below.') . ' ' .
				__('Assertion Consumer Service (ACS) URL: %s', rtrim((string) read_config_option('base_url'), '/') . '/login_sso.php?action=acs&realm={realm}') . ' ' .
				__('Metadata URL: %s', rtrim((string) read_config_option('base_url'), '/') . '/login_sso.php?action=metadata&realm={realm}'),
			'method'        => 'textbox',
			'value'         => '|arg1:sp_entity_id|',
			'max_length'    => '255'
		],
		'name_id_format' => [
			'friendly_name' => __('NameID Format'),
			'description'   => __('The subject identifier format Cacti will request from the Identity Provider.'),
			'method'        => 'drop_array',
			'value'         => '|arg1:name_id_format|',
			'array'         => [
				'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress' => __('Email Address'),
				'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent'   => __('Persistent'),
				'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified'  => __('Unspecified'),
			],
			'default'       => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress'
		],
		'sign_authn_requests' => [
			'friendly_name' => __('Sign AuthnRequests'),
			'description'   => __('Sign outbound Authentication Requests using the SP Private Key below.'),
			'method'        => 'checkbox',
			'value'         => '|arg1:sign_authn_requests|',
			'default'       => ''
		],
		'want_assertions_signed' => [
			'friendly_name' => __('Require Signed Assertions'),
			'description'   => __('Reject SAML Responses whose Assertion is not signed by the Identity Provider.'),
			'method'        => 'checkbox',
			'value'         => '|arg1:want_assertions_signed|',
			'default'       => 'on'
		],
		'sp_x509cert' => [
			'friendly_name' => __('SP Certificate (PEM)'),
			'description'   => __('Only required when signing AuthnRequests or supporting encrypted assertions.'),
			'method'        => 'textarea',
			'textarea_rows' => 4,
			'textarea_cols' => 60,
			'value'         => '|arg1:sp_x509cert|',
		],
		'sp_private_key' => [
			'friendly_name' => __('SP Private Key (PEM)'),
			'description'   => __('Only required when signing AuthnRequests or supporting encrypted assertions.'),
			'method'        => 'textarea',
			'textarea_rows' => 4,
			'textarea_cols' => 60,
			'value'         => '|arg1:sp_private_key|',
		],
		'idp_header' => [
			'friendly_name' => __('Identity Provider (IDP)'),
			'description'   => __('Settings provided by the external Identity Provider, e.g. Azure AD, Okta, or ADFS.'),
			'method'        => 'spacer'
		],
		'idp_entity_id' => [
			'friendly_name' => __('IDP Entity ID'),
			'description'   => __('The unique identifier (issuer) of the Identity Provider, from its metadata.'),
			'method'        => 'textbox',
			'value'         => '|arg1:idp_entity_id|',
			'max_length'    => '255'
		],
		'idp_sso_url' => [
			'friendly_name' => __('IDP Single Sign-On URL'),
			'description'   => __('The URL the browser is redirected to in order to authenticate.'),
			'method'        => 'textbox',
			'value'         => '|arg1:idp_sso_url|',
			'max_length'    => '255'
		],
		'idp_slo_url' => [
			'friendly_name' => __('IDP Single Logout URL'),
			'description'   => __('The URL used for Single Logout requests. Optional.'),
			'method'        => 'textbox',
			'value'         => '|arg1:idp_slo_url|',
			'max_length'    => '255'
		],
		'idp_x509cert' => [
			'friendly_name' => __('IDP Certificate (PEM)'),
			'description'   => __('The Identity Provider\'s public signing certificate, used to validate SAML Responses.'),
			'method'        => 'textarea',
			'textarea_rows' => 6,
			'textarea_cols' => 60,
			'value'         => '|arg1:idp_x509cert|',
		],
		'saml_claims_header' => [
			'friendly_name' => __('Claims'),
			'method'        => 'spacer'
		],
		'saml_claim_username' => [
			'friendly_name' => __('Username Attribute'),
			'description'   => __('The SAML Attribute that identifies the Cacti username. Leave blank to use the Assertion\'s NameID.'),
			'method'        => 'textbox',
			'value'         => '|arg1:claim_username|',
			'max_length'    => '255'
		],
		'saml_claim_full_name' => [
			'friendly_name' => __('Full Name Attribute'),
			'description'   => __('The SAML Attribute that will populate a new user\'s Full Name.'),
			'method'        => 'textbox',
			'value'         => '|arg1:claim_full_name|',
			'max_length'    => '255'
		],
		'saml_claim_email' => [
			'friendly_name' => __('Email Attribute'),
			'description'   => __('The SAML Attribute that will populate a new user\'s Email Address.'),
			'method'        => 'textbox',
			'value'         => '|arg1:claim_email|',
			'max_length'    => '255'
		],
		'saml_group_header' => [
			'friendly_name' => __('Group Membership'),
			'method'        => 'spacer'
		],
		'saml_group_claim' => [
			'friendly_name' => __('Group Claim Attribute'),
			'description'   => __('The SAML Attribute carrying the user\'s group membership, e.g. "memberOf" or "groups". Leave blank to skip group checking.'),
			'method'        => 'textbox',
			'value'         => '|arg1:group_claim|',
			'max_length'    => '255'
		],
		'saml_group_name' => [
			'friendly_name' => __('Required Group'),
			'description'   => __('The group name/DN that must appear in the Group Claim Attribute above for the user to be allowed to login. Leave blank to allow any successfully authenticated user to login.'),
			'method'        => 'textbox',
			'value'         => '|arg1:group_name|',
			'max_length'    => '255'
		],
	];

	$fields_openid = [
		'oidc_sp_header' => [
			'friendly_name' => __('Service Provider (SP)'),
			'description'   => __('Settings describing Cacti itself, the OpenID Connect Relying Party, as registered with the Identity Provider.'),
			'method'        => 'spacer'
		],
		'oidc_redirect_uri' => [
			'friendly_name' => __('Redirect URI'),
			'description'   => __('Register this callback URL with your Identity Provider: %s', rtrim((string) read_config_option('base_url'), '/') . '/login_sso.php?action=callback&realm={realm}'),
			'method'        => 'spacer'
		],
		'client_id' => [
			'friendly_name' => __('Client ID'),
			'description'   => __('The OAuth2/OpenID Connect Client ID issued by the Identity Provider.'),
			'method'        => 'textbox',
			'value'         => '|arg1:client_id|',
			'max_length'    => '255'
		],
		'client_secret' => [
			'friendly_name' => __('Client Secret'),
			'description'   => __('The OAuth2/OpenID Connect Client Secret issued by the Identity Provider.'),
			'method'        => 'textbox_password',
			'value'         => '|arg1:client_secret|',
			'max_length'    => '255'
		],
		'scopes' => [
			'friendly_name' => __('Scopes'),
			'description'   => __('A space delimited list of OAuth2 scopes to request.'),
			'method'        => 'textbox',
			'value'         => '|arg1:scopes|',
			'default'       => 'openid profile email',
			'max_length'    => '255'
		],
		'oidc_idp_header' => [
			'friendly_name' => __('Identity Provider (IDP)'),
			'description'   => __('Settings provided by the external OpenID Connect Identity Provider.'),
			'method'        => 'spacer'
		],
		'discovery_url' => [
			'friendly_name' => __('Discovery URL'),
			'description'   => __('The Identity Provider\'s OpenID Connect discovery document URL, typically ending in "/.well-known/openid-configuration".'),
			'method'        => 'textbox',
			'value'         => '|arg1:discovery_url|',
			'max_length'    => '255'
		],
		'oidc_claims_header' => [
			'friendly_name' => __('Claims'),
			'method'        => 'spacer'
		],
		'oidc_claim_username' => [
			'friendly_name' => __('Username Claim'),
			'description'   => __('The ID Token/UserInfo claim that identifies the Cacti username.'),
			'method'        => 'textbox',
			'value'         => '|arg1:claim_username|',
			'default'       => 'preferred_username',
			'max_length'    => '255'
		],
		'oidc_claim_full_name' => [
			'friendly_name' => __('Full Name Claim'),
			'description'   => __('The ID Token/UserInfo claim that will populate a new user\'s Full Name.'),
			'method'        => 'textbox',
			'value'         => '|arg1:claim_full_name|',
			'default'       => 'name',
			'max_length'    => '255'
		],
		'oidc_claim_email' => [
			'friendly_name' => __('Email Claim'),
			'description'   => __('The ID Token/UserInfo claim that will populate a new user\'s Email Address.'),
			'method'        => 'textbox',
			'value'         => '|arg1:claim_email|',
			'default'       => 'email',
			'max_length'    => '255'
		],
		'oidc_group_header' => [
			'friendly_name' => __('Group Membership'),
			'method'        => 'spacer'
		],
		'oidc_group_claim' => [
			'friendly_name' => __('Group Claim'),
			'description'   => __('The ID Token/UserInfo claim carrying the user\'s group membership, e.g. "groups". Leave blank to skip group checking.'),
			'method'        => 'textbox',
			'value'         => '|arg1:group_claim|',
			'max_length'    => '255'
		],
		'oidc_group_name' => [
			'friendly_name' => __('Required Group'),
			'description'   => __('The group name that must appear in the Group Claim above for the user to be allowed to login. Leave blank to allow any successfully authenticated user to login.'),
			'method'        => 'textbox',
			'value'         => '|arg1:group_name|',
			'max_length'    => '255'
		],
	];

	form_start('login_providers.php');

	html_start_box($header_label, '100%', true, 3, 'center', '');

	$fields_all = $fields_general + $fields_ldap + $fields_saml + $fields_openid;

	draw_edit_form([
		'config' => [],
		'fields' => inject_form_variables($fields_all, $provider)
	]);

	html_end_box(true, true);

	?>
	<script type='text/javascript'>
		var ldapFields = <?php print json_encode(array_keys($fields_ldap)); ?>;
		var samlFields = <?php print json_encode(array_keys($fields_saml)); ?>;
		var oidcFields = <?php print json_encode(array_keys($fields_openid)); ?>;

		function initProviderType(applyClaimDefault) {
			var type = parseInt($('#type').val());
			var groups = {
				<?php print PROVIDER_TYPE_LDAP; ?>: ldapFields,
				<?php print PROVIDER_TYPE_AD; ?>: ldapFields,
				<?php print PROVIDER_TYPE_SAML2; ?>: samlFields,
				<?php print PROVIDER_TYPE_OPENID; ?>: oidcFields
			};

			[ldapFields, samlFields, oidcFields].forEach(function(fields) {
				var toggles = {};
				fields.forEach(function(field) {
					toggles[field] = (groups[type] === fields);
				});
				toggleFields(toggles);
			});

			// Only swap the Full Name attribute default (OpenLDAP's 'cn' vs
			// Active Directory's 'displayName') on an actual user-driven type
			// change, and only while the field still holds one of those two
			// stock defaults - never on initial page load, so an existing
			// provider's saved (possibly blank/customized) value is untouched.
			if (!applyClaimDefault) {
				return;
			}

			var ldapDefault = 'cn';
			var adDefault   = 'displayName';
			var current     = $('#claim_full_name').val();

			if (current === '' || current === ldapDefault || current === adDefault) {
				$('#claim_full_name').val(type === <?php print PROVIDER_TYPE_AD; ?> ? adDefault : ldapDefault);
			}
		}

		function initGroupMember() {
			toggleFields({
				group_dn: true,
				group_attrib: true,
				group_member_type: true,
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
			});
		}

		$(function() {
			initProviderType();
			initSearch();
			initGroupMember();

			$('#mode').change(function() {
				initSearch();
			});
		});
	</script>
<?php

	form_save_button('login_providers.php', 'return', 'id');
}

function login_providers() : void {
	global $provider_types, $actions;

	// create the page filter
	$pageFilter = new CactiTableFilter(__('Login Providers'), 'login_providers.php', 'form_provider', 'sess_provider', 'login_providers.php?action=edit');

	$pageFilter->rows_label = __('Providers');
	$pageFilter->set_sort_array('name', 'ASC');
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
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . '(name LIKE ? OR type LIKE ?)';

		$sql_params[] = '%' . grv('filter') . '%';
		$sql_params[] = '%' . grv('filter') . '%';
	}

	$total_rows = db_fetch_cell_prepared("SELECT COUNT(*)
		FROM login_providers
		$sql_where",
		$sql_params);

	$providers = db_fetch_assoc_prepared("SELECT *
		FROM login_providers
		$sql_where
		ORDER BY " . sanitize_sql_column(grv('sort_column'), 'name') . ' ' . (strtoupper(grv('sort_direction')) === 'DESC' ? 'DESC' : 'ASC') . '
		LIMIT ' . ($rows * (grv('page') - 1)) . ',' . $rows,
		$sql_params);

	$display_text = [
		'name'       => [
			'display' => __('Name'),
			'sort'    => 'ASC'
		],
		'type'       => [
			'display' => __('Provider Type'),
			'sort'    => 'ASC'
		],
		'is_default' => [
			'display' => __('Default'),
			'sort'    => 'ASC'
		],
		'user_id'    => [
			'display' => __('Effective User'),
			'sort'    => 'ASC'
		],
		'enabled'    => [
			'display' => __('Enabled'),
			'sort'    => 'ASC'
		]
	];

	$nav = html_nav_bar('login_providers.php?filter=' . grv('filter'), MAX_DISPLAY_PAGES, grv('page'), $rows, $total_rows, 6, __('Login Providers'), 'page', 'main');

	form_start('login_providers.php', 'chk');

	print $nav;

	html_start_box('', '100%', false, 3, 'center', '');

	html_header_sort_checkbox($display_text, grv('sort_column'), grv('sort_direction'), false);

	if (cacti_sizeof($providers)) {
		foreach ($providers as $provider) {
			form_alternate_row('line' . $provider['id'], true);

			$effective_id = db_fetch_cell_prepared('SELECT username
				FROM user_auth
				WHERE id = ?',
				[$provider['user_id']]);

			form_selectable_cell(filter_value($provider['name'], grv('filter'), 'login_providers.php?action=edit&id=' . $provider['id']), $provider['id']);
			form_selectable_cell($provider_types[$provider['type']] ?? __('Unknown'), $provider['id']);
			form_selectable_cell(($provider['is_default'] == '0' ? '--' : __('Yes')), $provider['id']);
			form_selectable_ecell(($provider['user_id'] == '0' ? __('None Selected') : $effective_id), $provider['id']);
			form_selectable_cell($provider['enabled'] == 'on' ? __('Yes') : __('No'), $provider['id']);
			form_checkbox_cell($provider['name'], $provider['id']);

			form_end_row();
		}
	} else {
		print '<tr class="tableRow odd"><td colspan="' . (cacti_sizeof($display_text) + 1) . '"><em>' . __('No Login Providers Found') . '</em></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($providers)) {
		print $nav;
	}

	// draw the dropdown containing a list of available actions for this form
	draw_actions_dropdown($actions);

	form_end();
}
