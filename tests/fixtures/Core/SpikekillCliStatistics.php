<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
$root = dirname(__DIR__, 3);
cacti_fixture_source_function($root . '/lib/functions.php', 'cacti_sizeof', '');
cacti_fixture_source_function($root . '/include/global_languages.php', 'number_format_i18n', '');
require_once $root . '/lib/spikekill.php';
$cacti_locale = 'en';
$cacti_country = 'US';
$spiker = (new ReflectionClass(spikekill::class))->newInstanceWithoutConstructor();
$spiker->html = false;
foreach (['step' => 300, 'rra_pdp' => [1], 'rra_cf' => ['AVERAGE'], 'ds_name' => ['traffic']] as $name => $value) {
	(new ReflectionProperty(spikekill::class, $name))->setValue($spiker, $value);
}
$statistics = [[[
	'totalsamples' => 10, 'numsamples' => 9, 'average' => 5, 'stddev' => 3,
	'variance_avg' => 7, 'max_value' => 12, 'min_value' => 1, 'max_cutoff' => 8,
	'min_cutoff' => 2, 'stddev_killed' => 1, 'variance_killed' => 0,
	'outwind_samples' => 4, 'outwind_killed' => 2,
]]];
(new ReflectionMethod(spikekill::class, 'outputStatistics'))->invoke($spiker, $statistics);
$rows = array_values(array_filter(explode("\n", $spiker->get_output()), static function ($line) { return trim($line) !== ''; }));
$values = preg_split('/\s+/', trim($rows[2]));
if ($values !== ['5', 'mins', 'traffic', 'AVERAGE', '10', '9', '5', '3', '7', '12', '1', '8', '2', '1', '0', '4', '2']) {
	throw new RuntimeException('CLI statistics columns changed: ' . json_encode($values));
}
print json_encode(['passed' => 1], JSON_THROW_ON_ERROR) . "\n";
