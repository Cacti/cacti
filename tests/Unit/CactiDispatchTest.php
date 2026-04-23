<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/* Source-scan tests for lib/cacti_dispatch.php.
 *
 * cacti_dispatch() runs before any controller handler and enforces HTTP
 * method, realm permission, and optional object ACL in that order. The
 * tests here lock in each guard so future refactors cannot quietly drop
 * a check and regress authorization for every action table that starts
 * using the helper. */

$source = file_get_contents(__DIR__ . '/../../lib/cacti_dispatch.php');

test('cacti_dispatch reads the action through get_nfilter_request_var', function () use ($source) {
	expect($source)->toContain("\$action = get_nfilter_request_var('action')");
});

test('cacti_dispatch rejects unknown actions with a 403 and a WEBUI log', function () use ($source) {
	$start = strpos($source, '!isset($actions[$action])');
	expect($start)->not->toBeFalse();

	$block = substr($source, $start, 300);
	expect($block)->toContain("cacti_log('WARNING: cacti_dispatch: unknown action");
	expect($block)->toContain('raise_ajax_permission_denied()');
});

test('cacti_dispatch returns 405 with an Allow header when the HTTP method does not match', function () use ($source) {
	expect($source)->toContain("\$method !== 'ANY' && \$_SERVER['REQUEST_METHOD'] !== \$method");
	expect($source)->toContain("header('HTTP/1.1 405 Method Not Allowed')");
	expect($source)->toContain("header('Allow: ' . \$method)");
});

test('cacti_dispatch denies the request when the declared realm is not allowed', function () use ($source) {
	expect($source)->toContain('!is_realm_allowed($entry[\'realm\'])');
	$start = strpos($source, '!is_realm_allowed');
	$block = substr($source, $start, 400);
	expect($block)->toContain("cacti_log('WARNING: cacti_dispatch: realm ");
	expect($block)->toContain('raise_ajax_permission_denied()');
});

test('cacti_dispatch runs the per-action object ACL callback before dispatching', function () use ($source) {
	expect($source)->toContain("is_callable(\$entry['object_acl'])");
	expect($source)->toContain('!call_user_func($entry[\'object_acl\'])');
	$start = strpos($source, "is_callable(\$entry['object_acl'])");
	$block = substr($source, $start, 400);
	expect($block)->toContain("cacti_log('WARNING: cacti_dispatch: object ACL denied");
});

test('cacti_dispatch enforces the three guards in method -> realm -> object-ACL order', function () use ($source) {
	$method_pos = strpos($source, "\$method !== 'ANY'");
	$realm_pos  = strpos($source, '!is_realm_allowed');
	$acl_pos    = strpos($source, "is_callable(\$entry['object_acl'])");

	expect($method_pos)->not->toBeFalse();
	expect($realm_pos)->not->toBeFalse();
	expect($acl_pos)->not->toBeFalse();

	expect($method_pos)->toBeLessThan($realm_pos);
	expect($realm_pos)->toBeLessThan($acl_pos);
});

test('cacti_dispatch dispatches through call_user_func only after every guard passes', function () use ($source) {
	$dispatch_pos = strpos($source, 'call_user_func($entry[\'callback\'])');
	$acl_pos      = strpos($source, "is_callable(\$entry['object_acl'])");

	expect($dispatch_pos)->not->toBeFalse();
	expect($acl_pos)->toBeLessThan($dispatch_pos);
});

test('cacti_dispatch rejects a non-callable callback with an ERROR log', function () use ($source) {
	expect($source)->toContain('is_callable($entry[\'callback\'])');
	expect($source)->toContain("cacti_log('ERROR: cacti_dispatch: callback for action ");
});

test('cacti_dispatch ships a fallback raise_ajax_permission_denied', function () use ($source) {
	expect($source)->toContain("function_exists('raise_ajax_permission_denied')");
	$start = strpos($source, "function_exists('raise_ajax_permission_denied')");
	$block = substr($source, $start, 300);
	expect($block)->toContain("header('HTTP/1.1 403 Forbidden')");
});
