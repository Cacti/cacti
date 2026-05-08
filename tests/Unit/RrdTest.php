<?php

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 2) . '/lib/rrd.php';

it('escapes rrdtool strings correctly', function () {
    expect(rrdtool_escape_string('Normal String'))->toBe('Normal String');
    expect(rrdtool_escape_string('String with "quotes"'))->toBe('String with \"quotes\"');
    expect(rrdtool_escape_string('String with :colons:'))->toBe('String with \:colons\:');
});

it('escapes percent signs when requested', function () {
    expect(rrdtool_escape_string('100%', true))->toBe('100%');
    expect(rrdtool_escape_string('100%', false))->toBe('100%%');
});
