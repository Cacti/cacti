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

declare(strict_types = 1);

namespace Cacti\Auth;

/** Fixed-duration login attempt budgets shared by all web nodes. */
final class LoginThrottle {
	public static function allow(string $address, string $username) : bool {
		global $config;

		// A remote collector in recovery has no authoritative shared counter.
		if (($config['poller_id'] ?? 1) > 1 && ($config['connection'] ?? '') !== 'online') {
			return false;
		}

		$ip       = inet_pton($address);
		$account  = mb_convert_case(trim($username), MB_CASE_FOLD, 'UTF-8');
		$seconds  = max(1, min(3600, (int) read_config_option('auth_throttle_window')));
		$ip_max   = max(1, min(65535, (int) read_config_option('auth_throttle_ip')));
		$pair_max = max(1, min(65535, (int) read_config_option('auth_throttle_pair')));

		if ($ip !== false && strlen($ip) === 16 && substr($ip, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
			$ip = substr($ip, 12);
		}

		if ($ip === false || !db_begin_transaction()) {
			return false;
		}

		try {
			// Acquire the address row first on every node. A saturated address
			// cannot populate more account rows by submitting new identifiers.
			$allowed = self::consume(hash('sha256', "ip\0" . $ip), $ip_max, $seconds)
				&& self::consume(hash('sha256', "account\0" . $ip . "\0" . $account), $pair_max, $seconds);

			return db_commit_transaction() && $allowed;
		} catch (\Throwable $e) {
			db_rollback_transaction();
			cacti_log('ERROR: Could not update login throttle counters: ' . $e->getMessage(), false, 'AUTH');

			return false;
		}
	}

	private static function consume(string $bucket, int $maximum, int $seconds) : bool {
		// Database time supplies one clock across all web nodes. Compare the old
		// deadline before updating it. Denied attempts do not extend the window.
		$written = db_execute_prepared('INSERT INTO auth_login_windows (bucket, attempts, expires_at)
			VALUES (?, 1, UNIX_TIMESTAMP() + ?)
			ON DUPLICATE KEY UPDATE
				attempts = IF(expires_at <= UNIX_TIMESTAMP(), 1, LEAST(attempts + 1, ?)),
				expires_at = IF(expires_at <= UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + ?, expires_at)',
			[$bucket, $seconds, $maximum + 1, $seconds]);

		if ($written === false) {
			throw new \RuntimeException('Unable to reserve a login attempt.');
		}

		$count = db_fetch_cell_prepared('SELECT attempts FROM auth_login_windows WHERE bucket = ?', [$bucket]);

		return is_numeric($count) && (int) $count > 0 && (int) $count <= $maximum;
	}
}
