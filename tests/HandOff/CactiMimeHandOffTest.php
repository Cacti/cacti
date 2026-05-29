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

if (!file_exists(__DIR__ . '/../../lib/CactiMime.php')) {
	test('CactiMime hand-off: feature not present on this branch', function () {})
		->skip('lib/CactiMime.php absent — feature PR #7074 not merged into develop yet');
	return;
}

require_once __DIR__ . '/../../include/vendor/autoload.php';
require_once __DIR__ . '/../../lib/CactiMime.php';
require_once __DIR__ . '/../Unit/fixtures/mime/build_fixtures.php';

// Stub cacti_log so the libmagic-missing branch in CactiMime can run
// without pulling in lib/functions.php and its database dependencies.
if (!function_exists('cacti_log')) {
	function cacti_log(mixed $string, bool $output = false, string $environ = 'CMDPHP', mixed $level = '') : bool {
		return true;
	}
}

/**
 * Hand-off contracts between the upload form (package_import.php) and
 * CactiMime. Unit tests cover the class in isolation; these tests pin the
 * boundary the production caller actually relies on:
 *
 *   - $_FILES['import_file']['tmp_name'] is detected by content, not by
 *     the user-supplied filename.
 *   - $_FILES['import_file']['type'] (browser-supplied) never reaches the
 *     allowlist; the detected content MIME does.
 *   - The allowlist returned by packageImportMimes() drives the caller's
 *     accept/reject branch (the same branch that issues raise_message +
 *     header('Location: ...') in package_import.php form_save()).
 *   - When libmagic is absent, validate(strict=true) returns false and
 *     that boolean propagates to the caller's reject path.
 */

beforeAll(function () {
	cacti_mime_build_fixtures();
});

test('detect reads tmp_name content bytes, not the user-supplied filename', function () {
	// Simulate the exact upload shape package_import.php receives: a real ZIP
	// arrived in tmp_name but the browser's file-picker handed it a .png name.
	// PHP rewrites tmp_name to a server-controlled path, so the spoofed
	// extension only lives on $_FILES['import_file']['name'] -- detect()
	// must ignore it and read the magic bytes from tmp_name.
	$fx = cacti_mime_build_fixtures();

	$tmp_name = sys_get_temp_dir() . '/cacti-mime-handoff-' . getmypid() . '-phpUPLOAD';
	copy($fx['zip'], $tmp_name);

	$files_entry = [
		'name'     => 'innocent.png',
		'type'     => 'image/png',
		'tmp_name' => $tmp_name,
		'error'    => 0,
		'size'     => filesize($tmp_name),
	];

	expect(CactiMime::detect($files_entry['tmp_name']))->toBe('application/zip');

	unlink($tmp_name);
});

test('browser-supplied $_FILES type is ignored; detect uses content', function () {
	// The browser claims application/json. The bytes are XML. CactiMime
	// must report the XML content type, never the browser's claim.
	$fx = cacti_mime_build_fixtures();

	$tmp_name = sys_get_temp_dir() . '/cacti-mime-handoff-' . getmypid() . '-phpBROWSER';
	copy($fx['xml'], $tmp_name);

	$files_entry = [
		'name'     => 'data.json',
		'type'     => 'application/json',
		'tmp_name' => $tmp_name,
		'error'    => 0,
		'size'     => filesize($tmp_name),
	];

	$detected = CactiMime::detect($files_entry['tmp_name']);

	expect($detected)->not->toBe($files_entry['type']);
	expect($detected)->toBeIn(['application/xml', 'text/xml', 'application/x-xml']);

	unlink($tmp_name);
});

test('allowlist drives the caller accept branch for a real ZIP upload', function () {
	// Mirrors the form_save() branch:
	//   if (!CactiMime::validate($xmlfile, CactiMime::packageImportMimes())) { reject }
	// A real ZIP must traverse the accept side.
	$fx = cacti_mime_build_fixtures();

	$tmp_name = sys_get_temp_dir() . '/cacti-mime-handoff-' . getmypid() . '-phpACCEPT';
	copy($fx['zip'], $tmp_name);

	$files_entry = [
		'name'     => 'package.zip',
		'type'     => 'application/zip',
		'tmp_name' => $tmp_name,
		'error'    => 0,
		'size'     => filesize($tmp_name),
	];

	expect(CactiMime::validate($files_entry['tmp_name'], CactiMime::packageImportMimes()))->toBeTrue();

	unlink($tmp_name);
});

test('allowlist drives the caller reject branch for a PHP payload renamed .zip', function () {
	// Same form_save() branch, but with the obvious attack: a PHP file
	// named import.zip. The reject side is what triggers raise_message +
	// header('Location: package_import.php') in production.
	$tmp_name = sys_get_temp_dir() . '/cacti-mime-handoff-' . getmypid() . '-phpREJECT';
	file_put_contents($tmp_name, "<?php phpinfo(); ?>\n");

	$files_entry = [
		'name'     => 'import.zip',
		'type'     => 'application/zip',
		'tmp_name' => $tmp_name,
		'error'    => 0,
		'size'     => filesize($tmp_name),
	];

	$accepted = CactiMime::validate($files_entry['tmp_name'], CactiMime::packageImportMimes());

	expect($accepted)->toBeFalse();

	// Model the caller's branch: validate()===false flows into a
	// raise_message + header('Location: ...') reject path. Pin that the
	// boolean is the trigger.
	$rejected_branch_taken = ($accepted === false);
	expect($rejected_branch_taken)->toBeTrue();

	unlink($tmp_name);
});

test('libmagic absence drives validate(strict=true) to false at the caller boundary', function () {
	// CactiMimeTest already verifies the strict-mode boolean inside the
	// class via a subprocess. This hand-off variant asserts the same
	// boolean reaches the caller's reject branch -- the one that emits
	// the "import_mime_unavailable" raise_message and redirects the user.
	$fx         = cacti_mime_build_fixtures();
	$scriptPath = tempnam(sys_get_temp_dir(), 'cacti-mime-handoff-strict-');
	$libPath    = realpath(__DIR__ . '/../../lib/CactiMime.php');

	// The subprocess models the caller's boundary explicitly: it
	// invokes validate() and prints the exact branch token the caller
	// would take (ACCEPT or REJECT). When finfo is disabled, strict
	// mode must yield REJECT.
	$script  = "<?php\n";
	$script .= 'require_once ' . var_export($libPath, true) . ";\n";
	$script .= "if (!function_exists('cacti_log')) { function cacti_log(\$s,\$o=false,\$e='CMDPHP',\$l=''){ return true; } }\n";
	$script .= '$ok = CactiMime::validate($argv[1], CactiMime::packageImportMimes(), true);' . "\n";
	$script .= 'echo $ok ? "ACCEPT" : "REJECT";' . "\n";

	file_put_contents($scriptPath, $script);

	try {
		$command = escapeshellarg(PHP_BINARY)
			. ' -d disable_functions=finfo_open,finfo_file,finfo_close '
			. escapeshellarg($scriptPath)
			. ' '
			. escapeshellarg($fx['zip'])
			. ' 2>&1';

		$output = [];
		$status = 0;
		exec($command, $output, $status);

		expect($status)->toBe(0);
		expect(trim(implode("\n", $output)))->toBe('REJECT');
	} finally {
		if (is_file($scriptPath)) {
			unlink($scriptPath);
		}
	}
});
