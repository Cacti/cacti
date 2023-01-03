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

	/* If the server list is a space delimited set of servers
	 * process each server until you get a bind, or fail
	 */
	$ldap_servers = preg_split('/\s+/', $ldap->host);

	foreach($ldap_servers as $ldap_server) {
		$ldap->host = $ldap_server;

		$response = $ldap->Authenticate();

		if ($response['error_num'] == 0) {
			return $response;
		}
	}

	return $response;
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
  @arg $mode - '0' No Searching, '1' Anonymous Searching, '2' Specific Searching, Default = Configured settings value
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
0	Authentication Success
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
15	Unable to find user from DN
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

	/* If the server list is a space delimited set of servers
	 * process each server until you get a bind, or fail
	 */
	$ldap_servers = preg_split('/\s+/', $ldap->host);

	foreach($ldap_servers as $ldap_server) {
		$ldap->host = $ldap_server;

		$response = $ldap->Search();

		if ($response['error_num'] == 0) {
			return $response;
		}
	}

	return $response;
}

/* cacti_ldap_search_cn
  @arg $username - username to search for in the LDAP directory
  @arg $cn - array of CN to search on LDAP
  @arg $dn - configured LDAP DN for binding, '<username>' will be replaced with $username
  @arg $host - Hostname or IP of LDAP server, Default = Configured settings value
  @arg $port - Port of the LDAP server uses, Default = Configured settings value
  @arg $port_ssl - Port of the LDAP server uses for SSL, Default = Configured settings value
  @arg $version - '2' or '3', LDAP protocol version, Default = Configured settings value
  @arg $encryption - '0' None, '1' SSL, '2' TLS, Default = Configured settings value
  @arg $referrals - '0' Referrals from server are ignored, '1' Referrals from server are processed, Default = Configured setting value
  @arg $mode - '0' No Searching, '1' Anonymous Searching, '2' Specific Searching, Default = Configured settings value
  @arg $search_base - Search base DN, Default = Configured settings value
  @arg $search_filter - Filter to find the user, Default = Configured settings value
  @arg $specific_dn - DN for binding to perform user search, Default = Configured settings value
  @arg $specific_password - Password for binding to perform user search, Default - Configured settings value
  @return - array of values
    'cn' = array of values
    'error_num' = error number returned
    'error_text' = error text
    'dn' = found dn of user
Error codes:
#       Text
==============================================================
0       User found
1       No username defined
2       Unable to create LDAP connection object
3       Unable to find users DN
4       Protocol error, unable to set version
5       Protocol error, unable to start TLS communications
6       Protocol error
7       Invalid credential
8       Insufficient access
9       Unable to connect to server
10      Timeout
11      General bind error
12      Unable to set referrals option
13      More than one matching user found
14      Specific DN and Password required
15      CN unknown on LDAP
99      PHP LDAP not enabled
*/
function cacti_ldap_search_cn($username, $cn = array(), $dn = '', $host = '', $port = '', $port_ssl = '', $version = '', $encryption = '',
	$referrals = '', $mode = '', $search_base = '', $search_filter = '', $specific_dn = '', $specific_password = '') {

	$ldap = new Ldap;

	if (!empty($username))          $ldap->username          = $username;
	if (!empty($cn))                $ldap->cn                = $cn;
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

	return $ldap->Getcn();
}

abstract class LdapError {
	const None                  = 0;
	const Success               = 0;
	const Failure               = 1;
	const UndefinedUsername     = 2;
	const ProtocolErrorVersion  = 3;
	const ProtocolErrorReferral = 4;
	const ProtocolErrorTls      = 5;
	const MissingLdapObject     = 6;
	const ProtocolErrorGeneral  = 7;
	const InsufficientAccess    = 8;
	const ConnectionUnavailable = 9;
	const ConnectionTimeout     = 10;
	const ProtocolErrorBind     = 11;
	const SearchFoundNoGroup    = 12;
	const SearchFoundMultiUser  = 13;
	const SearchFoundNoUser     = 14;
	const SearchFoundNoUserDN   = 15;
	const UndefinedDnOrPassword = 16;
	const EmptyPassword         = 17;
	const Disabled              = 99;

