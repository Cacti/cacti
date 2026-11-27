<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The reports bulk action confirmation page lists, and posts back, only the
 * reports that pass cacti_authorize_resource(...'reports'), the same check the
 * action branch applies to the selection it acts on.
 */

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 3) . '/fixtures/report_action_review.php';
cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/auth.php', 'cacti_authorize_resource', 'CactiReportActionReview');
cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/html_reports.php', 'reports_form_actions', 'CactiReportActionReview');

function report_action_confirmation(bool $admin, array $selected) : string {
	$saved = [$_SESSION ?? array(), $_POST];

	$_SESSION['sess_user_id']          = 1;
	$_POST                             = array('drp_action' => (string) REPORTS_DELETE);
	$GLOBALS['report_action_admin']    = $admin;
	$GLOBALS['report_action_reports']  = array(
		11 => array('user_id' => 1, 'name' => 'Mine <weekly>'),
		12 => array('user_id' => 2, 'name' => 'Theirs'),
		13 => array('user_id' => 1, 'name' => 'Mine too'),
	);

	foreach ($selected as $id) {
		$_POST['chk_' . $id] = 'on';
	}

	ob_start();

	try {
		\CactiReportActionReview\reports_form_actions();

		return ob_get_contents();
	} finally {
		ob_end_clean();
		[$_SESSION, $_POST] = $saved;
	}
}

test('the confirmation page lists and posts back only the reports the user may act on', function () {
	$page = report_action_confirmation(false, [11, 12, 13, 99]);

	expect($page)->toContain('<li>Mine &lt;weekly&gt;</li><li>Mine too</li>')
		->and($page)->not->toContain('Theirs')
		->and($page)->toContain("name='selected_items' value='" . serialize(array('11', '13')) . "'");
});

test('a reports administrator still sees every selected report', function () {
	$page = report_action_confirmation(true, [11, 12]);

	expect($page)->toContain('<li>Mine &lt;weekly&gt;</li><li>Theirs</li>')
		->and($page)->toContain("name='selected_items' value='" . serialize(array('11', '12')) . "'");
});

test('a selection with no report the user may act on is treated as empty', function () {
	expect(fn () => report_action_confirmation(false, [12]))->toThrow(RuntimeException::class, 'nothing-selected-redirect');
});
