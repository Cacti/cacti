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
	 * Specifically validate a Host ID.
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
	 */
	public static function isValidIpAddress(string $ip): bool {
		return self::isValid($ip, [
			new Assert\Ip(['version' => 'all']),
		]);
	}

	/**
	 * Validate an email address.
	 */
	public static function isValidEmail(string $email): bool {
		return self::isValid($email, [
			new Assert\Email(mode: 'html5'),
			new Assert\Length(max: 254),
		]);
	}

	/**
	 * Validate an SNMP community string.
	 */
	public static function isValidSnmpCommunity(string $community): bool {
		return self::isValid($community, [
			new Assert\NotBlank(),
			new Assert\Regex('/^[a-zA-Z0-9_\-\.]+$/'),
		]);
	}
}
