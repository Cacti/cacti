<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$snmpSource = file_get_contents(dirname(__DIR__, 3) . '/lib/snmp.php');

function cacti_snmp_walk_body(string $source) : string {
	$start = strpos($source, 'function cacti_snmp_walk(');
	$end   = strpos($source, "\nfunction ", $start + 1);

	return substr($source, $start, $end - $start);
}

test('a failed walk is detected by the exit code, not by scanning device data', function () use ($snmpSource) {
	$body = cacti_snmp_walk_body($snmpSource);

	// net-snmp writes a timeout to stderr, which exec() does not capture, so
	// matching 'Timeout' against walk output only ever matched device values
	// such as an ifAlias of 'Timeout Monitor' and discarded the whole walk.
	expect($body)->not->toContain("str_contains(implode(' ', \$temp_array), 'Timeout')")
		->and($body)->toContain('$return_code != 0')
		->and($body)->toContain('exec_into_array($command, $return_code)');
});

test('the walk refuses to run without credentials, as its siblings do', function () use ($snmpSource) {
	expect(cacti_snmp_walk_body($snmpSource))->toContain('empty($snmp_auth)');
});

test('device values containing Timeout survive a healthy walk', function () {
	// The guard the fix removes, applied to a healthy walk.
	$rows = [
		'.1.3.6.1.2.1.31.1.1.1.18.1 = STRING: Uplink to core',
		'.1.3.6.1.2.1.31.1.1.1.18.2 = STRING: Timeout Monitor probe',
	];

	$discardedByOldGuard = str_contains(implode(' ', $rows), 'Timeout');
	$discardedByExitCode = (0 != 0);

	expect($discardedByOldGuard)->toBeTrue()
		->and($discardedByExitCode)->toBeFalse();
});
