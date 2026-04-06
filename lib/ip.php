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
*/

/**
 * cacti_normalize_ip - normalizes an IPv4 or IPv6 address for deterministic
 * database storage and equality comparison. Strips zone indices, converts
 * IPv4-mapped IPv6 to standard IPv4, and fully compresses IPv6.
 *
 * @param string $ip - The raw IP address string
 *
 * @return string|false Normalized IP string, or false if invalid
 */
function cacti_normalize_ip($ip) {
	/* Strip scope ID (zone index) for validation (e.g., fe80::1%eth0) */
	if (strpos($ip, '%') !== false) {
		$ip = explode('%', $ip, 2);
		$ip = $ip[0];
	}

	$binary = @inet_pton($ip);
	if ($binary === false) {
		return false;
	}

	/* Downgrade IPv4-mapped IPv6 addresses to raw IPv4 for consistent DB indexing */
	if (strlen($binary) === 16 && substr($binary, 0, 12) === "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff") {
		$binary = substr($binary, 12);
	}

	return inet_ntop($binary);
}

/**
 * cacti_ip_in_cidr - determines if a normalized IP address falls within a given
 * CIDR block using bitwise comparison. Replaces legacy regex/substring subnet matching.
 *
 * @param string $ip   - The IP address to check
 * @param string $cidr - The CIDR block (e.g., '192.168.1.0/24') or single IP
 *
 * @return bool
 */
function cacti_ip_in_cidr($ip, $cidr) {
	$ip = cacti_normalize_ip($ip);
	if ($ip === false) {
		return false;
	}

	if (strpos($cidr, '/') === false) {
		$subnet = cacti_normalize_ip($cidr);

		return $ip === $subnet;
	}

	$parts  = explode('/', $cidr, 2);
	$subnet = cacti_normalize_ip($parts[0]);
	$mask   = $parts[1];

	if ($subnet === false || !is_numeric($mask)) {
		return false;
	}

	$mask          = (int)$mask;
	$ipBinary      = inet_pton($ip);
	$subnetBinary  = inet_pton($subnet);

	/* Fail-closed on IPv4/IPv6 address family mismatch */
	if (strlen($ipBinary) !== strlen($subnetBinary)) {
		return false;
	}

	$maxMask = strlen($ipBinary) === 4 ? 32 : 128;
	if ($mask < 0 || $mask > $maxMask) {
		return false;
	}

	$bytePos = (int)($mask / 8);
	$bitPos  = $mask % 8;

	if ($bytePos > 0) {
		if (substr($ipBinary, 0, $bytePos) !== substr($subnetBinary, 0, $bytePos)) {
			return false;
		}
	}

	if ($bitPos > 0) {
		$ipByte      = ord($ipBinary[$bytePos]);
		$subnetByte  = ord($subnetBinary[$bytePos]);
		$maskByte    = 256 - (1 << (8 - $bitPos));

		if (($ipByte & $maskByte) !== ($subnetByte & $maskByte)) {
			return false;
		}
	}

	return true;
}

/**
 * cacti_get_secure_client_ip - resolves the true client IP address by strictly
 * enforcing Zero Trust boundaries. Traverses X-Forwarded-For backwards,
 * discarding IPs until it hits the first untrusted node.
 *
 * @param array $trustedProxies - Array of normalized IPs or CIDRs of trusted load balancers
 *
 * @return string Normalized client IP, or '0.0.0.0' on failure
 */
function cacti_get_secure_client_ip($trustedProxies = array()) {
	$remoteAddr = isset($_SERVER['REMOTE_ADDR']) ? cacti_normalize_ip($_SERVER['REMOTE_ADDR']) : false;

	if ($remoteAddr === false) {
		return '0.0.0.0';
	}

	if (empty($trustedProxies) || empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		return $remoteAddr;
	}

	$isTrusted = false;
	foreach ($trustedProxies as $proxyCidr) {
		if (cacti_ip_in_cidr($remoteAddr, $proxyCidr)) {
			$isTrusted = true;

			break;
		}
	}

	if ($isTrusted) {
		$forwardedIps = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));

		/* Traverse backwards to prevent header spoofing via multiple proxies */
		$forwardedIps = array_reverse($forwardedIps);

		foreach ($forwardedIps as $fwdIp) {
			$normalizedFwdIp = cacti_normalize_ip($fwdIp);
			if ($normalizedFwdIp === false) {
				continue;
			}

			$fwdIsTrusted = false;
			foreach ($trustedProxies as $proxyCidr) {
				if (cacti_ip_in_cidr($normalizedFwdIp, $proxyCidr)) {
					$fwdIsTrusted = true;

					break;
				}
			}

			if (!$fwdIsTrusted) {
				return $normalizedFwdIp;
			}
		}
	}

	return $remoteAddr;
}
