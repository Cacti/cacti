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

use Symfony\Component\Validator\Constraints as Assert;

beforeEach(function () {
    CactiValidator::reset();
});

it('validates graph title (NotBlank)', function () {
    $constraints = [new Assert\NotBlank()];
    expect(CactiValidator::isValid('Traffic - eth0', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeFalse();
});

it('validates graph height and width (Regex)', function () {
    $constraints = [new Assert\Regex('/^[0-9]+$/')];
    expect(CactiValidator::isValid('120', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('abc', $constraints))->toBeFalse();
});

it('validates graph limits (Regex)', function () {
    $constraints = [new Assert\Regex('/^((-?([0-9]+(\.[0-9]*)?|[0-9]*\.[0-9]+)([eE][+\-]?[0-9]+)?)|U)$/')];
    expect(CactiValidator::isValid('1000', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('0.001', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('U', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('invalid', $constraints))->toBeFalse();
    expect(CactiValidator::isValid('100;payload', $constraints))->toBeFalse();
    expect(CactiValidator::isValid('abcU', $constraints))->toBeFalse();
});

it('validates right axis (Regex)', function () {
    $constraints = [new Assert\Regex('/^-?([0-9]+(\.[0-9]*)?|\.[0-9]+):-?([0-9]+(\.[0-9]*)?|\.[0-9]+)$/')];
    expect(CactiValidator::isValid('1:0', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('0.5:100', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('invalid', $constraints))->toBeFalse();
});
