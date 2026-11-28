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

/** Server-side evidence for the MFA settings challenge. */
final class FreshAuthentication {
	public const WINDOW = 300;

	public static function isFresh(int $userId) : bool {
		$proof = $_SESSION['sess_fresh_auth'] ?? [];

		return $userId > 0 && ($proof['user'] ?? 0) === $userId && self::recent($proof['time'] ?? 0);
	}

	private static function recent(int $timestamp) : bool {
		$now = time();

		return $timestamp > 0 && $timestamp <= $now && $now - $timestamp < self::WINDOW;
	}

	public static function record(int $userId, int $timestamp) : void {
		if ($userId > 0 && self::recent($timestamp)) {
			$_SESSION['sess_fresh_auth'] = ['user' => $userId, 'time' => $timestamp];
		}
	}

	public static function begin(int $userId, int $realm) : string {
		unset($_SESSION['sess_fresh_auth']);
		$id                             = bin2hex(random_bytes(16));
		$_SESSION['sess_mfa_challenge'] = ['id' => $id, 'user' => $userId, 'realm' => $realm, 'start' => time()];

		return $id;
	}

	public static function complete(string $challengeId, int $userId, int $realm, int $authenticatedAt) : bool {
		$challenge = $_SESSION['sess_mfa_challenge'] ?? [];

		if (!$challenge || $challengeId !== $challenge['id']) {
			return false;
		}
		unset($_SESSION['sess_mfa_challenge']);

		if ($userId !== $challenge['user']
			|| $userId !== (int) ($_SESSION[SESS_USER_ID] ?? 0) || $realm !== $challenge['realm']
			|| !self::recent($challenge['start']) || !self::recent($authenticatedAt) || $authenticatedAt < $challenge['start']) {
			return false;
		}

		self::record($userId, $authenticatedAt);

		return true;
	}
}
