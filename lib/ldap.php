<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
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

/*
LDAP functions
*/

/* cacti_ldap_auth
  @arg $username - username of the user
  @arg $password - password of the user
  @arg $dn - LDAP DN for binding
  @arg $host - Hostname or IP of LDAP server, Default = Configured settings value
  @arg $port - Port of the LDAP server uses, Default = Configured settings value
  @arg $port_ssl - Port of the LDAP server uses for SSL, Default = Configured settings value
  @arg $version - '2' or '3', LDAP protocol version, Default = Configured settings value
  @arg $encryption - '0' None, '1' SSL, '2' TLS, Default = Configured settings value
  @arg $referrals - '0' Referrals from server are ignored, '1' Referrals from server are processed, Default = Configured setting value
  @arg $group_require - '0' Group membership is not required, '1' Group membership is required
  @arg $group_dn - LDAP Group DN
  @arg $group_attrib - Name of the LDAP Attrib that contains members
  @arg $group_require - '1' DN or '2' Username, user group member ship type

  @return - array of values
    'error_num' = error number returned
    'error_text' = error text

Error codes:

#	Text
==============================================================
0	Authentication Success
1	Authentication Failure
2	No username defined
3	Protocol error, unable to set version
4	Unable to set referrals option
5	Protocol error, unable to start TLS communications
6	Unable to create LDAP object
7	Protocol error
8	Insufficient access
9	Unable to connect to server
10	Timeout
11	General bind error
12	Group DN not found
99	PHP LDAP not enabled

*/
function cacti_ldap_auth($username, $password = '', $dn = '', $host = '', $port = '', $port_ssl = '', $version = '',
	$encryption = '', $referrals = '', $group_require = '', $group_dn = '', $group_attrib = '', $group_member_type = '') {

	$ldap = new Ldap;

	if (!empty($username))          $ldap->username          = $username;
	if (!empty($password))          $ldap->password          = $password;
	if (!empty($dn))                $ldap->dn                = $dn;
	if (!empty($host))              $ldap->host              = $host;
	if (!empty($port))              $ldap->port              = $port;
	if (!empty($port_ssl))          $ldap->port_ssl          = $port_ssl;
	if (!empty($version))           $ldap->version           = $version;
	if (!empty($encryption))        $ldap->encryption        = $encryption;
	if (!empty($referrals))         $ldap->referrals         = $referrals;
	if (!empty($group_require))     $ldap->group_require     = $group_require;
	if (!empty($group_dn))          $ldap->group_dn          = $group_dn;
	if (!empty($group_attrib))      $ldap->group_attrib      = $group_attrib;
	if (!empty($group_member_type)) $ldap->group_member_type = $group_member_type;

	return $ldap->Authenticate();
}

