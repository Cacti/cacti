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
 * get_default_contextoption() is the unchanged function from
 * lib/functions.php, loaded into this namespace so the settings can be
 * answered here. The reply handling runs against the PHP built-in web server.
 */

namespace RemoteCollectorTransportTest;

function read_config_option($name) {
	return isset($GLOBALS['remote_transport'][$name]) ? $GLOBALS['remote_transport'][$name] : '';
}

function get_url_type() {
	return $GLOBALS['remote_transport']['scheme'];
}

function api_plugin_hook_function($hook, $value) {
	return $value;
}

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 3) . '/Helpers/DataInputHttpServer.php';

\cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/functions.php', 'get_default_contextoption', __NAMESPACE__);

function remote_transport_options($scheme, array $settings = array()) {
	$GLOBALS['remote_transport'] = $settings + array('scheme' => $scheme, 'remote_agent_timeout' => '10');

	return get_default_contextoption();
}

beforeAll(function () {
	$GLOBALS['remote_transport_server'] = \data_input_http_server_start();
});

afterAll(function () {
	\data_input_http_server_stop($GLOBALS['remote_transport_server']);
});

test('the request options are under the key PHP reads for both schemes', function ($scheme) {
	$options = remote_transport_options($scheme);

	expect($options['http'])->toBe(array(
		'timeout'         => '10',
		'ignore_errors'   => true,
		'follow_location' => 0,
		'max_redirects'   => 0,
	))
		->and($options)->not->toHaveKey('https')
		->and(isset($options['ssl']['follow_location']))->toBeFalse();
})->with(array('http', 'https'));

test('certificate checks still follow the existing setting', function ($setting, $verify) {
	$options = remote_transport_options('https', array('allow_unsafe_https' => $setting));

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
	expect(\remote_agent_http_status($headers))->toBe($status);
})->with(array(
	'ok'         => array(array('HTTP/1.1 200 OK', 'Content-Type: text/plain'), 200),
	'not found'  => array(array('HTTP/1.1 404 Not Found'), 404),
	'redirected' => array(array('HTTP/1.1 302 Found', 'Location: /plain', 'HTTP/1.1 200 OK'), 200),
	'HTTP/2'     => array(array('HTTP/2 503'), 503),
	'none'       => array(array('Content-Type: text/plain'), 0),
	'empty'      => array(array(), 0),
));

test('a 2xx reply within the limit is returned', function () {
	$server = $GLOBALS['remote_transport_server'];

	if ($server === null) {
		$this->markTestSkipped('the PHP built-in web server did not start');
	}

	$context = stream_context_create(remote_transport_options('http'));
	$url     = 'http://' . $server['address'] . '/plain';

	expect(\remote_agent_read_response($url, $context))->toBe("line one\nline two\n")
		->and(\remote_agent_read_response($url, $context, 18))->toBe("line one\nline two\n");
});

test('a reply past the limit is a failed request', function () {
	$server = $GLOBALS['remote_transport_server'];

	if ($server === null) {
		$this->markTestSkipped('the PHP built-in web server did not start');
	}

	$context = stream_context_create(remote_transport_options('http'));

	expect(\remote_agent_read_response('http://' . $server['address'] . '/plain', $context, 17))->toBeFalse();
});

test('an error status or a redirect is a failed request', function ($path) {
	$server = $GLOBALS['remote_transport_server'];

	if ($server === null) {
		$this->markTestSkipped('the PHP built-in web server did not start');
	}

	$context = stream_context_create(remote_transport_options('http'));

	expect(\remote_agent_read_response('http://' . $server['address'] . $path, $context))->toBeFalse();
})->with(array(
	'not found' => array('/missing'),
	'redirect'  => array('/redirect'),
));
