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

it('validates color template name (NotBlank)', function () {
    $constraints = [new Assert\NotBlank()];
    expect(CactiValidator::isValid('Default Colors', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeFalse();
    expect(CactiValidator::isValid(null, $constraints))->toBeFalse();
});

it('validates color_id (Type numeric)', function () {
    $constraints = [new Assert\Type('numeric')];
    expect(CactiValidator::isValid('123', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('0', $constraints))->toBeTrue();
    // Symfony Type('numeric') handles numeric strings correctly.
    expect(CactiValidator::isValid('abc', $constraints))->toBeFalse();
});

it('verifies CactiValidator::validateInput behavior', function () {
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
    CactiValidator::validateInput('valid', 'name', $constraints);
    expect($_SESSION[SESS_FIELD_VALUES]['name'])->toBe('valid');
    expect(isset($_SESSION[SESS_ERROR_FIELDS]['name']))->toBeFalse();

    // Test invalid input
    $_SESSION = [];
    CactiValidator::validateInput('', 'name', $constraints, 999);
    expect($_SESSION[SESS_FIELD_VALUES]['name'])->toBe('');
    expect($_SESSION[SESS_ERROR_FIELDS]['name'])->toBe(999);
});
