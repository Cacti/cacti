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
 * right-axis-label are replaced with device-supplied SNMP values. Previously
 * the substitution ran over the whole graph command AFTER each argument was
 * cacti_escapeshellarg'd, so a device value containing a quote could break out
 * of the quoted RRDtool argument and inject directives. The substitution must
 * run INSIDE cacti_escapeshellarg, and the post-escape global pass must be gone.
 */

$rrd = file_get_contents(dirname(__DIR__, 4) . '/lib/rrd.php');

test('title substitution happens inside cacti_escapeshellarg', function () use ($rrd) {
	expect($rrd)->toContain(
		"cacti_escapeshellarg(rrd_substitute_host_query_data(html_escape(\$value), \$graph, array()))"
	);
});

test('right-axis-label substitution happens inside cacti_escapeshellarg', function () use ($rrd) {
	expect($rrd)->toContain(
		"--right-axis-label ' . cacti_escapeshellarg(rrd_substitute_host_query_data(\$value, \$graph, array()))"
	);
});

test('the post-escape whole-command substitution is removed', function () use ($rrd) {
	// this line injected the raw device value into already-quoted arguments
	expect($rrd)->not->toContain('$graph_opts = rrd_substitute_host_query_data($graph_opts, $graph, array());');
});

test('cacti_escapeshellarg neutralises a device-supplied quote (property this relies on)', function () {
	require_once dirname(__DIR__, 4) . '/lib/functions.php';
	$GLOBALS['config']['cacti_server_os'] = 'unix';
	// a substituted value with a quote, once wrapped, cannot terminate the arg
	$evil    = "eth0' COMMENT:pwned";
	$wrapped = cacti_escapeshellarg('Traffic ' . $evil);
	// the inner single quote must be escaped, not left bare-closing the argument
	expect($wrapped)->not->toContain("Traffic eth0' COMMENT");
	expect(substr($wrapped, 0, 1))->toBe("'");
});
