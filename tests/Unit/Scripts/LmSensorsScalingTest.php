<?php

declare(strict_types=1);

test('issue 7403 single-value lm-sensors reads use query-path scaling', function (): void {
	$source = file_get_contents(dirname(__DIR__, 3) . '/scripts/ss_netsnmp_lmsensors.php');
	$start  = strpos($source, "if (\$cacti_request == 'get')", strpos($source, "if (\$cacti_request == 'get')") + 1);

	expect($start)->not->toBeFalse();

	$body = substr($source, $start, strpos($source, "\n\t} else {", $start) - $start);

	expect($body)->toContain("\$sensor_type == 'voltage'")
		->and($body)->toContain("\$sensor_type == 'temperature'")
		->and($source)->not->toContain('4294967294')
		->and(substr_count($source, '4294967296'))->toBe(2)
		->and($body)->toContain('$snmp_test = ($snmp_test / 1000);')
		->and(45000 / 1000)->toBe(45);
});
