<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Tests for the DNS PTR forward-verification guard in remote_client_authorized().
 *
 * The guard calls gethostbyaddr() to resolve the client IP to a hostname, then
 * calls gethostbyname() on that hostname to forward-confirm the PTR result.
 * If the forward resolution does not match the original client IP the hostname
 * is discarded and the raw IP is used instead, preventing PTR spoofing.
 *
 * Two suites:
 *   1. Source-scan: verifies the guard structure is textually present.
 *   2. Behavioural: exercises the guard predicate in isolation.
 */

// ---------------------------------------------------------------------------
// Source-scan suite
// ---------------------------------------------------------------------------

$src = file_get_contents(__DIR__ . '/../../remote_agent.php');

test('remote_client_authorized calls gethostbyaddr to resolve the client IP', function () use ($src) {
	expect($src)->toContain('gethostbyaddr($client_addr)');
});

test('remote_client_authorized calls gethostbyname to forward-verify the PTR result', function () use ($src) {
	expect($src)->toContain('gethostbyname($client_name_raw)');
});

test('forward IP is compared against the original client IP', function () use ($src) {
	// The comparison must use the exact original $client_addr, not a copy or
	// normalised form, to prevent bypass via address formatting differences.
	expect($src)->toContain('$forward_ip !== $client_addr');
});

test('mismatched forward resolution logs a NOTE message', function () use ($src) {
	expect($src)->toContain("cacti_log('NOTE: PTR/forward DNS mismatch for '");
});

test('mismatched forward resolution falls back to raw client IP', function () use ($src) {
	// When the forward check fails, $client_name must be set to $client_addr
	// so the raw IP is used for poller hostname matching rather than the
	// unverified PTR name.
	$mismatch_pos  = strpos($src, '$forward_ip !== $client_addr');
	$fallback_pos  = strpos($src, '$client_name = $client_addr', $mismatch_pos ?: 0);

	expect($mismatch_pos)->not->toBeFalse()
		->and($fallback_pos)->not->toBeFalse()
		->and($mismatch_pos)->toBeLessThan($fallback_pos);
});

test('gethostbyaddr check precedes gethostbyname forward-verify', function () use ($src) {
	$addr_pos = strpos($src, 'gethostbyaddr($client_addr)');
	$name_pos = strpos($src, 'gethostbyname($client_name_raw)');

	expect($addr_pos)->not->toBeFalse()
		->and($name_pos)->not->toBeFalse()
		->and($addr_pos)->toBeLessThan($name_pos);
});

test('when gethostbyaddr returns the IP unchanged the hostname is set to the raw IP', function () use ($src) {
	// gethostbyaddr() returns the input string unchanged when resolution fails.
	// The guard detects this via ($client_name_raw == $client_addr) and skips
	// the forward verify, treating the client as unresolved.
	expect($src)->toContain('$client_name_raw == $client_addr');
});

// ---------------------------------------------------------------------------
// Behavioural suite — exercises the guard predicate in isolation
// ---------------------------------------------------------------------------

/*
 * Replicates the PTR forward-verification decision from remote_client_authorized().
 *
 * Returns the effective client name after applying the guard:
 *   - the PTR hostname when forward DNS confirms it maps back to $client_addr
 *   - $client_addr itself when PTR resolution fails or forward check fails
 *
 * $ptr_result  simulates the return value of gethostbyaddr($client_addr)
 * $forward_result simulates the return value of gethostbyname($ptr_result)
 */
function resolveClientName(string $client_addr, string $ptr_result, string $forward_result): string {
	// gethostbyaddr() returns the input unchanged on failure.
	if ($ptr_result === $client_addr) {
		return $client_addr;
	}

	// Forward-verify.
	if ($forward_result !== $client_addr) {
		return $client_addr;
	}

	// Strip domain suffix (mirrors remote_agent_strip_domain).
	$parts = explode('.', $ptr_result);

	return $parts[0];
}

// --- PTR resolution fails (gethostbyaddr returns the IP) ---

test('unresolved PTR returns the raw client IP', function () {
	// gethostbyaddr() returns the input IP when no PTR record exists.
	$result = resolveClientName('192.0.2.1', '192.0.2.1', '192.0.2.1');

	expect($result)->toBe('192.0.2.1');
});

// --- PTR resolves but forward check fails ---

test('PTR record that does not forward-resolve to the original IP is rejected', function () {
	// Attacker controls PTR to return 'trusted-poller.example.com', but the
	// forward lookup of that name returns a different IP.
	$result = resolveClientName('192.0.2.100', 'trusted-poller.example.com', '10.0.0.1');

	expect($result)->toBe('192.0.2.100');
});

test('PTR record whose forward lookup returns a completely unrelated IP is rejected', function () {
	$result = resolveClientName('198.51.100.5', 'attacker.example.com', '203.0.113.99');

	expect($result)->toBe('198.51.100.5');
});

// --- PTR resolves and forward check passes ---

test('PTR that forward-resolves back to the same IP passes and returns the short hostname', function () {
	// Forward lookup confirms 'poller1.example.com' -> '192.0.2.1'.
	$result = resolveClientName('192.0.2.1', 'poller1.example.com', '192.0.2.1');

	expect($result)->toBe('poller1');
});

test('hostname with multiple domain labels strips everything after the first dot', function () {
	$result = resolveClientName('10.0.0.5', 'poller-dc1.region.example.com', '10.0.0.5');

	expect($result)->toBe('poller-dc1');
});

// --- forward lookup returns the PTR hostname string instead of an IP (resolution failure) ---

test('gethostbyname returning the hostname unchanged counts as a mismatch', function () {
	// gethostbyname() returns the input hostname when it cannot resolve.
	// That string is not equal to the client IP, so the check fails correctly.
	$result = resolveClientName('192.0.2.50', 'ghost.example.com', 'ghost.example.com');

	expect($result)->toBe('192.0.2.50');
});

// --- IPv6 addresses ---

test('IPv6 client address with matching PTR and forward passes', function () {
	$result = resolveClientName('2001:db8::1', 'poller6.example.com', '2001:db8::1');

	expect($result)->toBe('poller6');
});

test('IPv6 client address with mismatched forward is rejected', function () {
	$result = resolveClientName('2001:db8::1', 'evil.example.com', '2001:db8::2');

	expect($result)->toBe('2001:db8::1');
});

// --- forward result is an empty string (unusual resolver behaviour) ---

test('empty string forward result does not match any real IP and is rejected', function () {
	$result = resolveClientName('192.0.2.7', 'poller.example.com', '');

	expect($result)->toBe('192.0.2.7');
});

// --- strict comparison: forward IP must exactly equal client IP ---

test('forward IP with trailing space does not match original IP', function () {
	// The guard uses !== (strict comparison), so whitespace padding in an
	// unexpected resolver response cannot slip through.
	$result = resolveClientName('192.0.2.9', 'poller.example.com', '192.0.2.9 ');

	expect($result)->toBe('192.0.2.9');
});
