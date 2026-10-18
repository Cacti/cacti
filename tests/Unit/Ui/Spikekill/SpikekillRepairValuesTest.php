<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
// Regression coverage for Cacti issue #8426.

require_once __DIR__ . '/../../../../lib/spikekill.php';

test('windowed deviation uses the same reference samples for its mean and deviation', function (mixed $minimum, mixed $maximum) {
	$repair            = (new ReflectionClass(spikekill::class))->newInstanceWithoutConstructor();
	$repair->method    = SPIKE_METHOD_STDDEV;
	$repair->out_start = 150;
	$repair->out_end   = 250;
	$repair->stddev    = 2;

	foreach (['ds_min' => [$minimum], 'ds_max' => [$maximum]] as $name => $value) {
		(new ReflectionProperty(spikekill::class, $name))->setValue($repair, $value);
	}
	$statistics = [[['sumofsamples' => 1020, 'numsamples' => 3]]];
	$samples    = [[[100 => '10', 200 => '1000', 225 => 'NaN', 300 => '10', 400 => 'NaN']]];
	(new ReflectionMethod(spikekill::class, 'calculateOverallStatistics'))->invokeArgs($repair, [&$statistics, &$samples]);
	expect($statistics[0][0]['average'])->toEqual(10)
		->and($statistics[0][0]['stddev'])->toEqual(0)
		->and($statistics[0][0]['min_cutoff'])->toEqual(10)
		->and($statistics[0][0]['max_cutoff'])->toEqual(10)
		->and($statistics[0][0]['stddev_killed'])->toBe(1);
})->with([[0, 10000], ['NaN', 'NaN']]);

test('absolute repair carries the last valid value separately for each data source', function () {
	$repair            = (new ReflectionClass(spikekill::class))->newInstanceWithoutConstructor();
	$repair->method    = SPIKE_METHOD_ABSOLUTE;
	$repair->avgnan    = 'last';
	$repair->absmax    = 100;
	$repair->out_start = 150;
	$repair->out_end   = 350;
	$output            = [
		'<row><timestamp>100</timestamp><v>10</v><v>0</v></row>',
		'<row><timestamp>200</timestamp><v>1000</v><v>500</v></row>',
		'<row><timestamp>300</timestamp><v>20</v><v>5</v></row>',
		'<row><timestamp>325</timestamp><v>NaN</v><v>NaN</v></row>',
		'<row><timestamp>400</timestamp><v>2000</v><v>500</v></row>',
	];
	$statistics = [];
	$repaired   = (new ReflectionMethod(spikekill::class, 'updateXML'))->invokeArgs($repair, [&$output, &$statistics]);
	$xml        = simplexml_load_string('<database>' . implode('', $repaired) . '</database>');
	expect((string) $xml->row[1]->v[0])->toBe(' 10')
		->and((string) $xml->row[1]->v[1])->toBe(' 0')
		->and((string) $xml->row[2]->v[0])->toBe(' 20')
		->and((string) $xml->row[3]->v[0])->toBe(' NaN')
		->and((string) $xml->row[4]->v[0])->toBe(' 2000');
});

test('deviation correction preserves unknown samples', function () {
	$repair           = (new ReflectionClass(spikekill::class))->newInstanceWithoutConstructor();
	$repair->method   = SPIKE_METHOD_STDDEV;
	$repair->avgnan   = 'last';
	$repair->numspike = 10;
	$output           = [
		'<row><timestamp>100</timestamp><v>10</v></row>',
		'<row><timestamp>200</timestamp><v>NaN</v></row>',
		'<row><timestamp>300</timestamp><v>1000</v></row>',
	];
	$statistics = [[['min_cutoff' => 5, 'max_cutoff' => 15]]];
	$repaired   = (new ReflectionMethod(spikekill::class, 'updateXML'))->invokeArgs($repair, [&$output, &$statistics]);
	$xml        = simplexml_load_string('<database>' . implode('', $repaired) . '</database>');
	expect((string) $xml->row[1]->v)->toBe(' NaN')
		->and((string) $xml->row[2]->v)->toBe(' 10');
});
