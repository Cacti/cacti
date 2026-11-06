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

use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\SodiumPasswordHasher;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/**
 * Local (AUTH_METHOD_CACTI) password hashing/verification backed by
 * symfony/password-hasher, with transparent legacy migration.
 *
 * This is the single implementation behind the lib/auth.php
 * compat_password_hash()/compat_password_verify()/compat_password_needs_rehash()
 * choke points. Callers keep their existing signatures; only the internals
 * move here. The behavior contract is:
 *
 *  - hash():        new/changed passwords use a modern, memory-hard algorithm
 *                   (argon2id via libsodium where available, else bcrypt).
 *  - verify():      accepts argon2id AND bcrypt AND every legacy format Cacti
 *                   has ever stored (raw unsalted md5 / sha256 hex), so no
 *                   existing local account is ever locked out.
 *  - needsRehash(): returns true for any hash that is not the current best
 *                   algorithm (legacy md5/sha256, or bcrypt once argon2id is
 *                   available), so a correct login transparently upgrades the
 *                   stored hash via the existing rehash-on-login path.
 *
 * Degrades gracefully: if symfony/password-hasher is not autoloadable (for
 * example an include/vendor tree that has drifted and is missing the
 * component), every method falls back to the native password_* path that
 * Cacti used before this component was wired in, so a running site can never
 * fatal on a missing dependency.
 */
final class PasswordManager {
	private static ?PasswordHasherInterface $hasher = null;
	private static ?bool $available = null;

	/**
	 * Whether symfony/password-hasher can be loaded. Uses class_exists() with
	 * autoloading enabled: when the component is absent the PSR-4 autoloader
	 * simply fails to resolve the class and returns false, rather than fatally
	 * require()-ing a file that is not on disk.
	 */
	public static function isAvailable() : bool {
		if (self::$available === null) {
			self::$available = class_exists(PasswordHasherFactory::class);
		}

		return self::$available;
	}

	/**
	 * Builds (once) a MigratingPasswordHasher whose best algorithm is argon2id
	 * (libsodium) where supported and bcrypt otherwise, and which additionally
	 * verifies bcrypt hashes. Any hash that is not produced by the best
	 * algorithm is reported by needsRehash(), which drives the migration.
	 */
	private static function hasher() : PasswordHasherInterface {
		if (self::$hasher === null) {
			$primary = SodiumPasswordHasher::isSupported() ? 'sodium' : 'bcrypt';

			$factory = new PasswordHasherFactory([
				'cacti' => array(
					'algorithm'    => $primary,
					'migrate_from' => array('bcrypt'),
				),
			]);

			self::$hasher = $factory->getPasswordHasher('cacti');
		}

		return self::$hasher;
	}

	/**
	 * Hash a new or changed password with the current best algorithm.
	 *
	 * @param string $password The plaintext password to hash.
	 *
	 * @return string The resulting hash.
	 */
	public static function hash(string $password) : string {
		if (self::isAvailable()) {
			return self::hasher()->hash($password);
		}

		// Graceful degradation: identical to the pre-component behavior.
		return password_hash($password, PASSWORD_DEFAULT);
	}

	/**
	 * Verify a plaintext password against a stored hash of any format Cacti
	 * supports: argon2id, bcrypt, or a legacy unsalted md5 / sha256 digest.
	 *
	 * @param string $password The plaintext password submitted at login.
	 * @param string $hash     The stored hash to verify against.
	 *
	 * @return bool True when the password matches the hash.
	 */
	public static function verify(string $password, string $hash) : bool {
		if ($hash === '') {
			return false;
		}

		if (self::isAvailable()) {
			// Covers argon2id and bcrypt (SodiumPasswordHasher falls back to
			// password_verify() for non-argon hashes).
			if (self::hasher()->verify($hash, $password)) {
				return true;
			}
		} elseif (password_verify($password, $hash)) {
			return true;
		}

		// Legacy unsalted formats, preserved from the original
		// compat_password_verify(). hash_equals() keeps the comparison
		// timing-safe.
		return self::verifyLegacy($password, $hash);
	}

	/**
	 * Whether a stored hash should be upgraded to the current best algorithm
	 * on the next successful login.
	 *
	 * @param string $hash The stored hash.
	 *
	 * @return bool True when the hash is a legacy format or a weaker-than-best
	 *              modern algorithm.
	 */
	public static function needsRehash(string $hash) : bool {
		if ($hash === '') {
			// Nothing stored to upgrade; the sole caller already guards this.
			return false;
		}

		// Legacy unsalted digests must always be upgraded.
		if (self::isLegacyHash($hash)) {
			return true;
		}

		if (self::isAvailable()) {
			return self::hasher()->needsRehash($hash);
		}

		return password_needs_rehash($hash, PASSWORD_DEFAULT);
	}

	/**
	 * Verify against the legacy unsalted formats Cacti stored before
	 * password_hash() was adopted: raw md5 and raw sha256 hex digests.
	 */
	private static function verifyLegacy(string $password, string $hash) : bool {
		if (function_exists('md5') && hash_equals($hash, md5($password))) {
			return true;
		}

		return hash_equals($hash, hash('sha256', $password));
	}

	/**
	 * Whether a hash is a legacy unsalted Cacti format: a bare md5 (32 hex
	 * chars) or sha256 (64 hex chars) digest with no algorithm prefix. Modern
	 * hashes ($2y$..., $argon2id$...) start with a '$' and never match.
	 */
	private static function isLegacyHash(string $hash) : bool {
		return (bool) preg_match('/^[a-f0-9]{32}$/i', $hash)
			|| (bool) preg_match('/^[a-f0-9]{64}$/i', $hash);
	}
}
