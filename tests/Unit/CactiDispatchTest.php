<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/* Source-scan tests for lib/cacti_dispatch.php.
 *
 * cacti_dispatch() runs before any controller handler and enforces HTTP
 * method, realm permission, and optional object ACL in that order. The
 * tests here lock in each guard so a future refactor cannot quietly
 * drop a check and regress authorisation for every action table that
 * starts using the helper. */

$source = file_get_contents(__DIR__ . '/../../lib/cacti_dispatch.php');

/* file_get_contents() returns false when the file is missing or
 * unreadable; coerce to an empty string here so each test can rely on
 * $source being a string and the dedicated load-test below still flags
 * the regression with a clear failure. */
if ($source === false) {
	$source = '';
}

test('cacti_dispatch source loads as a string', function () use ($source) {
	expect($source)->toBeString();
	expect(strlen($source))->toBeGreaterThan(0);
});

test('cacti_dispatch reads the action through get_nfilter_request_var', function () use ($source) {
	expect($source)->toContain("\$action  = get_nfilter_request_var('action')");
});

test('cacti_dispatch rejects non-string action inputs before offset access', function () use ($source) {
	expect($source)->toContain('!is_string($action)');
	$is_string_pos = strpos($source, '!is_string($action)');
	$offset_pos    = strpos($source, '!isset($actions[$action])');
	expect($is_string_pos)->not->toBeFalse();
	expect($offset_pos)->not->toBeFalse();
	expect($is_string_pos)->toBeLessThan($offset_pos);
});

test('cacti_dispatch denies unknown actions via cacti_dispatch_deny', function () use ($source) {
	$start = strpos($source, '!isset($actions[$action])');
	expect($start)->not->toBeFalse();

	$block = substr($source, $start, 400);
	expect($block)->toContain("cacti_log('WARNING: cacti_dispatch: unknown action");
	expect($block)->toContain('cacti_dispatch_deny(403)');
});

test('cacti_dispatch returns 405 with an Allow header when the HTTP method does not match', function () use ($source) {
	expect($source)->toContain("\$method !== 'ANY' && \$request_method !== \$method");
	expect($source)->toContain("header('HTTP/1.1 405 Method Not Allowed')");
	expect($source)->toContain("header('Allow: ' . \$method)");
});

test('cacti_dispatch uses cacti_strtoupper and defaults REQUEST_METHOD for CLI', function () use ($source) {
	expect($source)->toContain("cacti_strtoupper((string) \$entry['method'])");
	expect($source)->toContain("isset(\$_SERVER['REQUEST_METHOD']) ? cacti_strtoupper((string) \$_SERVER['REQUEST_METHOD']) : 'GET'");
});

test('cacti_dispatch denies the request when the declared realm is not allowed', function () use ($source) {
	expect($source)->toContain("!is_realm_allowed(\$entry['realm'])");
	$start = strpos($source, '!is_realm_allowed');
	$block = substr($source, $start, 400);
	expect($block)->toContain("cacti_log('WARNING: cacti_dispatch: realm ");
	expect($block)->toContain('cacti_dispatch_deny(403)');
});

test('cacti_dispatch fails closed when a declared object_acl is not callable', function () use ($source) {
	expect($source)->toContain("!is_callable(\$entry['object_acl'])");
	$start = strpos($source, "!is_callable(\$entry['object_acl'])");
	$block = substr($source, $start, 400);
	expect($block)->toContain("cacti_log('ERROR: cacti_dispatch: object_acl for action");
	expect($block)->toContain('cacti_dispatch_deny(403)');
});

test('cacti_dispatch denies when the object_acl callback returns false', function () use ($source) {
	expect($source)->toContain("!call_user_func(\$entry['object_acl'])");
	$start = strpos($source, "!call_user_func(\$entry['object_acl'])");
	$block = substr($source, $start, 400);
	expect($block)->toContain("cacti_log('WARNING: cacti_dispatch: object ACL denied");
});

test('cacti_dispatch enforces the three guards in method -> realm -> object-ACL order', function () use ($source) {
	$method_pos = strpos($source, "\$method !== 'ANY'");
	$realm_pos  = strpos($source, '!is_realm_allowed');
	$acl_pos    = strpos($source, "array_key_exists('object_acl'");

	expect($method_pos)->not->toBeFalse();
	expect($realm_pos)->not->toBeFalse();
	expect($acl_pos)->not->toBeFalse();

	expect($method_pos)->toBeLessThan($realm_pos);
	expect($realm_pos)->toBeLessThan($acl_pos);
});

test('cacti_dispatch runs the callback only after every guard passes', function () use ($source) {
	$dispatch_pos = strpos($source, "call_user_func(\$entry['callback'])");
	$acl_pos      = strpos($source, "array_key_exists('object_acl'");

	expect($dispatch_pos)->not->toBeFalse();
	expect($acl_pos)->toBeLessThan($dispatch_pos);
});

test('cacti_dispatch rejects a non-callable callback with an ERROR log and 500', function () use ($source) {
	expect($source)->toContain("!isset(\$entry['callback']) || !is_callable(\$entry['callback'])");
	expect($source)->toContain("cacti_log('ERROR: cacti_dispatch: callback for action ");
	expect($source)->toContain('cacti_dispatch_deny(500)');
});

test('cacti_dispatch_deny always emits an explicit HTTP status when headers are unsent', function () use ($source) {
	expect($source)->toContain('function cacti_dispatch_deny(');
	$start = strpos($source, 'function cacti_dispatch_deny(');
	$block = substr($source, $start, 600);

	expect($block)->toContain("function_exists('raise_ajax_permission_denied')");
	expect($block)->toContain('http_response_code($status)');
	expect($block)->toContain('!headers_sent()');
});

test('cacti_dispatch_deny clamps invalid status codes to 403', function () use ($source) {
	$start = strpos($source, 'function cacti_dispatch_deny(');
	$block = substr($source, $start, 600);
	expect($block)->toContain('$status < 400 || $status >= 600');
	expect($block)->toContain('$status = 403;');
});
