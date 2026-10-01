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

// LDAP functions

/** cacti_ldap_auth
 *
 * Return values
 * 'error_num' = error number returned
 * 'error_text' = error text
 *
 * @deprecated: 1.3
 *
 * Error codes:
 *
 * #	Text
 * ==============================================================
 * 0	Authentication Success
 * 1	Authentication Failure
 * 2	No username defined
 * 3	Protocol error, unable to set version
 * 4	Unable to set referrals option
 * 5	Protocol error, unable to start TLS communications
 * 6	Unable to create LDAP object
 * 7	Protocol error
 * 8	Insufficient access
 * 9	Unable to connect to server
 * 10	Timeout
 * 11	General bind error
 * 12	Group DN not found
 * 99	PHP LDAP not enabled
 *
 * @param string $username          - username of the user
 * @param string $password          - password of the user
 * @param string $dn                - LDAP DN for binding
 * @param string $host              - Hostname or IP of LDAP server, Default = Configured settings value
 * @param int    $port              - Port of the LDAP server uses, Default = Configured settings value
 * @param int    $port_ssl          - Port of the LDAP server uses for SSL, Default = Configured settings value
 * @param int    $version           - '2' or '3', LDAP protocol version, Default = Configured settings value
 * @param int    $encryption        - '0' None, '1' SSL, '2' TLS, Default = Configured settings value
 * @param int    $referrals         - '0' Referrals from server are ignored, '1' Referrals from server are processed, Default = Configured setting value
 * @param mixed  $group_require     - false Group membership is not required, '1' Group membership is required
 * @param string $group_dn          - LDAP Group DN
 * @param string $group_attrib      - Name of the LDAP Attrib that contains members
 * @param int    $group_member_type
 *
 * @return array - Return values
 */
function cacti_ldap_auth(string $username, string $password = '', string $dn = '', string $host = '', int $port = 0, int $port_ssl = 0, int $version = 0,
	int $encryption = 0, int $referrals = 0, mixed $group_require = false, string $group_dn = '', string $group_attrib = '', int $group_member_type = 0) : array {
	$ldap = new Ldap();

	if (!empty($username)) {
		$ldap->username = $username;
	}

	if (!empty($password)) {
		$ldap->password = $password;
	}

	if (!empty($dn)) {
		$ldap->dn = $dn;
	}

	if (!empty($host)) {
		$ldap->host = $host;
	}

	if (!empty($port)) {
		$ldap->port = $port;
	}

	if (!empty($port_ssl)) {
		$ldap->port_ssl = $port_ssl;
	}

	if (!empty($version)) {
		$ldap->version = $version;
	}

	if (!empty($encryption)) {
		$ldap->encryption = $encryption;
	}

	if (!empty($referrals)) {
		$ldap->referrals = $referrals;
	}

	if ($group_require != '') {
		$ldap->group_require = $group_require == 'on' ? true : false;
	} else {
		$ldap->group_require = false;
	}

	if (!empty($group_dn)) {
		$ldap->group_dn = $group_dn;
	}

	if (!empty($group_attrib)) {
		$ldap->group_attrib = $group_attrib;
	}

	if (!empty($group_member_type)) {
		$ldap->group_member_type = $group_member_type;
	}

	/**
	 * If the server list is a space delimited set of servers
	 * process each server until you get a bind, or fail
	 */
	$ldap_servers = preg_split('/\s+/', $ldap->host);

	$response = [];

	foreach ($ldap_servers as $ldap_server) {
		$ldap->host = $ldap_server;

		$response = $ldap->Authenticate();

		if ($response['error_num'] == 0) {
			return $response;
		}
	}

	return $response;
}

/** cacti_ldap_search_dn
 *
 * Return Values:
 * 'error_num' = error number returned
 * 'error_text' = error text
 * 'dn' = found dn of user
 *
 * @deprecated: 1.3
 *
 * Error codes:
 *
 * #	Text
 * ==============================================================
 * 0	Authentication Success
 * 1	No username defined
 * 2	Unable to create LDAP connection object
 * 3	Unable to find users DN
 * 4	Protocol error, unable to set version
 * 5	Protocol error, unable to start TLS communications
 * 6	Protocol error
 * 7	Invalid credential
 * 8	Insufficient access
 * 9	Unable to connect to server
 * 10	Timeout
 * 11	General bind error
 * 12	Unable to set referrals option
 * 13	More than one matching user found
 * 14	Specific DN and Password required
 * 15	Unable to find user from DN
 * 99	PHP LDAP not enabled
 *
 * @param string $username          - username to search for in the LDAP directory
 * @param string $dn                - configured LDAP DN for binding, '<username>' will be replaced with $username
 * @param string $host              - Hostname or IP of LDAP server, Default = Configured settings value
 * @param int    $port              - Port of the LDAP server uses, Default = Configured settings value
 * @param int    $port_ssl          - Port of the LDAP server uses for SSL, Default = Configured settings value
 * @param int    $version           - 2 or 3, LDAP protocol version, Default = Configured settings value
 * @param int    $encryption        - 0 None, 1 SSL, 2 TLS, Default = Configured settings value
 * @param int    $referrals         - 0 Referrals from server are ignored, 1 Referrals from server are processed, Default = Configured setting value
 * @param int    $mode              - 0 No Searching, 1 Anonymous Searching, 2 Specific Searching, Default = Configured settings value
 * @param string $search_base       - Search base DN, Default = Configured settings value
 * @param string $search_filter     - Filter to find the user, Default = Configured settings value
 * @param string $specific_dn       - DN for binding to perform user search, Default = Configured settings value
 * @param string $specific_password - Password for binding to perform user search, Default - Configured settings value
 *
 * @return array - array of values
 */
