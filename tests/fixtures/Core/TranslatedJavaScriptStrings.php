<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\TranslatedJavaScript;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
if (!getenv('CACTI_COVERAGE_FILE')) {
	require_once dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
}

require_once dirname(__DIR__, 3) . '/lib/functions.php';

function __($message) {
	return "Détails d'élément \"réseau\" & chemin \\test\n</script><b>texte</b>";
}

$cases = array(
	array('aggregate_graphs.php', 'Hide Item Details'),
	array('aggregate_graphs.php', 'Show Item Details'),
	array('cdef.php', 'Delete CDEF Item'),
	array('data_source_profiles.php', 'Delete Data Source Profile Item'),
	array('host_templates.php', 'Delete Item from Device Template'),
	array('package_import.php', 'Key Generation Required to Use Plugin'),
	array('package_import.php', 'Package Key Information Not Found'),
	array('package_import.php', 'File Differences for: '),
	array('aggregate_graphs.php', 'Total'),
	array('aggregate_graphs.php', 'All Items'),
	array('aggregate_graphs.php', 'Show'),
	array('data_source_profiles.php', 'Select Consolidation Function(s)'),
);

foreach ($cases as list($name, $message)) {
	$file = dirname(__DIR__, 3) . '/' . $name;
	$source = file_get_contents($file);
	$pattern = '/<\?php[^\r\n]*\b__\(' . preg_quote("'" . $message . "'", '/') . '\)[^\r\n]*?\?>/';
	if (preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE) !== 1) {
		throw new \RuntimeException('Cannot locate translated expression in ' . $name);
	}
	$line = substr_count(substr($source, 0, $match[0][1]), "\n") + 1;
	$temporary = tempnam(sys_get_temp_dir(), 'cacti-translated-js-');
	// Compile the exact shipped expression at its original source line.
	file_put_contents($temporary, '<?php namespace ' . __NAMESPACE__ . ';' . str_repeat("\n", $line - 1) . '?>' . $match[0][0]);
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($file);
	ob_start();
	try {
		require $temporary;
		$encoded = ob_get_contents();
	} finally {
		ob_end_clean();
		unlink($temporary);
	}
	if (json_decode($encoded, true) !== __($message) || stripos($encoded, '</script') !== false) {
		throw new \RuntimeException('Translated JavaScript string did not round trip safely: ' . $name);
	}
}

print json_encode(array('passed' => count($cases))) . "\n";
