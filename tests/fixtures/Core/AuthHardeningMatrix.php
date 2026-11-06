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

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
require dirname(__DIR__, 2) . '/Helpers/IsolatedProbe.php';
require dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
$root       = dirname(__DIR__, 3);
$assertions = 0;
$cases      = [['valid', true, 1, '', 1], ['invalid', false, 1, 'on', 2], ['locked', false, 0, 'on', 2], ['expired', true, 1, '', 0], ['external-valid', true, 1, '', 1], ['external-invalid', false, 1, 'on', 2], ['external-locked', false, 0, 'on', 2], ['external-expired', true, 1, '', 0], ['missing', false, 0, false, 0], ['get', false, 0, '', 1], ['tokenless', false, 0, '', 1]];
$scenarios  = [];

foreach ($cases as $case) {
	$scenarios[] = ['AuthSecondFactorProbe.php', $case[0], $case];
}

foreach (['disabled', 'missing', 'saml-default', 'saml-assertion', 'saml-message'] as $mode) {
	$scenarios[] = ['AuthProviderHardeningProbe.php', $mode, null];
}

foreach ($scenarios as [$fixture, $mode, $expected]) {
	$result = cacti_test_isolated_probe($root . '/tests/fixtures/' . $fixture, [$mode], true);

	if ($expected !== null) {
		[, $authenticated,$checks,$locked,$attempts] = $expected;

		if ($result['authenticated'] !== $authenticated || $result['token_checks'] !== $checks || ($result['user']['locked'] ?? false) !== $locked || ($result['user']['failed_attempts'] ?? 0) !== $attempts) {
			throw new RuntimeException('Second-factor contract mismatch');
		}
	} elseif (str_starts_with($mode,'saml')) {
		if ($result['wantAssertionsSigned'] !== ($mode !== 'saml-message') || $result['wantMessagesSigned'] !== ($mode === 'saml-message')) {
			throw new RuntimeException('SAML settings mismatch');
		}
	} elseif ($result !== ['result'=>[], 'message'=>'Access Denied!  Login Failed.', 'verifications'=>1]) {
		throw new RuntimeException('Generic local failure mismatch');
	}
	$assertions++;
}
print json_encode(['passed' => $assertions], JSON_THROW_ON_ERROR) . "\n";
