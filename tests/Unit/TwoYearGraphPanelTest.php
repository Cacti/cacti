<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRTool-based Graphing Solution                      |
 +-------------------------------------------------------------------------+
 */

beforeEach(function () {
	$this->originalTimezone = date_default_timezone_get();
	date_default_timezone_set('UTC');
});

afterEach(function () {
	date_default_timezone_set($this->originalTimezone);
});

$twoYearPanelFor = function ($date, $requested, $interval, $rows) {
	$source = file_get_contents(dirname(__DIR__, 2) . '/graph.php');
	$begin = strpos($source, "\t\t// Add a longer view");
	$end = strpos($source, "\t\tforeach (" . '$rras as $rra)', $begin);
	expect($begin)->not->toBeFalse();
	expect($end)->not->toBeFalse();

	// Substitute the request and translation dependencies of the panel block.
	$snippet = str_replace(
		array("get_request_var('rra_id')", '__n(', '__('),
		array('$requested', '$pluralize(', 'sprintf('),
		substr($source, $begin, $end - $begin)
	);
	$pluralize = function ($singular, $plural, $count) {
		return $count == 1 ? $singular : $plural;
	};
	$graph_end = strtotime($date);
	$rras = array(array('id' => 4, 'name' => 'Yearly', 'step' => 1,
		'steps' => $interval, 'rows' => $rows, 'timespan' => 31536000));
	eval($snippet);

	return array($rras, $graph_end - strtotime('-2 years', $graph_end));
};

test('AddsTwoYearPanelOnlyWhenRetentionCoversCalendarRange', function ($date, $rows, $expectedCount) use ($twoYearPanelFor) {
	list($rras, $span) = $twoYearPanelFor($date, 'all', 86400, $rows);
	expect($rras)->toHaveCount($expectedCount);
	if ($expectedCount == 2) {
		expect($rras[1]['timespan'])->toBe($span)
			->and($rras[1]['id'])->toBe(0);
	}
})->with(array(
	'InsufficientRetention' => array('2026-10-04', 365, 1),
	'TwoNonLeapYears' => array('2026-10-04', 730, 2),
	'LeapYearNeedsExtraDay' => array('2025-10-04', 730, 1),
	'LeapYearCovered' => array('2025-10-04', 731, 2)
));

test('KeepsSelectedArchiveViewUnchanged', function () use ($twoYearPanelFor) {
	list($rras) = $twoYearPanelFor('2026-10-04', '4', 86400, 731);
	expect($rras)->toHaveCount(1)
		->and($rras[0]['id'])->toBe(4);
});

test('LabelsTwoYearPanelWithArchiveAveragingInterval', function ($interval, $heading) use ($twoYearPanelFor) {
	list($rras) = $twoYearPanelFor('2026-10-04', 'all', $interval, (int) ceil(731 * 86400 / $interval));
	expect($rras)->toHaveCount(2)
		->and($rras[1]['name'])->toBe($heading);
})->with(array(
	'DailyAverage' => array(86400, '2 Years (1 Day Average)'),
	'TwoDayAverage' => array(172800, '2 Years (2 Days Average)'),
	'HourlyAverage' => array(3600, '2 Years (1 Hour Average)'),
	'TwoHourAverage' => array(7200, '2 Years (2 Hours Average)'),
	'MinuteAverage' => array(60, '2 Years (1 Minute Average)'),
	'SecondAverage' => array(1, '2 Years (1 Second Average)')
));
