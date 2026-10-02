<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-967c-6qj7-q7rh: a |query_*| rrd_maximum token, substituted from an
 * attacker-influenced SNMP field value, could carry a newline into the rrdtool
 * 'create' command and inject an independent rrdtool-protocol command. The
 * choke point __rrd_execute() strips control characters from the assembled
 * command before it reaches the rrdtool pipe, closing both the RRD-create path
 * here and the sibling graph-render path (GHSA-hcj6-pmvx-fwgr).
 */

$root   = dirname(__DIR__, 4);
$source = file_get_contents($root . '/lib/rrd.php');

test('__rrd_execute strips control characters before executing', function () use ($source) {
	$pos = strpos($source, 'function __rrd_execute(');

	expect($pos)->not->toBeFalse();

	$end  = strpos($source, "\nfunction ", $pos + 1);
	$body = substr($source, $pos, $end - $pos);

	expect($body)->toContain('rrd_strip_control_chars($command_line)');
});

test('rrd_strip_control_chars removes the control bytes that enable pipe injection', function () use ($source) {
	$pos = strpos($source, 'function rrd_strip_control_chars(');

	expect($pos)->not->toBeFalse();

	$end  = strpos($source, "\nfunction ", $pos + 1);
	$body = substr($source, $pos, $end - $pos);

	expect($body)->toContain('\x00-\x1f');
});
