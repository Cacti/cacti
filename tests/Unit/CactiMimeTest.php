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

// Stub cacti_log so the libmagic-missing branch in CactiMime can run
// without pulling in lib/functions.php and its database dependencies.
if (!function_exists('cacti_log')) {
	function cacti_log(mixed $string, bool $output = false, string $environ = 'CMDPHP', mixed $level = '') : bool {
		return true;
	}
}

beforeAll(function () {
	cacti_mime_build_fixtures();
});

test('detect returns application/zip for a real ZIP fixture', function () {
	$fx = cacti_mime_build_fixtures();
	expect(CactiMime::detect($fx['zip']))->toBe('application/zip');
});

test('detect returns an XML mime for a plain XML fixture', function () {
	$fx       = cacti_mime_build_fixtures();
	$detected = CactiMime::detect($fx['xml']);
	// libmagic and Symfony cycle between application/xml and text/xml
	// across versions; both are acceptable XML labels.
	expect($detected)->toBeIn(['application/xml', 'text/xml', 'application/x-xml']);
});

test('detect returns application/octet-stream for opaque bytes', function () {
	$fx       = cacti_mime_build_fixtures();
	$detected = CactiMime::detect($fx['unknown']);

	// libmagic occasionally maps high-entropy input to a generic data type.
	// We accept either octet-stream or any non-XML/non-zip label here, since
	// the contract is that an unrecognized file does not pass an allowlist.
	expect($detected)->not->toBe('application/zip');
	expect($detected)->not->toBe('application/xml');
	expect($detected)->not->toBe('text/xml');
});

test('validate accepts a file whose detected MIME is on the allowlist', function () {
	$fx = cacti_mime_build_fixtures();
	expect(CactiMime::validate($fx['zip'], ['application/zip']))->toBeTrue();
});

test('validate rejects a file whose detected MIME is not on the allowlist', function () {
	$fx = cacti_mime_build_fixtures();
	expect(CactiMime::validate($fx['xml'], ['application/zip']))->toBeFalse();
});

test('validate rejects opaque bytes against an XML/ZIP allowlist', function () {
	$fx = cacti_mime_build_fixtures();
	expect(CactiMime::validate($fx['unknown'], ['application/zip', 'application/xml', 'text/xml']))->toBeFalse();
});

test('validate(strict=true) passes through when finfo is available', function () {
	if (!function_exists('finfo_open')) {
		test()->markTestSkipped('finfo not present in this PHP build; the strict-mode fail-closed branch is verified in the subprocess test below.');
	}

	$fx = cacti_mime_build_fixtures();
	expect(CactiMime::validate($fx['zip'], ['application/zip'], true))->toBeTrue();
});

test('validate(strict=true) fails closed when finfo functions are unavailable', function () {
	$fx         = cacti_mime_build_fixtures();
	$scriptPath = tempnam(sys_get_temp_dir(), 'cacti-mime-strict-');
	$libPath    = realpath(__DIR__ . '/../../lib/CactiMime.php');

	$script  = "<?php\n";
	$script .= 'require_once ' . var_export($libPath, true) . ";\n";
	$script .= "if (!function_exists('cacti_log')) { function cacti_log(\$s,\$o=false,\$e='CMDPHP',\$l=''){ return true; } }\n";
	$script .= 'var_export(CactiMime::validate($argv[1], [' . "'application/zip'" . '], true));' . "\n";

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
		expect(trim(implode("\n", $output)))->toBe('false');
	} finally {
		if (is_file($scriptPath)) {
			unlink($scriptPath);
		}
	}
});

test('validate accepts the gzipped XML format used by package_import', function () {
	// import.php opens uploads via compress.zlib:// so gzipped XML is the
	// primary distribution format; allowlist must accept it.
	$fx       = cacti_mime_build_fixtures();
	$detected = CactiMime::detect($fx['gz']);
	expect($detected)->toBeIn(['application/gzip', 'application/x-gzip']);
	expect(CactiMime::validate($fx['gz'], CactiMime::packageImportMimes()))->toBeTrue();
});
