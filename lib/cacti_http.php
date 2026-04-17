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

/**
 * Centralized HTTP fetch gateway with SSRF protection.
 *
 * All outbound HTTP requests from Cacti SHOULD route through
 * cacti_http_fetch() to enforce TLS verification, reserved-IP
 * rejection, and timeout defaults.
 */

/**
 * Check whether an IP address falls into a reserved/private range.
 *
 * Covers RFC 1918, RFC 6598, loopback, link-local, multicast,
 * and IPv6 equivalents.
 *
 * @param  string $ip The IP address to check
 *
 * @return bool True if the IP is reserved/private
 */
function cacti_is_reserved_ip($ip) {
	return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_NO_PRIV_RANGE) === false;
}

/**
 * Build a stream context for HTTPS with sane defaults.
 *
 * @param  array $opts Override options merged into the ssl context
 *
 * @return resource A stream context resource
 */
function cacti_https_context($opts = array()) {
	$defaults = array(
		'ssl' => array(
			'verify_peer'       => true,
			'verify_peer_name'  => true,
			'allow_self_signed' => false,
			'SNI_enabled'       => true,
		),
		'http' => array(
			'timeout'         => 10,
			'follow_location' => 0,
			'max_redirects'   => 0,
			'protocol_version' => 1.1,
		),
	);

	if (!empty($opts['ssl'])) {
		$defaults['ssl'] = array_merge($defaults['ssl'], $opts['ssl']);
	}

	if (!empty($opts['http'])) {
		$defaults['http'] = array_merge($defaults['http'], $opts['http']);
	}

	return stream_context_create($defaults);
}

/**
 * Fetch a URL with SSRF protection and TLS verification.
 *
 * @param  string $url     The URL to fetch
 * @param  array  $opts    Stream context overrides (ssl, http keys)
 * @param  int    $timeout Connection timeout in seconds
 *
 * @return string|false Response body on success, false on failure
 */
function cacti_http_fetch($url, $opts = array(), $timeout = 10) {
	$parsed = parse_url($url);

	if ($parsed === false || !isset($parsed['scheme']) || !isset($parsed['host'])) {
		cacti_log('ERROR: cacti_http_fetch: invalid URL', false, 'SYSTEM');

		return false;
	}

	$scheme = strtolower($parsed['scheme']);

	if ($scheme !== 'http' && $scheme !== 'https') {
		cacti_log('ERROR: cacti_http_fetch: scheme must be http or https', false, 'SYSTEM');

		return false;
	}

	$host = $parsed['host'];

	/* resolve hostname and check against reserved ranges */
	$ips = gethostbynamel($host);

	if ($ips === false) {
		cacti_log('ERROR: cacti_http_fetch: DNS resolution failed for ' . $host, false, 'SYSTEM');

		return false;
	}

	foreach ($ips as $ip) {
		if (cacti_is_reserved_ip($ip)) {
			cacti_log('ERROR: cacti_http_fetch: reserved IP rejected for ' . $host . ' (' . $ip . ')', false, 'SYSTEM');

			return false;
		}
	}

	if (!isset($opts['http']['timeout'])) {
		$opts['http']['timeout'] = $timeout;
	}

	$context = cacti_https_context($opts);

	$result = @file_get_contents($url, false, $context);

	if ($result === false) {
		$err = error_get_last();
		$msg = ($err !== null) ? $err['message'] : 'unknown error';
		cacti_log('WARNING: cacti_http_fetch: fetch failed for ' . $host . ': ' . $msg, false, 'SYSTEM');

		return false;
	}

	if (read_config_option('log_verbosity') >= POLLER_VERBOSITY_DEBUG) {
		cacti_log('DEBUG: cacti_http_fetch: fetched ' . strlen($result) . ' bytes from ' . $host, false, 'SYSTEM', POLLER_VERBOSITY_DEBUG);
	}

	return $result;
}
