<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * In Ping-OR-SNMP mode Net_Ping::ping() set $snmp_result to true and then
 * skipped the SNMP test, because the guard read
 * "$avail_method != AVAIL_SNMP_OR_PING". The mode then resolved to
 * "true || $ping_result", so a device could never be reported down however
 * unreachable it was. SNMP is now attempted whenever the ping fails.
 *
 * ping_icmp() and ping_snmp() are the only things here that touch the network,
 * so a subclass replaces them with recorded answers and the real ping() runs.
 */

require_once dirname(__DIR__, 2) . '/include/global_constants.php';
require_once dirname(__DIR__, 2) . '/lib/ping.php';

if (!function_exists('__')) {
	function __($text, ...$args) {
		return $args === [] ? $text : vsprintf($text, $args);
	}
}

if (!function_exists('cacti_log')) {
	function cacti_log($message, $print = false, $tag = 'GENERAL', $level = 1) {
	}
}

/**
 * A Net_Ping whose probes answer from the test instead of the network.
 */
class RecordingPing extends Net_Ping {
	/** @var bool the answer ping_icmp() should give */
	public bool $icmp_answer = false;

	/** @var bool the answer ping_snmp() should give */
	public bool $snmp_answer = false;

	/** @var int how many times ping_snmp() was called */
	public int $snmp_calls = 0;

	public function ping_icmp() : bool {
		$this->ping_status   = $this->icmp_answer ? '1.000' : 'down';
		$this->ping_response = $this->icmp_answer ? 'ok' : 'unreachable';

		return $this->icmp_answer;
	}

	public function ping_snmp() : bool {
		$this->snmp_calls++;

		$this->snmp_status   = $this->snmp_answer ? '1.000' : 'down';
		$this->snmp_response = $this->snmp_answer ? 'ok' : 'no response';

		return $this->snmp_answer;
	}
}

/**
 * Builds a probe for one device.
 *
 * @param array $host        The device fields ping() reads.
 * @param bool  $icmp_answer Whether the ICMP probe should succeed.
 * @param bool  $snmp_answer Whether the SNMP probe should succeed.
 *
 * @return RecordingPing A probe ready to ping().
 */
function ping_probe(array $host, bool $icmp_answer, bool $snmp_answer) : RecordingPing {
	$ping = new RecordingPing();

	$ping->host        = $host + ['hostname' => 'dev.example.net', 'snmp_community' => 'public', 'snmp_version' => 2];
	$ping->icmp_answer = $icmp_answer;
	$ping->snmp_answer = $snmp_answer;

	return $ping;
}

/* Without sockets, ping() rewrites every availability method to AVAIL_SNMP, so
   the OR/AND distinctions the first group asserts would collapse. The fallback
   group is the mirror image and only runs where sockets really is missing, so
   between the two environments every branch is exercised. Run the fallback
   group locally with: php -d disable_functions=socket_create */
function ping_needs_sockets() : void {
	if (!function_exists('socket_create')) {
		test()->markTestSkipped('sockets support is not enabled in this PHP');
	}
}

/**
 * Skips a test when sockets is present, i.e. when the fallback cannot trigger.
 *
 * @return void
 */
function ping_needs_no_sockets() : void {
	if (function_exists('socket_create')) {
		test()->markTestSkipped('sockets support is enabled, so the fallback cannot trigger');
	}
}

test('an OR-mode device answering neither ping nor SNMP is reported down', function () {
	ping_needs_sockets();

	$ping = ping_probe([], false, false);

	// pre-fix this returned true: $snmp_result was assumed true and never tested
	expect($ping->ping(AVAIL_SNMP_OR_PING, PING_ICMP))->toBeFalse();
});

test('an OR-mode device that fails ping but answers SNMP is reported up', function () {
	ping_needs_sockets();

	$ping = ping_probe([], false, true);

	expect($ping->ping(AVAIL_SNMP_OR_PING, PING_ICMP))->toBeTrue()
		->and($ping->snmp_calls)->toBe(1);
});

test('a successful ping still short-circuits the SNMP probe in OR mode', function () {
	ping_needs_sockets();

	$ping = ping_probe([], true, false);

	// the point of OR mode: one success is enough, so do not pay for the walk
	expect($ping->ping(AVAIL_SNMP_OR_PING, PING_ICMP))->toBeTrue()
		->and($ping->snmp_calls)->toBe(0);
});

test('a v1/v2 device with no community is still assumed up when the ping fails', function () {
	ping_needs_sockets();

	$ping = ping_probe(['snmp_community' => '', 'snmp_version' => 2], false, false);

	// there is nothing to test against, so the long-standing behaviour stands
	expect($ping->ping(AVAIL_SNMP_OR_PING, PING_ICMP))->toBeTrue()
		->and($ping->snmp_calls)->toBe(0);
});

test('a v3 device with no community is still probed over SNMP', function () {
	ping_needs_sockets();

	$ping = ping_probe(['snmp_community' => '', 'snmp_version' => 3], false, true);

	expect($ping->ping(AVAIL_SNMP_OR_PING, PING_ICMP))->toBeTrue()
		->and($ping->snmp_calls)->toBe(1);
});

test('AND mode still needs both probes to answer', function () {
	ping_needs_sockets();

	$ping = ping_probe([], true, false);

	expect($ping->ping(AVAIL_SNMP_AND_PING, PING_ICMP))->toBeFalse();

	$ping = ping_probe([], true, true);

	expect($ping->ping(AVAIL_SNMP_AND_PING, PING_ICMP))->toBeTrue();
});

test('AND mode skips the SNMP probe once the ping has already failed', function () {
	ping_needs_sockets();

	$ping = ping_probe([], false, true);

	expect($ping->ping(AVAIL_SNMP_AND_PING, PING_ICMP))->toBeFalse()
		->and($ping->snmp_calls)->toBe(0);
});

/**
 * cmd.php builds reduced host arrays, so these keys can be absent rather than
 * empty. Reading them unguarded raised an undefined-key warning.
 */
test('a host array with no snmp keys at all is handled without warnings', function () {
	ping_needs_sockets();

	$ping = new RecordingPing();

	$ping->host        = ['hostname' => 'dev.example.net'];
	$ping->icmp_answer = false;
	$ping->snmp_answer = false;

	expect($ping->ping(AVAIL_SNMP_OR_PING, PING_ICMP))->toBeTrue()
		->and($ping->snmp_calls)->toBe(0);
});

test('the no-sockets fallback reports a communityless device down, not up', function () {
	ping_needs_no_sockets();

	// the fallback forces AVAIL_SNMP on a device that has no SNMP configured,
	// so there is nothing to test against and "up" would be a fabricated result
	$ping = ping_probe(['snmp_community' => '', 'snmp_version' => 2], false, false);

	expect($ping->ping(AVAIL_SNMP_OR_PING, PING_ICMP))->toBeFalse()
		->and($ping->snmp_calls)->toBe(0);
});

test('the no-sockets fallback still probes a device that has a community', function () {
	ping_needs_no_sockets();

	$ping = ping_probe([], false, true);

	expect($ping->ping(AVAIL_SNMP_OR_PING, PING_ICMP))->toBeTrue()
		->and($ping->snmp_calls)->toBe(1);
});
