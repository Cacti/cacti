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

it('validates data input name (NotBlank)', function () {
    $constraints = [new Assert\NotBlank()];
    expect(CactiValidator::isValid('Get SNMP Data', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeFalse();
});

it('validates type_id (Regex)', function () {
    $constraints = [new Assert\Regex('/^[0-9]+$/')];
    expect(CactiValidator::isValid('1', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('abc', $constraints))->toBeFalse();
});

it('validates data field name (NotBlank)', function () {
    $constraints = [new Assert\NotBlank()];
    expect(CactiValidator::isValid('Community String', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeFalse();
});
