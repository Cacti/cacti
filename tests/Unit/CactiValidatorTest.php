<?php

require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';

use Cacti\Security\CactiValidator;
use Symfony\Component\Validator\Constraints as Assert;

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

it('can validate against custom constraints', function () {
    $constraints = [
        new Assert\Email(),
        new Assert\NotBlank(),
    ];
    
    expect(CactiValidator::isValid('test@example.com', $constraints))->toBeTrue();
    expect(CactiValidator::isValid('invalid-email', $constraints))->toBeFalse();
});