/* cacti_ldap_search_dn
  @arg $username - username to search for in the LDAP directory
  @arg $dn - configured LDAP DN for binding, '<username>' will be replaced with $username
  @arg $host - Hostname or IP of LDAP server, Default = Configured settings value
  @arg $port - Port of the LDAP server uses, Default = Configured settings value
  @arg $port_ssl - Port of the LDAP server uses for SSL, Default = Configured settings value
  @arg $version - '2' or '3', LDAP protocol version, Default = Configured settings value
  @arg $encryption - '0' None, '1' SSL, '2' TLS, Default = Configured settings value
  @arg $referrals - '0' Referrals from server are ignored, '1' Referrals from server are processed, Default = Configured setting value
  @arg $mode - '0' No Searching, '1' Anonymous Searching, '2' Specfic Searching, Default = Configured settings value
  @arg $search_base - Search base DN, Default = Configured settings value
  @arg $search_filter - Filter to find the user, Default = Configured settings value
  @arg $specific_dn - DN for binding to perform user search, Default = Configured settings value
  @arg $specific_password - Password for binding to perform user search, Default - Configured settings value

  @return - array of values
    'error_num' = error number returned
    'error_text' = error text
    'dn' = found dn of user

Error codes:

#	Text
==============================================================
0	User found
1	No username defined
2	Unable to create LDAP connection object
3	Unable to find users DN
4	Protocol error, unable to set version
5	Protocol error, unable to start TLS communications
6	Protocol error
7	Invalid credential
8	Insufficient access
9	Unable to connect to server
10	Timeout
11	General bind error
12	Unable to set referrals option
13	More than one matching user found
14	Specific DN and Password required
99	PHP LDAP not enabled

*/
function cacti_ldap_search_dn($username, $dn = '', $host = '', $port = '', $port_ssl = '', $version = '', $encryption = '',
	$referrals = '', $mode = '', $search_base = '', $search_filter = '', $specific_dn = '', $specific_password = '') {

	$ldap = new Ldap;

	if (!empty($username))          $ldap->username          = $username;
	if (!empty($dn))                $ldap->dn                = $dn;
	if (!empty($host))              $ldap->host              = $host;
	if (!empty($port))              $ldap->port              = $port;
	if (!empty($port_ssl))          $ldap->port_ssl          = $port_ssl;
	if (!empty($version))           $ldap->version           = $version;
	if (!empty($encryption))        $ldap->encryption        = $encryption;
	if (!empty($referrals))         $ldap->referrals         = $referrals;
	if (!empty($mode))              $ldap->mode              = $mode;
	if (!empty($search_base))       $ldap->search_base       = $search_base;
	if (!empty($search_filter))     $ldap->search_filter     = $search_filter;
	if (!empty($specific_dn))       $ldap->specific_dn       = $specific_dn;
	if (!empty($specific_password)) $ldap->specific_password = $specific_password;

	return $ldap->Search();
}

class Ldap {
	function __construct() {
		/* Initialize LDAP parameters for Authenticate */
		$this->dn         = read_config_option('ldap_dn');
		$this->host       = read_config_option('ldap_server');
		$this->port       = read_config_option('ldap_port');
		$this->port_ssl   = read_config_option('ldap_port_ssl');
		$this->version    = read_config_option('ldap_version');
		$this->encryption = read_config_option('ldap_encryption');
		$this->referrals  = read_config_option('ldap_referrals');

		if (read_config_option('ldap_group_require') == 'on') {
			$this->group_require = true;
		} else {
			$this->group_require = false;
		}

		$this->group_dn          = read_config_option('ldap_group_dn');
		$this->group_attrib      = read_config_option('ldap_group_attrib');
		$this->group_member_type = read_config_option('ldap_group_member_type');

		/* Initialize LDAP parameters for Search */
		$this->mode              = read_config_option('ldap_mode');
		$this->search_base       = read_config_option('ldap_search_base');
		$this->search_filter     = read_config_option('ldap_search_filter');
		$this->specific_dn       = read_config_option('ldap_specific_dn');
		$this->specific_password = read_config_option('ldap_specific_password');

		return true;
	}

	function __destruct() {
		return true;
	}

	function ErrorHandler($level, $message, $file, $line, $context) {
		return true;
	}

	function SetLdapHandler() {
		/* drop out of cactis error handler */
		restore_error_handler();

		/* set an error handler for ldap */
		set_error_handler(array($this, 'ErrorHandler'));
	}

	function RestoreCactiHandler() {
		/* drop out of ldaps error handler */
		restore_error_handler();

		/* set an error handler for Cacti */
		set_error_handler('CactiErrorHandler');
	}

