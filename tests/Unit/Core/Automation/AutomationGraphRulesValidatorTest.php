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

require_once CACTI_PATH_INCLUDE . '/vendor/autoload.php';
require_once CACTI_PATH_LIBRARY . '/CactiValidator.php';
require_once CACTI_PATH_LIBRARY . '/api_automation.php';

use Symfony\Component\Validator\Constraints as Assert;

beforeEach(function () {
	CactiValidator::reset();
});

it('validates graph rule name (NotBlank)', function () {
	$constraints = [new Assert\NotBlank()];
	expect(CactiValidator::isValid('My Rule', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('', $constraints))->toBeFalse();
	expect(CactiValidator::isValid(null, $constraints))->toBeFalse();
});

it('validates graph rule numeric fields (NotBlank + Regex)', function () {
	$constraints = [new Assert\NotBlank(), new Assert\Regex('/^[0-9]+$/')];
	expect(CactiValidator::isValid('123', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('0', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('abc', $constraints))->toBeFalse();
	expect(CactiValidator::isValid('', $constraints))->toBeFalse();
	expect(CactiValidator::isValid(null, $constraints))->toBeFalse();
});

it('validates operation field (Regex allowing empty)', function () {
	$constraints = [new Assert\Regex('/^[-0-9]+$/')];
	expect(CactiValidator::isValid('1', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('-1', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('0', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('', $constraints))->toBeTrue();
	expect(CactiValidator::isValid(null, $constraints))->toBeTrue();
	expect(CactiValidator::isValid('abc', $constraints))->toBeFalse();
});

it('validates operator field (Regex allowing empty)', function () {
	$constraints = [new Assert\Regex('/^[0-9]+$/')];
	expect(CactiValidator::isValid('1', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('', $constraints))->toBeTrue();
	expect(CactiValidator::isValid(null, $constraints))->toBeTrue();
	expect(CactiValidator::isValid('abc', $constraints))->toBeFalse();
});

it('verifies CactiValidator::validateInput populates session and raises message on failure', function () {
    // Define constants if not present
    if (!defined('SESS_FIELD_VALUES')) define('SESS_FIELD_VALUES', 'sess_field_values');
    if (!defined('SESS_ERROR_FIELDS')) define('SESS_ERROR_FIELDS', 'sess_error_fields');

    // Mock raise_message if it doesn't exist (a full-suite run may have already
    // loaded the real one from lib/functions.php; either is safe to call here).
    if (!function_exists('raise_message')) {
        function raise_message($id) {}
    }

    $_SESSION = [];
    $constraints = [new Assert\NotBlank()];

    // Test valid input
    CactiValidator::validateInput('valid', 'test_field', $constraints);
    expect($_SESSION[SESS_FIELD_VALUES]['test_field'])->toBe('valid');
    expect(isset($_SESSION[SESS_ERROR_FIELDS]['test_field']))->toBeFalse();

    // Test invalid input
    $_SESSION = [];
    CactiValidator::validateInput('', 'test_field', $constraints, 999);
    expect($_SESSION[SESS_FIELD_VALUES]['test_field'])->toBe('');
    expect($_SESSION[SESS_ERROR_FIELDS]['test_field'])->toBe(999);
});
