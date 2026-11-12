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
 * IP range membership helpers.
 *
 * A single home for "does this address fall inside one of these ranges" so
 * that trusted proxy lists, discovery ignore lists and similar allow or deny
 * checks share one matcher instead of each re-implementing netmask arithmetic.
 * Everything here is native PHP: addresses are validated with filter_var(),
 * packed with inet_pton() and compared bytewise under the prefix mask.
 *
 * Families never cross: an IPv4 address only matches IPv4 ranges and an IPv6
 * address only IPv6 ranges.  An IPv4-mapped IPv6 address (::ffff:a.b.c.d) is
 * IPv6, so it matches ::ffff:0:0/96 but never 10.0.0.0/8, and a bare IPv4
 * address never matches an IPv4-mapped entry.
 */

/**
 * cacti_ip_pack - validates an address and returns its packed binary form
 *
 * filter_var() is the validator so that spellings inet_pton() tolerates on
 * some platforms, such as an IPv4 octet with a leading zero, are rejected
 * consistently.  Zone identifiers (fe80::1%eth0) and whitespace are rejected.
 *
 * @param string $ip An IPv4 or IPv6 address without a CIDR suffix
 *
 * @return string|null 4 bytes for IPv4, 16 bytes for IPv6, or null if invalid
 */
function cacti_ip_pack(string $ip) : ?string {
	if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
		return null;
	}

	$packed = inet_pton($ip);

	return $packed === false ? null : $packed;
}

/**
 * cacti_ip_cidr_parse - strictly parses a plain address or a CIDR block
 *
 * A bare address is a block of the family's full width (/32 or /128).  The
 * prefix must be unsigned decimal digits no larger than that width; leading
 * zeros are read as the integer value (/08 is /8).  Anything else, including
 * an empty prefix, a sign, a fraction, embedded whitespace or a hostname, is
 * not a block.
 *
 * @param string $cidr A plain IPv4/IPv6 address or an address/prefix block
 *
 * @return array{0: string, 1: int}|null The packed network address and the
 *                                       prefix length, or null when invalid
 */
function cacti_ip_cidr_parse(string $cidr) : ?array {
	$slash  = strpos($cidr, '/');
	$packed = cacti_ip_pack($slash === false ? $cidr : substr($cidr, 0, $slash));

	if ($packed === null) {
		return null;
	}

	$width = strlen($packed) * 8;

	if ($slash === false) {
		return [$packed, $width];
	}

	$prefix = substr($cidr, $slash + 1);

	/* Bound the digit count before casting so an absurdly long prefix cannot
	 * saturate to PHP_INT_MAX and then be compared as a number. */
	if (!ctype_digit($prefix) || strlen(ltrim($prefix, '0')) > 3 || (int) $prefix > $width) {
		return null;
	}

	return [$packed, (int) $prefix];
}

/**
 * cacti_ip_cidr_contains - tests a packed address against a parsed block
 *
 * @param string                   $packed A packed address from cacti_ip_pack()
 * @param array{0: string, 1: int} $block  A block from cacti_ip_cidr_parse()
 *
 * @return bool True when both are the same family and the leading prefix bits
 *              agree
 */
function cacti_ip_cidr_contains(string $packed, array $block) : bool {
	[$network, $bits] = $block;

	if (strlen($network) !== strlen($packed)) {
		return false;
	}

	$whole = intdiv($bits, 8);

	if ($whole > 0 && substr($packed, 0, $whole) !== substr($network, 0, $whole)) {
		return false;
	}

	$remainder = $bits % 8;

	if ($remainder === 0) {
		return true;
	}

	$mask = (0xff << (8 - $remainder)) & 0xff;

	return (ord($packed[$whole]) & $mask) === (ord($network[$whole]) & $mask);
}

/**
 * cacti_ip_in_cidr - tests whether an address is inside any of the given blocks
 *
 * Each entry may be a plain address (an exact match, so ::1 also matches
 * 0:0:0:0:0:0:0:1) or a CIDR block such as 10.0.0.0/8 or 2001:db8::/32.  /0
 * matches every address of the entry's family.  An invalid address, an invalid
 * entry or a non-string entry never matches and never raises an error; a later
 * valid entry is still considered.  Entries are not trimmed.
 *
 * @param string       $ip    The address to test, without any CIDR suffix
 * @param string|array $cidrs One block or a list of blocks
 *
 * @return bool True when $ip is inside at least one valid same-family block
 */
function cacti_ip_in_cidr(string $ip, string|array $cidrs) : bool {
	$packed = cacti_ip_pack($ip);

	if ($packed === null) {
		return false;
	}

	foreach ((array) $cidrs as $cidr) {
		if (!is_string($cidr)) {
			continue;
		}

		$block = cacti_ip_cidr_parse($cidr);

		if ($block !== null && cacti_ip_cidr_contains($packed, $block)) {
			return true;
		}
	}

	return false;
}

/**
 * cacti_ip_in_ranges - tests an address against an admin-entered range list
 *
 * Like cacti_ip_in_cidr(), but each entry is trimmed and empty entries are
 * skipped.  An entry that is not a valid address or CIDR block of the
 * address's family (for example a hostname) falls back to exact string
 * equality, so a list of literal values keeps the behavior of the plain
 * comparison this helper replaces.  A malformed block contains '/', so it
 * never equals an address.  Callers that hold admin-entered lists should drop
 * and log invalid entries once with cacti_ip_ranges_filter() rather than per
 * address.
 *
 * @param string $ip     The address (or literal value) to test
 * @param array  $ranges The list of plain-IP or CIDR range entries
 *
 * @return bool True when $ip matches any entry in $ranges
 */
function cacti_ip_in_ranges(string $ip, array $ranges) : bool {
	if ($ip === '' || $ranges === []) {
		return false;
	}

	$packed = cacti_ip_pack($ip);

	foreach ($ranges as $range) {
		$range = trim((string) $range);

		if ($range === '') {
			continue;
		}

		if ($packed !== null) {
			$block = cacti_ip_cidr_parse($range);

			if ($block !== null && strlen($block[0]) === strlen($packed)) {
				if (cacti_ip_cidr_contains($packed, $block)) {
					return true;
				}

				/* A same-family block has been decided; string equality
				 * could only mislead. */
				continue;
			}
		}

		if (hash_equals($range, $ip)) {
			return true;
		}
	}

	return false;
}

/**
 * cacti_ip_ranges_filter - drops and logs entries that are not an IP or CIDR
 *
 * Admin-entered lists such as automation_networks.ignore_ips are free text.
 * Validating them once before a sweep keeps the per-address match cheap and
 * logs each bad entry a single time instead of once per candidate address.
 *
 * @param array  $ranges  The raw range entries
 * @param string $context A short description of where the list came from,
 *                        used in the log message
 *
 * @return array The trimmed entries that are a valid address or CIDR block
 */
function cacti_ip_ranges_filter(array $ranges, string $context) : array {
	$valid = [];

	foreach ($ranges as $range) {
		$range = trim((string) $range);

		if ($range === '') {
			continue;
		}

		if (cacti_ip_cidr_parse($range) === null) {
			cacti_log('WARNING: Ignoring invalid IP address or CIDR block "' . $range . '" in ' . $context, false, 'AUTOM8', POLLER_VERBOSITY_LOW);

			continue;
		}

		$valid[] = $range;
	}

	return $valid;
}
