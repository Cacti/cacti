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

test('function extraction preserves multiline signatures, nested closures, literals and source lines', function () {
	$definition = <<<'SOURCE'
function &fixture(
    string $value = '} comment-like // text'
): mixed {
    $closure = function () use ($value) { return "value {$value}"; };
    /* } is not a closing token */
    $result = $closure();
    return $result;
}
SOURCE;
	$source = "<?php\n\n" . $definition . "\nfunction unrelated() { return null; }";
	expect(PhpSource::functionDefinition($source, 'fixture'))->toBe(['code' => $definition, 'line' => 3]);
});

test('function extraction rejects absent or ambiguous definitions', function () {
	expect(fn () => PhpSource::functionDefinition('<?php function present() {}', 'absent'))->toThrow(RuntimeException::class)
		->and(fn () => PhpSource::functionDefinition('<?php namespace A { function same() {} } namespace B { function same() {} }', 'same'))->toThrow(RuntimeException::class)
		->and(fn () => PhpSource::functionDefinition('<?php function broken( {', 'broken'))->toThrow(ParseError::class);
});

test('source slices execute unchanged function bodies at their original line numbers', function () {
	require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
	$path = tempnam(sys_get_temp_dir(), 'cacti-extraction-test-');

	try {
		file_put_contents($path, "<?php\n\nfunction line_marker(): array {\nreturn [__LINE__, 'literal }'];\n}\n");
		cacti_fixture_source_function($path, 'line_marker', 'CactiExtractionContract');
		expect(CactiExtractionContract\line_marker())->toBe([4, 'literal }']);
		$temporary = array_search(realpath($path), $GLOBALS['cacti_fixture_source_map'], true);
		expect($temporary)->toBeString()->and(file_exists($temporary))->toBeFalse();
		unset($GLOBALS['cacti_fixture_source_map'][$temporary]);
	} finally {
		unlink($path);
	}
});
