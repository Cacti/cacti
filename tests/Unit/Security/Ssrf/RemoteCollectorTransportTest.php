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

/*
 * A request to a remote data collector has a timeout, does not follow
 * redirects, counts only a 2xx reply as a reply, and reads no more than a
 * fixed number of bytes. PHP reads the request options of both http and https
 * requests from the 'http' context key, so that is where they are set.
 *
 * The probe runs the unchanged get_default_contextoption() from
 * lib/functions.php with the settings answered from the scenario. The reply
 * handling runs here against the PHP built-in web server, which only speaks
 * plain http on a loopback port.
 */

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

function remote_collector_context(...$arguments) {
	return cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/Core/RemoteCollectorContextProbe.php', array(json_encode($arguments)));
}

require_once dirname(__DIR__, 3) . '/Helpers/DataInputHttpServer.php';

function remote_collector_local_url($path) {
	$server = $GLOBALS['remote_collector_server'];

	if ($server === null) {
		return null;
	}

	return 'http://' . $server['address'] . $path; // NOSONAR local plain-HTTP fixture server
}

beforeAll(function () {
	$GLOBALS['remote_collector_server'] = data_input_http_server_start();
});

afterAll(function () {
	data_input_http_server_stop($GLOBALS['remote_collector_server']);
});

test('the request options are under the key PHP reads for both schemes', function ($scheme) {
	$options = remote_collector_context(array('scheme' => $scheme));

	expect($options['http'])->toBe(array(
		'timeout'         => '10',
		'ignore_errors'   => true,
		'follow_location' => 0,
		'max_redirects'   => 0,
	))
		->and($options)->not->toHaveKey('https')
		->and(isset($options['ssl']['follow_location']))->toBeFalse();
})->with(array('http', 'https'));

test('an unusable timeout setting falls back to five seconds', function ($timeout) {
	$options = remote_collector_context(array('scheme' => 'https', 'remote_agent_timeout' => $timeout));

	expect($options['http']['timeout'])->toBe(5);
})->with(array('empty' => array(''), 'zero' => array('0'), 'text' => array('soon')));

test('certificate checks still follow the existing setting', function ($setting, $verify) {
	$options = remote_collector_context(array('scheme' => 'https', 'allow_unsafe_https' => $setting));

	expect($options['ssl'])->toBe(array(
		'verify_peer'       => $verify,
		'verify_peer_name'  => $verify,
		'allow_self_signed' => !$verify,
	));
})->with(array(
	'default' => array('', true),
	'unsafe'  => array('on', false),
));

test('the last status line is the status of the reply', function (array $headers, $status) {
	expect(remote_agent_http_status($headers))->toBe($status);
})->with(array(
	'ok'         => array(array('HTTP/1.1 200 OK', 'Content-Type: text/plain'), 200),
	'not found'  => array(array('HTTP/1.1 404 Not Found'), 404),
	'redirected' => array(array('HTTP/1.1 302 Found', 'Location: /plain', 'HTTP/1.1 200 OK'), 200),
	'HTTP/2'     => array(array('HTTP/2 503'), 503),
	'none'       => array(array('Content-Type: text/plain'), 0),
	'empty'      => array(array(), 0),
));

test('a 2xx reply within the limit is returned', function () {
	$url = remote_collector_local_url('/plain');

	if ($url === null) {
		$this->markTestSkipped('the PHP built-in web server did not start');
	}

	$context = stream_context_create(remote_collector_context(array('scheme' => 'http')));

	expect(remote_agent_read_response($url, $context))->toBe("line one\nline two\n")
		->and(remote_agent_read_response($url, $context, 18))->toBe("line one\nline two\n");
});

test('a reply past the limit is a failed request', function () {
	$url = remote_collector_local_url('/plain');

	if ($url === null) {
		$this->markTestSkipped('the PHP built-in web server did not start');
	}

	$context = stream_context_create(remote_collector_context(array('scheme' => 'http')));

	expect(remote_agent_read_response($url, $context, 17))->toBeFalse();
});

test('an error status or a redirect is a failed request', function ($path) {
	$url = remote_collector_local_url($path);

	if ($url === null) {
		$this->markTestSkipped('the PHP built-in web server did not start');
	}

	$context = stream_context_create(remote_collector_context(array('scheme' => 'http')));

	expect(remote_agent_read_response($url, $context))->toBeFalse();
})->with(array(
	'not found' => array('/missing'),
	'redirect'  => array('/redirect'),
));

test('only an http or https URL is requested', function ($url) {
	expect(remote_agent_read_response($url, stream_context_create(array())))->toBeFalse();
})->with(array(
	'file path'   => array(__FILE__),
	'file scheme' => array('file://' . __FILE__),
	'php stream'  => array('php://memory'),
));
