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

it('validates host_template (NotBlank + Regex)', function () {
    $constraints = [new Assert\NotBlank(), new Assert\Regex('/^[0-9]+$/')];
    expect(CactiValidator::isValid('1', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('0', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeFalse();
    expect(CactiValidator::isValid(null, $constraints))->toBeFalse();
    expect(CactiValidator::isValid('abc', $constraints))->toBeFalse();
});

it('validates availability_method (NotBlank + Regex)', function () {
    $constraints = [new Assert\NotBlank(), new Assert\Regex('/^[0-9]+$/')];
    expect(CactiValidator::isValid('1', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('0', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeFalse();
    expect(CactiValidator::isValid(null, $constraints))->toBeFalse();
    expect(CactiValidator::isValid('abc', $constraints))->toBeFalse();
});

it('verifies CactiValidator::validateInput populates session and raises message on failure', function () {
    // Define constants if not present
    if (!defined('SESS_FIELD_VALUES')) define('SESS_FIELD_VALUES', 'sess_field_values');
    if (!defined('SESS_ERROR_FIELDS')) define('SESS_ERROR_FIELDS', 'sess_error_fields');
    
    // Mock raise_message if it doesn't exist
    if (!function_exists('raise_message')) {
        function raise_message($id) { $GLOBALS['raised_message'] = $id; }
    }

    $_SESSION = [];
    $constraints = [new Assert\NotBlank(), new Assert\Regex('/^[0-9]+$/')];
    
    // Test valid input
    CactiValidator::validateInput('123', 'host_template', $constraints);
    expect($_SESSION[SESS_FIELD_VALUES]['host_template'])->toBe('123');
    expect(isset($_SESSION[SESS_ERROR_FIELDS]['host_template']))->toBeFalse();

    // Test invalid input
    $_SESSION = [];
    $GLOBALS['raised_message'] = null;
    CactiValidator::validateInput('', 'host_template', $constraints, 3);
    expect($_SESSION[SESS_FIELD_VALUES]['host_template'])->toBe('');
    expect($_SESSION[SESS_ERROR_FIELDS]['host_template'])->toBe(3);
    expect($GLOBALS['raised_message'])->toBe(3);
});
