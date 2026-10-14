<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
$root = dirname(__DIR__, 3);
require_once $root . '/lib/spikekill.php';
$source = file_get_contents($root . '/lib/spikekill.php');
$spiker = (new ReflectionClass(spikekill::class))->newInstanceWithoutConstructor();
$passed = 0;
foreach (['Float Found,', 'Window Float Found,'] as $message) {
	$pattern = '/if \(\$this->debug\) \{\s*\$this->debug\(sprintf\([\x27"]' . preg_quote($message, '/') . '[^\n]*\n\s*\}/';
	if (!preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE)) {
		throw new RuntimeException('Missing float debug guard');
	}
	$line = substr_count(substr($source, 0, $match[0][1]), "\n") + 1;
	$temporary = tempnam(sys_get_temp_dir(), 'cacti-float-debug-');
	$prefix = '<?php return function () { $rra_num = 0; $ds_num = 0; $timestamp = 1700000000; $dsvalue = 10; $sample = 10;';
	file_put_contents($temporary, $prefix . str_repeat("\n", $line - 1) . $match[0][0] . "\n};");
	$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($root . '/lib/spikekill.php');
	try {
		$probe = (require $temporary)->bindTo($spiker, spikekill::class);
		foreach ([false, true] as $enabled) {
			$spiker->debug = $enabled;
			ob_start();
			$probe();
			$output = ob_get_clean();
			if ($enabled ? !str_starts_with($output, 'DEBUG: ' . $message) : $output !== '') {
				throw new RuntimeException('Float debug output changed');
			}
			$passed++;
		}
	} finally {
		unlink($temporary);
	}
}
print json_encode(['passed' => $passed], JSON_THROW_ON_ERROR) . "\n";
