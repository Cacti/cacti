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

namespace Cacti\Security;

use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CactiValidator {
	private static ?ValidatorInterface $validator = null;

	private static function getValidator(): ValidatorInterface {
		if (self::$validator === null) {
			self::$validator = Validation::createValidator();
		}
		return self::$validator;
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
		$violations = self::getValidator()->validate($value, $constraints);
		return count($violations) === 0;
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
	 */
	public static function isValidRrdPath(string $path): bool {
		return self::isValid($path, [
			new Assert\Regex('/^[a-zA-Z0-9_\-\.\/]+$/'),
			new Assert\Regex([
				'pattern' => '/\.\./',
				'match'   => false,
				'message' => 'Path cannot contain ".." traversal.',
			]),
		]);
	}
}
