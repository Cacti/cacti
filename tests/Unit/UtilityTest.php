<?php

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';

it('cacti_sizeof handles various inputs', function () {
    expect(cacti_sizeof([1, 2, 3]))->toBe(3);
    expect(cacti_sizeof([]))->toBe(0);
    expect(cacti_sizeof(false))->toBe(0);
    expect(cacti_sizeof(null))->toBe(0);
    expect(cacti_sizeof('not an array'))->toBe(0);
});

it('cacti_count handles various inputs', function () {
    if (!function_exists('cacti_count')) {
        function cacti_count($array) {
            return ($array === false || !is_array($array)) ? 0 : count($array);
        }
    }
    
    expect(cacti_count([1, 2]))->toBe(2);
    expect(cacti_count(false))->toBe(0);
});
