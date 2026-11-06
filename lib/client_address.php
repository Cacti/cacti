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
 * cacti_ip_family - returns the IP family of an address
 *
 * @param string $ip The address to classify, without any CIDR suffix
 *
 * @return int 4 for IPv4, 6 for IPv6, or 0 when the value is not an IP address
 */
function cacti_ip_family(string $ip) : int {
	if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
		return 4;
	}

	if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
		return 6;
	}

	return 0;
}

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

		if (filter_var($address, FILTER_VALIDATE_IP)) {
			$slash       = strpos($entry, '/');
			$entry_ip    = ($slash !== false) ? substr($entry, 0, $slash) : $entry;
			$address_fam = cacti_ip_family($address);

			/* An IPv4-mapped IPv6 address (::ffff:a.b.c.d) must never silently match a
			 * bare IPv4 entry, so only consult IpUtils when both sides are the same IP
			 * family.  Newer Symfony releases normalize the mapped form inside checkIp(),
			 * and this family gate keeps the match deterministic regardless of version. */
			if ($address_fam !== 0 && $address_fam === cacti_ip_family($entry_ip) && IpUtils::checkIp($address, $entry)) {
				return true;
			}
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
