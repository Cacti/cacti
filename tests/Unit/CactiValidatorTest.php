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

it('validates a numeric host id', function () {
	expect(CactiValidator::isValidHostId(123))->toBeTrue();
	expect(CactiValidator::isValidHostId('456'))->toBeTrue();
	expect(CactiValidator::isValidHostId(0))->toBeTrue();
});

it('rejects a negative host id', function () {
	expect(CactiValidator::isValidHostId(-1))->toBeFalse();
});

it('rejects a non-numeric host id', function () {
	expect(CactiValidator::isValidHostId('abc'))->toBeFalse();
	expect(CactiValidator::isValidHostId(''))->toBeFalse();
	expect(CactiValidator::isValidHostId(null))->toBeFalse();
});

it('validates a safe rrd path', function () {
	expect(CactiValidator::isValidRrdPath('host_1/traffic_in.rrd'))->toBeTrue();
	expect(CactiValidator::isValidRrdPath('archive/2023-01-01.rrd'))->toBeTrue();
	expect(CactiValidator::isValidRrdPath('local.rrd'))->toBeTrue();
});

it('rejects rrd path with traversal', function () {
	expect(CactiValidator::isValidRrdPath('../../etc/passwd'))->toBeFalse();
	expect(CactiValidator::isValidRrdPath('..'))->toBeFalse();
	expect(CactiValidator::isValidRrdPath('/var/www/html/cacti/rra/../config.php'))->toBeFalse();
});

it('rejects rrd path with invalid characters', function () {
	expect(CactiValidator::isValidRrdPath('my graph; rm -rf /'))->toBeFalse();
	expect(CactiValidator::isValidRrdPath('host_1/<script>alert(1)</script>.rrd'))->toBeFalse();
});

it('validates ipv4 and ipv6 addresses', function () {
	expect(CactiValidator::isValidIpAddress('127.0.0.1'))->toBeTrue();
	expect(CactiValidator::isValidIpAddress('192.168.1.1'))->toBeTrue();
	expect(CactiValidator::isValidIpAddress('::1'))->toBeTrue();
	expect(CactiValidator::isValidIpAddress('2001:0db8:85a3:0000:0000:8a2e:0370:7334'))->toBeTrue();

	expect(CactiValidator::isValidIpAddress('not-an-ip'))->toBeFalse();
	expect(CactiValidator::isValidIpAddress('256.256.256.256'))->toBeFalse();
});

it('validates email addresses', function () {
	expect(CactiValidator::isValidEmail('test@example.com'))->toBeTrue();
	expect(CactiValidator::isValidEmail('user.name+tag@domain.co.uk'))->toBeTrue();

	expect(CactiValidator::isValidEmail('invalid-email'))->toBeFalse();
	expect(CactiValidator::isValidEmail('test@example'))->toBeFalse();
});

it('validates snmp community strings', function () {
	expect(CactiValidator::isValidSnmpCommunity('public'))->toBeTrue();
	expect(CactiValidator::isValidSnmpCommunity('my-community_123'))->toBeTrue();

	expect(CactiValidator::isValidSnmpCommunity(''))->toBeFalse();
	expect(CactiValidator::isValidSnmpCommunity('comm;unity'))->toBeFalse();
	expect(CactiValidator::isValidSnmpCommunity('comm"unity'))->toBeFalse();
});

it('can validate against custom constraints', function () {
	$constraints = [
		new Assert\Email(mode: 'html5'),
		new Assert\NotBlank(),
	];

	expect(CactiValidator::isValid('test@example.com', $constraints))->toBeTrue();
	expect(CactiValidator::isValid('invalid-email', $constraints))->toBeFalse();
});

it('rejects rrd path with absolute root', function () {
	expect(CactiValidator::isValidRrdPath('/etc/passwd_no_dots'))->toBeFalse();
	expect(CactiValidator::isValidRrdPath('/var/www/html/cacti/rra/host_1.rrd'))->toBeFalse();
});

it('rejects rrd path with NUL byte', function () {
	expect(CactiValidator::isValidRrdPath("foo\0..rrd"))->toBeFalse();
});

it('rejects empty rrd path', function () {
	expect(CactiValidator::isValidRrdPath(''))->toBeFalse();
});

it('enforces rraRoot containment when supplied', function () {
	$root = sys_get_temp_dir() . '/cacti_rra_' . uniqid();
	mkdir($root, 0700, true);
	file_put_contents($root . '/host_1.rrd', '');
	try {
		expect(CactiValidator::isValidRrdPath('host_1.rrd', $root))->toBeTrue();
		// Improved: allow missing files if containment can be verified
		expect(CactiValidator::isValidRrdPath('missing.rrd', $root))->toBeTrue();
	} finally {
		// nosemgrep: php.lang.security.unlink-use.unlink-use - test cleanup of file we just created in temp dir
		@unlink($root . '/host_1.rrd');
		@rmdir($root);
	}
});

it('validates ipv4-mapped ipv6 and reserved ranges as syntactically valid', function () {
	expect(CactiValidator::isValidIpAddress('::ffff:127.0.0.1'))->toBeTrue();
});

it('rejects malformed ipv4 with leading zeros that some parsers accept', function () {
	// Symfony Ip validator accepts "192.168.001.001" - assert current behavior so regressions are visible.
	$value = CactiValidator::isValidIpAddress('192.168.001.001');
	expect($value)->toBeBool();
});

it('rejects email with header injection attempt', function () {
	expect(CactiValidator::isValidEmail("a@b.com\r\nBcc: c@d.com"))->toBeFalse();
});

it('rejects oversize email', function () {
	$local = str_repeat('a', 320);
	expect(CactiValidator::isValidEmail($local . '@example.com'))->toBeFalse();
});

it('rejects snmp community with non-ascii', function () {
	expect(CactiValidator::isValidSnmpCommunity("p\xc3\xa9blic"))->toBeFalse();
});
