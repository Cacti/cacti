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

// Dependency-free YAML writer and JSON-form reader in lib/yaml.php (#4030).

require_once dirname(__DIR__, 4) . '/lib/yaml.php';

/* Values that a YAML 1.1 or 1.2 parser would re-type, or that would break
   the document, if they were written as plain scalars. */
function yaml_codec_tricky_strings() : array {
	return [
		'', ' ', 'yes', 'No', 'ON', 'off', 'y', 'n', '~', 'null', 'NULL', 'true',
		'0123', '0x1F', '0o17', '1e3', '.inf', '-.inf', '.nan', '12:30:00',
		'2026-11-25', '- item', '? key', 'key: value', '# comment', 'a #b',
		'*alias', '&anchor', '!tag', '!!binary', '%YAML', '@at', '`tick',
		'{a: 1}', '[1, 2]', '|', '>', '---', '...', "'single'", '"double"',
		"line\nbreak", "tab\there", "cr\rhere", 'back\\slash', 'a/b',
		"\x00nul", "\x1Bescape", "\x7Fdel", "\u{0080}c1", "\u{0085}nel",
		"\u{009F}c1", "\u{2028}ls", "\u{2029}ps", "\u{FEFF}bom",
		"\u{FFFE}nonchar", "\u{FFFF}nonchar", 'émoji 🚀', '日本語',
	];
}

test('cacti_yaml_encode writes nested mappings and sequences in block style', function () {
	$data = [
		'name'  => 'Interface - Traffic',
		'items' => [
			['id' => 1, 'ratio' => 1.5, 'enabled' => true],
			['note' => null, 'tags' => []],
			[[1, 2], 'x'],
		],
		'empty' => [],
	];

	expect(cacti_yaml_encode($data))->toBe(
		"name: \"Interface - Traffic\"\n" .
		"items:\n" .
		"  - id: 1\n" .
		"    ratio: 1.5\n" .
		"    enabled: true\n" .
		"  - note: null\n" .
		"    tags: []\n" .
		"  - - - 1\n" .
		"      - 2\n" .
		"    - \"x\"\n" .
		"empty: []\n"
	);
});

test('cacti_yaml_encode writes an empty root array as an empty sequence', function () {
	expect(cacti_yaml_encode([]))->toBe("[]\n");
});

test('cacti_yaml_encode quotes every string value', function () {
	foreach (yaml_codec_tricky_strings() as $value) {
		$line = cacti_yaml_encode(['v' => $value]);

		expect($line)->toStartWith('v: "')->toEndWith("\"\n");
		expect(substr_count($line, "\n"))->toBe(1);
	}
});

test('cacti_yaml_encode escapes characters YAML does not allow unescaped', function () {
	expect(cacti_yaml_encode(['v' => "\x00\x1B\x7F\u{0080}\u{0085}\u{009F}\u{2028}\u{FFFE}\u{FFFF}"]))
		->toBe("v: \"\\u0000\\u001b\\u007F\\u0080\\u0085\\u009F\\u2028\\uFFFE\\uFFFF\"\n");
});

test('cacti_yaml_encode leaves printable Unicode and slashes unescaped', function () {
	expect(cacti_yaml_encode(['v' => 'a/b é 🚀 日本']))->toBe("v: \"a/b é 🚀 日本\"\n");
});

test('cacti_yaml_encode writes identifier keys bare and quotes the rest', function () {
	$yaml = cacti_yaml_encode([
		'hash'      => 1,
		'_private'  => 2,
		'Yes'       => 3,
		'off'       => 4,
		'null'      => 5,
		'5'         => 6,
		'two words' => 7,
		'a:b'       => 8,
		''          => 9,
	]);

	expect($yaml)->toBe(
		"hash: 1\n" .
		"_private: 2\n" .
		"\"Yes\": 3\n" .
		"\"off\": 4\n" .
		"\"null\": 5\n" .
		"\"5\": 6\n" .
		"\"two words\": 7\n" .
		"\"a:b\": 8\n" .
		"\"\": 9\n"
	);
});

test('cacti_yaml_encode writes floats with a decimal point or exponent', function () {
	expect(cacti_yaml_encode([1.0, -0.0, 0.1, 1.0e25, -2.5e-7]))
		->toBe("- 1.0\n- -0.0\n- 0.1\n- 1.0e+25\n- -2.5e-7\n");
});

test('cacti_yaml_encode rejects INF and NAN', function () {
	expect(fn () => cacti_yaml_encode([INF]))->toThrow(InvalidArgumentException::class, 'INF or NAN')
		->and(fn () => cacti_yaml_encode([-INF]))->toThrow(InvalidArgumentException::class, 'INF or NAN')
		->and(fn () => cacti_yaml_encode([NAN]))->toThrow(InvalidArgumentException::class, 'INF or NAN');
});

test('cacti_yaml_encode rejects strings and keys that are not valid UTF-8', function () {
	expect(fn () => cacti_yaml_encode(['v' => "bad\xC3"]))->toThrow(InvalidArgumentException::class, 'not valid UTF-8')
		->and(fn () => cacti_yaml_encode(["bad\xFF" => 1]))->toThrow(InvalidArgumentException::class, 'not valid UTF-8');
});

