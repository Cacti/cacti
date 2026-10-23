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

test('PHP function extraction preserves multiline signatures nested bodies and source lines', function () {
	$source = <<<'SOURCE'
<?php
// A misleading declaration: function selected() { }
$message = 'function selected() { }';
$ignored = function () { return '}'; };

    function selected(
        string $name,
        object $object,
    ): string {
        $nested = static function () {
            return ['brace' => '}'];
        };
        if ($name !== '') {
            return "{$object->value} ${name}";
        }
        return $nested()['brace'];
    }
function following() { return 'excluded'; }
SOURCE;
	$definition = PhpSource::functionDefinition($source, 'selected');
	expect($definition['line'])->toBe(6)
		->and($definition['source'])->toStartWith("function selected(\n")
		->and($definition['source'])->toEndWith("\n    }")
		->and($definition['source'])->toContain('static function ()', '"{$object->value} ${name}"')
		->and($definition['source'])->not->toContain('function following');
});

test('PHP function extraction supports reference returns and rejects missing or incomplete declarations', function () {
	$source = '<?php function & selected (array &$values): array { return $values; }';
	expect(PhpSource::functionDefinition($source, 'selected'))->toBe(['source' => substr($source, 6), 'line' => 1]);
	expect(fn () => PhpSource::functionDefinition('<?php // function missing() {}', 'missing'))
		->toThrow(RuntimeException::class, 'Cannot locate source function missing');
	expect(fn () => PhpSource::functionDefinition('<?php function broken() { if (true) { }', 'broken'))
		->toThrow(RuntimeException::class, 'Incomplete source function broken');
});

test('PHP source helpers load standalone without Pest expectation registration', function () {
	$script  = 'require $argv[1]; echo json_encode(Cacti\\Tests\\Helpers\\PhpSource::functionDefinition("<?php function standalone() {}", "standalone"));';
	$process = proc_open([PHP_BINARY, '-r', $script, dirname(__DIR__, 2) . '/Helpers/PhpSource.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	expect(is_resource($process))->toBeTrue();
	$output = stream_get_contents($pipes[1]);
	$errors = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	expect(proc_close($process))->toBe(0)->and($errors)->toBe('')
		->and(json_decode($output, true))->toBe(['source' => 'function standalone() {}', 'line' => 1]);
});