function cacti_ldap_search_dn(string $username, string $dn = '', string $host = '', int $port = 0, int $port_ssl = 0,
	int $version = 0, int $encryption = 0, int $referrals = 0, int $mode = 0, string $search_base = '',
	string $search_filter = '', string $specific_dn = '', string $specific_password = '') : array {
	$ldap = new Ldap();

	if (!empty($username)) {
		$ldap->username = $username;
	}

	if (!empty($dn)) {
		$ldap->dn = $dn;
	}

	if (!empty($host)) {
		$ldap->host = $host;
	}

	if (!empty($port)) {
		$ldap->port = $port;
	}

	if (!empty($port_ssl)) {
		$ldap->port_ssl = $port_ssl;
	}

	if (!empty($version)) {
		$ldap->version = $version;
	}

	if (!empty($encryption)) {
		$ldap->encryption = $encryption;
	}

	if (!empty($referrals)) {
		$ldap->referrals = $referrals;
	}

	if (!empty($mode)) {
		$ldap->mode = $mode;
	}

	if (!empty($search_base)) {
		$ldap->search_base = $search_base;
	}

	if (!empty($search_filter)) {
		$ldap->search_filter = $search_filter;
	}

	if (!empty($specific_dn)) {
		$ldap->specific_dn = $specific_dn;
	}

	if (!empty($specific_password)) {
		$ldap->specific_password = $specific_password;
	}

	/* If the server list is a space delimited set of servers
	 * process each server until you get a bind, or fail
	 */
	$ldap_servers = preg_split('/\s+/', $ldap->host);

	$response = [];

	foreach ($ldap_servers as $ldap_server) {
		$ldap->host = $ldap_server;

		$response = $ldap->Search();

		if ($response['error_num'] == 0) {
			return $response;
		}
	}

	return $response;
}

/** cacti_ldap_search_cn
 *
 * Return Values:
 * 'cn' = array of values
 * 'error_num' = error number returned
 * 'error_text' = error text
 * 'dn' = found dn of user
 *
 * @deprecated: 1.3
 *
 * Error codes:
 * #       Text
 * ==============================================================
 * 0       User found
 * 1       No username defined
 * 2       Unable to create LDAP connection object
 * 3       Unable to find users DN
 * 4       Protocol error, unable to set version
 * 5       Protocol error, unable to start TLS communications
 * 6       Protocol error
 * 7       Invalid credential
 * 8       Insufficient access
 * 9       Unable to connect to server
 * 10      Timeout
 * 11      General bind error
 * 12      Unable to set referrals option
 * 13      More than one matching user found
 * 14      Specific DN and Password required
 * 15      CN unknown on LDAP
 * 99      PHP LDAP not enabled
 *
 * @param string $username          - username to search for in the LDAP directory
 * @param array  $cn                - array of CN to search on LDAP
 * @param string $dn                - configured LDAP DN for binding, '<username>' will be replaced with $username
 * @param string $host              - Hostname or IP of LDAP server, Default = Configured settings value
 * @param int    $port              - Port of the LDAP server uses, Default = Configured settings value
 * @param int    $port_ssl          - Port of the LDAP server uses for SSL, Default = Configured settings value
 * @param int    $version           - 2 or 3, LDAP protocol version, Default = Configured settings value
 * @param int    $encryption        - 0 None, 1 SSL, 2 TLS, Default = Configured settings value
 * @param int    $referrals         - 0 Referrals from server are ignored, 1 Referrals from server are processed, Default = Configured setting value
 * @param int    $mode              - 0 No Searching, 1 Anonymous Searching, 2 Specific Searching, Default = Configured settings value
 * @param string $search_base       - Search base DN, Default = Configured settings value
 * @param string $search_filter     - Filter to find the user, Default = Configured settings value
 * @param string $specific_dn       - DN for binding to perform user search, Default = Configured settings value
 * @param string $specific_password - Password for binding to perform user search, Default - Configured settings value
 *
 * @return array - array of values
 */
function cacti_ldap_search_cn(string $username, array $cn = [], string $dn = '', string $host = '',
	int $port = 0, int $port_ssl = 0, int $version = 0, int $encryption = 0,
	int $referrals = 0, int $mode = 0, string $search_base = '', string $search_filter = '',
	string $specific_dn = '', string $specific_password = '') : array {
	$ldap = new Ldap();

	if (!empty($username)) {
		$ldap->username = $username;
	}

	if (!empty($cn)) {
		$ldap->cn = $cn;
	}

	if (!empty($dn)) {
		$ldap->dn = $dn;
	}

	if (!empty($host)) {
		$ldap->host = $host;
	}

	if (!empty($port)) {
		$ldap->port = $port;
	}

	if (!empty($port_ssl)) {
		$ldap->port_ssl = $port_ssl;
	}

	if (!empty($version)) {
		$ldap->version = $version;
	}

	if (!empty($encryption)) {
		$ldap->encryption = $encryption;
	}

	if (!empty($referrals)) {
		$ldap->referrals = $referrals;
	}

	if (!empty($mode)) {
		$ldap->mode = $mode;
	}

	if (!empty($search_base)) {
		$ldap->search_base = $search_base;
	}

	if (!empty($search_filter)) {
		$ldap->search_filter = $search_filter;
	}

	if (!empty($specific_dn)) {
		$ldap->specific_dn = $specific_dn;
	}

	if (!empty($specific_password)) {
		$ldap->specific_password = $specific_password;
	}

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

	public static function GetErrorDetails(int $returnError, mixed $ldapConn = null, string $ldapServer = '', int $ldapError = 0) : array {
		$error_num  = $returnError;
		$error_text = '';

		if ($ldapConn && $ldapError == 0) {
			$ldapError = ldap_error($ldapConn);
		}

		if ($returnError === LdapError::None || $returnError === LdapError::Success) {
			$error_text = __('Authentication Success');
		} else {
			$error_text = match ($returnError) {
				LdapError::Failure                  => __('Authentication Failure'),
				LdapError::Disabled                 => __('PHP LDAP not enabled'),
				LdapError::UndefinedUsername        => __('No username defined'),
				LdapError::ProtocolErrorVersion     => __('Protocol Error, Unable to set version (%s) on Server (%s)', $ldapError, $ldapServer),
				LdapError::ProtocolErrorReferral    => __('Protocol Error, Unable to set referrals option (%s) on Server (%s)', $ldapError, $ldapServer),
				LdapError::ProtocolErrorTls         => __('Protocol Error, unable to start TLS communications (%s) on Server (%s)', $ldapError, $ldapServer),
				LdapError::ProtocolErrorGeneral     => __('Protocol Error, General failure (%s)', $ldapError, $ldapServer),
				LdapError::ProtocolErrorBind        => __('Protocol Error, Unable to bind, LDAP result: (%s) on Server (%s)', $ldapError, $ldapServer),
				LdapError::ConnectionUnavailable    => __('Unable to Connect to Server (%s)', $ldapServer),
				LdapError::ConnectionTimeout        => __('Connection Timeout to Server (%s)', $ldapServer),
				LdapError::InsufficientAccess       => __('Insufficient Access to Server (%s)', $ldapServer),
				LdapError::SearchFoundNoGroup       => __('Group DN could not be found to compare on Server (%s)', $ldapServer),
				LdapError::SearchFoundMultiUser     => __('More than one matching user found'),
				LdapError::SearchFoundNoUserDN      => __('Unable to find user from DN'),
				LdapError::SearchFoundNoUser        => __('Unable to find users DN'),
				LdapError::MissingLdapObject        => __('Unable to create LDAP connection object to Server (%s)', $ldapServer),
				LdapError::UndefinedDnOrPassword    => __('Specific DN and Password required'),
				LdapError::EmptyPassword            => __('Invalid Password provided.  Login failed.'),
				default                             => __('Unexpected error %s (Ldap Error: %s) on Server (%s)', $returnError, $ldapError, $ldapServer),
			};
		}

		return [
			'error_num'  => $error_num,
			'error_text' => $error_text,
			'error_ldap' => $ldapError,
			'dn'         => '',
			'stack'      => cacti_debug_backtrace('', false, false)
		];
	}
}

