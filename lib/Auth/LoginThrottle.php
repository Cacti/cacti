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
	private function __construct(private array $reservations) {
	}

	public static function reserve(string $address, string $username) : ?self {
		global $config;

		// A remote collector in recovery has no authoritative shared counter.
		if (($config['poller_id'] ?? 1) > 1 && ($config['connection'] ?? '') !== 'online') {
			return null;
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
			return null;
		}

		try {
			// Acquire the address row first on every node. A saturated address
			// cannot populate more account rows by submitting new identifiers.
			$ip_key     = hash('sha256', "ip\0" . $ip);
			$pair_key   = hash('sha256', "account\0" . $ip . "\0" . $account);
			$ip_until   = self::consume($ip_key, $ip_max, $seconds);
			$pair_until = $ip_until ? self::consume($pair_key, $pair_max, $seconds) : 0;

			if (!db_commit_transaction() || !$pair_until) {
				return null;
			}

			return new self([$ip_key => $ip_until, $pair_key => $pair_until]);
		} catch (\Throwable $e) {
			db_rollback_transaction();
			cacti_log('ERROR: Could not update login throttle counters: ' . $e->getMessage(), false, 'AUTH');

			return null;
		}
	}

	private static function consume(string $bucket, int $maximum, int $seconds) : int {
		// The upsert locks this bucket until both limits have been evaluated.
		$written = db_execute_prepared('INSERT INTO auth_login_windows (bucket, attempts, expires_at)
			VALUES (?, 0, UNIX_TIMESTAMP() + ?)
			ON DUPLICATE KEY UPDATE
				attempts = IF(expires_at <= UNIX_TIMESTAMP(), 0, attempts),
				expires_at = IF(expires_at <= UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + ?, expires_at)',
			[$bucket, $seconds, $seconds]);

		if ($written === false) {
			throw new \RuntimeException('Unable to reserve a login attempt.');
		}

		$row = db_fetch_row_prepared('SELECT attempts, expires_at FROM auth_login_windows WHERE bucket = ?', [$bucket]);

		if (!is_array($row) || !isset($row['attempts'], $row['expires_at'])) {
			throw new \RuntimeException('Unable to read the reserved login window.');
		}

		if ($row['attempts'] >= $maximum) {
			return 0;
		}

		if (!db_execute_prepared('UPDATE auth_login_windows SET attempts = attempts + 1 WHERE bucket = ?', [$bucket])) {
			throw new \RuntimeException('Unable to count a login attempt.');
		}

		return (int) $row['expires_at'];
	}

	public function completeSuccessfulLogin() : void {
		// Release only this attempt, once, in the windows that admitted it.
		$reservations       = $this->reservations;
		$this->reservations = [];

		if (!$reservations || !db_begin_transaction()) {
			return;
		}

		try {
			foreach ($reservations as $bucket => $until) {
				if (!db_execute_prepared('UPDATE auth_login_windows SET attempts = attempts - 1
					WHERE bucket = ? AND expires_at = ? AND attempts > 0', [$bucket, $until])) {
					throw new \RuntimeException('Unable to release a successful login attempt.');
				}
			}
			db_commit_transaction();
		} catch (\Throwable $e) {
			db_rollback_transaction();
			cacti_log('ERROR: Could not release login throttle counters: ' . $e->getMessage(), false, 'AUTH');
		}
	}
}