	function Authenticate() {
		$output = array();

		/* function check */
		if (!function_exists('ldap_connect')) {
			$output['error_num'] = 99;
			$output['error_text'] = __('PHP LDAP not enabled');
			return $output;
		}

		/* validation */
		if (empty($this->username)) {
			$output['error_num'] = '2';
			$output['error_text'] = __('No username defined');

			return $output;
		}

		$this->dn = str_replace('<username>', $this->username, $this->dn);

		/* Fix encoding of username and password */
		$this->username = html_entity_decode($this->username, $this->GetMask(), 'UTF-8');
		$this->password = html_entity_decode($this->password, $this->GetMask(), 'UTF-8');

		$this->SetLdapHandler();

		/* Determine connection method and create LDAP Object */
		if ($this->encryption == '1') {
			/* This only works with OpenLDAP, I'm pretty sure this will not work with Solaris, Tony */
			$ldap_conn = ldap_connect('ldaps://' . $this->host . ':' . $this->port_ssl);
		} else {
			$ldap_conn = ldap_connect($this->host, $this->port);
		}

		if ($ldap_conn) {
			/* Set protocol version */
			cacti_log('LDAP: Setting protocol version to ' . $this->version, false, 'AUTH');

			if (!ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, $this->version)) {
				$output['error_num'] = '3';
				$output['error_text'] = __('Protocol Error, Unable to set version');
				cacti_log('LDAP: ' . $output['error_text'], false, 'AUTH');
				ldap_close($ldap_conn);
				$this->RestoreCactiHandler();
				return $output;
			}

			/* set referrals */
			if ($this->referrals == '0') {
				if (!ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0)) {
					$output['error_num'] = '4';
					$output['error_text'] = __('Unable to set referrals option');
					cacti_log('LDAP: ' . $output['error_text'], false, 'AUTH');
					ldap_close($ldap_conn);
					$this->RestoreCactiHandler();
					return $output;
				}
			}

			/* start TLS if requested */
			if ($this->encryption == '2') {
				if (!ldap_start_tls($ldap_conn)) {
					$output['error_num'] = '5';
					$output['error_text'] = __('Protocol Error, unable to start TLS communications');
					cacti_log('LDAP: ' . $output['error_text'], false, 'AUTH');
					ldap_close($ldap_conn);
					$this->RestoreCactiHandler();
					return $output;
				}
			}