class Ldap {
	public string $dn;
	private array $connection = [];
	public array  $cn;
	public string $host;
	public mixed $username   = '';
	public mixed $password   = '';
	public int    $port;
	public int    $port_ssl;
	public int    $version;
	public int    $encryption;
	public int    $referrals;
	public int    $tls_certificate;
	public int    $network_timeout;
	public int    $bind_timeout;
	public int    $debug;
	public bool   $group_require;
	public string $group_dn;
	public string $group_attrib;
	public int    $group_member_type;
	public int    $mode;
	public string $search_base;
	public string $search_filter;
	public string $specific_dn;
	public string $specific_password;
	public string $cn_full_name;
	public string $cn_email;

	function __construct() {
		// No DB lookup here: Cacti\Auth\LdapLoginProvider::buildLdap() is the
		// single source of truth for mapping login_providers.parameters onto
		// these properties, so this class stays a pure connection/protocol
		// wrapper with no direct DB coupling.
		$this->debug = POLLER_VERBOSITY_HIGH;
		$this->host  = '';
	}

	function __destruct() {
	}

	function ErrorHandler(int $level, string $message, string $file, int $line, array $context = []) : bool {
		return true;
	}

	function SetLdapHandler() : void {
		// drop out of cactis error handler
		restore_error_handler();

		// set an error handler for ldap
		set_error_handler([$this, 'ErrorHandler']);

		cacti_session_close();
	}

	function RestoreCactiHandler() : void {
		// drop out of ldaps error handler
		restore_error_handler();

		// set an error handler for Cacti
		set_error_handler('CactiErrorHandler');

		cacti_session_start();
	}

	function RecordError(array $output, string $section = 'LDAP') : void {
		$logDN = empty($output['dn']) ? '' : (', DN: ' . $output['dn']);
		cacti_log($section . ': ' . $output['error_text'] . $logDN, false, 'AUTH');
		cacti_log($section . ': ' . $output['stack'], false, 'AUTH', $this->debug);
	}

	function Connect() : array {
		$output    = [];
		$ldap_conn = null;

		// function check
		if (!function_exists('ldap_connect')) {
			return [
				'ldap_conn' => $ldap_conn,
				'output'    => LdapError::GetErrorDetails(LdapError::Disabled)
			];
		}

		// validation
		if (empty($this->username)) {
			return [
				'ldap_conn' => $ldap_conn,
				'output'    => LdapError::GetErrorDetails(LdapError::UndefinedUsername)
			];
		}

		/**
		 * NOTE: The next several settings must be made prior to initial LDAP connection
		 */

		// Set debug if selective debug is enabled.  This places log data into the apache error_log
		if (get_selective_log_level() == POLLER_VERBOSITY_DEBUG || $this->debug == POLLER_VERBOSITY_DEBUG) {
			cacti_log('LDAP: Setting php-ldap into DEBUG mode.  Check your Web Server error_log for details', false, 'AUTH', $this->debug);
			ldap_set_option(null, LDAP_OPT_DEBUG_LEVEL, 7);
		}

		if (getenv('TLS_CERT') != '' && defined('LDAP_OPT_X_TLS_CERTFILE')) {
			cacti_log('LDAP: Settings TLS_CERT to ' . getenv('TLS_CERT'), false, 'AUTH', $this->debug);
			ldap_set_option(null, LDAP_OPT_X_TLS_CERTFILE, getenv('TLS_CERT'));
		}

		if (getenv('TLS_CACERT') != '' && defined('LDAP_OPT_X_TLS_CACERTFILE')) {
			cacti_log('LDAP: Settings TLS_CACERT to ' . getenv('TLS_CACERT'), false, 'AUTH', $this->debug);
			ldap_set_option(null, LDAP_OPT_X_TLS_CACERTFILE, getenv('TLS_CACERT'));
		}

		if (getenv('TLS_KEY') != '' && defined('LDAP_OPT_X_TLS_KEYFILE')) {
			cacti_log('LDAP: Settings TLS_KEY to ' . getenv('TLS_KEY'), false, 'AUTH', $this->debug);
			ldap_set_option(null, LDAP_OPT_X_TLS_KEYFILE, getenv('TLS_KEY'));
		}

		if (getenv('TLS_CACERTDIR') != '' && defined('LDAP_OPT_X_TLS_CACERTDIR')) {
			cacti_log('LDAP: Settings TLS_CACERTDIR to ' . getenv('TLS_CACERTDIR'), false, 'AUTH', $this->debug);
			ldap_set_option(null, LDAP_OPT_X_TLS_CACERTDIR, getenv('TLS_CACERTDIR'));
		}

		if ($this->encryption >= 1) {
			$cert = $this->tls_certificate;

			if ($cert === '') {
				$cert = LDAP_OPT_X_TLS_NEVER;
			}

			// For good measure, we will use both the php function and set the environment
			switch($cert) {
				case LDAP_OPT_X_TLS_NEVER:
					cacti_log('LDAP: Setting TLS Certificate Requirement to \'never\'', false, 'AUTH', $this->debug);
					putenv('TLS_REQCERT=never');

					break;
				case LDAP_OPT_X_TLS_HARD:
					cacti_log('LDAP: Setting TLS Certificate Requirement to \'hard\'', false, 'AUTH', $this->debug);
					putenv('TLS_REQCERT=hard');

					break;
				case LDAP_OPT_X_TLS_DEMAND:
					cacti_log('LDAP: Setting TLS Certificate Requirement to \'demand\'', false, 'AUTH', $this->debug);
					putenv('TLS_REQCERT=demand');

					break;
				case LDAP_OPT_X_TLS_ALLOW:
					cacti_log('LDAP: Setting TLS Certificate Requirement to \'allow\'', false, 'AUTH', $this->debug);
					putenv('TLS_REQCERT=allow');

					break;
				case LDAP_OPT_X_TLS_TRY:
					cacti_log('LDAP: Setting TLS Certificate Requirement to \'try\'', false, 'AUTH', $this->debug);
					putenv('TLS_REQCERT=try');

					break;
			}

			ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, $cert);
		}

