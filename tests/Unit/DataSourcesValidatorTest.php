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

it('validates data source name (Regex)', function () {
    $constraints = [new Assert\Regex('/^[a-zA-Z0-9_-]{1,19}$/')];
    expect(CactiValidator::isValid('traffic_in', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('too_long_name_for_rrd', $constraints))->toBeFalse();
    expect(CactiValidator::isValid('invalid!', $constraints))->toBeFalse();
});

it('validates rrd limits with ifSpeed (Regex)', function () {
    $constraints = [new Assert\Regex('/^((-?([0-9]+(\.[0-9]*)?|[0-9]*\.[0-9]+)([eE][+\-]?[0-9]+)?)|U|\|query_ifSpeed\||\|query_ifHighSpeed\|)$/')];
    expect(CactiValidator::isValid('100', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('U', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('|query_ifSpeed|', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('|query_ifHighSpeed|', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('invalid', $constraints))->toBeFalse();
    expect(CactiValidator::isValid('100;payload', $constraints))->toBeFalse();
    expect(CactiValidator::isValid('abcU', $constraints))->toBeFalse();
    expect(CactiValidator::isValid('x|query_ifSpeed|y', $constraints))->toBeFalse();
});

it('validates data source profile id (NotBlank + Regex)', function () {
    $constraints = [new Assert\NotBlank(), new Assert\Regex('/^[0-9]+$/')];
    expect(CactiValidator::isValid('1', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeFalse();
});
