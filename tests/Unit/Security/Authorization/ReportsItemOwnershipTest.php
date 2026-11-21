<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The report item edit and save paths authorize the item's report with
 * cacti_authorize_resource(...'reports'), the same check the move and remove
 * handlers perform.
 */

$src = file_get_contents(dirname(__DIR__, 4) . '/lib/html_reports.php');

test('item edit authorizes the parent report', function () use ($src) {
	$pos = strpos($src, 'function reports_item_edit(');
	$seg = substr($src, $pos, strpos($src, "\n}\n", $pos) - $pos);
	expect($seg)->toContain("cacti_authorize_resource(\$_SESSION['sess_user_id'], (int) \$report_item['report_id'], 'reports')");
});

test('item save authorizes the destination and existing report', function () use ($src) {
	$pos = strpos($src, "save_component_report_item");
	$seg = substr($src, $pos, 900);
	expect($seg)->toContain("cacti_authorize_resource(\$_SESSION['sess_user_id'], (int) \$save['report_id'], 'reports')")
		->and($seg)->toContain('SELECT report_id FROM reports_items WHERE id = ?');
});

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 3) . '/fixtures/report_item_review.php';
cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/auth.php', 'cacti_authorize_resource', 'CactiReportItemReview');
cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/html_reports.php', 'reports_item_edit', 'CactiReportItemReview');

test('report item editing enforces ownership while preserving owner and admin access', function (bool $admin, int $owner, int $item_id, int $report_id, bool $exists, string $expected) {
	$saved = $_SESSION ?? array();
	$_SESSION['sess_user_id'] = 1;
	$GLOBALS['report_review_admin']     = $admin;
	$GLOBALS['report_review_owner']     = $owner;
	$GLOBALS['report_review_item_id']   = $item_id;
	$GLOBALS['report_review_report_id'] = $report_id;
	$GLOBALS['report_review_item']      = $exists ? array('report_id' => 11, 'graph_template_id' => 77) : array();
	try {
		expect(fn() => \CactiReportItemReview\reports_item_edit())->toThrow(RuntimeException::class, $expected);
	} finally {
		$_SESSION = $saved;
	}
})->with([
	'another user\'s item'               => [false, 2, 42, 11, true, 'denied-redirect'],
	'own item'                           => [false, 1, 42, 11, true, 'authorized-render'],
	'administrator'                      => [true, 2, 42, 11, true, 'authorized-render'],
	'missing item'                       => [false, 1, 42, 11, false, 'denied-redirect'],
	'own item under a different report'  => [false, 1, 42, 12, true, 'denied-redirect'],
	'new item on another user\'s report' => [false, 2, 0, 11, false, 'denied-redirect'],
	'new item on own report'             => [false, 1, 0, 11, false, 'authorized-render'],
]);