			/* Bind to the LDAP directory */
			$ldap_response = ldap_bind($ldap_conn, $this->dn, $this->password);
			if ($ldap_response) {
				if ($this->group_require == 1) {
					$ldap_group_response = false;

					/* Process group membership if required */
					if ($this->group_member_type == 1) {
						$ldap_group_response = ldap_compare($ldap_conn, $this->group_dn, $this->group_attrib, $this->dn);
					} else if ($this->group_member_type == 2) {
						/* Do a lookup to find this user's true DN. */
						/* ldap_exop_whoami is not yet included in PHP. For reference, the
						 * feature request: http://bugs.php.net/bug.php?id=42060
						 * And the patch against lastest PHP release:
						 * http://cvsweb.netbsd.org/bsdweb.cgi/pkgsrc/databases/php-ldap/files/ldap-ctrl-exop.patch
						*/
						$true_dn_result = ldap_search($ldap_conn, $this->search_base, 'userPrincipalName=' . $this->dn, array('dn'));
						$first_entry    = ldap_first_entry($ldap_conn, $true_dn_result);

						/* we will test in two ways */
						if ($first_entry !== false) {
							$true_dn             = ldap_get_dn($ldap_conn, $first_entry);
							$ldap_group_response = ldap_compare($ldap_conn, $this->group_dn, $this->group_attrib, $true_dn);
						} else {
							$ldap_group_response = ldap_compare($ldap_conn, $this->group_dn, $this->group_attrib, $this->username);
						}
					}

					if ($ldap_group_response === true) {
						/* Auth ok */
						$output['error_num'] = '0';
						$output['error_text'] = __('Authentication Success');
					} else if ($ldap_group_response === false) {
						$output['error_num'] = '8';
						$output['error_text'] = __('Insufficient access');
						cacti_log('LDAP: ' . $output['error_text'], false, 'AUTH');
						ldap_close($ldap_conn);
						$this->RestoreCactiHandler();
						return $output;
					} else {
						$output['error_num'] = '12';
						$output['error_text'] = __('Group DN could not be found to compare');
						cacti_log('LDAP: ' . $output['error_text'], false, 'AUTH');
						ldap_close($ldap_conn);
						$this->RestoreCactiHandler();
						return $output;
					}
				} else {
					/* Auth ok - No group membership required */
					$output['error_num'] = '0';
					$output['error_text'] = __('Authentication Success');
				}
			} else {
				/* unable to bind */
				$ldap_error = ldap_errno($ldap_conn);
				if ($ldap_error == 0x03) {
					/* protocol error */
					$output['error_num'] = '7';
					$output['error_text'] = __('Protocol Error');
				} elseif ($ldap_error == 0x31) {
					/* invalid credentials */
					$output['error_num'] = '1';
					$output['error_text'] = __('Authentication Failure');
				} elseif ($ldap_error == 0x32) {
					/* insuffient access */
					$output['error_num'] = '8';
					$output['error_text'] = __('Insufficient Access');
				} elseif ($ldap_error == 0x51) {
					/* unable to connect to server */
					$output['error_num'] = '9';
					$output['error_text'] = __('Unable to Connect to Server');
				} elseif ($ldap_error == 0x55) {
					/* timeout */
					$output['error_num'] = '10';
					$output['error_text'] = __('Connection Timeout');
				} else {
					/* general bind error */
					$output['error_num'] = '11';
					$output['error_text'] = __('General Bind Error, LDAP result:') . ' ' . ldap_error($ldap_conn);
				}
			}
		} else {
			/* Error intializing LDAP */
			$output['error_num'] = '6';
			$output['error_text'] = __('Unable to Create LDAP Object');
		}

		/* Close LDAP connection */
		ldap_close($ldap_conn);

		if ($output['error_num'] > 0) {
			cacti_log('LDAP: ' . $output['error_text'], false, 'AUTH');
		}

		$this->RestoreCactiHandler();

		return $output;
	}

	function GetMask() {
		if (!defined('ENT_HTML401')) {
			return ENT_COMPAT;
		} else {
			return ENT_COMPAT | ENT_HTML401;
		}
	}

	function Search() {
		$output = array();

		/* function check */
		if (!function_exists('ldap_connect')) {
			$output['error_num'] = 99;
			$output['error_text'] = __('PHP LDAP not enabled');
			return $output;
		}

		/* validation */
		if (empty($this->username)) {
			$output['dn'] = '';
			$output['error_num'] = '1';
			$output['error_text'] = __('No username defined');
			cacti_log('LDAP_SEARCH: No username defined', false, 'AUTH');
			return $output;
		}

		/* Encode username */
		$this->username = html_entity_decode($this->username, $this->GetMask(), 'UTF-8');

		/* strip bad chars from username - prevent altering filter from username */
		$this->username = str_replace(array('&', '|', '(', ')', '*', '>', '<', '!', '='), '', $this->username);
		$this->dn = str_replace('<username>', $this->username, $this->dn);

		if ($this->mode == '0') {
			/* Just bind mode, make dn and return */
			$output['dn'] = $this->dn;
			$output['error_num'] = '0';
			$output['error_text'] = __('User found');
			return $output;
		} elseif ($this->mode == '2') {
			/* Specific */
			if (empty($this->specific_dn) || empty($this->specific_password)) {
				$output['dn'] = $this->dn;
				$output['error_num'] = '14';
				$output['error_text'] = __('Specific DN and Password required');
				return $output;
			}
		} elseif ($this->mode == '1'){
			/* assume anonymous */
			$this->specific_dn       = '';
			$this->specific_password = '';
		}

		$this->search_filter = str_replace('<username>', $this->username, $this->search_filter);

		/* Fix encoding on ldap specific search DN and password */
		$this->specific_password = html_entity_decode($this->specific_password, $this->GetMask(), 'UTF-8');
		$this->specific_dn       = html_entity_decode($this->specific_dn, $this->GetMask(), 'UTF-8');

		/* Searching mode */
		if ($this->encryption == '1') {
			/* This only works with OpenLDAP, I'm pretty sure this will not work with Solaris, Tony */
			$ldap_conn = @ldap_connect('ldaps://' . $this->host . ':' . $this->port_ssl);
		} else {
			$ldap_conn = @ldap_connect($this->host, $this->port);
		}

		if ($ldap_conn) {
			/* Set protocol version */
			if (!@ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, $this->version)) {
				/* protocol error */
				$output['dn'] = '';
				$output['error_num'] = '4';
				$output['error_text'] = __('Protocol Error, unable to set version');
				cacti_log('LDAP_SEARCH: ' . $output['error_text'], false, 'AUTH');
				@ldap_close($ldap_conn);
				return $output;
			}

			/* set referrals */
			if ($this->referrals == '0') {
				if (!@ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0)) {
					/* referrals set error */
					$output['dn'] = '';
					$output['error_num'] = '13';
					$output['error_text'] = __('Unable to set referrals option');
					cacti_log('LDAP_SEARCH: ' . $output['error_text'], false, 'AUTH');
					@ldap_close($ldap_conn);
					return $output;
				}
			}

			/* start TLS if requested */
			if ($this->encryption == '2') {
				if (!@ldap_start_tls($ldap_conn)) {
					/* TLS startup error */
					$output['dn'] = '';
					$output['error_num'] = '5';
					$output['error_text'] = __('Protocol Error, unable to start TLS communications');
					cacti_log('LDAP_SEARCH: ' . $output['error_text'], false, 'AUTH');
					@ldap_close($ldap_conn);
					return $output;
				}
			}

			/* bind to the directory */
			if (ldap_bind($ldap_conn, $this->specific_dn, $this->specific_password)) {
				/* Search */
				$ldap_results = ldap_search($ldap_conn, $this->search_base, $this->search_filter, array('dn'));
				if ($ldap_results) {
					$ldap_entries =  ldap_get_entries($ldap_conn, $ldap_results);

					if ($ldap_entries['count'] == '1') {
						/* single response return user dn */
						$output['dn'] = $ldap_entries['0']['dn'];
						$output['error_num'] = '0';
						$output['error_text'] = __('User found');
						cacti_log('LDAP_SEARCH: ' . $output['error_text'] . ', DN \'' . $output['dn'] . '\'', false, 'AUTH');
					} elseif ($ldap_entries['count'] > 1) {
						/* more than 1 result */
						$output['dn'] = '';
						$output['error_num'] = '13';
						$output['error_text'] = __('More than one matching user found');
					} else {
						/* no search results */
						$output['dn'] = '';
						$output['error_num'] = '3';
						$output['error_text'] = __('Unable to find users DN');
					}
				} else {
					/* no search results, user not found*/
					$output['dn'] = '';
					$output['error_num'] = '3';
					$output['error_text'] = __('Unable to find users DN');
				}
			} else {
				/* unable to bind */
				$ldap_error = ldap_errno($ldap_conn);
				if ($ldap_error == 0x03) {
					/* protocol error */
					$output['dn'] = '';
					$output['error_num'] = '6';
					$output['error_text'] = __('Protocol Error');
				} elseif ($ldap_error == 0x31) {
					/* invalid credentials */
					$output['dn'] = '';
					$output['error_num'] = '7';
					$output['error_text'] = __('Invalid Credentials');
				} elseif ($ldap_error == 0x32) {
					/* insuffient access */
					$output['dn'] = '';
					$output['error_num'] = '8';
					$output['error_text'] = __('Insufficient Access');
				} elseif ($ldap_error == 0x51) {
					/* unable to connect to server */
					$output['dn'] = '';
					$output['error_num'] = '9';
					$output['error_text'] = __('Unable to Connect to Server');
				} elseif ($ldap_error == 0x55) {
					/* timeout */
					$output['dn'] = '';
					$output['error_num'] = '10';
					$output['error_text'] = __('Connection Timeout');
				} else {
					/* general bind error */
					$output['dn'] = '';
					$output['error_num'] = '11';
					$output['error_text'] = __('General Bind Error, LDAP result:') . ' ' . ldap_error($ldap_conn);
				}
			}
		} else {
			/* unable to setup connection */
			$output['dn'] = '';
			$output['error_num'] = '2';
			$output['error_text'] = __('Unable to create LDAP connection object');
		}

		@ldap_close($ldap_conn);

		if ($output['error_num'] > 0) {
			cacti_log('LDAP_SEARCH: ' . $output['error_text'], false, 'AUTH');
		}

		return $output;
	}
}

