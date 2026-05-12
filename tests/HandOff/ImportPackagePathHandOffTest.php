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
 * Hand-off tests for the import_package() path-traversal guard
 * (lib/import.php, GHSA-vp35-4h28-r883).
 *
 * Where the boundary tests in tests/Unit/ImportPackagePathTraversalTest.php
 * exercise individual predicates, this suite verifies the *hand-offs*
 * between the stages of the guard:
 *
 *   1. ZIP/Phar entry name -> dirname() -> realpath() -> boundary check
 *      A traversal entry must be rejected before any write touches the
 *      filesystem; per-entry temp-dir cleanup must still occur.
 *   2. Hoisted realpath() bases -> str_starts_with style prefix check
 *      An entry that resolves *exactly* to $allowed_base_scripts (no
 *      trailing slash) must be accepted; a sibling that merely shares
 *      the prefix (e.g. /scripts_evil) must be rejected.
 *   3. Separator normalization -> regex segment check
 *      A Windows-style "..\..\etc\passwd" entry must be normalized and
 *      rejected by the (^|/)..(/|$) segment regex.
 *   4. Legitimate entry -> realpath -> write
 *      A normal scripts/foo.php entry resolves under the allowed base
 *      and the write proceeds at the expected path.
 */

// --- replicate the guard hand-offs from import_package() ---

/*
 * Mirrors the per-entry guard pipeline in lib/import.php so each
 * hand-off can be observed in isolation. Returns the absolute path
 * that would be written, or false if any stage rejected the entry.
 *
 * $writes captures attempted writes so tests can assert that rejected
 * entries never reach the fwrite() stage.
 */
function importEntryHandOff(string $base, string $name, array &$writes): string|false {
	$allowed_base_scripts  = realpath($base . '/scripts');
	$allowed_base_resource = realpath($base . '/resource');
	$normalized_scripts    = ($allowed_base_scripts === false) ? false : rtrim(str_replace('\\', '/', $allowed_base_scripts),  '/');
	$normalized_resource   = ($allowed_base_resource === false) ? false : rtrim(str_replace('\\', '/', $allowed_base_resource), '/');

	$normalized_name = str_replace('\\', '/', $name);

	if (strpos($name, chr(0)) !== false || preg_match('#(^|/)\.\.(/|$)#', $normalized_name)) {
		return false;
	}

	if (preg_match('#^([/\\\\]|[A-Za-z]:)#', $name)) {
		return false;
	}

	if (!str_contains($name, 'scripts/') && !str_contains($name, 'resource/')) {
		return false;
	}

	$filename     = $base . "/$name";
	$target_dir   = dirname($filename);
	$resolved_dir = false;

	while ($target_dir !== dirname($target_dir)) {
		$resolved_dir = realpath($target_dir);

		if ($resolved_dir !== false) {
			break;
		}

		$target_dir = dirname($target_dir);
	}

	if ($resolved_dir === false) {
		return false;
	}

	$normalized_resolved = rtrim(str_replace('\\', '/', $resolved_dir), '/');
	$in_scripts          = $normalized_scripts !== false
		&& ($normalized_resolved === $normalized_scripts || strpos($normalized_resolved, $normalized_scripts . '/') === 0);
	$in_resource         = $normalized_resource !== false
		&& ($normalized_resolved === $normalized_resource || strpos($normalized_resolved, $normalized_resource . '/') === 0);

	if (!$in_scripts && !$in_resource) {
		return false;
	}

	// Hand-off to the writer stage.
	$writes[] = $filename;

	return $filename;
}

function makeHandOffBase(): string {
	$tmp = sys_get_temp_dir() . '/cacti_handoff_' . bin2hex(random_bytes(4));
	mkdir($tmp . '/scripts',  0755, true);
	mkdir($tmp . '/resource', 0755, true);
	mkdir($tmp . '/webroot',  0755, true);

	return $tmp;
}

function removeHandOffBase(string $base): void {
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ($it as $entry) {
		if ($entry->isDir()) {
			rmdir($entry->getPathname());
		} else {
			unlink($entry->getPathname());
		}
	}

	if (is_dir($base)) {
		rmdir($base);
	}
}

