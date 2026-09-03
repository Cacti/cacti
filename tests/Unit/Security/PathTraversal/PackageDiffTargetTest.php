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
 * package_diff_file() renders a local file beside the Package's copy. Path
 * containment alone keeps the read under CACTI_PATH_BASE, which still covers
 * include/config.php and the database passwords in it.
 *
 * The signature the diff path checks proves provenance, not trust: a Package
 * carries the public key it is verified against, so anyone able to import can
 * sign their own. import_validate_signature() is the trust check and the diff
 * request never reaches it, so the credential files are denied outright.
 */

require_once dirname(__DIR__, 3) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/Helpers/FakeMySQLPDO.php';
require_once dirname(__DIR__, 4) . '/include/global.php';
require_once dirname(__DIR__, 4) . '/lib/import.php';

use Cacti\Filesystem\CactiPath;

/**
 * Resolve a request filename the way package_diff_file() does, then ask the
 * guard about it.
 */
function packageDiffAllows(string $filename): bool {
	$target = CactiPath::resolveWithinBase(CACTI_PATH_BASE, CACTI_PATH_BASE . '/' . $filename, true);

	return $target !== false && import_diff_target_allowed($target);
}

test('the package diff refuses everything outside the importable directories', function (string $filename) {
	expect(packageDiffAllows($filename))->toBeFalse();
})->with([
	'include/config.php',
	'include/./config.php',
	'resource/../include/config.php',
	'include/config_local.php',
	'include/vendor/composer/autoload_psr4.php',
	// log/ holds SNMP communities and data input arguments; it is the reason a
	// list of refusals was the wrong shape.
	'log/cacti.log',
	'log/cacti_stderr.log',
	'rra/1.rrd',
	'cache/anything',
	'.env',
	'cli/repair_database.php',
]);

test('the package diff allows the directories an import writes to', function (string $filename) {
	expect(packageDiffAllows($filename))->toBeTrue();
})->with([
	'resource/script_server/host_cpu.xml',
	'scripts/ss_host_cpu.php',
	// A file the package adds does not exist locally yet, so it arrives through
	// the missing-leaf branch of the resolver.
	'scripts/not_yet_installed.php',
	'resource/script_queries/new_query.xml',
]);

test('the guard refuses a path outside the base', function () {
	expect(import_diff_target_allowed('/etc/passwd'))->toBeFalse();
});

/**
 * The decode form_actions() performs on an attacker-supplied POST key name.
 *
 * @return array{0:bool, 1:mixed} Whether the entry is usable, and the decoded value.
 */
function packageImportDecodesKey(string $suffix): array {
	$id = json_decode((string) base64_decode($suffix, true), true);

	$usable = is_array($id) && isset($id['pfile'], $id['package'])
		&& is_string($id['pfile']) && is_string($id['package']);

	return [$usable, $id];
}

test('a malformed package import key is skipped rather than fatal', function (string $suffix) {
	[$usable, $id] = packageImportDecodesKey($suffix);

	expect($usable)->toBeFalse();

	// The guard has to run before the typed call, which rejects null outright.
	if ($usable) {
		CactiPath::makeRelativeIfWithinBase($id['pfile'], CACTI_PATH_BASE);
	}
})->with([
	'not base64'          => ['not-base64!!!'],
	'base64, not json'    => ['bm90IGpzb24='],
	'json scalar'         => ['NDI='],
	'json null'           => ['bnVsbA=='],
	'array missing pfile' => ['eyJwYWNrYWdlIjoieCJ9'],
]);

test('a well formed package import key still resolves', function () {
	$suffix = base64_encode((string) json_encode(['pfile' => CACTI_PATH_BASE . '/resource/x.xml', 'package' => 'p']));

	[$usable, $id] = packageImportDecodesKey($suffix);

	expect($usable)->toBeTrue()
		->and(CactiPath::makeRelativeIfWithinBase($id['pfile'], CACTI_PATH_BASE))->toBe('resource/x.xml');
});
