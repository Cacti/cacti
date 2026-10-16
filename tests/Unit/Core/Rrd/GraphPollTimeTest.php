<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */

namespace GraphPollTimeTest;

$source = file_get_contents(dirname(__DIR__, 4) . '/lib/rrd.php');
$function = substr($source, strpos($source, 'function rrdtool_function_graph('));
$start = strpos($function, "\tif (empty(\$graph_data_array['graph_start']))");
$end = strpos($function, "\n\t\$local_data_ids", $start);

if ($start === false || $end === false) {
	throw new \RuntimeException('Unable to extract graph time defaults.');
}

// Exercise the renderer's actual default-time block without rendering an image or accessing a database.
eval('namespace GraphPollTimeTest; function graphTimes($graph_data_array) {' .
	substr($function, $start, $end - $start) . ' return $graph_data_array; }'); // nosemgrep: php.lang.security.eval-use.eval-use

function time() {
	return 1792161900;
}

function read_config_option($name, $force = false) {
	if ($name === 'poller_interval') {
		return '300';
	}

	return $force ? $GLOBALS['graph_poll_time_database'] : 1792160400;
}

test('automatic graph end uses the latest poll instead of a stale session value', function () {
	$GLOBALS['graph_poll_time_database'] = '1792161600';
	$result = graphTimes(array());

	expect($result['graph_start'])->toBe(-86400)
		->and($result['graph_end'])->toBe(-300);
});

test('successive standalone graph requests follow later completed polls', function () {
	$GLOBALS['graph_poll_time_database'] = '1792161000';
	$first = graphTimes(array());
	$GLOBALS['graph_poll_time_database'] = '1792161600';
	$second = graphTimes(array());

	expect($first['graph_end'])->toBe(-900)
		->and($second['graph_end'])->toBe(-300);
});

test('an explicit historical graph range remains unchanged', function () {
	$result = graphTimes(array('graph_start' => 1700000000, 'graph_end' => 1700003600));

	expect($result)->toBe(array('graph_start' => 1700000000, 'graph_end' => 1700003600));
});

test('missing and future poll timestamps retain the poll-interval fallback', function () {
	foreach (array('', '1792162200') as $last_run) {
		$GLOBALS['graph_poll_time_database'] = $last_run;
		expect(graphTimes(array())['graph_end'])->toBe(-300);
	}
});

test('a custom relative start is preserved when selecting the latest poll', function () {
	$GLOBALS['graph_poll_time_database'] = '1792161600';
	$result = graphTimes(array('graph_start' => -3600));

	expect($result['graph_start'])->toBe(-3600)
		->and($result['graph_end'])->toBe(-300);
});