	public static function GetErrorDetails($returnError, $ldapConn = null, $ldapServer = '', $ldapError = 0) {
		$error_num  = $returnError;
		$error_text = '';

		if ($ldapConn && $ldapError == 0) {
			$ldapError = ldap_error($ldapConn);
		}

		switch ($returnError) {
			case LdapError::None:
			case LdapError::Success:
				$error_text = __('Authentication Success');
				break;

			case LdapError::Failure:
				$error_text = __('Authentication Failure');
				break;

			case LdapError::Disabled:
				$error_text = __('PHP LDAP not enabled');
				break;

			case LdapError::UndefinedUsername:
				$error_text = __('No username defined');
				break;

			case LdapError::ProtocolErrorVersion:
				$error_text = __('Protocol Error, Unable to set version (%s) on Server (%s)', $ldapError, $ldapServer);
				break;

			case LdapError::ProtocolErrorReferral:
				$error_text = __('Protocol Error, Unable to set referrals option (%s) on Server (%s)', $ldapError, $ldapServer);
				break;

			case LdapError::ProtocolErrorTls:
				$error_text = __('Protocol Error, unable to start TLS communications (%s) on Server (%s)', $ldapError, $ldapServer);
				break;

			case LdapError::ProtocolErrorGeneral:
				$error_text = __('Protocol Error, General failure (%s)', $ldapError, $ldapServer);
				break;

			case LdapError::ProtocolErrorBind:
				$error_text = __('Protocol Error, Unable to bind, LDAP result: (%s) on Server (%s)', $ldapError, $ldapServer);
				break;

			case LdapError::ConnectionUnavailable:
				$error_text = __('Unable to Connect to Server (%s)', $ldapServer);
				break;

			case LdapError::ConnectionTimeout:
				$error_text =  __('Connection Timeout to Server (%s)', $ldapServer);
				break;

			case LdapError::InsufficientAccess:
				$error_text = __('Insufficient Access to Server (%s)', $ldapServer);
				break;

			case LdapError::SearchFoundNoGroup:
				$error_text = __('Group DN could not be found to compare on Server (%s)', $ldapServer);
				break;

			case LdapError::SearchFoundMultiUser:
				$error_text = __('More than one matching user found');
				break;

			case LdapError::SearchFoundNoUserDN:
				$error_text = __('Unable to find user from DN');
				break;

			case LdapError::SearchFoundNoUser:
				$error_text = __('Unable to find users DN');
				break;

			case LdapError::MissingLdapObject:
				$error_text = __('Unable to create LDAP connection object to Server (%s)', $ldapServer);
				break;

			case LdapError::UndefinedDnOrPassword:
				$error_text = __('Specific DN and Password required');
				break;

			case LdapError::EmptyPassword:
				$error_text = __('Invalid Password provided.  Login failed.');
				break;

			default:
				$error_text = __('Unexpected error %s (Ldap Error: %s) on Server (%s)', $returnError, $ldapError, $ldapServer);
				break;
		}

		return array(
			'error_num'  => $error_num,
			'error_text' => $error_text,
			'error_ldap' => $ldapError,
			'dn'         => '',
			'stack'      => cacti_debug_backtrace('', false, false)
		);
	}
}

class Ldap {
	public $dn;
	public $host;
	public $username;
	public $password;
	public $port;
	public $port_ssl;
	public $version;
	public $encryption;
	public $referrals;
	public $debug;
	public $group_require;
	public $group_dn;
	public $group_attrib;
	public $group_member_type;
	public $mode;
	public $search_base;
	public $search_filter;
	public $specific_dn;
	public $specific_password;

