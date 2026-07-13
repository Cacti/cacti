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

it('validates network rule name (NotBlank)', function () {
	$constraints = [new Assert\NotBlank()];
	expect(CactiValidator::isValid('My Network', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('', $constraints))->toBeFalse();
});

it('validates numeric fields (NotBlank + Regex)', function () {
	$constraints = [new Assert\NotBlank(), new Assert\Regex('/^[0-9]+$/')];
	expect(CactiValidator::isValid('123', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('0', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('abc', $constraints))->toBeFalse();
	expect(CactiValidator::isValid('', $constraints))->toBeFalse();
});

it('validates notification email (Email)', function () {
	$constraints = [new Assert\Email(mode: 'html5')];
	expect(CactiValidator::isValid('test@example.com', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('invalid-email', $constraints))->toBeFalse();
	// Email is allowed to be empty in automation_networks.php for some fields (ignore_ips, dns_servers)
	// but here we are testing the Email constraint itself.
	expect(CactiValidator::isValid('', $constraints))->toBeTrue(); // Symfony Email allows empty if not combined with NotBlank
});

it('verifies CactiValidator::validateInput populates session for networks', function () {
	if (!defined('SESS_FIELD_VALUES')) define('SESS_FIELD_VALUES', 'sess_field_values');
	if (!defined('SESS_ERROR_FIELDS')) define('SESS_ERROR_FIELDS', 'sess_error_fields');
	
	if (!function_exists('raise_message')) {
		function raise_message($id) { $GLOBALS['raised_message'] = $id; }
	}

	$_SESSION = [];
	$constraints = [new Assert\NotBlank()];
	
	CactiValidator::validateInput('valid_net', 'name', $constraints);
	expect($_SESSION[SESS_FIELD_VALUES]['name'])->toBe('valid_net');
	expect(isset($_SESSION[SESS_ERROR_FIELDS]['name']))->toBeFalse();

	$_SESSION = [];
	$GLOBALS['raised_message'] = null;
	CactiValidator::validateInput('', 'name', $constraints, 3);
	expect($_SESSION[SESS_FIELD_VALUES]['name'])->toBe('');
	expect($_SESSION[SESS_ERROR_FIELDS]['name'])->toBe(3);
	expect($GLOBALS['raised_message'])->toBe(3);
});
