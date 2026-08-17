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

require_once dirname(__DIR__, 4) . '/lib/CactiSettings.php';

use Symfony\Component\Validator\Constraints as Assert;

// =====================================================================
// CactiSettings::validate -- input shapes
//
// Constraints are declared as closures that return Constraint instances,
// matching the form used in include/global_settings.php. The closure form
// is required there because that file loads before vendor/autoload.php.
// =====================================================================

test('empty posted settings returns empty violations', function () {
	$definitions = [
		'foo' => [
			'method'      => 'textbox',
			'constraints' => [fn () => new Assert\NotBlank()],
		],
	];

	expect(CactiSettings::validate([], $definitions))->toBe([]);
});

test('valid posted value passes all constraints', function () {
	$definitions = [
		'snmp_port' => [
			'method'      => 'textbox',
			'constraints' => [
				fn () => new Assert\Regex(pattern: '/^\d+$/'),
				fn () => new Assert\Range(min: 1, max: 65535),
			],
		],
	];

	expect(CactiSettings::validate(['snmp_port' => '161'], $definitions))->toBe([]);
});

test('failing constraint returns map keyed by setting name', function () {
	$definitions = [
		'snmp_port' => [
			'method'      => 'textbox',
			'constraints' => [
				fn () => new Assert\Range(min: 1, max: 65535),
			],
		],
	];

	$result = CactiSettings::validate(['snmp_port' => '70000'], $definitions);

	expect($result)->toHaveKey('snmp_port')
		->and($result['snmp_port'])->toBeString()
		->and($result['snmp_port'])->not->toBe('');
});

test('multiple failing constraints on same setting return only the first message', function () {
	$definitions = [
		'name' => [
			'method'      => 'textbox',
			'constraints' => [
				fn () => new Assert\NotBlank(message: 'first violation'),
				fn () => new Assert\Length(min: 3, minMessage: 'second violation'),
			],
		],
	];

	$result = CactiSettings::validate(['name' => ''], $definitions);

	expect($result)->toHaveKey('name')
		->and($result['name'])->toBe('first violation');
});

test('posted setting with no matching definition is ignored', function () {
	$definitions = [
		'known' => [
			'method'      => 'textbox',
			'constraints' => [fn () => new Assert\NotBlank()],
		],
	];

	expect(CactiSettings::validate(['unknown' => ''], $definitions))->toBe([]);
});

test('definition without constraints key is ignored', function () {
	$definitions = [
		'plain' => [
			'method' => 'textbox',
		],
	];

	expect(CactiSettings::validate(['plain' => ''], $definitions))->toBe([]);
});

test('definition with empty constraints array is ignored', function () {
	$definitions = [
		'empty' => [
			'method'      => 'textbox',
			'constraints' => [],
		],
	];

	expect(CactiSettings::validate(['empty' => ''], $definitions))->toBe([]);
});

test('tab-grouped definitions are flattened transparently', function () {
	// Mirrors the actual shape of $settings in include/global_settings.php
	// where the outer key is the tab name and the inner array is the
	// {setting_name => def} map.
	$definitions = [
		'snmp' => [
			'snmp_port' => [
				'method'      => 'textbox',
				'constraints' => [fn () => new Assert\Range(min: 1, max: 65535)],
			],
		],
	];

	$result = CactiSettings::validate(['snmp_port' => '70000'], $definitions);

	expect($result)->toHaveKey('snmp_port');
});

test('plain Constraint instances (non-closure) are still accepted', function () {
	// Backward-compatible form for callers that run after vendor autoload.
	$definitions = [
		'host' => [
			'method'      => 'textbox',
			'constraints' => [new Assert\NotBlank()],
		],
	];

	$result = CactiSettings::validate(['host' => ''], $definitions);

	expect($result)->toHaveKey('host');
});

// =====================================================================
// Constraint type coverage -- each pilot constraint type
// =====================================================================

