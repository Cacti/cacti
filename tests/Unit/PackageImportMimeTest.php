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

require_once __DIR__ . '/../../include/vendor/autoload.php';
require_once __DIR__ . '/../../lib/CactiMime.php';
require_once __DIR__ . '/fixtures/mime/build_fixtures.php';

if (!function_exists('cacti_log')) {
	function cacti_log(mixed $string, bool $output = false, string $environ = 'CMDPHP', mixed $level = '') : bool {
		return true;
	}
}

/**
 * The full package_import.php form_save() path requires session, db, and
 * include/auth.php. Driving it from a unit test would mean booting most
 * of Cacti. These tests instead exercise the same allowlist that
 * package_import.php uses, against payloads that mimic the realistic
 * attack patterns a reviewer cares about: extension/content mismatches.
 */

test('php payload renamed with .zip extension is rejected', function () {
	$path = sys_get_temp_dir() . '/cacti-mime-imp-' . getmypid() . '-payload.zip';
	file_put_contents($path, "<?php phpinfo(); ?>\n");

	expect(CactiMime::validate($path, CactiMime::packageImportMimes(), true))->toBeFalse();

	unlink($path);
});

test('a real ZIP file is accepted by the package_import allowlist', function () {
	$fx = cacti_mime_build_fixtures();
	expect(CactiMime::validate($fx['zip'], CactiMime::packageImportMimes(), true))->toBeTrue();
});

test('a ZIP file with a misleading .xml name is rejected when only XML is allowed', function () {
	$fx       = cacti_mime_build_fixtures();
	$xml_name = sys_get_temp_dir() . '/cacti-mime-imp-' . getmypid() . '-misleading.xml';
	copy($fx['zip'], $xml_name);

	// Allowlist does not include zip, so a zip-by-content file masquerading
	// as .xml must fail the allowlist.
	expect(CactiMime::validate($xml_name, ['application/xml', 'text/xml'], true))->toBeFalse();

	unlink($xml_name);
});

test('the realistic .xml.gz upload format passes the package_import allowlist', function () {
	$fx = cacti_mime_build_fixtures();
	expect(CactiMime::validate($fx['gz'], CactiMime::packageImportMimes(), true))->toBeTrue();
});
