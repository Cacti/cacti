<?php

test('SAML callback exemption preserves signature and request-ID validation', function (string $mode) {
	$root    = dirname(__DIR__, 4);
	$process = proc_open([PHP_BINARY, $root . '/tests/fixtures/SamlCallbackProbe.php', $mode], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	$output  = stream_get_contents($pipes[1]);
	$error   = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	expect(proc_close($process))->toBe(0)->and($error)->toBe('');
	$result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
	$exempt = !in_array($mode, ['inactive', 'disabled', 'openid', 'other-page'], true);
	expect($result['exempt'])->toBe($exempt)->and($result['csrf'])->toBe($exempt);
	expect($result['login'])->toBe($mode === 'success')->and($result['replay'])->toBeFalse();

	if ($mode === 'success') {
		expect($result['username'])->toBe('alice@example.test');
	}
})->with(['success', 'mismatch', 'missing', 'unsigned', 'tampered', 'inactive', 'disabled', 'openid', 'other-page']);
