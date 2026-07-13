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

it('validates dynamic custom data (Regex)', function () {
    $dynamic_regex = '^[0-9]+$';
    $constraints = [new Assert\Regex('/' . $dynamic_regex . '/')];
    expect(CactiValidator::isValid('123', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('abc', $constraints))->toBeFalse();
});

it('validates custom data with NotBlank', function () {
    $constraints = [new Assert\NotBlank()];
    expect(CactiValidator::isValid('some data', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeFalse();
});
