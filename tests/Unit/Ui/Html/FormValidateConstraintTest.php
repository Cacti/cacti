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

/*
 * symfony/validator 7.3 deprecated configuring a constraint with an options
 * array, and 8.0 removes it.  The derived and lazy-spec constraints must be
 * built with named arguments so validating a form emits no deprecation.
 */

require_once dirname(__DIR__, 4) . '/lib/form_validate.php';

/**
 * Run a callback and return the E_USER_DEPRECATED messages it raised.
 *
 * @param callable $callback
 *
 * @return array<int,string>
 */
function form_validate_collect_deprecations(callable $callback) : array {
	$messages = [];

	set_error_handler(function (int $errno, string $errstr) use (&$messages) : bool {
		$messages[] = $errstr;

		return true;
	}, E_USER_DEPRECATED);

	try {
		$callback();
	} finally {
		restore_error_handler();
	}

	return $messages;
}

test('derived and lazy-spec constraints are built without deprecations', function () : void {
	$constraints = [];

	$deprecations = form_validate_collect_deprecations(function () use (&$constraints) : void {
		$constraints = array_merge(
			form_validate_constraints_for([
				'friendly_name' => 'Hex Value',
				'method'        => 'textbox',
				'max_length'    => 6,
				'required'      => true,
				'constraints'   => [['Regex', ['pattern' => '/^[0-9A-Fa-f]{6}$/', 'message' => 'bad hex']]],
			]),
			form_validate_constraints_for([
				'friendly_name' => 'Mode',
				'method'        => 'drop_array',
				'array'         => [1 => 'One', 2 => 'Two'],
				'required'      => true,
			])
		);
	});

	expect($deprecations)->toBe([])
		->and(array_map('get_class', $constraints))->toBe([
			Symfony\Component\Validator\Constraints\NotBlank::class,
			Symfony\Component\Validator\Constraints\Length::class,
			Symfony\Component\Validator\Constraints\Regex::class,
			Symfony\Component\Validator\Constraints\NotBlank::class,
			Symfony\Component\Validator\Constraints\Choice::class,
		]);
});

test('named-argument constraints keep their configured limits and messages', function () : void {
	$fields = [
		'hex' => [
			'friendly_name' => 'Hex Value',
			'method'        => 'textbox',
			'max_length'    => 6,
			'constraints'   => [['Regex', ['pattern' => '/^[0-9A-Fa-f]{6}$/', 'message' => 'bad hex']]],
		],
	];

	expect(form_validate_fields($fields, ['hex' => '00FF00']))->toBe([])
		->and(form_validate_fields($fields, ['hex' => 'zz'])['hex'] ?? [])->toContain('bad hex');
});

test('a lazy spec whose options are not an array is skipped', function () : void {
	expect(form_validate_build_constraint(['Regex', 'not-an-array']))->toBeNull();
});

test('a field is only required when it says so', function () : void {
	$fields = [
		'name' => ['friendly_name' => 'Name', 'method' => 'textbox', 'max_length' => 40],
		'hex'  => ['friendly_name' => 'Hex Value', 'method' => 'textbox', 'required' => true],
	];

	expect(array_map('get_class', form_validate_constraints_for($fields['name'])))->toBe([Symfony\Component\Validator\Constraints\Length::class])
		->and(form_validate_fields($fields, ['name' => '', 'hex' => '00FF00']))->toBe([])
		->and(array_keys(form_validate_fields($fields, ['name' => '', 'hex' => ''])))->toBe(['hex']);
});
