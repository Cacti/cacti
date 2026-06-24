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
*/

test('FILTER_VALIDATE_REGEXP requires delimiters and works for true/false parameters', function () {
	// Without delimiters, filter_var returns false (and emits a warning in strict environments)
	$broken_regex = '^(true|false)$';

	// The warning text varies across PHP/PCRE builds, so any E_WARNING counts
	$warning_emitted = false;
	set_error_handler(function ($errno) use (&$warning_emitted) {
		// The exact warning text varies across PHP/PCRE builds, so only
		// check the severity
		if ($errno === E_WARNING) {
			$warning_emitted = true;

			return true;
		}

		return false;
	});

	try {
		$broken_result = filter_var('true', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => $broken_regex]]);
	} finally {
		restore_error_handler();
	}

	expect($broken_result)->toBeFalse()
		->and($warning_emitted)->toBeTrue();

	// With delimiters, it works correctly
	$fixed_regex  = '/^(true|false)$/';
	$fixed_result = filter_var('true', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => $fixed_regex]]);
	expect($fixed_result)->toBe('true');

	$fixed_result_false = filter_var('false', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => $fixed_regex]]);
	expect($fixed_result_false)->toBe('false');

	$fixed_result_invalid = filter_var('not_true', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => $fixed_regex]]);
	expect($fixed_result_invalid)->toBeFalse();
});

test('FILTER_VALIDATE_REGEXP works for template parameters with delimiters', function () {
	$regex = '/^(cg_[0-9]+|dq_[0-9]+|-?[0-9]+)$/';

	expect(filter_var('cg_123', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => $regex]]))->toBe('cg_123')
		->and(filter_var('dq_456', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => $regex]]))->toBe('dq_456')
		->and(filter_var('123', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => $regex]]))->toBe('123')
		->and(filter_var('-1', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => $regex]]))->toBe('-1')
		->and(filter_var('invalid', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => $regex]]))->toBeFalse()
		->and(filter_var('-', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => $regex]]))->toBeFalse()
		->and(filter_var('--1', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => $regex]]))->toBeFalse()
		->and(filter_var('1-2', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => $regex]]))->toBeFalse();
});
