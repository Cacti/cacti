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

require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/lib/CactiValidator.php';
require_once dirname(__DIR__, 2) . '/lib/api_automation.php';

use Symfony\Component\Validator\Constraints as Assert;

beforeEach(function () {
	CactiValidator::reset();
});

it('validates automation name (NotBlank)', function () {
	$constraints = [new Assert\NotBlank()];
	expect(CactiValidator::isValid('Default SNMP', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('', $constraints))->toBeFalse();
});

it('validates SNMP community (NotBlank + Regex)', function () {
	$constraints = [new Assert\NotBlank(), new Assert\Regex('/^[a-zA-Z0-9_\-\.]+$/')];
	expect(CactiValidator::isValid('public', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('v2-community.string_123', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('comm unity', $constraints))->toBeFalse();
	expect(CactiValidator::isValid('comm!unity', $constraints))->toBeFalse();
	expect(CactiValidator::isValid('', $constraints))->toBeFalse();
});

it('validates SNMP version (Choice)', function () {
	$constraints = [new Assert\NotBlank(), new Assert\Choice(['1', '2', '3'])];
	expect(CactiValidator::isValid('1', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('2', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('3', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('0', $constraints))->toBeFalse();
	expect(CactiValidator::isValid('4', $constraints))->toBeFalse();
	expect(CactiValidator::isValid('', $constraints))->toBeFalse();
});

it('validates SNMP port (Range)', function () {
	$constraints = [new Assert\NotBlank(), new Assert\Range(min: 1, max: 65535)];
	expect(CactiValidator::isValid('161', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('1', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('65535', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('0', $constraints))->toBeFalse();
	expect(CactiValidator::isValid('65536', $constraints))->toBeFalse();
	expect(CactiValidator::isValid('-1', $constraints))->toBeFalse();
});

it('validates numeric fields (NotBlank + GreaterThanOrEqual)', function () {
	$constraints = [new Assert\NotBlank(), new Assert\Type('numeric'), new Assert\GreaterThanOrEqual(0)];
	expect(CactiValidator::isValid('0', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('100', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('-1', $constraints))->toBeFalse();
	expect(CactiValidator::isValid('abc', $constraints))->toBeFalse();
});

it('validates bulk_walk_size (NotBlank + numeric)', function () {
	$constraints = [new Assert\NotBlank(), new Assert\Type('numeric')];
	expect(CactiValidator::isValid('20', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('-1', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('0', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('abc', $constraints))->toBeFalse();
});

it('verifies automation_validate_input behavior for SNMP', function () {
	if (!defined('SESS_FIELD_VALUES')) define('SESS_FIELD_VALUES', 'sess_field_values');
	if (!defined('SESS_ERROR_FIELDS')) define('SESS_ERROR_FIELDS', 'sess_error_fields');
	
	if (!function_exists('raise_message')) {
		function raise_message($id) { $GLOBALS['raised_message'] = $id; }
	}

	$_SESSION = [];
	$constraints = [new Assert\NotBlank()];
	
	automation_validate_input('snmp_opt', 'name', $constraints);
	expect($_SESSION[SESS_FIELD_VALUES]['name'])->toBe('snmp_opt');
	expect(isset($_SESSION[SESS_ERROR_FIELDS]['name']))->toBeFalse();

	$_SESSION = [];
	$GLOBALS['raised_message'] = null;
	automation_validate_input('', 'name', $constraints, 3);
	expect($_SESSION[SESS_FIELD_VALUES]['name'])->toBe('');
	expect($_SESSION[SESS_ERROR_FIELDS]['name'])->toBe(3);
	expect($GLOBALS['raised_message'])->toBe(3);
});
