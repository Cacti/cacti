<?php
declare(strict_types=1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
// Regression coverage for Cacti issue #8426.

require_once dirname(__DIR__, 4) . '/lib/spikekill.php';

test('windowed deviation uses the surrounding sample mean', function ($minimum, $maximum) {
	$repair = (new ReflectionClass(spikekill::class))->newInstanceWithoutConstructor();
	$repair->method = SPIKE_METHOD_STDDEV;
	$repair->out_start = 150;
	$repair->out_end = 250;
	$repair->stddev = 2;
	$repair->percent = 0;
	foreach (['ds_min' => [$minimum], 'ds_max' => [$maximum]] as $name => $value) {
		(new ReflectionProperty(spikekill::class, $name))->setValue($repair, $value);
	}
	$statistics = [[['sumofsamples' => 1020, 'numsamples' => 3]]];
	$samples = [[[100 => '10', 200 => '1000', 225 => 'NaN', 300 => '10', 400 => 'NaN']]];
	(new ReflectionMethod(spikekill::class, 'calculateOverallStatistics'))->invokeArgs($repair, [&$statistics, &$samples]);
	expect($statistics[0][0]['average'])->toEqual(10)
		->and($statistics[0][0]['stddev'])->toEqual(0)
		->and($statistics[0][0]['min_cutoff'])->toEqual(10)
		->and($statistics[0][0]['max_cutoff'])->toEqual(10)
		->and($statistics[0][0]['stddev_killed'])->toBe(1);
})->with([[0, 10000], ['NaN', 'NaN']]);

test('nonwindowed or empty-reference deviation retains its existing mean', function ($start, $end) {
	$repair = (new ReflectionClass(spikekill::class))->newInstanceWithoutConstructor();
	$repair->method = SPIKE_METHOD_STDDEV;
	$repair->out_start = $start;
	$repair->out_end = $end;
	$repair->stddev = 2;
	$repair->percent = 0;
	foreach (['ds_min' => ['NaN'], 'ds_max' => ['NaN']] as $name => $value) {
		(new ReflectionProperty(spikekill::class, $name))->setValue($repair, $value);
	}
	$statistics = [[['sumofsamples' => 1020, 'numsamples' => 3]]];
	$samples = [[[100 => '10', 200 => '1000', 300 => '10']]];
	(new ReflectionMethod(spikekill::class, 'calculateOverallStatistics'))->invokeArgs($repair, [&$statistics, &$samples]);
	expect($statistics[0][0]['average'])->toEqual(340);
})->with([[0, 0], [1, 400]]);