test('NotBlank rejects empty string', function () {
	$definitions = [
		'host' => [
			'method'      => 'textbox',
			'constraints' => [fn () => new Assert\NotBlank()],
		],
	];

	$result = CactiSettings::validate(['host' => ''], $definitions);

	expect($result)->toHaveKey('host');
});

test('NotBlank accepts non-empty string', function () {
	$definitions = [
		'host' => [
			'method'      => 'textbox',
			'constraints' => [fn () => new Assert\NotBlank()],
		],
	];

	expect(CactiSettings::validate(['host' => 'localhost'], $definitions))->toBe([]);
});

test('Length enforces max bound', function () {
	$definitions = [
		'path' => [
			'method'      => 'textbox',
			'constraints' => [fn () => new Assert\Length(max: 5)],
		],
	];

	$result = CactiSettings::validate(['path' => 'too-long'], $definitions);

	expect($result)->toHaveKey('path');
});

test('Length accepts string within bounds', function () {
	$definitions = [
		'path' => [
			'method'      => 'textbox',
			'constraints' => [fn () => new Assert\Length(min: 1, max: 255)],
		],
	];

	expect(CactiSettings::validate(['path' => '/usr/bin/rrdtool'], $definitions))->toBe([]);
});

test('Range rejects value above max', function () {
	$definitions = [
		'port' => [
			'method'      => 'textbox',
			'constraints' => [fn () => new Assert\Range(min: 1, max: 65535)],
		],
	];

	$result = CactiSettings::validate(['port' => '70000'], $definitions);

	expect($result)->toHaveKey('port');
});

test('Range rejects value below min', function () {
	$definitions = [
		'port' => [
			'method'      => 'textbox',
			'constraints' => [fn () => new Assert\Range(min: 1, max: 65535)],
		],
	];

	$result = CactiSettings::validate(['port' => '0'], $definitions);

	expect($result)->toHaveKey('port');
});

test('Choice rejects value not in list', function () {
	$definitions = [
		'interval' => [
			'method'      => 'drop_array',
			'constraints' => [fn () => new Assert\Choice(choices: ['60', '300', 60, 300])],
		],
	];

	$result = CactiSettings::validate(['interval' => '42'], $definitions);

	expect($result)->toHaveKey('interval');
});

test('Choice accepts value in list', function () {
	$definitions = [
		'interval' => [
			'method'      => 'drop_array',
			'constraints' => [fn () => new Assert\Choice(choices: ['60', '300', 60, 300])],
		],
	];

	expect(CactiSettings::validate(['interval' => '300'], $definitions))->toBe([]);
});

test('Regex rejects non-numeric string', function () {
	$definitions = [
		'count' => [
			'method'      => 'textbox',
			'constraints' => [fn () => new Assert\Regex(pattern: '/^\d+$/')],
		],
	];

	$result = CactiSettings::validate(['count' => 'abc'], $definitions);

	expect($result)->toHaveKey('count');
});

test('Regex accepts matching string', function () {
	$definitions = [
		'count' => [
			'method'      => 'textbox',
			'constraints' => [fn () => new Assert\Regex(pattern: '/^\d+$/')],
		],
	];

	expect(CactiSettings::validate(['count' => '42'], $definitions))->toBe([]);
});

test('Positive rejects zero and negatives', function () {
	$definitions = [
		'id' => [
			'method'      => 'textbox',
			'constraints' => [fn () => new Assert\Positive()],
		],
	];

	$result = CactiSettings::validate(['id' => '0'], $definitions);

	expect($result)->toHaveKey('id');
});

test('multiple settings with mixed pass/fail report only the failing ones', function () {
	$definitions = [
		'good' => [
			'method'      => 'textbox',
			'constraints' => [fn () => new Assert\NotBlank()],
		],
		'bad' => [
			'method'      => 'textbox',
			'constraints' => [fn () => new Assert\NotBlank()],
		],
	];

	$result = CactiSettings::validate(['good' => 'ok', 'bad' => ''], $definitions);

	expect($result)->toHaveKey('bad')
		->and($result)->not->toHaveKey('good');
});
