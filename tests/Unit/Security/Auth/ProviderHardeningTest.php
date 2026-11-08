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
test('disabled, missing and wrong-password local logins have the same generic failure and one verification',function () {
	$probe    = dirname(__DIR__,3) . '/fixtures/AuthProviderHardeningProbe.php';
	$disabled = cacti_test_isolated_probe($probe,['disabled']);
	$missing  = cacti_test_isolated_probe($probe,['missing']);
	$wrong    = cacti_test_isolated_probe($probe,['wrong']);
	expect($disabled)->toBe($missing)->and($wrong)->toBe($missing)->and($disabled['result'])->toBe([])->and($disabled['message'])->toBe('Access Denied!  Login Failed.')->and($disabled['verifications'])->toBe(1);
});
test('SAML settings never require a signed outer message, so assertion-only IdPs keep working',function ($mode,$assertion) {
	// php-saml rejects a Response with neither signature regardless of these flags
	// (NO_SIGNATURE_FOUND); wantMessagesSigned would only reject IdPs that sign
	// the assertion alone.
	$r = cacti_test_isolated_probe(dirname(__DIR__,3) . '/fixtures/AuthProviderHardeningProbe.php',[$mode]);
	expect($r['wantAssertionsSigned'])->toBe($assertion)->and($r['wantMessagesSigned'] ?? false)->toBeFalse();
})->with([['saml-default', true], ['saml-assertion', true], ['saml-message', false]]);
