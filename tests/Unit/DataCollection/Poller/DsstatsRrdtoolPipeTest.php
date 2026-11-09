<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression: dsstats_rrdtool_execute() stopped reading at the first output
 * containing 'OK', so 'rrdtool info' for a data source named LOOKUPS ended
 * early and every later command read the previous command's output.
 */

namespace DsstatsRrdtoolPipeTest;

function cacti_log($message, $output = false, $environ = 'CMDPHP') {
}

$source = file_get_contents(dirname(__DIR__, 4) . '/lib/dsstats.php');

if ($source === false || preg_match('/^function dsstats_rrdtool_execute\(.*?^}\R/ms', $source, $matches) !== 1) {
	throw new \RuntimeException('Unable to extract dsstats_rrdtool_execute() for the pipe test.');
}

// the code is lib/dsstats.php from this checkout
eval('namespace DsstatsRrdtoolPipeTest;' . $matches[0]);

test('the pipe reader stops at the status line, not at OK inside a data source name', function () {
	$info   = "filename = \"ok.rrd\"\nds[LOOKUPS].index = 0\nds[LOOKUPS].type = \"GAUGE\"\nOK u:0.00 s:0.00 r:0.00\n";
	$second = "ERROR: opening 'missing.rrd': No such file or directory\n";

	$stdout = fopen('php://temp', 'w+');
	fwrite($stdout, $info . $second);
	rewind($stdout);

	$pipes = array(fopen('php://memory', 'w+'), $stdout);

	expect(dsstats_rrdtool_execute('info ok.rrd', $pipes))->toBe($info)
		->and(dsstats_rrdtool_execute('info missing.rrd', $pipes))->toBe($second);
});
