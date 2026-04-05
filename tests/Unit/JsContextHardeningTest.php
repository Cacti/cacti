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
 * Regression checks for JavaScript-context output hardening.
 *
 * These tests ensure request/session derived values are encoded or normalized
 * before being embedded in JavaScript.
 */

$authProfilePath = __DIR__ . '/../../auth_profile.php';
$authResetpasswordPath = __DIR__ . '/../../auth_resetpassword.php';
$authChangepasswordPath = __DIR__ . '/../../auth_changepassword.php';
$pluginsPath = __DIR__ . '/../../plugins.php';
$htmlGraphPath = __DIR__ . '/../../lib/html_graph.php';
$dataDebugPath = __DIR__ . '/../../data_debug.php';

test('auth_profile encodes tab for JavaScript and redirect URL', function () use ($authProfilePath) {
	$contents = file_get_contents($authProfilePath);

	expect($contents)->toContain("json_encode((string) grv('tab'))");
	expect($contents)->toContain("gfrv('tab', FILTER_VALIDATE_REGEXP");
	expect($contents)->toContain('rawurlencode($currentTab)');
});

test('plugins page normalizes state and encodes sort column in JavaScript', function () use ($pluginsPath) {
	$contents = file_get_contents($pluginsPath);

	expect($contents)->toContain("json_encode((string) grv('sort_column'))");
	expect($contents)->toContain("var tableState = <?php print (int) grv('state'); ?>;");
	expect($contents)->not->toContain("var tableState = <?php print grv('state'); ?>");
});

test('graph list view uses JSON and sanitized CSV for graph list', function () use ($htmlGraphPath) {
	$contents = file_get_contents($htmlGraphPath);

	expect($contents)->toContain('$graph_list_js  = []');
	expect($contents)->toContain('ctype_digit($item)');
	expect($contents)->toContain('json_encode($graph_list_js)');
	expect($contents)->toContain("graph_list=<?php print \$graph_list_csv; ?>");
	expect($contents)->not->toContain("new Array(<?php print grv('graph_list'); ?>)");
});

test('graph pages encode JS-bound PHP strings safely', function () use ($htmlGraphPath) {
	$contents = file_get_contents($htmlGraphPath);

	expect($contents)->toContain('var pageAction      = <?php print json_encode($action); ?>');
	expect($contents)->toContain('var graphPage       = <?php print json_encode($page); ?>');
	expect($contents)->toContain("json_encode((string) \$suffix)");
});

test('data debug escapes tooltip title values before rendering', function () use ($dataDebugPath) {
	$contents = file_get_contents($dataDebugPath);

	expect($contents)->toContain('$value_title = htmle((string) $value);');
});

test('auth reset password encodes return location in onclick handlers', function () use ($authResetpasswordPath) {
	$contents = file_get_contents($authResetpasswordPath);

	expect($contents)->toContain("document.location=<?php print json_encode((string) \$return); ?>");
	expect($contents)->not->toContain("document.location=\"<?php print \$return; ?>\"");
});

test('auth change password encodes return location in onclick handler', function () use ($authChangepasswordPath) {
	$contents = file_get_contents($authChangepasswordPath);

	expect($contents)->toContain("document.location=<?php print json_encode((string) \$return); ?>");
	expect($contents)->not->toContain("onClick='document.location=\\\"\$return\\\"'");
});
