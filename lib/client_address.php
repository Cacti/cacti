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

use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Client address resolution.
 *
 * A reverse proxy appends to X-Forwarded-For, so the rightmost entry is the
 * one nearest to us and the leftmost is whatever the client chose to send.
 * Forwarded headers are therefore only meaningful when the immediate peer is
 * a proxy listed in $trusted_proxies; from anyone else they are client input.
 */

/**
 * cacti_trusted_proxy_match - tests an address against the trusted proxy list
 *
 * Matching is IP normalized, so equivalent spellings of the same address match
 * (::1 and 0:0:0:0:0:0:0:1), while an IPv4-mapped entry never silently matches
 * its bare IPv4 form.  CIDR entries such as 10.0.0.0/8 are supported.  A
 * malformed, non-IP entry falls back to exact string equality so existing
 * configurations keep working.
 *
 * @param string $address The address to test
 * @param array  $trusted The configured trusted proxy entries
 *
 * @return bool True when the address matches a trusted entry
 */
function cacti_trusted_proxy_match(string $address, array $trusted) : bool {
	if ($address === '' || !cacti_sizeof($trusted)) {
		return false;
	}

	foreach ($trusted as $entry) {
		$entry = (string) $entry;

		if ($entry === '') {
			continue;
		}

		if (filter_var($address, FILTER_VALIDATE_IP) && IpUtils::checkIp($address, $entry)) {
			return true;
		}

		if (hash_equals($entry, $address)) {
			return true;
		}
	}

	return false;
}

/**
 * cacti_server_header_key - maps a configured header name to its $_SERVER key
 *
 * The allowed header list mixes wire names (X-Forwarded-For) with the CGI
 * spelling PHP actually exposes (HTTP_X_FORWARDED_FOR).
 *
 * @param string $header The configured header name
 *
 * @return string The $_SERVER key to read
 */
function cacti_server_header_key(string $header) : string {
	$header = strtoupper(str_replace('-', '_', trim($header)));

	if ($header === 'REMOTE_ADDR' || str_starts_with($header, 'HTTP_')) {
		return $header;
	}

	return 'HTTP_' . $header;
}

/**
 * cacti_resolve_client_addr - resolves the client address from a request
 *
 * Returns REMOTE_ADDR unless the immediate peer is a trusted proxy.  For a
 * trusted peer the forwarded chain is walked from right to left, discarding
 * hops that are themselves trusted, and the first address a trusted hop
 * actually observed is returned.  A malformed entry aborts that header rather
 * than allowing an attacker to push the walk further left.
 *
 * @param array $server  The request server variables
 * @param array $trusted The configured trusted proxy entries
 * @param array $headers The forwarded headers permitted by configuration
 *
 * @return string|false The client address, or false when none is available
 */
function cacti_resolve_client_addr(array $server, array $trusted, array $headers) : string|false {
	$remote = isset($server['REMOTE_ADDR']) ? trim((string) $server['REMOTE_ADDR']) : '';

	if ($remote === '' || !filter_var($remote, FILTER_VALIDATE_IP)) {
		return false;
	}

	if (!cacti_trusted_proxy_match($remote, $trusted)) {
		return $remote;
	}

	foreach ($headers as $header) {
		$key = cacti_server_header_key($header);

		if ($key === 'REMOTE_ADDR' || empty($server[$key])) {
			continue;
		}

		$chain = array_map('trim', explode(',', (string) $server[$key]));

		for ($i = cacti_sizeof($chain) - 1; $i >= 0; $i--) {
			if ($chain[$i] === '') {
				continue;
			}

			if (!filter_var($chain[$i], FILTER_VALIDATE_IP)) {
				continue 2;
			}

			if (!cacti_trusted_proxy_match($chain[$i], $trusted)) {
				return $chain[$i];
			}
		}
	}

	return $remote;
}