		// Walk through ldap servers for a valid connections
		if ($this->encryption == 1) {
			cacti_log('LDAP: Connect using ldaps://' . $this->host . ':' . $this->port_ssl, false, 'AUTH', $this->debug);
			$ldap_conn = ldap_connect('ldaps://' . $this->host . ':' . $this->port_ssl);
		} else {
			cacti_log('LDAP: Connect using ldap://' . $this->host . ':' . $this->port, false, 'AUTH', $this->debug);
			$ldap_conn = ldap_connect($this->host, $this->port);
		}

		if ($ldap_conn) {
			cacti_log('LDAP: Successfully Connected to LDAP', false, 'AUTH', $this->debug);

			// Set protocol version
			cacti_log('LDAP: Setting protocol version to ' . $this->version, false, 'AUTH', $this->debug);

			if (!ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, $this->version)) {
				$output = LdapError::GetErrorDetails(LdapError::ProtocolErrorVersion, $ldap_conn, $this->host);
				Ldap::RecordError($output);
				ldap_close($ldap_conn);
				$this->connection = [];

				return [
					'ldap_conn' => $ldap_conn,
					'output'    => $output
				];
			}

			// set reasonable timeouts
			$network_timeout = $this->network_timeout;

			if (defined('LDAP_OPT_NETWORK_TIMEOUT')) {
				cacti_log("LDAP: Setting Network Timeout to $network_timeout seconds", false, 'AUTH', $this->debug);
				ldap_set_option($ldap_conn, LDAP_OPT_NETWORK_TIMEOUT, $network_timeout);
			}

			$bind_timeout = $this->bind_timeout;

			if (defined('LDAP_OPT_TIMEOUT')) {
				cacti_log("LDAP: Setting Bind Timeout to $bind_timeout seconds", false, 'AUTH', $this->debug);
				ldap_set_option($ldap_conn, LDAP_OPT_TIMEOUT, $bind_timeout);
			}

