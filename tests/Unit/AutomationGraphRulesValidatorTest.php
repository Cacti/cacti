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
