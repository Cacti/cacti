<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

use Symfony\Component\Mime\MimeTypesInterface;

require_once CACTI_PATH_LIBRARY . '/CactiMime.php';

final class CactiMimeFake implements MimeTypesInterface {
	public function __construct(private ?string $detected, private bool $supported = true) {
	}

	public function getExtensions(string $mimeType) : array {
		return [];
	}

	public function getMimeTypes(string $ext) : array {
		return [];
	}

	public function isGuesserSupported() : bool {
		return $this->supported;
	}

	public function guessMimeType(string $path) : ?string {
		return $this->detected;
	}
}

function cacti_mime_fixture(string $contents) : string {
	$path = tempnam(sys_get_temp_dir(), 'cacti-mime-');
	file_put_contents($path, $contents);

	return $path;
}

test('the injected detector accepts an allowlisted content type', function () {
	$path = cacti_mime_fixture('<?xml version="1.0"?><package/>');

	try {
		$detector = new CactiMimeDetector(new CactiMimeFake('application/xml'));
		expect($detector->validate($path, CactiMime::packageImportMimes()))->toBeTrue();
	} finally {
		unlink($path);
	}
});

test('the injected detector rejects renamed script content', function () {
	$path = cacti_mime_fixture('<?php system($_GET["cmd"]);');

	try {
		$detector = new CactiMimeDetector(new CactiMimeFake('text/x-php'));
		expect($detector->validate($path, CactiMime::packageImportMimes()))->toBeFalse();
	} finally {
		unlink($path);
	}
});

test('unsupported and inconclusive detectors fail closed', function (?string $mime, bool $supported) {
	$path = cacti_mime_fixture('opaque');

	try {
		$detector = new CactiMimeDetector(new CactiMimeFake($mime, $supported));
		expect($detector->validate($path, CactiMime::packageImportMimes()))->toBeFalse();
	} finally {
		unlink($path);
	}
})->with([
	'unsupported'   => ['application/xml', false],
	'inconclusive'  => [null, true],
]);

test('the package allowlist contains the supported package formats only', function () {
	expect(CactiMime::packageImportMimes())->toBe([
		'application/zip',
		'application/gzip',
		'application/x-gzip',
		'application/xml',
		'application/x-xml',
		'text/xml',
	]);
});

test('production detection accepts supported content without trusting a filename', function (string $contents) {
	$path = cacti_mime_fixture($contents);

	try {
		expect(CactiMime::validate($path, CactiMime::packageImportMimes()))->toBeTrue();
	} finally {
		unlink($path);
	}
})->with([
	'xml'  => '<?xml version="1.0"?><package/>',
	'gzip' => fn () => gzencode('<?xml version="1.0"?><package/>'),
]);

test('production detection rejects script bytes renamed as a package', function () {
	$path    = cacti_mime_fixture('<?php system($_GET["cmd"]);');
	$renamed = $path . '.xml.gz';
	rename($path, $renamed);

	try {
		expect(CactiMime::validate($renamed, CactiMime::packageImportMimes()))->toBeFalse();
	} finally {
		unlink($renamed);
	}
});

test('the upload is validated before its bytes enter the session', function () {
	$source   = file_get_contents(CACTI_PATH_BASE . '/package_import.php');
	$validate = strpos($source, 'CactiMime::validate($xmlfile');
	$retain   = strpos($source, "\$_SESSION['sess_import_package'] = file_get_contents(\$xmlfile)");

	expect($validate)->not->toBeFalse()
		->and($retain)->not->toBeFalse()
		->and($validate)->toBeLessThan($retain);
});
