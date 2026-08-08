<?php
declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 */

require_once dirname(__DIR__, 2) . '/lib/remote_agent_transport.php';

test('remote-agent HTTP status parser returns the final valid status', function () {
	expect(remote_agent_http_status([
		'HTTP/1.1 301 Moved Permanently',
		'Location: /other',
		'HTTP/2 204',
	]))->toBe(204)
		->and(remote_agent_http_status(['Content-Type: text/plain']))->toBeNull()
		->and(remote_agent_http_status(['HTTP/1.1 invalid']))->toBeNull();
});

test('HTTPS remote-agent context verifies peers and disables redirects by default', function () {
	$options = remote_agent_context_options('https', 10, true, '/tmp/test-ca.pem', 'collector.example.test');

	expect($options['ssl']['verify_peer'])->toBeTrue()
		->and($options['ssl']['verify_peer_name'])->toBeTrue()
		->and($options['ssl']['allow_self_signed'])->toBeFalse()
		->and($options['ssl']['peer_name'])->toBe('collector.example.test')
		->and($options['ssl']['cafile'])->toBe('/tmp/test-ca.pem')
		->and($options['http']['timeout'])->toBe(10)
		->and($options['http']['follow_location'])->toBe(0)
		->and($options['http']['max_redirects'])->toBe(0)
		->and($options)->not->toHaveKey('https');
});

test('remote-agent TLS verification requires an explicit opt out', function () {
	$options = remote_agent_context_options('https', 5, false, '', '127.0.0.1');

	expect($options['ssl']['verify_peer'])->toBeFalse()
		->and($options['ssl']['verify_peer_name'])->toBeFalse()
		->and($options['ssl']['allow_self_signed'])->toBeTrue()
		->and($options['http']['timeout'])->toBe(5)
		->and($options['ssl'])->not->toHaveKey('cafile');
});

test('HTTP remote-agent context applies timeout without TLS options', function () {
	$options = remote_agent_context_options('http', 5, true);

	expect($options)->toHaveKey('http')
		->and($options)->not->toHaveKey('ssl');
});

test('remote collector handoff enforces path status and response-size contracts', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/lib/functions.php');

	expect($source)->toContain('strpbrk($url, "\\0\\r\\n")')
		->and($source)->toContain('REMOTE_AGENT_MAX_RESPONSE_BYTES + 1')
		->and($source)->toContain('remote_agent_http_status($http_response_header ?? [])')
		->and($source)->toContain('$status < 200 || $status >= 300');
});

test('remote graph JSON accepts only strict supported image envelopes', function (string $image) {
	$envelope = ['image' => base64_encode($image), 'graph_start' => 1];

	expect(remote_graph_json_envelope(json_encode($envelope, JSON_THROW_ON_ERROR)))->toBe($envelope);
})->with([
	'png'                  => "\x89PNG\r\n\x1a\ndata",
	'gif'                  => 'GIF89adata',
	'svg'                  => '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
	'svg with declaration' => '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL . '<svg xmlns="http://www.w3.org/2000/svg"></svg>'
]);

test('remote graph JSON rejects malformed missing and unsupported images', function (string $response) {
	expect(remote_graph_json_envelope($response))->toBeFalse();
})->with([
	'malformed JSON'   => '{',
	'non-object JSON'  => '[]',
	'missing image'    => '{"graph_start":1}',
	'non-string image' => '{"image":7}',
	'invalid base64'   => '{"image":"%%%"}',
	'empty image'      => '{"image":""}',
	'unsupported data' => '{"image":"' . base64_encode('plain text') . '"}',
	'non-SVG XML'      => '{"image":"' . base64_encode('<?xml version="1.0"?><html></html>') . '"}'
]);

test('remote graph endpoints preserve local request identity over remote metadata', function () {
	$source = file_get_contents(dirname(__DIR__, 2) . '/graph_json.php');

	expect($source)->toContain("unset(\$decoded['type'], \$decoded['local_graph_id'], \$decoded['rra_id'])")
		->and($source)->toContain('array_merge($decoded, $oarray)');
});
