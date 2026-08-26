<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$vendorRoot = dirname(__DIR__, 3) . '/include/vendor';

test('the web tree excludes unused vendor development entry points', function () use ($vendorRoot) {
	$excluded = array(
		'phpmailer/get_oauth_token.php',
		'phpmailer/vendor',
		'phpdiff/example',
		'cldr-to-gettext-plural-rules/bin',
		'cldr-to-gettext-plural-rules/tests',
		'gettext/tests',
		'flag-icons/yarn.lock',
		'flag-icons/package.json',
		'flag-icons/index.html',
	);

	foreach ($excluded as $path) {
		expect(file_exists($vendorRoot . '/' . $path))->toBeFalse($path . ' must not ship in the web tree');
	}
});

test('required vendor runtime files remain packaged', function () use ($vendorRoot) {
	$required = array(
		'phpmailer/src/PHPMailer.php',
		'phpdiff/Diff.php',
		'cldr-to-gettext-plural-rules/src/autoloader.php',
		'gettext/src/Translator.php',
		'flag-icons/css/flag-icons.css',
		'flag-icons/flags/4x3/us.svg',
	);

	foreach ($required as $path) {
		expect(is_file($vendorRoot . '/' . $path))->toBeTrue($path . ' must remain available at runtime');
	}
});

test('every flag referenced by the runtime stylesheet remains packaged', function () use ($vendorRoot) {
	$stylesheet = $vendorRoot . '/flag-icons/css/flag-icons.css';
	$css = file_get_contents($stylesheet);
	preg_match_all('/url\(([^)]+)\)/', $css, $matches);

	expect($matches[1])->not->toBeEmpty();
	foreach (array_unique($matches[1]) as $relativePath) {
		$relativePath = trim($relativePath, " \t\n\r\0\x0B\"'");
		expect(is_file(dirname($stylesheet) . '/' . $relativePath))->toBeTrue($relativePath . ' is referenced by flag-icons.css');
	}
});