	function __construct() {
		/* Initialize LDAP parameters for Authenticate */
		$this->dn         = read_config_option('ldap_dn');
		$this->host       = read_config_option('ldap_server');
		$this->port       = read_config_option('ldap_port');
		$this->port_ssl   = read_config_option('ldap_port_ssl');
		$this->version    = read_config_option('ldap_version');
		$this->encryption = read_config_option('ldap_encryption');
		$this->referrals  = read_config_option('ldap_referrals');
		$this->debug      = read_config_option('ldap_debug');

		if ($this->debug == 'on') {
			$this->debug = POLLER_VERBOSITY_LOW;
		} else {
			$this->debug = POLLER_VERBOSITY_HIGH;
		}

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

	function ErrorHandler($level, $message, $file, $line, $context = []) {
		return true;
	}

	function SetLdapHandler() {
		/* drop out of cactis error handler */
		restore_error_handler();

		/* set an error handler for ldap */
		set_error_handler(array($this, 'ErrorHandler'));

		cacti_session_close();
	}

	function RestoreCactiHandler() {
		/* drop out of ldaps error handler */
		restore_error_handler();

		/* set an error handler for Cacti */
		set_error_handler('CactiErrorHandler');

		cacti_session_start();
	}

	function RecordError($output, $section = 'LDAP') {
		$logDN = empty($output['dn']) ? '' : (', DN: ' . $output['dn']);
		cacti_log($section . ': ' . $output['error_text'] . $logDN, false, 'AUTH');
		cacti_log($section . ': ' . $output['stack'], false, 'AUTH', $this->debug);
	}

	function Connect() {
		$output    = array();
		$ldap_conn = null;

		/* function check */
		if (!function_exists('ldap_connect')) {
			return array(
				'ldap_conn' => $ldap_conn,
				'output' => LdapError::GetErrorDetails(LdapError::Disabled)
			);
		}

		/* validation */
		if (empty($this->username)) {
			return array(
				'ldap_conn' => $ldap_conn,
				'output' => LdapError::GetErrorDetails(LdapError::UndefinedUsername)
			);
		}

		/**
		 * NOTE: The next several settings must be made prior to initial LDAP connection
		 */

		/* Set debug if selective debug is enabled.  This places log data into the apache error_log */
		if (get_selective_log_level() == POLLER_VERBOSITY_DEBUG) {
			cacti_log('LDAP: Setting php-ldap into DEBUG mode.  Check your Web Server error_log for details', false, 'AUTH', $this->debug);
			ldap_set_option(null, LDAP_OPT_DEBUG_LEVEL, 7);
		}

		if (getenv('TLS_CERT') != '' && defined('LDAP_OPT_X_TLS_CERTFILE')) {
			cacti_log('NOTE: Settings TLS_CERT to ' . getenv('TLS_CERT'), false, 'AUTH', $this->debug);
			ldap_set_option(null, LDAP_OPT_X_TLS_CERTFILE, getenv('TLS_CERT'));
		}

		if (getenv('TLS_CACERT') != '' && defined('LDAP_OPT_X_TLS_CACERTFILE')) {
			cacti_log('NOTE: Settings TLS_CACERT to ' . getenv('TLS_CACERT'), false, 'AUTH', $this->debug);
			ldap_set_option(null, LDAP_OPT_X_TLS_CACERTFILE, getenv('TLS_CACERT'));
		}

		if (getenv('TLS_KEY') != '' && defined('LDAP_OPT_X_TLS_KEYFILE')) {
			cacti_log('NOTE: Settings TLS_KEY to ' . getenv('TLS_KEY'), false, 'AUTH', $this->debug);
			ldap_set_option(null, LDAP_OPT_X_TLS_KEYFILE, getenv('TLS_KEY'));
		}

		if (getenv('TLS_CACERTDIR') != '' && defined('LDAP_OPT_X_TLS_CACERTDIR')) {
			cacti_log('NOTE: Settings TLS_CACERTDIR to ' . getenv('TLS_CACERTDIR'), false, 'AUTH', $this->debug);
			ldap_set_option(null, LDAP_OPT_X_TLS_CACERTDIR, getenv('TLS_CACERTDIR'));
		}

		if ($this->encryption >= 1) {
			$cert = read_config_option('ldap_tls_certificate');
			if ($cert == '') {
				$cert = LDAP_OPT_X_TLS_NEVER;
			}

			// For good measure, we will use both the php function and set the environment
			switch($cert) {
				case LDAP_OPT_X_TLS_NEVER:
					cacti_log('NOTE: Setting TLS Certificate Requirement to \'never\'', false, 'AUTH', $this->debug);
					putenv('TLS_REQCERT=never');
					break;
				case LDAP_OPT_X_TLS_HARD:
					cacti_log('NOTE: Setting TLS Certificate Requirement to \'hard\'', false, 'AUTH', $this->debug);
					putenv('TLS_REQCERT=hard');
					break;
				case LDAP_OPT_X_TLS_DEMAND:
					cacti_log('NOTE: Setting TLS Certificate Requirement to \'demand\'', false, 'AUTH', $this->debug);
					putenv('TLS_REQCERT=demand');
					break;
				case LDAP_OPT_X_TLS_ALLOW:
					cacti_log('NOTE: Setting TLS Certificate Requirement to \'allow\'', false, 'AUTH', $this->debug);
					putenv('TLS_REQCERT=allow');
					break;
				case LDAP_OPT_X_TLS_TRY:
					cacti_log('NOTE: Setting TLS Certificate Requirement to \'try\'', false, 'AUTH', $this->debug);
					putenv('TLS_REQCERT=try');
					break;
			}

			ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, $cert);
		}

		// Walk through ldap servers for a valid connections
		if ($this->encryption == '1') {
			cacti_log('LDAP: Connect using ldaps://' . $this->host . ':' . $this->port_ssl, false, 'AUTH', $this->debug);
			$ldap_conn = ldap_connect('ldaps://' . $this->host . ':' . $this->port_ssl);
		} else {
			cacti_log('LDAP: Connect using ldap://'. $this->host . ':' . $this->port, false, 'AUTH', $this->debug);
			$ldap_conn = ldap_connect($this->host, $this->port);
		}

		if ($ldap_conn) {
			/* Set protocol version */
			cacti_log('LDAP: Setting protocol version to ' . $this->version, false, 'AUTH', POLLER_VERBOSITY_MEDIUM);

			if (!ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, $this->version)) {
				$output = LdapError::GetErrorDetails(LdapError::ProtocolErrorVersion, $ldap_conn, $this->host);
				Ldap::RecordError($output);
				ldap_close($ldap_conn);

				return array(
					'ldap_conn' => $ldap_conn,
					'output'    => $output
				);
			}

			/* set reasonable timeouts */
			$network_timeout = read_config_option('ldap_network_timeout');
			if (defined('LDAP_OPT_NETWORK_TIMEOUT')) {
				cacti_log("NOTE: Setting Network Timeout to $network_timeout seconds", false, 'AUTH', $this->debug);
				ldap_set_option($ldap_conn, LDAP_OPT_NETWORK_TIMEOUT, $network_timeout);
			}

			$bind_timeout = read_config_option('ldap_bind_timeout');
			if (defined('LDAP_OPT_TIMELIMIT')) {
				cacti_log("NOTE: Setting Bind Timeout to $bind_timeout seconds", false, 'AUTH', $this->debug);
				ldap_set_option($ldap_conn, LDAP_OPT_TIMEOUT, $bind_timeout);
			}

			/* set referrals */
			if ($this->referrals == '0') {
				if (!ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0)) {
					$output = LdapError::GetErrorDetails(LdapError::ProtocolErrorReferral, $ldap_conn, $this->host);
					Ldap::RecordError($output);
					ldap_close($ldap_conn);

					return array(
						'ldap_conn' => $ldap_conn,
						'output'    => $output
					);
				}
			}

