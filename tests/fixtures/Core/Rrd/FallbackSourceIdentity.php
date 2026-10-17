<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

require dirname(__DIR__, 3) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 3) . '/bootstrap-unit.php';
require_once CACTI_PATH_LIBRARY . '/rrd.php';

// Compile the original statements without rewriting their source or line numbers.
$file   = CACTI_PATH_LIBRARY . '/rrd.php';
$source = file_get_contents($file);
$start  = strpos($source, '// Restore the $cf_reference');
$end    = strpos($source, '// +++++++++++++++++++++++ GRAPH ITEMS: CDEF START', $start);

if ($start === false || $end === false) {
	throw new RuntimeException('Cannot locate consolidation fallback');
}
$blocks = [[$start, substr($source, $start, $end - $start)]];

if (!preg_match('/^.*\$cdef_string = str_replace\(\x27CURRENT_DATA_SOURCE\x27.*$/m', $source, $replacement, PREG_OFFSET_CAPTURE)) {
	throw new RuntimeException('Cannot locate data source substitution');
}
$blocks[]  = [$replacement[0][1], $replacement[0][0]];
$temporary = [];

try {
	foreach ($blocks as [$offset, $body]) {
		$path = tempnam(sys_get_temp_dir(), 'cacti-cf-contract-');
		file_put_contents($path, '<?php ' . str_repeat("\n", substr_count(substr($source, 0, $offset), "\n")) . $body);
		$GLOBALS['cacti_fixture_source_map'][$path] = $file;
		$temporary[]                                = $path;
	}

	foreach ([[3, 1], [1, 3], [1, 2], [1, 4], [3, 3]] as [$preferred, $available]) {
		$graph_item  = ['data_template_rrd_id' => 42, 'cf_reference' => $preferred, 'consolidation_function_id' => $preferred];
		$cf_ds_cache = ['7:1' => 0, '42:' . $available => 9];
		require $temporary[0];
		$cdef_string = 'CURRENT_DATA_SOURCE,2,*';
		require $temporary[1];

		if ($cf_ds_key !== '42:' . $available || $cdef_string !== generate_graph_def_name(9) . ',2,*') {
			throw new RuntimeException('Fallback selected a different data source');
		}
	}
	print "PASS: all four consolidation fallbacks and preferred consolidation retain source identity\n";
} finally {
	foreach ($temporary as $path) {
		unlink($path);
	}
}