			// set referrals
			if ($this->referrals == 0) {
				if (!ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0)) {
					$output = LdapError::GetErrorDetails(LdapError::ProtocolErrorReferral, $ldap_conn, $this->host);

					Ldap::RecordError($output);

					ldap_close($ldap_conn);
					$this->connection = [];

					return [
						'ldap_conn' => $ldap_conn,
						'output'    => $output
					];
				}
			}

			// start TLS if requested
			if ($this->encryption == 2) {
				if (!ldap_start_tls($ldap_conn)) {
					$output = LdapError::GetErrorDetails(LdapError::ProtocolErrorTls, $ldap_conn, $this->host);

					Ldap::RecordError($output);

					ldap_close($ldap_conn);
					$this->connection = [];

					return [
						'ldap_conn' => $ldap_conn,
						'output'    => $output
					];
				}
			}

			return ['ldap_conn' => $ldap_conn, 'output' => $output];
		} else {
			cacti_log('WARNING: Unable to Connect to LDAP', false, 'AUTH', $this->debug);

			$output = LdapError::GetErrorDetails(LdapError::ConnectionUnavailable, $ldap_conn, $this->host);
			Ldap::RecordError($output);

			return [
				'ldap_conn' => $ldap_conn,
				'output'    => $output
			];
		}
	}

	function Authenticate() : array {
		$output = [];

		cacti_log('LDAP: Authentication Start', false, 'AUTH', $this->debug);

		// Determine connection method and create LDAP Object
		$this->SetLdapHandler();

		if (empty($this->connection)) {
			$this->connection = $this->Connect();
		}

		if (cacti_sizeof($this->connection['output'])) {
			$this->RestoreCactiHandler();

			return $this->connection['output'];
		}

		if ($this->connection['ldap_conn'] === false) {
			$this->RestoreCactiHandler();

			return LdapError::GetErrorDetails(LdapError::MissingLdapObject, false, $this->host);
		}

		$ldap_conn = $this->connection['ldap_conn'];

		// Decode username, and remove bad characters
		$this->username = html_entity_decode($this->username, $this->GetMask(), 'UTF-8');
		$this->password = html_entity_decode($this->password, $this->GetMask(), 'UTF-8');

		/**
		 * The blocklist above only strips search filter metacharacters. A DN has a
		 * separate grammar (RFC 4514) in which the comma, backslash, plus, quote,
		 * semicolon, hash and surrounding whitespace all survive it, so an
		 * unescaped username can graft extra RDNs onto the configured template.
		 * Escape at the point of use and leave $this->username as typed, because
		 * the group comparison below and the log lines still want the raw value.
		 */
		$this->dn       = str_replace('<username>', ldap_escape($this->username, '', LDAP_ESCAPE_DN), $this->dn);

		if ($this->password == '') {
			ldap_close($ldap_conn);
			$this->connection = [];
			$this->RestoreCactiHandler();

			return LdapError::GetErrorDetails(LdapError::EmptyPassword);
		}

		// Bind to the LDAP directory
		cacti_log(sprintf('LDAP: Binding User \'%s\' with DN \'%s\' on Server \'%s\'', $this->username, $this->dn, $this->host), false, 'AUTH', $this->debug);

		$ldap_response = ldap_bind($ldap_conn, $this->dn, $this->password);

		if ($ldap_response) {
			if ($this->group_require == 1) {
				$ldap_group_response = false;

				// Process group membership if required
				if ($this->group_member_type == 1) {
					$ldap_group_response = ldap_compare($ldap_conn, $this->group_dn, $this->group_attrib, $this->dn);

					if (!$ldap_group_response) {
						$ldap_group_response = Ldap::isUserInLDAPGroup($ldap_conn, $this->search_base, $this->group_dn, $this->dn);
					}
				} elseif ($this->group_member_type == 2) {
					$filter_user    = ldap_escape($this->username, '', LDAP_ESCAPE_FILTER);
					$true_dn_result = ldap_search($ldap_conn, $this->search_base, '(|(uid=' . $filter_user . ')(cn=' . $filter_user . ')(userPrincipalName=' . $filter_user . '))', ['dn']);
					$first_entry    = ldap_first_entry($ldap_conn, $true_dn_result);

					// we will test in two ways
					if ($first_entry !== false) {
						$true_dn             = ldap_get_dn($ldap_conn, $first_entry);
						$ldap_group_response = ldap_compare($ldap_conn, $this->group_dn, $this->group_attrib, $true_dn);
					} else {
						$ldap_group_response = ldap_compare($ldap_conn, $this->group_dn, $this->group_attrib, $this->username);
					}
				}

				if ($ldap_group_response === true) {
					// Auth ok
					$output = LdapError::GetErrorDetails(LdapError::Success);
				} elseif ($ldap_group_response === false) {
					$output = LdapError::GetErrorDetails(LdapError::InsufficientAccess, $ldap_conn, $this->host);
					Ldap::RecordError($output);
					ldap_close($ldap_conn);
					$this->connection = [];
					$this->RestoreCactiHandler();

					return $output;
				} else {
					$output = LdapError::GetErrorDetails(LdapError::SearchFoundNoGroup, $ldap_conn, $this->host);
					Ldap::RecordError($output);
					ldap_close($ldap_conn);
					$this->connection = [];
					$this->RestoreCactiHandler();

					return $output;
				}
			} else {
				// Auth ok - No group membership required
				$output = LdapError::GetErrorDetails(LdapError::Success);
			}
		} else {
			// unable to bind
			$ldap_error = ldap_errno($ldap_conn);

			if ($ldap_error == 0x02) {
				// protocol error
				$output = LdapError::GetErrorDetails(LdapError::ProtocolErrorGeneral, $ldap_conn, $this->host);
			} elseif ($ldap_error == 0x31) {
				// invalid credentials
				$output = LdapError::GetErrorDetails(LdapError::Failure, $ldap_conn, $this->host);
			} elseif ($ldap_error == 0x32) {
				// insufficient access
				$output = LdapError::GetErrorDetails(LdapError::InsufficientAccess, $ldap_conn, $this->host);
			} elseif ($ldap_error == 0x51) {
				// unable to connect to server
				$output = LdapError::GetErrorDetails(LdapError::ConnectionUnavailable, $ldap_conn, $this->host);
			} elseif ($ldap_error == 0x55) {
				// timeout
				$output = LdapError::GetErrorDetails(LdapError::ConnectionTimeout, $ldap_conn, $this->host);
			} else {
				// general bind error
				$output = LdapError::GetErrorDetails(LdapError::ProtocolErrorBind, $ldap_conn, $this->host);
			}
		}

		// Close LDAP connection
		ldap_close($ldap_conn);
		$this->connection = [];

		if ($output['error_num'] > 0) {
			Ldap::RecordError($output);
		}

		$this->RestoreCactiHandler();

		return $output;
	}

	function GetMask() : int {
		if (!defined('ENT_HTML401')) {
			return ENT_COMPAT;
		} else {
			return ENT_COMPAT | ENT_HTML401;
		}
	}

	/**
	 * Resolve which of the supplied raw group names the just-authenticated user
	 * is a member of, for automatic User Group assignment only.
	 *
	 * The admin configures a plain group name (e.g. "Cacti Admins"), never a
	 * full DN. For each name a small battery of directory-shape-specific checks
	 * is run against the configured Search Base so the same raw name works on
	 * Active Directory (including nested groups), FreeIPA, 389 Directory Server
	 * and RFC2307 OpenLDAP without the admin needing to know the group's DN.
	 * A value that is already a full DN is still accepted and matched exactly.
	 *
	 * The directory is searched with the service account (Specific Searching)
	 * or anonymously (Anonymous Searching); no other mode can enumerate groups,
	 * which is why the UI only offers this feature for those two modes.
	 *
	 * @param string $username   The login name of the authenticated user.
	 * @param array  $groupNames The raw group names to test.
	 *
	 * @return array|null The subset of $groupNames the user belongs to, or null
	 *                    when membership could not be determined (connect/bind
	 *                    failure) so the caller can leave assignments untouched
	 *                    rather than revoking them on a transient outage.
	 */
	function ResolveGroupMemberships(string $username, array $groupNames) : ?array {
		if (!cacti_sizeof($groupNames)) {
			return [];
		}

		$search_base = trim($this->search_base);

		if ($search_base === '') {
			return null;
		}

		$this->SetLdapHandler();

		$connection = $this->Connect();

		if (cacti_sizeof($connection['output']) || empty($connection['ldap_conn'])) {
			$this->RestoreCactiHandler();

			return null;
		}

		$ldap_conn = $connection['ldap_conn'];

		// Group enumeration needs a directory-read bind that is NOT the user's
		// own (an OTP/MFA bind cannot be reused, and the user may lack read
		// access to group objects): the service account, or anonymous.
		$bound = false;

		if ($this->mode == 2 && $this->specific_dn !== '' && $this->specific_password !== '') {
			$bound = @ldap_bind($ldap_conn, html_entity_decode($this->specific_dn, $this->GetMask(), 'UTF-8'), html_entity_decode($this->specific_password, $this->GetMask(), 'UTF-8'));
		} elseif ($this->mode == 1) {
			$bound = @ldap_bind($ldap_conn);
		}

		if (!$bound) {
			ldap_close($ldap_conn);
			$this->RestoreCactiHandler();

			return null;
		}

		$username_esc = ldap_escape($username, '', LDAP_ESCAPE_FILTER);
		$user_dn      = $this->ResolveUserDnForGroups($ldap_conn, $username_esc, $search_base);
		$user_dn_esc  = $user_dn !== '' ? ldap_escape($user_dn, '', LDAP_ESCAPE_FILTER) : '';
		$is_ad        = $this->IsActiveDirectory($ldap_conn);

		$matched = [];

		foreach ($groupNames as $group) {
			$in = $this->UserInGroupByName($ldap_conn, $search_base, $username_esc, $user_dn, $user_dn_esc, (string) $group, $is_ad);

			if ($in === null) {
				// Membership for at least one rule could not be determined (a
				// probe errored); fail safe for the whole set so the caller
				// leaves every assignment untouched rather than revoking
				// memberships on a transient directory error.
				ldap_close($ldap_conn);
				$this->RestoreCactiHandler();

				return null;
			}

			if ($in === true) {
				$matched[] = $group;
			}
		}

		ldap_close($ldap_conn);
		$this->RestoreCactiHandler();

		return $matched;
	}

	/**
	 * Resolve a user's DN under the search base, constrained to user object
	 * classes and user-naming attributes. Fails closed on ambiguity (more than
	 * one match) so a colliding account cannot supply the DN used by the
	 * member/uniqueMember checks.
	 *
	 * @param mixed  $ldap_conn    The bound LDAP connection.
	 * @param string $username_esc The filter-escaped username.
	 * @param string $search_base  The base DN to search under.
	 *
	 * @return string The user's DN, or '' when it cannot be unambiguously resolved.
	 */
	protected function ResolveUserDnForGroups($ldap_conn, string $username_esc, string $search_base) : string {
		// memberUid is deliberately excluded: it is a posixGroup attribute, so
		// including it could resolve a group entry as the user.
		$filter = "(&(|(objectClass=person)(objectClass=posixAccount)(objectClass=user)(objectClass=account))(|(uid=$username_esc)(sAMAccountName=$username_esc)(userPrincipalName=$username_esc)(cn=$username_esc)))";
		$search = @ldap_search($ldap_conn, $search_base, $filter, ['1.1']);

		if ($search === false) {
			return '';
		}

		$entries = @ldap_get_entries($ldap_conn, $search);

		if (is_array($entries) && isset($entries['count']) && (int) $entries['count'] === 1 && isset($entries[0]['dn'])) {
			return (string) $entries[0]['dn'];
		}

		return '';
	}

	/**
	 * Whether the connected directory is Active Directory, probed once via the
	 * rootDSE capability OID 1.2.840.113556.1.4.800. Used to gate the AD-only
	 * nested-group matching rule so non-AD servers are not sent an OID they
	 * reject once per group checked.
	 *
	 * @param mixed $ldap_conn The bound LDAP connection.
	 *
	 * @return bool True when the server is Active Directory.
	 */
	protected function IsActiveDirectory($ldap_conn) : bool {
		$result = @ldap_read($ldap_conn, '', '(objectClass=*)', ['supportedCapabilities']);

		if ($result !== false) {
			$entries = @ldap_get_entries($ldap_conn, $result);

			if (is_array($entries) && isset($entries[0]['supportedcapabilities'])
				&& in_array('1.2.840.113556.1.4.800', (array) $entries[0]['supportedcapabilities'], true)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Test whether a user belongs to a single group identified by a raw name
	 * (or, for back-compat, a full DN). Runs a battery of shape-specific checks
	 * so one plain name matches across AD/FreeIPA/389DS/RFC2307.
	 *
	 * @param mixed  $ldap_conn    The bound LDAP connection.
	 * @param string $search_base  The base DN to search under.
	 * @param string $username_esc The filter-escaped username.
	 * @param string $user_dn      The resolved user DN ('' when unresolved).
	 * @param string $user_dn_esc  The filter-escaped user DN ('' when unresolved).
	 * @param string $group        The configured group name or DN.
	 * @param bool   $is_ad        Whether the directory is Active Directory.
	 *
	 * @return bool|null True when the user is a member, false when confirmed not
	 *                   a member, and null when membership could not be
	 *                   determined (every probe errored) so the caller can
	 *                   preserve existing assignments rather than revoke them.
	 */
	protected function UserInGroupByName($ldap_conn, string $search_base, string $username_esc, string $user_dn, string $user_dn_esc, string $group, bool $is_ad) : ?bool {
		$queries = $this->BuildGroupMembershipQueries($search_base, $username_esc, $user_dn, $user_dn_esc, $group, $is_ad);

		if (!cacti_sizeof($queries)) {
			return false;
		}

		$errored = false;

		foreach ($queries as $query) {
			if ($query['read']) {
				$search = @ldap_read($ldap_conn, $query['base'], $query['filter'], $query['return']);
			} else {
				$search = @ldap_search($ldap_conn, $query['base'], $query['filter'], $query['return']);
			}

			if ($search === false) {
				// A failed search (timeout, dropped connection, bad base) is not
				// a confirmed non-membership; remember it so an all-errored,
				// no-match outcome is reported as undetermined (null) below.
				$errored = true;

				continue;
			}

			$results = @ldap_get_entries($ldap_conn, $search);

			if ($results === false) {
				$errored = true;

				continue;
			}

			if (is_array($results) && isset($results['count']) && $results['count'] > 0) {
				return true;
			}
		}

		return $errored ? null : false;
	}

	/**
	 * Build the shape-specific membership probes for one group, keyed by the
	 * directory type. Separated from execution so the query construction (in
	 * particular the Active Directory handling) is unit-testable without a live
	 * directory.
	 *
	 * @param string $search_base  The base DN to search under.
	 * @param string $username_esc The filter-escaped username.
	 * @param string $user_dn      The resolved user DN ('' when unresolved).
	 * @param string $user_dn_esc  The filter-escaped user DN ('' when unresolved).
	 * @param string $group        The configured group name or DN.
	 * @param bool   $is_ad        Whether the directory is Active Directory.
	 *
	 * @return array List of ['base','read','filter','return'] probe descriptors.
	 */
	protected function BuildGroupMembershipQueries(string $search_base, string $username_esc, string $user_dn, string $user_dn_esc, string $group, bool $is_ad) : array {
		if ($group === '') {
			return [];
		}

		$group_esc = ldap_escape($group, '', LDAP_ESCAPE_FILTER);

		// Detect whether the stored value is a full DN, deriving its CN only for
		// the bare-CN fallback case below.
		$group_cn    = $group;
		$rdn         = ldap_explode_dn($group, 1);
		$group_is_dn = is_array($rdn) && isset($rdn['count']) && $rdn['count'] > 0;

		if ($group_is_dn && isset($rdn[0]) && $rdn[0] !== '') {
			$group_cn = (string) $rdn[0];
		}

		$group_cn_esc = ldap_escape($group_cn, '', LDAP_ESCAPE_FILTER);

		$queries = [];

		if ($group_is_dn) {
			// The configured group is a DN, so every check is a base-scope read
			// against an exact entry - no subtree/cn ambiguity.
			if ($user_dn !== '') {
				// memberOf on the exact user entry (portable across AD, FreeIPA,
				// and any memberOf-plugin directory incl. 389 DS).
				$queries[] = ['base' => $user_dn, 'read' => true, 'filter' => "(memberOf=$group_esc)", 'return' => ['1.1']];

				// AD nested groups via LDAP_MATCHING_RULE_IN_CHAIN.
				if ($is_ad) {
					$queries[] = ['base' => $user_dn, 'read' => true, 'filter' => "(memberOf:1.2.840.113556.1.4.1941:=$group_esc)", 'return' => ['1.1']];
				}
			}

			// OpenLDAP RFC2307 posixGroup (memberUid holds the bare username).
			$queries[] = ['base' => $group, 'read' => true, 'filter' => "(&(objectClass=posixGroup)(memberUid=$username_esc))", 'return' => ['1.1']];

			// RFC2307bis groupOfNames/groupOfUniqueNames at the exact group DN.
			if ($user_dn !== '') {
				$queries[] = ['base' => $group, 'read' => true, 'filter' => "(&(|(objectClass=groupOfNames)(objectClass=groupOfUniqueNames))(|(member=$user_dn_esc)(uniqueMember=$user_dn_esc)))", 'return' => ['1.1']];
			}
		} else {
			// Active Directory group objects are objectClass=group, matched by
			// neither posixGroup nor groupOfNames, so AD needs its own cn-scoped
			// subtree search. member:1.2.840.113556.1.4.1941
			// (LDAP_MATCHING_RULE_IN_CHAIN) matches direct AND nested membership
			// in a single query.
			if ($is_ad && $user_dn !== '') {
				$queries[] = ['base' => $search_base, 'read' => false, 'filter' => "(&(objectClass=group)(cn=$group_cn_esc)(member:1.2.840.113556.1.4.1941:=$user_dn_esc))", 'return' => ['cn']];
			}

			// Bare group name: fall back to a cn subtree search. memberOf cannot
			// be used here because it stores DNs, not names.
			$queries[] = ['base' => $search_base, 'read' => false, 'filter' => "(&(objectClass=posixGroup)(cn=$group_cn_esc)(memberUid=$username_esc))", 'return' => ['memberUid']];

			if ($user_dn !== '') {
				$queries[] = ['base' => $search_base, 'read' => false, 'filter' => "(&(|(objectClass=groupOfNames)(objectClass=groupOfUniqueNames))(cn=$group_cn_esc)(|(member=$user_dn_esc)(uniqueMember=$user_dn_esc)))", 'return' => ['cn']];
			}
		}

		return $queries;
	}

	function Search() : array {
		$output = [];

		// Determine connection method and create LDAP Object
		$this->SetLdapHandler();

		if (empty($this->connection)) {
			$this->connection = $this->Connect();
		}

		if (cacti_sizeof($this->connection['output'])) {
			$this->RestoreCactiHandler();

			return $this->connection['output'];
		}

		if ($this->connection['ldap_conn'] === false) {
			$this->RestoreCactiHandler();

			return LdapError::GetErrorDetails(LdapError::MissingLdapObject, false, $this->host);
		}

		$ldap_conn = $this->connection['ldap_conn'];

		// Decode username, and remove bad characters
		$this->username = html_entity_decode($this->username, $this->GetMask(), 'UTF-8');
		$this->dn       = str_replace('<username>', ldap_escape($this->username, '', LDAP_ESCAPE_DN), $this->dn);

		if ($this->mode == 0) {
			// Just bind mode, make dn and return
			$output       = LdapError::GetErrorDetails(LdapError::Success);
			$output['dn'] = $this->dn;
			$this->RestoreCactiHandler();

			return $output;
		}

		if ($this->mode == 2) {
			// Specific
			if (empty($this->specific_dn) || empty($this->specific_password)) {
				$output       = LdapError::GetErrorDetails(LdapError::UndefinedDnOrPassword);
				$output['dn'] = $this->dn;
				Ldap::RecordError($output, 'LDAP_SEARCH');
				$this->RestoreCactiHandler();

				return $output;
			}
		} elseif ($this->mode == 1) {
			// assume anonymous
			$this->specific_dn       = '';
			$this->specific_password = '';
		}

		$this->search_filter = str_replace('<username>', ldap_escape($this->username, '', LDAP_ESCAPE_FILTER), $this->search_filter);

		// Fix encoding on ldap specific search DN and password
		$this->specific_password = html_entity_decode($this->specific_password, $this->GetMask(), 'UTF-8');
		$this->specific_dn       = html_entity_decode($this->specific_dn, $this->GetMask(), 'UTF-8');

		// bind to the directory
		if (ldap_bind($ldap_conn, $this->specific_dn, $this->specific_password)) {
			// Search
			$ldap_results = ldap_search($ldap_conn, $this->search_base, $this->search_filter, ['dn']);

			if ($ldap_results) {
				$ldap_entries = ldap_get_entries($ldap_conn, $ldap_results);

				if ($ldap_entries !== false && $ldap_entries['count'] === 1) {
					// single response return user dn
					$output       = LdapError::GetErrorDetails(LdapError::Success);
					$output['dn'] = $ldap_entries['0']['dn'];
					Ldap::RecordError($output, 'LDAP_SEARCH');
				} elseif (is_numeric($ldap_entries['count']) && $ldap_entries['count'] > 1) {
					// more than 1 result
					$output = LdapError::GetErrorDetails(LdapError::SearchFoundMultiUser);
				} else {
					// no search results
					$output = LdapError::GetErrorDetails(LdapError::SearchFoundNoUserDN);
				}
			} else {
				// no search results, user not found
				$output = LdapError::GetErrorDetails(LdapError::SearchFoundNoUser);
			}
		} else {
			// unable to bind
			$ldap_error = ldap_errno($ldap_conn);

			if ($ldap_error == 0x02) {
				// protocol error
				$output = LdapError::GetErrorDetails(LdapError::ProtocolErrorGeneral, $ldap_conn, $this->host);
			} elseif ($ldap_error == 0x31) {
				// invalid credentials
				$output = LdapError::GetErrorDetails(LdapError::Failure, $ldap_conn, $this->host);
			} elseif ($ldap_error == 0x32) {
				// insufficient access
				$output = LdapError::GetErrorDetails(LdapError::InsufficientAccess, $ldap_conn, $this->host);
			} elseif ($ldap_error == 0x51) {
				// unable to connect to server
				$output = LdapError::GetErrorDetails(LdapError::ConnectionUnavailable, $ldap_conn, $this->host);
			} elseif ($ldap_error == 0x55) {
				// timeout
				$output = LdapError::GetErrorDetails(LdapError::ConnectionTimeout, $ldap_conn, $this->host);
			} else {
				// general bind error
				$output = LdapError::GetErrorDetails(LdapError::ProtocolErrorBind, $ldap_conn, $this->host);
			}
		}

		ldap_close($ldap_conn);
		$this->connection = [];

		if ($output['error_num'] > 0) {
			Ldap::RecordError($output, 'LDAP_SEARCH');
		}

		$this->RestoreCactiHandler();

		return $output;
	}

	function Getcn() : array {
		$output = [];

		// Determine connection method and create LDAP Object
		$this->SetLdapHandler();

		if (empty($this->connection)) {
			$this->connection = $this->Connect();
		}

		if (cacti_sizeof($this->connection['output'])) {
			$this->RestoreCactiHandler();

			return $this->connection['output'];
		}

		if ($this->connection['ldap_conn'] === false) {
			$this->RestoreCactiHandler();

			return LdapError::GetErrorDetails(LdapError::MissingLdapObject, false, $this->host);
		}

		$ldap_conn = $this->connection['ldap_conn'];

		// Decode username, and remove bad characters
		$this->username = html_entity_decode($this->username, $this->GetMask(), 'UTF-8');
		$this->dn       = str_replace('<username>', ldap_escape($this->username, '', LDAP_ESCAPE_DN), $this->dn);

		if ($this->mode == 0) {
			// Just bind mode, make dn and return
			$output       = LdapError::GetErrorDetails(LdapError::Success);
			$output['dn'] = $this->dn;
			ldap_close($ldap_conn);
			$this->connection = [];
			$this->RestoreCactiHandler();

			return $output;
		}

		if ($this->mode == 2) {
			// Specific
			if (empty($this->specific_dn) || empty($this->specific_password)) {
				$output       = LdapError::GetErrorDetails(LdapError::UndefinedDnOrPassword);
				$output['dn'] = $this->dn;
				ldap_close($ldap_conn);
				$this->connection = [];
				$this->RestoreCactiHandler();

				return $output;
			}
		} elseif ($this->mode == 1) {
			// assume anonymous
			$this->specific_dn       = '';
			$this->specific_password = '';
		}

		$this->search_filter = str_replace('<username>', ldap_escape($this->username, '', LDAP_ESCAPE_FILTER), $this->search_filter);

		// Fix encoding on ldap specific search DN and password
		$this->specific_password = html_entity_decode($this->specific_password, $this->GetMask(), 'UTF-8');
		$this->specific_dn       = html_entity_decode($this->specific_dn, $this->GetMask(), 'UTF-8');

		// bind to the directory
		if (ldap_bind($ldap_conn, $this->specific_dn, $this->specific_password)) {
			// Search
			$ldap_results = ldap_search($ldap_conn, $this->search_base, $this->search_filter, $this->cn);

			if ($ldap_results) {
				$ldap_entries =  ldap_get_entries($ldap_conn, $ldap_results);

				if ($ldap_entries !== false && isset($ldap_entries['count']) && $ldap_entries['count'] === 1) {
					$output = LdapError::GetErrorDetails(LdapError::Success);

					// ldap_get_entries() always lowercases attribute keys
					// regardless of the case requested (e.g. AD's
					// "displayName" comes back as "displayname"); look the
					// value up case-insensitively but keep it under the
					// originally-requested key so callers reading
					// $cn[$this->cn[0]] still match what they asked for.
					$attr0 = strtolower($this->cn[0]);
					$attr1 = strtolower($this->cn[1]);

					// check if we got an full username entry
					if (array_key_exists($attr0, $ldap_entries[0])) {
						$output['cn'][$this->cn[0]] = $ldap_entries[0][$attr0][0];
					} else {
						$output['cn'][$this->cn[0]] = '';
					}

					// check if we got an email entry
					if (array_key_exists($attr1, $ldap_entries[0])) {
						$output['cn'][$this->cn[1]] = $ldap_entries[0][$attr1][0];
					} else {
						$output['cn'][$this->cn[1]] = '';
					}
				} elseif (is_array($ldap_entries) && isset($ldap_entries['count']) && $ldap_entries['count'] > 1) {
					$output = LdapError::GetErrorDetails(LdapError::SearchFoundMultiUser);
				} else {
					$output = LdapError::GetErrorDetails(LdapError::SearchFoundNoUser);
				}
			} else {
				// no search results, user not found
				$output = LdapError::GetErrorDetails(LdapError::SearchFoundNoUserDN);
			}
		} else {
			// unable to bind
			$ldap_error = ldap_errno($ldap_conn);

			if ($ldap_error == 0x02) {
				// protocol error
				$output = LdapError::GetErrorDetails(LdapError::ProtocolErrorGeneral, $ldap_conn, $this->host);
			} elseif ($ldap_error == 0x31) {
				// invalid credentials
				$output = LdapError::GetErrorDetails(LdapError::Failure, $ldap_conn, $this->host);
			} elseif ($ldap_error == 0x32) {
				// insufficient access
				$output = LdapError::GetErrorDetails(LdapError::InsufficientAccess, $ldap_conn, $this->host);
			} elseif ($ldap_error == 0x51) {
				// unable to connect to server
				$output = LdapError::GetErrorDetails(LdapError::ConnectionUnavailable, $ldap_conn, $this->host);
			} elseif ($ldap_error == 0x55) {
				// timeout
				$output = LdapError::GetErrorDetails(LdapError::ConnectionTimeout, $ldap_conn, $this->host);
			} else {
				// general bind error
				$output = LdapError::GetErrorDetails(LdapError::ProtocolErrorBind, $ldap_conn, $this->host);
			}
		}

		ldap_close($ldap_conn);
		$this->connection = [];

		if ($output['error_num'] > 0) {
			Ldap::RecordError($output, 'LDAP_SEARCH_CN');
		}

		$this->RestoreCactiHandler();

		return $output;
	}

	function isUserInLDAPGroup(object $ldapConn, string $ldapbasedn, string $groupDN, string $ldapUser) : bool {
		$query = cacti_ldap_filter(
			'(&(distinguishedName=<user>)(memberOf:1.2.840.113556.1.4.1941:=<group>))',
			['user' => $ldapUser, 'group' => $groupDN]
		);
		$ldapSearch  = ldap_search($ldapConn, $ldapbasedn, $query, ['dn']);

		if ($ldapSearch) {
			$ldapResults = ldap_get_entries($ldapConn, $ldapSearch);

			// user should only be returned once IF they're a member of the group
			if ($ldapResults !== false) {
				return isset($ldapResults['count']) && $ldapResults['count'] === 1 ? true : false;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
}

/**
 * Build an LDAP filter string with safe variable substitution.
 *
 * @param string               $template Filter template with <key> placeholders
 * @param array<string, mixed> $vars     Associative array of key => value pairs
 *
 * @return string The assembled, injection-safe LDAP filter
 */
function cacti_ldap_filter(string $template, array $vars) : string {
	$map = [];

	foreach ($vars as $key => $value) {
		$map['<' . $key . '>'] = ldap_escape((string) $value, '', LDAP_ESCAPE_FILTER);
	}

	return strtr($template, $map);
}
