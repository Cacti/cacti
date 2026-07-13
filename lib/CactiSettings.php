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
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/CactiValidator.php';

/**
 * Declarative settings validation backed by Symfony Validator.
 *
 * Settings in include/global_settings.php may declare a 'constraints' key
 * holding an array of callables. Each callable returns a Symfony Constraint
 * instance. The closure form is required because global_settings.php is
 * loaded before include/vendor/autoload.php, so eager instantiation of
 * Symfony classes in the array literal would fatal at file-load time. The
 * closures defer class resolution until validate() runs, by which point the
 * autoloader is registered.
 *
 * CactiSettings::validate() runs the entire posted set through the validator
 * in one pass and returns a map of {setting_name => first violation message}
 * suitable for surfacing via raise_message().
 *
 * The implicit type hints from 'method' (textbox/drop_array/...) are not
 * touched. Callers can mix them with explicit constraints; explicit always
 * wins where both apply.
 */
class CactiSettings {
	/**
	 * Validate posted settings against their declared Symfony constraints.
	 *
	 * CONTRACT: only keys present in $posted_settings run their constraints.
	 * Unposted keys (an unchecked checkbox, an inactive tab, a multiselect
	 * that submitted nothing) are silently skipped. NotBlank-style guards
	 * therefore only fire on fields that always post a value (textboxes,
	 * required selects). For settings that may legitimately not post, write
	 * the constraint as a closure that tolerates an empty string, or
	 * enforce the requirement at the consumer site instead.
	 *
	 * @param array $posted_settings      Map of {name => raw posted value} (typically $_POST).
	 * @param array $settings_definitions Settings definition array as built by global_settings.php.
	 *                                    Either a flat name=>def map or a tab=>name=>def map; both
	 *                                    shapes are tolerated. The 'constraints' key on each entry
	 *                                    is array<callable>: callables that return a Constraint
	 *                                    instance. Plain Constraint instances are also accepted.
	 *
	 * @return array Map of {setting_name => first violation message}. Empty array means valid.
	 */
	public static function validate(array $posted_settings, array $settings_definitions) : array {
		$violations = [];
		$flat       = self::flatten_definitions($settings_definitions);

		foreach ($posted_settings as $name => $value) {
			if (!is_string($name)) {
				continue;
			}

			if (!isset($flat[$name])) {
				continue;
			}

			$definition = $flat[$name];

			if (!is_array($definition) || !isset($definition['constraints'])) {
				continue;
			}

			$constraints = $definition['constraints'];

			if (!is_array($constraints) || empty($constraints)) {
				continue;
			}

			// Resolve closure-wrapped constraints. The closure form is what
			// global_settings.php uses; instantiation happens here, after the
			// autoloader is registered. Plain Constraint instances are still
			// accepted so unit tests and any post-autoload caller can pass
			// them directly.
			$resolved = [];

			foreach ($constraints as $constraint) {
				if (is_callable($constraint)) {
					$resolved[] = $constraint();
				} else {
					$resolved[] = $constraint;
				}
			}

			$result = CactiValidator::validate($value, $resolved);

			if (count($result) === 0) {
				continue;
			}

			// Single-message-per-setting matches the existing raise_message() UX
			// where one message renders per error.
			$violations[$name] = (string) $result->get(0)->getMessage();
		}

		return $violations;
	}

	/**
	 * Accept either {name => def} or {tab => {name => def}} and return the
	 * flat form. The settings array in global_settings.php is grouped by tab,
	 * but callers may pass a single tab's slice instead. Both work.
	 *
	 * @param array $definitions
	 *
	 * @return array
	 */
	private static function flatten_definitions(array $definitions) : array {
		$flat = [];

		foreach ($definitions as $key => $entry) {
			if (!is_array($entry)) {
				continue;
			}

			// A real setting definition has a 'method' key. A tab is a map
			// of names to definitions and lacks 'method' at the top level.
			if (isset($entry['method'])) {
				$flat[$key] = $entry;
			} else {
				foreach ($entry as $sub_name => $sub_entry) {
					if (is_array($sub_entry)) {
						$flat[$sub_name] = $sub_entry;
					}
				}
			}
		}

		return $flat;
	}
}