test('cacti_yaml_encode rejects objects and resources', function () {
	$handle = fopen('php://memory', 'r');

	try {
		expect(fn () => cacti_yaml_encode(['v' => new stdClass()]))->toThrow(InvalidArgumentException::class, 'stdClass')
			->and(fn () => cacti_yaml_encode(['v' => $handle]))->toThrow(InvalidArgumentException::class, 'resource');
	} finally {
		fclose($handle);
	}
});

test('cacti_yaml_encode enforces the nesting limit', function () {
	$nested = ['leaf'];

	for ($i = 1; $i < 4; $i++) {
		$nested = [$nested];
	}

	expect(cacti_yaml_encode($nested, 4))->toBe("- - - - \"leaf\"\n");
	expect(fn () => cacti_yaml_encode($nested, 3))->toThrow(InvalidArgumentException::class, 'more than 3 levels');
	expect(fn () => cacti_yaml_encode(['a' => ['b' => ['c' => 1]]], 2))->toThrow(InvalidArgumentException::class, 'more than 2 levels');
});

test('cacti_yaml_encode output is read back unchanged by a YAML 1.2 parser', function () {
	if (!class_exists(Symfony\Component\Yaml\Yaml::class)) {
		$this->markTestSkipped('symfony/yaml is not installed with the development dependencies');
	}

	$data = [
		'strings' => yaml_codec_tricky_strings(),
		'keys'    => array_fill_keys(['yes', 'No', '~', '0123', 'a: b', '- x', '#', '*a'], 'v'),
		'numbers' => [0, -1, PHP_INT_MAX, PHP_INT_MIN, 1.0, 0.1, 1.0e25, -2.5e-7],
		'other'   => [true, false, null, []],
		'deep'    => ['a' => ['b' => [['c' => [1, ['d' => 'e']]]]]],
	];

	expect(Symfony\Component\Yaml\Yaml::parse(cacti_yaml_encode($data)))->toBe($data);
});

test('cacti_yaml_decode reads the JSON form of YAML', function () {
	expect(cacti_yaml_decode('{"name": "Traffic", "items": [1, 2.5, true, null, {"a": []}]}'))
		->toBe(['name' => 'Traffic', 'items' => [1, 2.5, true, null, ['a' => []]]]);
	expect(cacti_yaml_decode('[]'))->toBe([]);
});

test('cacti_yaml_decode returns what json_encode wrote', function () {
	$data = ['strings' => yaml_codec_tricky_strings(), 'n' => [0, -1, 1.5, true, null]];

	expect(cacti_yaml_decode(json_encode($data, JSON_THROW_ON_ERROR)))->toBe($data);
});

test('cacti_yaml_decode skips a UTF-8 byte order mark', function () {
	expect(cacti_yaml_decode("\xEF\xBB\xBF{\"a\": 1}"))->toBe(['a' => 1]);
});

test('cacti_yaml_decode returns integers too large for PHP as strings', function () {
	expect(cacti_yaml_decode('[123456789012345678901234567890]'))->toBe(['123456789012345678901234567890']);
});

test('cacti_yaml_decode rejects block-style YAML', function () {
	expect(fn () => cacti_yaml_decode(cacti_yaml_encode(['a' => 1])))
		->toThrow(InvalidArgumentException::class, 'not in the supported JSON form');
	expect(fn () => cacti_yaml_decode("a: &x [1]\nb: *x\n"))
		->toThrow(InvalidArgumentException::class, 'not in the supported JSON form');
});

test('cacti_yaml_decode rejects a document that is not a mapping or sequence', function () {
	foreach (['"text"', '1', 'true', 'null'] as $document) {
		expect(fn () => cacti_yaml_decode($document))
			->toThrow(InvalidArgumentException::class, 'must be a mapping or a sequence');
	}
});

test('cacti_yaml_decode rejects empty and malformed documents', function () {
	foreach (['', '   ', '{', '{"a": 1,}', "{\"a\": \"bad\xC3\"}"] as $document) {
		expect(fn () => cacti_yaml_decode($document))
			->toThrow(InvalidArgumentException::class, 'not in the supported JSON form');
	}
});

test('cacti_yaml_decode enforces the size limit before decoding', function () {
	expect(cacti_yaml_decode('[1]', 3))->toBe([1]);
	expect(fn () => cacti_yaml_decode('[10]', 3))->toThrow(InvalidArgumentException::class, 'larger than 3 bytes');
});

test('cacti_yaml_decode enforces the nesting limit', function () {
	expect(cacti_yaml_decode('[[["x"]]]', CACTI_YAML_MAX_BYTES, 3))->toBe([[['x']]]);
	expect(fn () => cacti_yaml_decode('[[[["x"]]]]', CACTI_YAML_MAX_BYTES, 3))
		->toThrow(InvalidArgumentException::class, 'not in the supported JSON form');
});

test('cacti_yaml_encode and cacti_yaml_decode reject a nesting limit below 1', function () {
	expect(fn () => cacti_yaml_encode(['a'], 0))->toThrow(InvalidArgumentException::class, 'at least 1')
		->and(fn () => cacti_yaml_decode('[]', CACTI_YAML_MAX_BYTES, 0))->toThrow(InvalidArgumentException::class, 'at least 1');
});
