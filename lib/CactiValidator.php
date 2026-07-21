<?php
declare(strict_types = 1);
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

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CactiValidator {
	private static ?ValidatorInterface $validator = null;

	/**
	 * The validator is built without a Symfony Translator. Cacti uses its
	 * own gettext-based __() pipeline, so call sites are expected to pass
	 * already-translated strings via each constraint's message option
	 * (e.g. message: __('...'), notInRangeMessage: __('...')). Default
	 * Symfony constraint messages render in English; if a constraint
	 * requires localization, supply an explicit __() message argument.
	 */
	private static function getValidator(): ValidatorInterface {
		if (self::$validator === null) {
			self::$validator = Validation::createValidator();
		}

		return self::$validator;
	}

	/**
	 * Reset the cached validator (for tests).
	 */
	public static function reset(): void {
		self::$validator = null;
	}

	/**
	 * Validate a value against a set of constraints.
	 *
	 * @param mixed $value       The value to validate.
	 * @param array $constraints The Symfony constraints to apply.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public static function isValid(mixed $value, array $constraints): bool {
		return count(self::validate($value, $constraints)) === 0;
	}

	/**
	 * Validate a value and return the raw Symfony violations.
	 *
	 * @param mixed $value       The value to validate.
	 * @param array $constraints The Symfony constraints to apply.
	 *
	 * @return ConstraintViolationListInterface
	 */
	public static function validate(mixed $value, array $constraints): ConstraintViolationListInterface {
		return self::getValidator()->validate($value, $constraints);
	}

	/**
	 * Specifically validate a Host ID (numeric and >= 0).
	 *
	 * @param mixed $value The value to validate.
	 *
	 * @return bool True if the value is a valid host id, false otherwise.
	 */
	public static function isValidHostId(mixed $value): bool {
		return self::isValid($value, [
			new Assert\NotNull(),
			new Assert\Type('numeric'),
			new Assert\GreaterThanOrEqual(0),
		]);
	}

	/**
	 * Specifically validate an RRD path to prevent traversal.
	 *
	 * When $rraRoot is supplied, the path is also checked against the real
	 * resolved root to defeat symlink-based escapes. The path must already
	 * exist on disk for this branch to succeed; callers validating a
	 * not-yet-created file should pass $rraRoot = null and check
	 * containment after creation.
	 *
	 * @param string      $path    The relative RRD path to validate.
	 * @param string|null $rraRoot Optional real RRA root to enforce containment against.
	 *
	 * @return bool True if the path is safe and (when $rraRoot is set) contained, false otherwise.
	 */
	public static function isValidRrdPath(string $path, ?string $rraRoot = null): bool {
		if ($path === '' || strpos($path, "\0") !== false) {
			return false;
		}

		if (!preg_match('/^[a-zA-Z0-9_\-\.\/]+$/', $path)) {
			return false;
		}

		if (str_contains($path, '..')) {
			return false;
		}

		if ($path[0] === '/') {
			return false;
		}

		if ($rraRoot !== null) {
			$realRoot = realpath($rraRoot);

			if ($realRoot === false) {
				return false;
			}

			// Find the deepest existing parent directory to validate containment
			// for files that don't exist yet.
			$testPath = $rraRoot . DIRECTORY_SEPARATOR . $path;
			$current  = $testPath;

			while (!file_exists($current) && $current !== dirname($current)) {
				$current = dirname($current);
			}

			$realPath = realpath($current);

			if ($realPath === false) {
				return false;
			}

			if ($realPath !== $realRoot && strncmp($realPath, $realRoot . DIRECTORY_SEPARATOR, strlen($realRoot) + 1) !== 0) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate an IP address (v4 or v6).
	 *
	 * @param string $ip The IP address to validate.
	 *
	 * @return bool True if the value is a valid IPv4 or IPv6 address, false otherwise.
	 */
	public static function isValidIpAddress(string $ip): bool {
		return self::isValid($ip, [
			new Assert\Ip(['version' => 'all']),
		]);
	}

	/**
	 * Validate an email address (HTML5 syntax, max 254 chars).
	 *
	 * @param string $email The email address to validate.
	 *
	 * @return bool True if the value is a valid email address, false otherwise.
	 */
	public static function isValidEmail(string $email): bool {
		return self::isValid($email, [
			new Assert\Email(mode: 'html5'),
			new Assert\Length(max: 254),
		]);
	}

	/**
	 * Validate an SNMP community string (non-blank, max 255 chars, printable ASCII only).
	 *
	 * @param string $community The SNMP community string to validate.
	 *
	 * @return bool True if the value is a valid community string, false otherwise.
	 */
	public static function isValidSnmpCommunity(string $community): bool {
		return self::isValid($community, [
			new Assert\NotBlank(),
			new Assert\Length(max: 255),
			new Assert\Regex('/^[\x20-\x7E]+$/'),
		]);
	}

	/**
	 * Common input validation helper.
	 * Populates $_SESSION with the value and any error states.
	 *
	 * @param mixed  $value       The value to validate.
	 * @param string $name        The field name (used in $_SESSION).
	 * @param array  $constraints The Symfony Validator constraints.
	 * @param mixed  $message_id  The Cacti message ID to raise on failure.
	 *
	 * @return mixed The original value.
	 */
	public static function validateInput(mixed $value, string $name, array $constraints, mixed $message_id = 3): mixed {
		$_SESSION[SESS_FIELD_VALUES][$name] = $value;

		$violations = self::validate($value, $constraints);

		if ($violations->count() > 0) {
			$_SESSION[SESS_ERROR_FIELDS][$name] = $message_id;
			raise_message($message_id);
		}

		return $value;
	}
}
