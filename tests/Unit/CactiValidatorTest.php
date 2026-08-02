<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Cacti\Security\CactiValidator does not exist on 1.2.x. These tests were
 * merged without lib/CactiValidator.php, so requiring it aborted the whole
 * Unit suite at load time. They are kept as a specification of the intended
 * API and skipped until the helper is actually backported.
 */

test('CactiValidator::isValidHostId validates numeric IDs', function () {
	expect(\Cacti\Security\CactiValidator::isValidHostId(123))->toBeTrue();
	expect(\Cacti\Security\CactiValidator::isValidHostId('456'))->toBeTrue();
	expect(\Cacti\Security\CactiValidator::isValidHostId(0))->toBeTrue();
})->skip('lib/CactiValidator.php is not present on 1.2.x');

test('CactiValidator::isValidHostId rejects invalid IDs', function () {
	expect(\Cacti\Security\CactiValidator::isValidHostId('abc'))->toBeFalse();
	expect(\Cacti\Security\CactiValidator::isValidHostId(-1))->toBeFalse();
	expect(\Cacti\Security\CactiValidator::isValidHostId(null))->toBeFalse();
})->skip('lib/CactiValidator.php is not present on 1.2.x');

test('CactiValidator::isValidRrdPath validates safe paths', function () {
	expect(\Cacti\Security\CactiValidator::isValidRrdPath('local_host_cpu_8.rrd'))->toBeTrue();
	expect(\Cacti\Security\CactiValidator::isValidRrdPath('/var/lib/cacti/rra/test.rrd'))->toBeTrue();
})->skip('lib/CactiValidator.php is not present on 1.2.x');

test('CactiValidator::isValidRrdPath rejects traversal and bad chars', function () {
	expect(\Cacti\Security\CactiValidator::isValidRrdPath('../../../etc/passwd'))->toBeFalse();
	expect(\Cacti\Security\CactiValidator::isValidRrdPath('test.rrd; rm -rf /'))->toBeFalse();
})->skip('lib/CactiValidator.php is not present on 1.2.x');
