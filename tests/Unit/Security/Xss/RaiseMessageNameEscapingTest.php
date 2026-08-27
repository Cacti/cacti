<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression test for the raise_message stored-XSS cluster (GHSA-9737): name
 * values embedded in raise_message() reach $('body').append() as HTML, so they
 * must use __esc() (which escapes the formatted string), not __().
 */

test('graph_templates sync messages escape the template name', function () {
	$s = file_get_contents(dirname(__DIR__, 3) . '/graph_templates.php');
	expect(substr_count($s, "__esc('Sync of Graph Template"))->toBe(4)
		->and($s)->not->toContain("__('Sync of Graph Template");
});

test('host device-to-report message escapes the report name', function () {
	$s = file_get_contents(dirname(__DIR__, 3) . '/host.php');
	expect($s)->toContain("__esc('Unable to add some Devices to Report")
		->and($s)->not->toContain("__('Unable to add some Devices to Report");
});
