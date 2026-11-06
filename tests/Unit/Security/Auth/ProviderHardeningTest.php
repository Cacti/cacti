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

require_once dirname(__DIR__,3) . '/Helpers/IsolatedProbe.php';
test('disabled and missing local accounts have the same generic failure and dummy verification',function () {
	$probe    = dirname(__DIR__,3) . '/fixtures/AuthProviderHardeningProbe.php';
	$disabled = cacti_test_isolated_probe($probe,['disabled']);
	$missing  = cacti_test_isolated_probe($probe,['missing']);
	expect($disabled)->toBe($missing)->and($disabled['result'])->toBe([])->and($disabled['message'])->toBe('Access Denied!  Login Failed.')->and($disabled['verifications'])->toBe(1);
});
test('SAML settings always require an assertion or response signature',function ($mode,$assertion,$message) {
	$r = cacti_test_isolated_probe(dirname(__DIR__,3) . '/fixtures/AuthProviderHardeningProbe.php',[$mode]);
	expect($r['wantAssertionsSigned'])->toBe($assertion)->and($r['wantMessagesSigned'])->toBe($message);
})->with([['saml-default', true, false], ['saml-assertion', true, false], ['saml-message', false, true]]);
