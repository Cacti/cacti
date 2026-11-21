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
 * The graph data export already guards the title, vertical label and column
 * legend cells. These tests cover the remaining cells (Nth Percentile and
 * Summation value and format, and the data cells) and the SpikeKill dump and
 * backup file names.
 */

$xportSource     = file_get_contents(__DIR__ . '/../../../graph_xport.php');
$spikekillSource = file_get_contents(__DIR__ . '/../../../lib/spikekill.php');

if ($xportSource === false || $spikekillSource === false) {
	throw new RuntimeException('Unable to read graph_xport.php or lib/spikekill.php');
}

/* Same extract-and-eval approach as GraphXportCsvInjectionTest.php: the unit
 * bootstrap does not load page scripts. */
if (!function_exists('graph_xport_csv_cell')) {
	preg_match('/\nfunction graph_xport_csv_cell\b.*?\n\}/s', "\n" . $xportSource, $cellMatch);

	if (!empty($cellMatch)) {
		eval($cellMatch[0]);
	}
}

test('the Nth Percentile, Summation and data cells go through the cell guard', function () use ($xportSource) {
	expect(substr_count($xportSource, "graph_xport_csv_cell(\$item['value']) . '\",\"' . graph_xport_csv_cell(\$item['format'])"))->toBe(2);
	expect($xportSource)->toContain("\$data .= ',\"' . graph_xport_csv_cell(\$row['col' . \$i]) . '\"';");
	expect($xportSource)->not->toContain("'\",\"' . \$item['value'] . '\",\"' . \$item['format']");
	expect($xportSource)->not->toContain("\$data .= ',\"' . \$row['col' . \$i] . '\"';");
});

test('ordinary numbers are written unchanged', function () {
	foreach (array('0', '42', '-1.5', '+5', '1.2345000000e+03', '-1.2000000000e-05', 'NaN', '') as $value) {
		expect(graph_xport_csv_cell($value))->toBe($value);
	}

	expect(graph_xport_csv_cell(-1.5))->toBe('-1.5');
});

test('a non-numeric value with a leading formula character is still prefixed', function () {
	foreach (array('-1+1', '+1+2', '=1+1', '@SUM(A1)', '-2 Mbps', "\tx") as $value) {
		expect(graph_xport_csv_cell($value))->toBe("'" . $value);
	}

	expect(graph_xport_csv_cell('|95:bits:0:max:2|'))->toBe('|95:bits:0:max:2|');
	expect(graph_xport_csv_cell('95th "peak"'))->toBe('95th ""peak""');
});

test('SpikeKill dump and backup names use the system random source', function () use ($spikekillSource) {
	$start = strpos($spikekillSource, 'public function remove_spikes()');
	$end   = strpos($spikekillSource, 'execute the dump command', $start);

	expect($start)->not->toBeFalse();
	expect($end)->not->toBeFalse();

	$body = substr($spikekillSource, $start, $end - $start);

	expect($body)->toContain('$this->seed = bin2hex(random_bytes(8));');
	expect($body)->toContain("'.dump.' . \$this->seed");
	expect($body)->toContain("'.backup.' . \$this->seed . '.rrd'");
	expect($spikekillSource)->not->toContain('mt_rand(');
});
