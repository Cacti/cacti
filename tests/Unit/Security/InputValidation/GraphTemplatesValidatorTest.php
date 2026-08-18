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

it('validates graph template name (NotBlank)', function () {
    $constraints = [new Assert\NotBlank()];
    expect(CactiValidator::isValid('Interface - Traffic (bits/sec)', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeFalse();
});

it('validates numeric fields (Regex)', function () {
    $constraints = [new Assert\Regex('/^[0-9]+$/')];
    expect(CactiValidator::isValid('123', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('abc', $constraints))->toBeFalse();
});

it('validates floating point limits (Regex)', function () {
    $constraints = [new Assert\Regex('/^((-?([0-9]+(\.[0-9]*)?|[0-9]*\.[0-9]+)([eE][+\-]?[0-9]+)?)|U)$/')];
    expect(CactiValidator::isValid('100', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('100.5', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('-50.2', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('1.2e10', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('U', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('abc', $constraints))->toBeFalse();
    expect(CactiValidator::isValid('100;payload', $constraints))->toBeFalse();
    expect(CactiValidator::isValid('abcU', $constraints))->toBeFalse();
});

it('validates dashes pattern (Regex)', function () {
    $constraints = [new Assert\Regex('/^[0-9]+[,0-9]*$/')];
    expect(CactiValidator::isValid('5,5', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('10', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('abc', $constraints))->toBeFalse();
});

it('validates textalign (Regex)', function () {
    $constraints = [new Assert\Regex('/^[a-z]+$/')];
    expect(CactiValidator::isValid('left', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('CENTER', $constraints))->toBeFalse(); // Only lowercase a-z
    expect(CactiValidator::isValid('123', $constraints))->toBeFalse();
});
