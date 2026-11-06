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

$root       = dirname(__DIR__,3);
$coverage   = getenv('CACTI_COVERAGE_FILE');
$merged     = [];
$assertions = 0;
$cases      = [['valid', true, 1, '', 1], ['invalid', false, 1, 'on', 2], ['locked', false, 0, 'on', 2], ['expired', true, 1, '', 0], ['external-valid', true, 1, '', 1], ['external-invalid', false, 1, 'on', 2], ['external-locked', false, 0, 'on', 2], ['external-expired', true, 1, '', 0], ['missing', false, 0, false, 0]];
$scenarios  = [];

foreach ($cases as $case) {
	$scenarios[] = ['AuthSecondFactorProbe.php', $case[0], $case];
}

foreach (['disabled', 'missing', 'saml-default', 'saml-assertion', 'saml-message'] as $mode) {
	$scenarios[] = ['AuthProviderHardeningProbe.php', $mode, null];
}

foreach ($scenarios as $index=>[$fixture,$mode,$expected]) {
	$child = $coverage !== false && $coverage !== '' ? $coverage . '.' . $index : false;

	if ($child !== false) {
		putenv('CACTI_COVERAGE_FILE=' . $child);
	}
	$args = [PHP_BINARY];

	if (getenv('CACTI_PR_XDEBUG_EXTENSION')) {
		$args = array_merge($args,['-d', 'zend_extension=' . getenv('CACTI_PR_XDEBUG_EXTENSION')]);
	}
	$args    = array_merge($args,[$root . '/tests/fixtures/' . $fixture, $mode]);
	$process = proc_open($args,[1=>['pipe', 'w'], 2=>['pipe', 'w']],$pipes);
	$output  = stream_get_contents($pipes[1]);
	$error   = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	if (proc_close($process) !== 0 || $error !== '') {
		throw new RuntimeException($mode . ': ' . $error);
	}
	$result = json_decode($output,true,512,JSON_THROW_ON_ERROR);

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

	if ($child !== false) {
		$data = json_decode(file_get_contents($child),true,512,JSON_THROW_ON_ERROR);

		foreach ($data as $file=>$entry) {
			foreach ($entry['lines'] ?? [] as $line=>$hits) {
				$merged[$file]['lines'][$line] = max($hits,$merged[$file]['lines'][$line] ?? -2);
			}
			$merged[$file]['functions'] = [];
		}
		unlink($child);
	}
}

if ($coverage !== false && $coverage !== '') {
	putenv('CACTI_COVERAGE_FILE=' . $coverage);
	file_put_contents($coverage,json_encode($merged,JSON_THROW_ON_ERROR));
}
print json_encode(['passed'=>$assertions],JSON_THROW_ON_ERROR) . "\n";
