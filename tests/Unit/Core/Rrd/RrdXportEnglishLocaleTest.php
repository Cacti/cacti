<?php
declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Regression test for the forced-English locale gate in __rrd_execute()
 * (lib/rrd.php ~381).
 *
 * rrdtool prints xport XML <v> values with printf %e, which is
 * LC_NUMERIC-sensitive. Under comma-decimal locales (de_DE, fr_FR) that
 * yields "1,23e+04", and rrdxport2array()/floatval() truncates at the
 * comma. fetch and info already force LANG=en for this reason; xport
 * must take the same path.
 *
 * @group regression
 */

test('xport is forced to the English locale alongside fetch and info', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/rrd.php');

	expect($source)->toContain("in_array(\$operation, ['fetch', 'info', 'xport'], true)")
		->toContain('$language = rrdtool_command_language($command_line);');
});
