<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/lib/CactiValidator.php';

test('CactiValidator::isValidHostId validates numeric IDs', function () {
	expect(\Cacti\Security\CactiValidator::isValidHostId(123))->toBeTrue();
	expect(\Cacti\Security\CactiValidator::isValidHostId('456'))->toBeTrue();
	expect(\Cacti\Security\CactiValidator::isValidHostId(0))->toBeTrue();
});

test('CactiValidator::isValidHostId rejects invalid IDs', function () {
	expect(\Cacti\Security\CactiValidator::isValidHostId('abc'))->toBeFalse();
	expect(\Cacti\Security\CactiValidator::isValidHostId(-1))->toBeFalse();
	expect(\Cacti\Security\CactiValidator::isValidHostId(null))->toBeFalse();
});

test('CactiValidator::isValidRrdPath validates safe paths', function () {
	expect(\Cacti\Security\CactiValidator::isValidRrdPath('local_host_cpu_8.rrd'))->toBeTrue();
	expect(\Cacti\Security\CactiValidator::isValidRrdPath('/var/lib/cacti/rra/test.rrd'))->toBeTrue();
});

test('CactiValidator::isValidRrdPath rejects traversal and bad chars', function () {
	expect(\Cacti\Security\CactiValidator::isValidRrdPath('../../../etc/passwd'))->toBeFalse();
	expect(\Cacti\Security\CactiValidator::isValidRrdPath('test.rrd; rm -rf /'))->toBeFalse();
});
