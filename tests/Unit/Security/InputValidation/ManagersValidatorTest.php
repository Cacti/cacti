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

it('validates description (NotBlank)', function () {
    $constraints = [new Assert\NotBlank()];
    expect(CactiValidator::isValid('Notification Receiver', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeFalse();
});

it('validates hostname (NotBlank)', function () {
    $constraints = [new Assert\NotBlank()];
    expect(CactiValidator::isValid('localhost', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeFalse();
});

it('validates disabled status (Regex /^on$/ optional)', function () {
    $constraints = [new Assert\Regex('/^(on)?$/')];
    expect(CactiValidator::isValid('on', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('off', $constraints))->toBeFalse();
});

it('validates snmp_version (NotBlank, Regex /^[1-3]$/)', function () {
    $constraints = [new Assert\NotBlank(), new Assert\Regex('/^[1-3]$/')];
    expect(CactiValidator::isValid('1', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('2', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('3', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeFalse();
    expect(CactiValidator::isValid('0', $constraints))->toBeFalse();
    expect(CactiValidator::isValid('4', $constraints))->toBeFalse();
});

it('validates snmp_auth_protocol (Regex optional)', function () {
    $constraints = [new Assert\Regex('/^(\[None\]|MD5|SHA|SHA224|SHA256|SHA392|SHA512)?$/')];
    expect(CactiValidator::isValid('MD5', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('[None]', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('INVALID', $constraints))->toBeFalse();
});

it('validates snmp_priv_protocol (Regex optional)', function () {
    $constraints = [new Assert\Regex('/^(\[None\]|DES|AES|AES128|AES192|AES192C|AES256|AES256C)?$/')];
    expect(CactiValidator::isValid('AES128', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('[None]', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('INVALID', $constraints))->toBeFalse();
});

it('validates snmp_port (NotBlank, Regex /^[0-9]+$/)', function () {
    $constraints = [new Assert\NotBlank(), new Assert\Regex('/^[0-9]+$/')];
    expect(CactiValidator::isValid('161', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('0', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeFalse();
    expect(CactiValidator::isValid('abc', $constraints))->toBeFalse();
});

it('validates snmp_message_type (NotBlank, Regex /^[1-2]$/)', function () {
    $constraints = [new Assert\NotBlank(), new Assert\Regex('/^[1-2]$/')];
    expect(CactiValidator::isValid('1', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('2', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('', $constraints))->toBeFalse();
    expect(CactiValidator::isValid('3', $constraints))->toBeFalse();
});
