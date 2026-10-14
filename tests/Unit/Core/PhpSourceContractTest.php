<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

require_once dirname(__DIR__, 2) . '/Helpers/PhpSource.php';

use Cacti\Tests\Helpers\PhpSource;

test('PHP source contracts tolerate whitespace without changing literal contents', function () {
	$source = "<?php guard(\n\t\$path, /* boundary */\n\t'line\\n literal');";

	expect(PhpSource::position($source, "guard(\$path, 'line\\n literal')"))->toBe(6)
		->and(PhpSource::position($source, "guard(\$path, 'line literal')"))->toBeFalse();
});

test('PHP source contracts reject text in comments or quoted strings', function () {
	$source = '<?php /* guard($path); */ $message = \'guard($path);\';';

	expect(PhpSource::count($source, 'guard($path)'))->toBe(0);
});

test('PHP source contracts retain argument order and original byte offsets', function () {
	$source = "<?php guard(\n\t\$path, \$base);\nguard(\$base, \$path);";

	expect(PhpSource::count($source, 'guard($path, $base)'))->toBe(1)
		->and(PhpSource::position($source, 'guard($base, $path)'))->toBe(strrpos($source, 'guard'));
});

test('PHP whitespace normalization preserves literal contents and removes comment-only contracts', function () {
	$source = '<?php $value = "one  two\\n";' . "\n" .
		'/* db_qstr("comment only"); */ db_qstr(' . "\n\t" . '$value' . "\n" . ');';
	$normalized = \Cacti\Tests\Helpers\PhpSource::normalizeWhitespace($source);
	expect($normalized)->toContain('"one  two\\n"');
	expect($normalized)->not->toContain('comment only');
	expect($normalized)->toContain('db_qstr( $value );');
});
