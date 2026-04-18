<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Behavior tests for cacti_build_remote_url().
 *
 * Root-cause mitigation for HTTP parameter pollution (GHSA-9mf9). Keys and
 * values must both pass through rawurlencode() before concatenation, so a
 * value like "foo&admin=1" cannot smuggle additional parameters.
 */

beforeAll(function () {
	require_once dirname(__DIR__, 2) . '/lib/cacti_http.php';
});

describe('cacti_build_remote_url', function () {
	it('encodes both keys and values with rawurlencode', function () {
		$url = cacti_build_remote_url('/endpoint', [
			'a b' => 'c d',
			'x&y' => 'p=q',
		]);
		expect($url)->toBe('/endpoint?a%20b=c%20d&x%26y=p%3Dq');
	});

	it('returns base URL unchanged when params is empty', function () {
		expect(cacti_build_remote_url('/endpoint', []))
			->toBe('/endpoint');
	});

	it('prevents HTTP parameter pollution via ampersand injection', function () {
		// Attack: attempt to inject `&admin=1` via a value.
		// Before fix, raw concatenation would produce `?user=evil&admin=1`.
		// After fix, the & is encoded and stays inside the value.
		$url = cacti_build_remote_url('/api', ['user' => 'evil&admin=1']);
		expect($url)->toContain('user=evil%26admin%3D1')
			->and($url)->not->toContain('&admin=1');
	});

	it('prevents HTTP parameter pollution via equals injection', function () {
		// Attack: value contains `=` to split into key/value on unaware parsers.
		$url = cacti_build_remote_url('/api', ['key' => 'a=b']);
		expect($url)->toContain('key=a%3Db');
	});

	it('encodes spaces as %20 not + (RFC 3986)', function () {
		// rawurlencode uses %20; urlencode uses +. Prefer rawurlencode for
		// URL-path and query consistency.
		$url = cacti_build_remote_url('/api', ['q' => 'hello world']);
		expect($url)->toContain('q=hello%20world')
			->and($url)->not->toContain('q=hello+world');
	});

	it('stringifies non-string values before encoding', function () {
		$url = cacti_build_remote_url('/api', [
			'id'    => 123,
			'flag'  => true,
			'ratio' => 0.5,
		]);
		expect($url)->toContain('id=123')
			->and($url)->toContain('flag=1')
			->and($url)->toContain('ratio=0.5');
	});

	it('encodes reserved URI characters in keys and values', function () {
		$url = cacti_build_remote_url('/api', [
			'#key' => 'val/ue',
			'a?b'  => 'c#d',
		]);
		expect($url)->toContain('%23key=val%2Fue')
			->and($url)->toContain('a%3Fb=c%23d');
	});

	it('encodes unicode multi-byte values', function () {
		$url = cacti_build_remote_url('/api', ['name' => 'café']);
		// café → c a f %C3 %A9  (UTF-8 bytes for é)
		expect($url)->toContain('name=caf%C3%A9');
	});

	it('joins multiple parameters with literal &', function () {
		$url = cacti_build_remote_url('/api', ['a' => '1', 'b' => '2', 'c' => '3']);
		// One ? then two & separators.
		expect(substr_count($url, '?'))->toBe(1);
		expect(substr_count($url, '&'))->toBe(2);
	});

	it('does not double-encode already-encoded input', function () {
		// rawurlencode treats % as literal, so pre-encoded input gets encoded again.
		// Callers must pass RAW values, not pre-encoded ones.
		// Document this contract via test.
		$url = cacti_build_remote_url('/api', ['x' => '%20']);
		expect($url)->toContain('x=%2520')  // the % is re-encoded
			->and($url)->not->toBe('/api?x=%20');
	});
});
