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
 * Check whether a data input command string is safe to store.
 * Allows shell pipes and Windows-style path separators for common
 * command usage, but blocks command separators and substitution patterns.
 */
function cacti_input_string_is_safe(string $input_string): bool {
	$input_string_bare = preg_replace('/<[a-zA-Z_]+>/', '', $input_string);

	return !preg_match('/[;&`$\n\r]/', $input_string_bare);
}

/**
 * Validate sort column identifier for query ORDER BY usage.
 * Allows dotted columns (h.hostname), backtick-quoted identifiers,
 * aggregate functions (COUNT(*), FIELD(status,1,3,2)), and spaces
 * inside function calls. Blocks semicolons, comments, and UNION.
 */
function cacti_validate_sort_column(string $value, string $default = ''): string {
	if (preg_match('/;|--|\/\*|\bUNION\b|\bDROP\b|\bDELETE\b|\bINSERT\b|\bUPDATE\b/i', $value)) {
		return $default;
	}

	return preg_match('/^[a-zA-Z_`][a-zA-Z0-9_`()., *]*$/', $value) ? $value : $default;
}

/**
 * Validate sort direction to strict ASC/DESC.
 */
function cacti_validate_sort_direction(string $value, string $default = 'ASC'): string {
	$value = strtoupper(trim($value));

	return in_array($value, ['ASC', 'DESC'], true) ? $value : $default;
}

/**
 * Normalize an arbitrary id list to strictly positive integers.
 *
 * @return array<int>
 */
function cacti_extract_positive_int_ids(array $items): array {
	return array_values(array_filter(array_map('intval', $items), function ($v) {
		return $v > 0;
	}));
}

/**
 * Verify that forward DNS for $client_name contains $client_addr.
 *
 * @param callable $dns_lookup function(string $host): mixed
 */
function cacti_remote_agent_forward_matches(string $client_addr, string $client_name, callable $dns_lookup): bool {
	$forward_records = $dns_lookup($client_name);

	if (!is_array($forward_records)) {
		return false;
	}

	foreach ($forward_records as $record) {
		$ip = isset($record['ip']) ? $record['ip'] : (isset($record['ipv6']) ? $record['ipv6'] : '');

		if ($ip === $client_addr) {
			return true;
		}
	}

	return false;
}

/**
 * Check if the remote agent host/address is authorized by poller list/whitelist.
 */
function cacti_remote_agent_is_authorized_host(string $client_name, string $client_addr, array $pollers, array $whitelist): bool {
	foreach ($pollers as $poller) {
		if (isset($poller['hostname']) && (strcasecmp($poller['hostname'], $client_name) === 0 || $poller['hostname'] === $client_addr)) {
			return true;
		}
	}

	return in_array($client_addr, $whitelist, true);
}
