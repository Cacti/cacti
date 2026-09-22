<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for GHSA-c3wx-gf8r-j4m7.
 * host.php emitted the device `location` into the <option value='...'> of the
 * Location filter with the display text escaped but the value attribute raw, so
 * a single quote in a location broke out and executed script. Validated live:
 * the value attribute must be html_escape()d.
 */
$hostSource = file_get_contents(__DIR__ . '/../../../host.php');

test('the location filter option value attribute is html_escaped', function () use ($hostSource) {
	expect($hostSource)->toContain("<option value='\" . html_escape(\$l['location'])")
		->and($hostSource)->not->toContain("<option value='\" . \$l['location'] . \"'\"");
});
