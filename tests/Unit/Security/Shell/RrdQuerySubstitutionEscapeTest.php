<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression: |query_*|/|host_*| tokens in a graph title, vertical-label or
 * right-axis-label are replaced with device-supplied SNMP values. The value is
 * resolved (credential-stripped and substituted) by rrdtool_resolve_graph_text()
 * before it reaches cacti_escapeshellarg, so a device value that contains a quote
 * is escaped and cannot break out of the RRDtool argument; the post-escape
 * whole-command pass that previously injected into the quoted args must be gone.
 */

$rrd = file_get_contents(dirname(__DIR__, 4) . '/lib/rrd.php');

test('the resolved title value is escaped', function () use ($rrd) {
	expect($rrd)->toContain('cacti_escapeshellarg(htmle($value))');
});

test('the resolved right-axis-label value is escaped', function () use ($rrd) {
	expect($rrd)->toContain("--right-axis-label ' . cacti_escapeshellarg(\$value)");
});

test('the post-escape whole-command substitution is removed', function () use ($rrd) {
	expect($rrd)->not->toContain('$graph_opts = rrd_substitute_host_query_data($graph_opts, $graph, []);');
});

test('cacti_escapeshellarg neutralises a device-supplied quote', function () {
	require_once dirname(__DIR__, 4) . '/lib/functions.php';
	$GLOBALS['config']['cacti_server_os'] = 'unix';
	$wrapped = cacti_escapeshellarg("Traffic eth0' COMMENT:pwned");
	expect($wrapped)->not->toContain("Traffic eth0' COMMENT");
	expect(substr($wrapped, 0, 1))->toBe("'");
});
