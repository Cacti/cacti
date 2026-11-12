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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

// Synthetic CLI-only matching profile. No network, database or discovery scan.
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$addresses  = filter_var($argv[1] ?? 150000, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2500000]]);
$rangeCount = filter_var($argv[2] ?? 64, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 512]]);
$scenario   = $argv[3] ?? 'miss';
$rounds     = filter_var($argv[4] ?? 3, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);

if (!in_array($scenario, ['miss', 'first', 'last'], true) || $addresses === false || $rangeCount === false || $rounds === false) {
	fwrite(STDERR, "Usage: php tests/performance/ip-range-membership.php [addresses:1..2500000] [ranges:1..512] [miss|first|last] [rounds:1..5]\n");
	exit(2);
}

$root       = dirname(__DIR__, 2);
$sourceRoot = getenv('CACTI_IP_PROFILE_SOURCE_ROOT') ?: $root;
require_once $sourceRoot . '/lib/functions.php';

$ranges    = [];
$rangeBase = ip2long('198.18.0.0');

for ($index = 0; $index < $rangeCount; $index++) {
	$ranges[] = long2ip($rangeBase + $index * 256) . ($index % 2 === 0 ? '/24' : '');
}

if ($scenario === 'first') {
	$ranges[0] = '10.0.0.0/8';
} elseif ($scenario === 'last') {
	$ranges[$rangeCount - 1] = '10.0.0.0/8';
}

$base     = ip2long('10.0.0.1');
$expected = $scenario === 'miss' ? 0 : $addresses;
$samples  = [];

for ($round = 0; $round < $rounds; $round++) {
	$matches = 0;
	$start   = hrtime(true);

	for ($index = 0; $index < $addresses; $index++) {
		$matches += (int) cacti_ip_in_ranges(long2ip($base + $index), $ranges);
	}
	$elapsed = (hrtime(true) - $start) / 1e9;

	if ($matches !== $expected) {
		throw new RuntimeException('Profile matching result differs from the fixture');
	}
	$samples[] = $elapsed;
}
sort($samples);
$middle = intdiv(count($samples), 2);
$median = count($samples) % 2 === 0 ? ($samples[$middle - 1] + $samples[$middle]) / 2 : $samples[$middle];
print json_encode([
	'php'            => PHP_VERSION,
	'scenario'       => $scenario,
	'addresses'      => $addresses,
	'ranges'         => $rangeCount,
	'rounds'         => $rounds,
	'matches'        => $expected,
	'seconds'        => $samples,
	'median_seconds' => $median,
	'peak_bytes'     => memory_get_peak_usage(true),
	'source_root'    => realpath($sourceRoot),
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n";
