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

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';

$root = dirname(__DIR__, 3);

require_once $root . '/include/global_constants.php';
require_once $root . '/include/vendor/autoload.php';
require_once $root . '/lib/functions.php';
require_once $root . '/lib/auth.php';

// Exercises the production local password functions, at the pinned bcrypt
// cost.
$checks = 0;
$check  = static function (bool $condition, string $label) use (&$checks) : void {
	if (!$condition) {
		throw new RuntimeException('Local password matrix failed: ' . $label);
	}
	$checks++;
};

// md5('legacy secret'), as a very old release stored it
$md5    = 'c5af7d393bbc916cec4e8a7bd4d82050';
$sha256 = hash('sha256', 'legacy secret');

$hash = compat_password_hash('modern secret', PASSWORD_DEFAULT);
$check(compat_password_verify('modern secret', $hash), 'current hash verifies');
$check(!compat_password_verify('other secret', $hash), 'wrong password rejected');
$check(!compat_password_needs_rehash($hash, PASSWORD_DEFAULT), 'current hash is current');
$check(!compat_password_verify('anything', ''), 'empty hash never verifies');
$check(!compat_password_verify('anything', 'not a hash'), 'unrecognised hash never verifies');

$check(compat_password_verify('legacy secret', $md5), 'legacy md5 verifies');
$check(compat_password_verify('legacy secret', $sha256), 'legacy sha256 verifies');
$check(!compat_password_verify('wrong secret', $md5), 'wrong legacy md5 rejected');
$check(!compat_password_verify('wrong secret', $sha256), 'wrong legacy sha256 rejected');
$check(compat_password_needs_rehash($md5, PASSWORD_DEFAULT), 'legacy md5 is upgraded');
$check(compat_password_needs_rehash($sha256, PASSWORD_DEFAULT), 'legacy sha256 is upgraded');

$explicit = compat_password_hash('explicit secret', PASSWORD_BCRYPT, ['cost' => 4]);
$check(password_get_info($explicit)['options']['cost'] === 4, 'explicit options are honoured');
$check(!compat_password_needs_rehash($explicit, PASSWORD_BCRYPT, ['cost' => 4]), 'explicit options compare as given');
$check(compat_password_needs_rehash($explicit, PASSWORD_DEFAULT), 'a weak bcrypt is upgraded');

[$algo, $options] = cacti_password_algorithm();

$check($algo === PASSWORD_BCRYPT && $options === ['cost' => 12], 'new hashes are bcrypt cost 12');
$check(password_get_info($hash)['options']['cost'] === 12, 'a new hash carries cost 12');

foreach ([10, 11] as $cost) {
	$check(compat_password_needs_rehash(password_hash('old', PASSWORD_BCRYPT, ['cost' => $cost]), PASSWORD_DEFAULT), 'bcrypt cost ' . $cost . ' is upgraded');
}

$real  = password_hash('decoy secret', $algo, $options);
$decoy = cacti_password_decoy_for($algo, $options);

$check(password_get_info($decoy)['algo'] === password_get_info($real)['algo'], 'decoy algorithm matches');
$check(password_get_info($decoy)['options'] === password_get_info($real)['options'], 'decoy cost matches');
$check(!compat_password_verify('decoy secret', $decoy), 'decoy matches nothing');

$decoy_first  = cacti_password_decoy_hash();
$decoy_second = cacti_password_decoy_hash();

$check($decoy_first === $decoy_second, 'decoy is built once per process');

print json_encode(['passed' => $checks], JSON_THROW_ON_ERROR) . "\n";
