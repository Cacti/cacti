<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * graph_view.php never validates host_id, and both filter builders in
 * lib/html_graph.php read it before the filter array that declares it, so
 * get_request_var() handed back $_REQUEST untouched.  The value is interpolated
 * into the graph template lookup, and the '> 0' test in front of it does not
 * gate a payload.  These tests pin the boundary filter, the cast at the sink,
 * and the comparison quirk that made the guard useless.
 */

$src = file_get_contents(CACTI_PATH_LIBRARY . '/html_graph.php');

expect($src)->not->toBeFalse();

test('a non-numeric payload passes the > 0 guard that used to protect the query', function () {
	/* the reason the read had to be filtered: PHP compares a non-numeric string
	   to an int as a string, so the leading digit alone clears the guard */
	$payload = '1 AND (SELECT 1)';

	expect(is_numeric($payload))->toBeFalse()
		->and($payload > 0)->toBeTrue()
		->and($payload == 0)->toBeFalse();
});

test('both host_id filter builders validate at the read', function () use ($src) {
	expect($src)->not->toContain("\$host_id = grv('host_id');");

	expect(substr_count($src, "\$host_id = gfrv('host_id');"))->toBe(2);
});

test('the host template lookup casts host_id before interpolating it', function () use ($src) {
	expect($src)->not->toContain("'gl.host_id=' . \$host_id");

	expect(substr_count($src, "'gl.host_id=' . (int) \$host_id"))->toBe(2);
});

test('the cast covers the session sourced value as well as the request', function () use ($src) {
	/* $_SESSION[..._host_id] reaches the same fragment without passing through
	   the request filter, so the cast has to sit at the sink, not only the read */
	expect($src)->toContain("\$host_id = \$_SESSION[\$session_var . '_host_id'];");

	$sink    = strpos($src, "'gl.host_id=' . (int) \$host_id");
	$session = strpos($src, "\$_SESSION[\$session_var . '_host_id']");

	expect($session)->toBeLessThan($sink);
});
