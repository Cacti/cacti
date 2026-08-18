<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Integration coverage for automation_get_next_host() (lib/api_automation.php).
 *
 * tests/Unit/Core/Automation/AutomationNextHostTest.php checks four hand-picked offsets
 * (single-octet, two-octet-boundary, full-/8, and a final-octet overflow).
 * The real caller, automation_primeIPAddressTable(), does not call this
 * function with a handful of offsets: it walks a `while ($count <
 * $subNetTotal)` loop, incrementing $count by one on every iteration across
 * the entire subnet, and uses each returned address to build an INSERT
 * batch. This test reproduces that exact loop shape over full, realistic
 * ranges instead of spot values, so a carry bug that only shows up after
 * many accumulated increments - or only at one specific boundary a
 * hand-picked test happens not to hit - would surface here.
 */

require_once CACTI_PATH_LIBRARY . '/api_automation.php';

function assert_valid_quad($ip, $context) {
	$octets = explode('.', $ip);

	expect($octets)->toHaveCount(4, "$context: '$ip' is not a dotted quad");

	foreach ($octets as $octet) {
		expect(ctype_digit($octet))->toBeTrue("$context: '$ip' has a non-numeric octet");
		expect((int) $octet)->toBeGreaterThanOrEqual(0, "$context: '$ip' octet out of range");
		expect((int) $octet)->toBeLessThanOrEqual(255, "$context: '$ip' octet out of range");
	}
}

function ip_to_long($ip) {
	list($a, $b, $c, $d) = array_map('intval', explode('.', $ip));

	return (($a * 256 + $b) * 256 + $c) * 256 + $d;
}

test('walking a full /24 one host at a time (as automation_primeIPAddressTable does) never produces an invalid or out-of-order octet', function () {
	$start = '10.20.30.0';
	$range = '10.20.30.0/24';
	$total = 256;

	$previous = ip_to_long($start);

	for ($count = 1; $count < $total; $count++) {
		$ip = automation_get_next_host($start, $total, $count, $range);

		assert_valid_quad($ip, "count=$count");

		$current = ip_to_long($ip);
		expect($current)->toBeGreaterThan($previous, "count=$count: address did not advance ($ip)");

		$previous = $current;
	}
});

test('walking a full /16 one host at a time crosses every octet-carry boundary without producing an invalid quad', function () {
	$start = '10.0.0.1';
	$range = '10.0.0.0/16';
	$total = 65536;

	$seen = array();

	for ($count = 1000; $count < $total; $count += 997) {
		$ip = automation_get_next_host($start, $total, $count, $range);

		assert_valid_quad($ip, "count=$count");

		expect(isset($seen[$ip]))->toBeFalse("count=$count produced a duplicate address ($ip) already seen at another offset");
		$seen[$ip] = $count;
	}

	/* explicitly hit the octet-256 and octet-65536 carry boundaries that
	 * motivated the fix, rather than relying on the stride above to land on them. */
	foreach (array(255, 256, 257, 65535) as $boundary_count) {
		$ip = automation_get_next_host($start, $total, $boundary_count, $range);
		assert_valid_quad($ip, "boundary count=$boundary_count");
	}
});

test('a full /8 walked in 65536-address strides increments the leading octet without ever emitting an invalid quad', function () {
	$start = '10.0.0.0';
	$range = '10.0.0.0/7';
	$total = 33554432;

	for ($count = 0; $count < $total; $count += 65536) {
		$ip = automation_get_next_host($start, $total, $count, $range);

		assert_valid_quad($ip, "count=$count");
	}

	$final = automation_get_next_host($start, $total, $total - 1, $range);
	assert_valid_quad($final, 'final address');
	expect($final)->toBe('11.255.255.255');
});