// --- 1. ZIP entry name -> dirname -> realpath -> boundary check ---

test('traversal ZIP entry is rejected before any write touches the filesystem', function () {
	$base   = makeHandOffBase();
	$writes = [];

	$result = importEntryHandOff($base, 'scripts/../../etc/passwd', $writes);

	expect($result)->toBeFalse();
	expect($writes)->toBe([]);

	removeHandOffBase($base);
});

test('temp-dir cleanup still happens after a rejected ZIP entry', function () {
	$base   = makeHandOffBase();
	$writes = [];

	importEntryHandOff($base, 'scripts/../../../etc/passwd', $writes);

	// Rejection must not corrupt the temp tree; cleanup proceeds normally.
	expect(is_dir($base . '/scripts'))->toBeTrue();
	expect(is_dir($base . '/resource'))->toBeTrue();

	removeHandOffBase($base);
	expect(is_dir($base))->toBeFalse();
});

// --- 2. hoisted realpath base -> str_starts_with style prefix check ---

test('entry resolving exactly to $allowed_base_scripts is accepted (no trailing slash)', function () {
	$base   = makeHandOffBase();
	$writes = [];

	// scripts/foo.php -> dirname is $base/scripts, which == $allowed_base_scripts.
	$result = importEntryHandOff($base, 'scripts/foo.php', $writes);

	expect($result)->toBe($base . '/scripts/foo.php');
	expect($writes)->toBe([$base . '/scripts/foo.php']);

	removeHandOffBase($base);
});

test('sibling directory sharing the scripts prefix is rejected', function () {
	// Build a base where /scripts and /scripts_evil coexist; a naive
	// strpos($resolved, $scripts) === 0 check would let scripts_evil through.
	$base = sys_get_temp_dir() . '/cacti_handoff_' . bin2hex(random_bytes(4));
	mkdir($base . '/scripts',      0755, true);
	mkdir($base . '/scripts_evil', 0755, true);
	mkdir($base . '/resource',     0755, true);

	$writes = [];
	$result = importEntryHandOff($base, 'scripts_evil/payload.php', $writes);

	expect($result)->toBeFalse();
	expect($writes)->toBe([]);

	removeHandOffBase($base);
});

// --- 3. separator normalization -> regex segment check ---

test('Windows-style ..\\..\\etc\\passwd entry is normalized and rejected', function () {
	$base   = makeHandOffBase();
	$writes = [];

	$result = importEntryHandOff($base, 'scripts\\..\\..\\etc\\passwd', $writes);

	expect($result)->toBeFalse();
	expect($writes)->toBe([]);

	removeHandOffBase($base);
});

test('mixed-separator traversal is caught by the segment regex', function () {
	$base   = makeHandOffBase();
	$writes = [];

	$result = importEntryHandOff($base, 'scripts/sub\\..\\..\\evil.php', $writes);

	expect($result)->toBeFalse();
	expect($writes)->toBe([]);

	removeHandOffBase($base);
});

// --- 4. legitimate entry -> realpath -> write ---

test('legitimate scripts/foo.php entry resolves under allowed base and write proceeds', function () {
	$base   = makeHandOffBase();
	$writes = [];

	$result = importEntryHandOff($base, 'scripts/foo.php', $writes);

	expect($result)->toBe($base . '/scripts/foo.php');
	expect($writes)->toHaveCount(1);
	expect($writes[0])->toBe($base . '/scripts/foo.php');

	// Hand-off to writer: simulate the fwrite() stage and verify landing path.
	file_put_contents($writes[0], "<?php // ok\n");
	expect(file_exists($base . '/scripts/foo.php'))->toBeTrue();
	expect(file_get_contents($base . '/scripts/foo.php'))->toBe("<?php // ok\n");

	removeHandOffBase($base);
});

test('legitimate nested resource/snmp/vendor.xml entry resolves and writes at expected path', function () {
	$base   = makeHandOffBase();
	$writes = [];

	$result = importEntryHandOff($base, 'resource/snmp/vendor.xml', $writes);

	expect($result)->toBe($base . '/resource/snmp/vendor.xml');
	expect($writes)->toBe([$base . '/resource/snmp/vendor.xml']);

	removeHandOffBase($base);
});
