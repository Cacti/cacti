<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
require_once dirname(__DIR__, 3) . '/lib/snmp.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 2) . '/Helpers/CiscoNumericFixture.php';
cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/snmp.php', 'cacti_snmp_get_raw', 'CactiTests\\CiscoNumeric');

test('native numeric SNMP bypasses display hints and restores retrieval and warning handlers', function () {
	$state             = \CactiTests\CiscoNumeric\State::class;
	$state::$method    = SNMP_METHOD_PHP;
	$state::$retrieval = 0;
	$state::$warning   = '';
	$handler           = static fn () =>true;
	set_error_handler($handler);

	try {
		expect(\CactiTests\CiscoNumeric\cacti_snmp_get_raw('test','test','.1.2.3','2', numeric_value: true))->toBe('-95');
		expect($state::$retrieval)->toBe(0);
		$previous = set_error_handler($handler);
		restore_error_handler();
		expect($previous)->toBe($handler);

		foreach (['No Such Object available on this agent at this OID'=>'No Such Object', 'No Such Instance currently exists at this OID'=>'No Such Object', 'Error in packet: (noSuchName)'=>'No Such Object', 'No response from test'=>'U', 'Authentication failure'=>'U'] as $warning=>$expected) {
			$state::$warning = $warning;
			expect(\CactiTests\CiscoNumeric\cacti_snmp_get_raw('test','test','.1.2.3','2', numeric_value: true))->toBe($expected);
			expect($state::$retrieval)->toBe(0);
		}
	} finally {
		restore_error_handler();
		$state::$warning = '';
	}
});

test('binary numeric SNMP disables MIB formatting only for numeric callers', function () {
	$state           = \CactiTests\CiscoNumeric\State::class;
	$state::$method  = SNMP_METHOD_PHP + 1;
	$state::$command = '';
	$state::$args    = [];
	$state::$value   = '-95';
	expect(\CactiTests\CiscoNumeric\cacti_snmp_get_raw('test','test','.1.2.3','2', numeric_value: true))->toBe('-95');

	if ($state::$command !== '') {
		expect($state::$command)->toContain("-m '' -OU");
	} else {
		expect(array_slice($state::$args,0,3))->toBe(['-m', '', '-OU']);
	}
	$state::$command = '';
	$state::$args    = [];
	\CactiTests\CiscoNumeric\cacti_snmp_get_raw('test','test','.1.2.3','2');
	expect($state::$command)->not->toContain('-OU');
	expect($state::$args)->not->toContain('-OU');
});
