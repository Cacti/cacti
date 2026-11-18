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

namespace CactiLoginWorkFixture;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

foreach (['cacti_password_algorithm', 'cacti_password_decoy_for', 'cacti_password_decoy_hash', 'compat_password_verify', 'cacti_password_verify_login'] as $function) {
	\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/auth.php', $function, __NAMESPACE__);
}

function password_verify(string $password, string $hash) : bool {
	$info = \password_get_info($hash);
	$GLOBALS['work'] += $info['algo'] === PASSWORD_BCRYPT ? 2 ** $info['options']['cost'] : 0;

	return \password_verify($password, $hash);
}

$passed = 0;

foreach ([4, 10, 11, 12] as $cost) {
	$stored          = \password_hash('stored secret', PASSWORD_BCRYPT, ['cost' => $cost]);
	$GLOBALS['work'] = 0;

	if (cacti_password_verify_login('wrong secret', $stored) || $GLOBALS['work'] !== 2 ** 12) {
		throw new \RuntimeException('Failed login work does not match policy');
	}
	$passed++;
}

$GLOBALS['work'] = 0;

if (cacti_password_verify_login('wrong secret', cacti_password_decoy_hash()) || $GLOBALS['work'] !== 2 ** 12) {
	throw new \RuntimeException('Decoy work does not match policy');
}
$passed++;

$legacy          = 'c5af7d393bbc916cec4e8a7bd4d82050';
$GLOBALS['work'] = 0;

if (cacti_password_verify_login('wrong secret', $legacy) || $GLOBALS['work'] !== 2 ** 12) {
	throw new \RuntimeException('Legacy failure work does not match policy');
}
$passed++;

if (compat_password_verify('legacy secret', $legacy) !== function_exists('md5')) {
	throw new \RuntimeException('Legacy verification did not respect runtime availability');
}
$passed++;

print json_encode(['passed' => $passed], JSON_THROW_ON_ERROR);
