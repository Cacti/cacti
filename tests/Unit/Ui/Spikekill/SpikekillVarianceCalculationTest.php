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

/*
 * Tests for the variance column expression in spikekill::outputStatistics()
 * (lib/spikekill.php).
 *
 * The old expression was:
 *   ($ds['variance'] != 'N/A' ? round($ds['stddev'] ^ 2, 2) : 'N/A')
 * Two bugs: '^' is bitwise XOR, not exponentiation, so it never computed
 * stddev squared; and the guard checked $ds['variance'], a key that is
 * never populated, so the XOR ran even when stddev was the string 'N/A'
 * -- 'N/A' ^ 2 throws a TypeError on PHP 8 (string ^ int is unsupported).
 *
 * The fix uses '**' for exponentiation and guards on $ds['stddev'] itself:
 *   ($ds['stddev'] != 'N/A' ? round($ds['stddev'] ** 2, 2) : 'N/A')
 *
 * The expression is pulled directly out of the real source file and
 * wrapped in a throwaway function, following the extract-and-eval pattern
 * used in Issue7070PercentileContractTest.php.
 */

require_once __DIR__ . '/../../../../lib/spikekill.php';

$source = file_get_contents(__DIR__ . '/../../../../lib/spikekill.php');

preg_match(
	'/\(\$ds\[\'stddev\'\]\s*!=\s*\'N\/A\'\s*\?\s*round\(\$ds\[\'stddev\'\]\s*\*\*\s*2,\s*2\)\s*:\s*\'N\/A\'\)/',
	$source,
	$matches
);

test('the fixed variance expression is present in the source', function () use ($matches) {
	expect($matches)->not->toBeEmpty();
});

$variance_of = function (mixed $stddev) use ($matches) {
	// eval() here only wraps an expression regex-extracted from this
	// repo's own lib/spikekill.php (not external/user input) into a
	// throwaway named function. Test-only.
	$name = 'spikekill_variance_' . str_replace('.', '_', uniqid('', true));
	eval("function $name(\$ds) { return {$matches[0]}; }");

	return $name(['stddev' => $stddev]);
};

test('variance is stddev squared, not stddev XOR 2', function () use ($variance_of) {
	expect($variance_of(3))->toEqual(9.0);
	expect($variance_of(4))->toEqual(16.0);

	// The pre-fix '^' operator gave 3 ^ 2 === 1, nowhere near 9.
	expect($variance_of(3))->not->toEqual(1);
});

test('variance is N/A when stddev is N/A, without throwing', function () use ($variance_of) {
	expect($variance_of('N/A'))->toBe('N/A');
});

test('outputStatistics renders variance in its own aligned column', function () {
	$spikekill = (new ReflectionClass(spikekill::class))->newInstanceWithoutConstructor();
	$set       = static function (string $property, mixed $value) use ($spikekill): void {
		$reflection = new ReflectionProperty(spikekill::class, $property);
		$reflection->setValue($spikekill, $value);
	};

	$set('html', false);
	$set('rra_pdp', [1]);
	$set('ds_name', ['traffic_in']);
	$set('rra_cf', ['AVERAGE']);

	$statistics = [[[
		'totalsamples'    => 10,
		'numsamples'      => 9,
		'average'         => 5,
		'stddev'          => 3,
		'max_value'       => 12,
		'min_value'       => 1,
		'max_cutoff'      => 8,
		'min_cutoff'      => 2,
		'stddev_killed'   => 1,
		'outwind_samples' => 4,
		'outwind_killed'  => 2,
	]]];
	$method = new ReflectionMethod(spikekill::class, 'outputStatistics');
	$method->invoke($spikekill, $statistics);

	$lines = array_values(array_filter(explode("\n", $spikekill->get_output()), static fn (string $line): bool => trim($line) !== ''));

	expect($lines)->toHaveCount(3)
		->and(preg_split('/\s+/', trim($lines[0])))->toBe([
			'Size', 'DS', 'CF', 'Samples', 'NonNan', 'Avg', 'StdDev', 'Variance',
			'MaxValue', 'MinValue', 'MaxStdDev', 'MinStdDev', 'StdKilled', 'WindSamples', 'WindKilled',
		])
		->and(preg_split('/\s+/', trim($lines[2])))->toBe([
			'0', 'secs', 'traffic_in', 'AVERAGE', '10', '9', '5', '3', '9', '12', '1', '8', '2', '1', '4', '2',
		]);
});