			/* start TLS if requested */
			if ($this->encryption == '2') {
				if (!ldap_start_tls($ldap_conn)) {
					$output = LdapError::GetErrorDetails(LdapError::ProtocolErrorTls, $ldap_conn, $this->host);
					Ldap::RecordError($output);
					ldap_close($ldap_conn);

					return array(
						'ldap_conn' => $ldap_conn,
						'output'    => $output
					);
				}
			}
		}

		return array('ldap_conn' => $ldap_conn, 'output' => $output);
	}

	function Authenticate() {
		$output = array();

		/* Determine connection method and create LDAP Object */
		$this->SetLdapHandler();

		$connection = $this->Connect();

		if (cacti_sizeof($connection['output'])) {
			$this->RestoreCactiHandler();
			return $connection['output'];
		} elseif ($connection['ldap_conn'] === false) {
			$this->RestoreCactiHandler();
			return LdapError::GetErrorDetails(LdapError::MissingLdapObject, false, $this->host);
		}

		$ldap_conn = $connection['ldap_conn'];

		/* Decode username, and remove bad characters */
		$this->username = html_entity_decode($this->username, $this->GetMask(), 'UTF-8');
		$this->username = str_replace(array('&', '|', '(', ')', '*', '>', '<', '!', '='), '', $this->username);
		$this->password = html_entity_decode($this->password, $this->GetMask(), 'UTF-8');
		$this->dn = str_replace('<username>', $this->username, $this->dn);

		if ($this->password == '') {
			return LdapError::GetErrorDetails(LdapError::EmptyPassword);
		}

		/* Bind to the LDAP directory */
		cacti_log('LDAP: Binding with "' . $this->dn . '"', false, 'AUTH', $this->debug);
		$ldap_response = ldap_bind($ldap_conn, $this->dn, $this->password);
		if ($ldap_response) {
			if ($this->group_require == 1) {
				$ldap_group_response = false;

				/* Process group membership if required */
				if ($this->group_member_type == 1) {
					$ldap_group_response = ldap_compare($ldap_conn, $this->group_dn, $this->group_attrib, $this->dn);
					if (!$ldap_group_response) {
						$ldap_group_response = Ldap::isUserInLDAPGroup($ldap_conn, $this->search_base, $this->group_dn, $this->dn);
					}
				} elseif ($this->group_member_type == 2) {
					/* Do a lookup to find this user's true DN. */
					/* ldap_exop_whoami is not yet included in PHP. For reference, the
					 * feature request: http://bugs.php.net/bug.php?id=42060
					 * And the patch against latest PHP release:
					 * http://cvsweb.netbsd.org/bsdweb.cgi/pkgsrc/databases/php-ldap/files/ldap-ctrl-exop.patch
					*/
					$true_dn_result = ldap_search($ldap_conn, $this->search_base, 'userPrincipalName=' . $this->dn, array('dn'));
					$first_entry    = ldap_first_entry($ldap_conn, $true_dn_result);

					/* we will test in two ways */
					if ($first_entry !== false) {
						$true_dn     = ldap_get_dn($ldap_conn, $first_entry);
						$ldap_group_response = ldap_compare($ldap_conn, $this->group_dn, $this->group_attrib, $true_dn);
					} else {
						$ldap_group_response = ldap_compare($ldap_conn, $this->group_dn, $this->group_attrib, $this->username);
					}
				}

				if ($ldap_group_response === true) {
					/* Auth ok */
					$output = LdapError::GetErrorDetails(LdapError::Success);
				} elseif ($ldap_group_response === false) {
					$output = LdapError::GetErrorDetails(LdapError::InsufficientAccess, $ldap_conn, $this->host);
					Ldap::RecordError($output);
					ldap_close($ldap_conn);
					$this->RestoreCactiHandler();
					return $output;
				} else {
					$output = LdapError::GetErrorDetails(LdapError::SearchFoundNoGroup, $ldap_conn, $this->host);
					Ldap::RecordError($output);
					ldap_close($ldap_conn);
					$this->RestoreCactiHandler();
					return $output;
				}
			} else {
				/* Auth ok - No group membership required */
				$output = LdapError::GetErrorDetails(LdapError::Success);
			}
		} else {
			/* unable to bind */
			$ldap_error = ldap_errno($ldap_conn);
			if ($ldap_error == 0x02) {
				/* protocol error */
				$output = LdapError::GetErrorDetails(LdapError::ProtocolErrorGeneral, $ldap_conn, $this->host);
			} elseif ($ldap_error == 0x31) {
				/* invalid credentials */
				$output = LdapError::GetErrorDetails(LdapError::Failure, $ldap_conn, $this->host);
			} elseif ($ldap_error == 0x32) {
				/* insufficient access */
				$output = LdapError::GetErrorDetails(LdapError::InsufficientAccess, $ldap_conn, $this->host);
			} elseif ($ldap_error == 0x51) {
				/* unable to connect to server */
				$output = LdapError::GetErrorDetails(LdapError::ConnectionUnavailable, $ldap_conn, $this->host);
			} elseif ($ldap_error == 0x55) {
				/* timeout */
				$output = LdapError::GetErrorDetails(LdapError::ConnectionTimeout, $ldap_conn, $this->host);
			} else {
				/* general bind error */
				$output = LdapError::GetErrorDetails(LdapError::ProtocolErrorBind, $ldap_conn, $this->host);
			}
		}

		/* Close LDAP connection */
		ldap_close($ldap_conn);

		if ($output['error_num'] > 0) {
			Ldap::RecordError($output);
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

		/* Determine connection method and create LDAP Object */
		$this->SetLdapHandler();

		$connection = $this->Connect();

		if (cacti_sizeof($connection['output'])) {
			$this->RestoreCactiHandler();
			return $connection['output'];
		} elseif ($connection['ldap_conn'] === false) {
			$this->RestoreCactiHandler();
			return LdapError::GetErrorDetails(LdapError::MissingLdapObject, false, $this->host);
		}

		$ldap_conn = $connection['ldap_conn'];

		/* Decode username, and remove bad characters */
		$this->username = html_entity_decode($this->username, $this->GetMask(), 'UTF-8');
		$this->username = str_replace(array('&', '|', '(', ')', '*', '>', '<', '!', '='), '', $this->username);
		$this->dn       = str_replace('<username>', $this->username, $this->dn);

		if ($this->mode == '0') {
			/* Just bind mode, make dn and return */
			$output = LdapError::GetErrorDetails(LdapError::Success);
			$output['dn'] = $this->dn;
			$this->RestoreCactiHandler();
			return $output;
		} elseif ($this->mode == '2') {
			/* Specific */
			if (empty($this->specific_dn) || empty($this->specific_password)) {
				$output = LdapError::GetErrorDetails(LdapError::UndefinedDnOrPassword);
				$output['dn'] = $this->dn;
				Ldap::RecordError($output, 'LDAP_SEARCH');
				$this->RestoreCactiHandler();
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

		/* bind to the directory */
		if (ldap_bind($ldap_conn, $this->specific_dn, $this->specific_password)) {
			/* Search */
			$ldap_results = ldap_search($ldap_conn, $this->search_base, $this->search_filter, array('dn'));
			if ($ldap_results) {
				$ldap_entries = ldap_get_entries($ldap_conn, $ldap_results);

				if ($ldap_entries['count'] == '1') {
					/* single response return user dn */
					$output = LdapError::GetErrorDetails(LdapError::Success);
					$output['dn'] = $ldap_entries['0']['dn'];
					Ldap::RecordError($output, 'LDAP_SEARCH');
				} elseif ($ldap_entries['count'] > 1) {
					/* more than 1 result */
					$output = LdapError::GetErrorDetails(LdapError::SearchFoundMultiUser);
				} else {
					/* no search results */
					$output = LdapError::GetErrorDetails(LdapError::SearchFoundNoUserDN);
				}
			} else {
				/* no search results, user not found*/
				$output = LdapError::GetErrorDetails(LdapError::SearchFoundNoUser);
			}
		} else {
			/* unable to bind */
			$ldap_error = ldap_errno($ldap_conn);
			if ($ldap_error == 0x02) {
				/* protocol error */
				$output = LdapError::GetErrorDetails(LdapError::ProtocolErrorGeneral, $ldap_conn, $this->host);
			} elseif ($ldap_error == 0x31) {
				/* invalid credentials */
				$output = LdapError::GetErrorDetails(LdapError::Failure, $ldap_conn, $this->host);
			} elseif ($ldap_error == 0x32) {
				/* insufficient access */
				$output = LdapError::GetErrorDetails(LdapError::InsufficientAccess, $ldap_conn, $this->host);
			} elseif ($ldap_error == 0x51) {
				/* unable to connect to server */
				$output = LdapError::GetErrorDetails(LdapError::ConnectionUnavailable, $ldap_conn, $this->host);
			} elseif ($ldap_error == 0x55) {
				/* timeout */
				$output = LdapError::GetErrorDetails(LdapError::ConnectionTimeout, $ldap_conn, $this->host);
			} else {
				/* general bind error */
				$output = LdapError::GetErrorDetails(LdapError::ProtocolErrorBind, $ldap_conn, $this->host);
			}
		}

		ldap_close($ldap_conn);

		if ($output['error_num'] > 0) {
			Ldap::RecordError($output, 'LDAP_SEARCH');
		}

		$this->RestoreCactiHandler();

		return $output;
	}

	function Getcn() {
		$output = array();

		/* Determine connection method and create LDAP Object */
		$this->SetLdapHandler();

		$connection = $this->Connect();

		if (cacti_sizeof($connection['output'])) {
			$this->RestoreCactiHandler();
			return $connection['output'];
		} elseif ($connection['ldap_conn'] === false) {
			$this->RestoreCactiHandler();
			return LdapError::GetErrorDetails(LdapError::MissingLdapObject, false, $this->host);
		}

		$ldap_conn = $connection['ldap_conn'];

		/* Decode username, and remove bad characters */
		$this->username = html_entity_decode($this->username, $this->GetMask(), 'UTF-8');
		$this->username = str_replace(array('&', '|', '(', ')', '*', '>', '<', '!', '='), '', $this->username);
		$this->dn       = str_replace('<username>', $this->username, $this->dn);

		if ($this->mode == '0') {
			/* Just bind mode, make dn and return */
			$output = LdapError::GetErrorDetails(LdapError::Success);
			$output['dn'] = $this->dn;
			return $output;
		} elseif ($this->mode == '2') {
			/* Specific */
			if (empty($this->specific_dn) || empty($this->specific_password)) {
				$output = LdapError::GetErrorDetails(LdapError::UndefinedDnOrPassword);
				$output['dn'] = $this->dn;
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

		/* bind to the directory */
		if (ldap_bind($ldap_conn, $this->specific_dn, $this->specific_password)) {
			/* Search */
			$ldap_results = ldap_search($ldap_conn, $this->search_base, $this->search_filter, $this->cn);

			if ($ldap_results) {
				$ldap_entries =  ldap_get_entries($ldap_conn, $ldap_results);
				/* We find 1 entries */
				if ($ldap_entries['count'] == 1) {
					$output = LdapError::GetErrorDetails(LdapError::Success);
					// check if we got an full username entry
					if (array_key_exists($this->cn[0], $ldap_entries[0])) {
						$output['cn'][$this->cn[0]] = $ldap_entries[0][$this->cn[0]][0];
					} else {
						$output['cn'][$this->cn[0]] = '';
					}

					// check if we got an email entry
					if(array_key_exists($this->cn[1], $ldap_entries[0])) {
						$output['cn'][$this->cn[1]] = $ldap_entries[0][$this->cn[1]][0];
					} else {
						$output['cn'][$this->cn[1]] = '';
					}
				} else {
					$output = LdapError::GetErrorDetails(LdapError::SearchFoundMultiUser);
				}
			} else {
				/* no search results, user not found*/
				$output = LdapError::GetErrorDetails(LdapError::SearchFoundNoUserDN);
			}
		} else {
			/* unable to bind */
			$ldap_error = ldap_errno($ldap_conn);
			if ($ldap_error == 0x02) {
				/* protocol error */
				$output = LdapError::GetErrorDetails(LdapError::ProtocolErrorGeneral, $ldap_conn, $this->host);
			} elseif ($ldap_error == 0x31) {
				/* invalid credentials */
				$output = LdapError::GetErrorDetails(LdapError::Failure, $ldap_conn, $this->host);
			} elseif ($ldap_error == 0x32) {
				/* insufficient access */
				$output = LdapError::GetErrorDetails(LdapError::InsufficientAccess, $ldap_conn, $this->host);
			} elseif ($ldap_error == 0x51) {
				/* unable to connect to server */
				$output = LdapError::GetErrorDetails(LdapError::ConnectionUnavailable, $ldap_conn, $this->host);
			} elseif ($ldap_error == 0x55) {
				/* timeout */
				$output = LdapError::GetErrorDetails(LdapError::ConnectionTimeout, $ldap_conn, $this->host);
			} else {
				/* general bind error */
				$output = LdapError::GetErrorDetails(LdapError::ProtocolErrorBind, $ldap_conn, $this->host);
			}
		}

		ldap_close($ldap_conn);

		if ($output['error_num'] > 0) {
			Ldap::RecordError($output, 'LDAP_SEARCH_CN');
		}

		$this->RestoreCactiHandler();

		return $output;
	}

	function isUserInLDAPGroup($ldapConn, $ldapbasedn, $groupDN, $ldapUser) {
		$query       = "(&(distinguishedName=$ldapUser)(memberOf:1.2.840.113556.1.4.1941:=$groupDN))";
		$ldapSearch  = @ldap_search($ldapConn,$ldapbasedn,$query,array("dn"));
		$ldapResults = @ldap_get_entries($ldapConn, $ldapSearch);

		// user should only be returned once IF they're a member of the group
		return isset($ldapResults['count']) && $ldapResults['count'] == 1 ? true:false;
	}
}

