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

it('validates repo name (NotBlank + Length)', function () {
    $constraints = [new Assert\NotBlank(), new Assert\Length(max: 32)];
    expect(CactiValidator::isValid('Main Repo', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeFalse();
    expect(CactiValidator::isValid(str_repeat('a', 33), $constraints))->toBeFalse();
});

it('validates repo type (Regex /^[0-2]$/)', function () {
    $constraints = [new Assert\Regex('/^[0-2]$/')];
    expect(CactiValidator::isValid('0', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('1', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('2', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('3', $constraints))->toBeFalse();
    expect(CactiValidator::isValid('abc', $constraints))->toBeFalse();
});

it('validates repo location (NotBlank + Length)', function () {
    $constraints = [new Assert\NotBlank(), new Assert\Length(max: 128)];
    expect(CactiValidator::isValid('https://github.com/Cacti/cacti', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeFalse();
});
