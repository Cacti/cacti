<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

beforeAll(function () {
	require_once dirname(__DIR__, 4) . '/lib/rrd.php';
});

test('CF fallback keeps CURRENT_DATA_SOURCE on the same data source', function ($preferred, $available) {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/rrd.php');
	$start  = strpos($source, '// Restore the $cf_reference');
	$end    = strpos($source, '// +++++++++++++++++++++++ GRAPH ITEMS: CDEF START', $start);
	expect($start)->not->toBeFalse()->and($end)->not->toBeFalse();
	$graph_item = ['data_template_rrd_id' => 42, 'cf_reference' => $preferred, 'consolidation_function_id' => $preferred];
	// DEF 0 belongs to a different source and must never replace source 42.
	$cf_ds_cache = ['7:1' => 0, '42:' . $available => 9];
	$replacement = array_values(array_filter(explode("\n", $source), static fn ($line) => str_contains($line, "str_replace('CURRENT_DATA_SOURCE',") && str_contains($line, '$cdef_string =')));
	expect($replacement)->not->toBeEmpty();
	$temporary = [];

	try {
		foreach ([substr($source, $start, $end - $start), $replacement[0]] as $body) {
			$path = tempnam(sys_get_temp_dir(), 'cacti-cf-test-');
			file_put_contents($path, '<?php ' . $body);
			$temporary[] = $path;
		}
		include $temporary[0];
		$cdef_string = 'CURRENT_DATA_SOURCE,2,*';
		include $temporary[1];
	} finally {
		foreach ($temporary as $path) {
			unlink($path);
		}
	}
	expect($cdef_string)->toBe(generate_graph_def_name(9) . ',2,*');
})->with([[3, 1], [1, 3], [1, 2], [1, 4], [3, 3]]);

test('compiled production fallback and substitution retain the selected source', function () {
	$fixture = dirname(__DIR__, 3) . '/fixtures/Core/Rrd/FallbackSourceIdentity.php';
	exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($fixture), $output, $status);
	expect($status)->toBe(0);
	expect(implode("\n", $output))->toStartWith('PASS: all four consolidation fallbacks');
});
