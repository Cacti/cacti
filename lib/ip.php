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

require_once __DIR__ . '/client_address.php';

/**
 * IP range membership helpers.
 *
 * A single home for "does this address fall inside one of these ranges"
 * so that remote-poller ACLs, discovery ignore lists and similar allow or
 * deny checks share one well-tested matcher instead of each re-implementing
 * netmask arithmetic.  Matching is delegated to Symfony's IpUtils::checkIp(),
 * which understands IPv4, IPv6 and CIDR notation, with a hand-rolled
 * packed-address fallback when the component is not autoloaded.
 */

/**
 * cacti_ip_in_ranges - tests whether an address matches any of a list of ranges
 *
 * Each range entry may be a plain IPv4/IPv6 address (10.0.0.5, ::1) or a CIDR
 * block (10.0.0.0/8, 2001:db8::/32).  A plain address is an exact match, so a
 * list of bare addresses behaves exactly like the literal comparisons this
 * helper replaces while additionally honouring CIDR notation.
 *
 * Comparison only happens within the same IP family, so an IPv4-mapped IPv6
 * entry never silently matches a bare IPv4 address.  A range entry that is
 * neither a valid address nor a valid CIDR block (for example a hostname)
 * falls back to exact string equality so existing configurations keep working.
 *
 * @param string $ip     The address to test, without any CIDR suffix
 * @param array  $ranges The list of plain-IP or CIDR range entries
 *
 * @return bool True when $ip matches any entry in $ranges
 */
function cacti_ip_in_ranges(string $ip, array $ranges) : bool {
	if ($ip === '' || !cacti_sizeof($ranges)) {
		return false;
	}

	$ip_is_valid = filter_var($ip, FILTER_VALIDATE_IP) !== false;
	$ip_family   = $ip_is_valid ? cacti_ip_family($ip) : 0;
	$have_iputils = class_exists('Symfony\Component\HttpFoundation\IpUtils');

	foreach ($ranges as $range) {
		$range = trim((string) $range);

		if ($range === '') {
			continue;
		}

		if ($ip_is_valid) {
			$slash     = strpos($range, '/');
			$range_ip  = ($slash !== false) ? substr($range, 0, $slash) : $range;
			$range_fam = cacti_ip_family($range_ip);

			if ($range_fam !== 0 && $range_fam === $ip_family) {
				if ($have_iputils) {
					if (IpUtils::checkIp($ip, $range)) {
						return true;
					}
				} elseif (cacti_ip_in_range_fallback($ip, $range)) {
					return true;
				}

				/* Same-family IP entry already handled; do not also apply the
				 * string-equality fallback below, which could only mislead. */
				continue;
			}
		}

		/* Non-IP entry (hostname, malformed value) or a family mismatch:
		 * preserve the exact string match of the list comparison replaced. */
		if (hash_equals($range, $ip)) {
			return true;
		}
	}

	return false;
}

/**
 * cacti_ip_in_range_fallback - packed-address CIDR match without Symfony
 *
 * Used only when Symfony\Component\HttpFoundation\IpUtils is unavailable.
 * Handles a bare address (exact match) or an address/prefix CIDR block for
 * both IPv4 and IPv6 by comparing the leading prefix bits of the packed forms.
 *
 * @param string $ip    The address to test (already validated by the caller)
 * @param string $range A plain address or address/prefix CIDR block
 *
 * @return bool True when $ip is contained in $range
 */
function cacti_ip_in_range_fallback(string $ip, string $range) : bool {
	$slash = strpos($range, '/');

	$addr = @inet_pton($ip);

	if ($addr === false) {
		return false;
	}

	if ($slash === false) {
		$net = @inet_pton($range);

		return $net !== false && strlen($net) === strlen($addr) && hash_equals($net, $addr);
	}

	$subnet = substr($range, 0, $slash);
	$bits   = substr($range, $slash + 1);
	$net    = @inet_pton($subnet);

	if ($net === false || strlen($net) !== strlen($addr) || !ctype_digit($bits)) {
		return false;
	}

	$bits = (int) $bits;
	$len  = strlen($net) * 8;

	if ($bits > $len) {
		return false;
	}

	$whole_bytes = intdiv($bits, 8);
	$rem_bits    = $bits % 8;

	if ($whole_bytes > 0 && !hash_equals(substr($net, 0, $whole_bytes), substr($addr, 0, $whole_bytes))) {
		return false;
	}

	if ($rem_bits === 0) {
		return true;
	}

	$mask = 0xff << (8 - $rem_bits) & 0xff;

	return (ord($addr[$whole_bytes]) & $mask) === (ord($net[$whole_bytes]) & $mask);
}
