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

$root = dirname(__DIR__, 2);

test('installer csrf failures return a scoped json recovery response', function () use ($root) {
	$csrf = file_get_contents($root . '/include/csrf.php');

	$jsonBranch = strpos($csrf, "defined('IN_CACTI_INSTALL')");
	$redirect   = strpos($csrf, "raise_message('csrf_timeout')");
	$regenerate = strpos($csrf, 'session_regenerate_id();');
	$freshToken = strpos($csrf, "'csrfMagicToken' => csrf_get_tokens()");

	expect($jsonBranch)->not->toBeFalse()
		->and($redirect)->not->toBeFalse()
		->and($jsonBranch < $redirect)->toBeTrue()
		->and($regenerate < $freshToken)->toBeTrue()
		->and($csrf)->toContain("isset(\$GLOBALS['auth_json'])")
		->and($csrf)->toContain("!empty(\$GLOBALS['is_request_ajax'])")
		->and($csrf)->toContain("http_response_code(403)")
		->and($csrf)->toContain("'error'          => 'csrf_timeout'")
		->and($csrf)->toContain("'csrfMagicToken' => csrf_get_tokens()")
		->and($csrf)->toContain("header('Cache-Control: no-store')")
		->and($csrf)->toContain("header('X-Content-Type-Options: nosniff')")
		->and($csrf)->toContain("header('Location: ' . sanitize_uri(\$_SERVER['REQUEST_URI']))");
});

test('successful installer json responses roll the csrf token forward', function () use ($root) {
	$stepJson = file_get_contents($root . '/install/step_json.php');

	expect($stepJson)->toContain("\$response['csrfMagicToken'] = csrf_get_tokens();")
		->and($stepJson)->toContain("header('Cache-Control: no-store')")
		->and($stepJson)->toContain("header('X-Content-Type-Options: nosniff')")
		->and(strpos($stepJson, "\$response['csrfMagicToken'] = csrf_get_tokens();"))
		->toBeLessThan(strpos($stepJson, "header('Content-Type: application/json')"));
});

test('installer retries csrf timeouts once for every json action', function () use ($root) {
	$javascript = file_get_contents($root . '/install/install.js');

	expect($javascript)->toContain('function refreshCsrfMagicToken(data)')
		->and($javascript)->toContain("data.responseJSON.error == 'csrf_timeout'")
		->and($javascript)->toContain('if (!retryAttempted && tokenRefreshed)')
		->and($javascript)->toContain('function performStep(installStep, suppressRefresh, forceReload, csrfRetry)')
		->and($javascript)->toContain('performStep(installStep, suppressRefresh, forceReload, true);')
		->and($javascript)->toContain('function performTestConnection(csrfRetry)')
		->and($javascript)->toContain('performTestConnection(true);')
		->and($javascript)->toContain('PopupError(response.message, response.title);');
});

test('installer bootstrap establishes json ajax scope before csrf validation', function () use ($root) {
	$stepJson = file_get_contents($root . '/install/step_json.php');
	$global   = file_get_contents($root . '/include/global.php');

	$installMode = strpos($stepJson, "define('IN_CACTI_INSTALL', 1)");
	$jsonMode    = strpos($stepJson, '$auth_json = true;');
	$authInclude = strpos($stepJson, "include_once('include/auth.php');");
	$ajaxDetect  = strpos($global, "strcasecmp(\$_SERVER['HTTP_X_REQUESTED_WITH'], 'xmlhttprequest')");
	$csrfInclude = strpos($global, "require_once(CACTI_PATH_INCLUDE . '/csrf.php');");

	expect($installMode)->not->toBeFalse()
		->and($jsonMode)->not->toBeFalse()
		->and($authInclude)->not->toBeFalse()
		->and($installMode < $authInclude)->toBeTrue()
		->and($jsonMode < $authInclude)->toBeTrue()
		->and($ajaxDetect)->not->toBeFalse()
		->and($csrfInclude)->not->toBeFalse()
		->and($ajaxDetect < $csrfInclude)->toBeTrue();
});
