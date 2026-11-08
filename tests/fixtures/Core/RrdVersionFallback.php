<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Native probe for the RRDtool version floor with no database and no stored
// rrdtool_version option. include/global_arrays.php reads get_rrdtool_version()
// while the unit environment boots and the answer is cached, so coverage has
// to start before the boot, unlike the other RRDtool probes.

if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/bootstrap-unit.php';

/**
 * Fail the probe with a message when a condition does not hold.
 *
 * @param bool   $condition The expectation.
 * @param string $message   What went wrong.
 *
 * @return void
 */
function rrd_fallback_probe_assert(bool $condition, string $message) : void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

$lowest = array_key_first($rrdtool_versions);

rrd_fallback_probe_assert($lowest === '1.5.0', 'The lowest supported RRDtool release is ' . $lowest);
rrd_fallback_probe_assert(get_rrdtool_version() === $lowest, 'An unset rrdtool_version fell back to ' . get_rrdtool_version());
rrd_fallback_probe_assert(read_default_config_option('rrdtool_version') === $lowest, 'The rrdtool_version default is not the lowest supported release');
rrd_fallback_probe_assert(($data_source_types[6] ?? '') === 'DCOUNTER' && ($data_source_types[7] ?? '') === 'DDERIVE', 'DCOUNTER and DDERIVE are not offered');

print "rrd version fallback: 1.5.0, DCOUNTER and DDERIVE offered\n";
