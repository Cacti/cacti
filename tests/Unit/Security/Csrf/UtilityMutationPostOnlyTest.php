<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Utility actions that rebuild caches, purge logs or schedule RRD removal
 * must arrive as a POST, which csrf-magic validates, and the UI must send
 * them that way.
 */

$root = dirname(__DIR__, 4);

$utilities_src = file_get_contents($root . '/utilities.php');
$rrdclean_src  = file_get_contents($root . '/rrdcleaner.php');
$clog_src      = file_get_contents($root . '/lib/clog_webapi.php');

function utility_post_case_body(string $src, string $case): string {
	$start = strpos($src, "case '" . $case . "'");
	expect($start)->not->toBeFalse();
	$end = strpos($src, 'break;', $start);
	expect($end)->not->toBeFalse();

	return substr($src, $start, $end - $start);
}

test('utilities.php requires POST before each state-changing action', function (string $case, string $call) use ($utilities_src) {
	$body  = utility_post_case_body($utilities_src, $case);
	$guard = strpos($body, 'csrf_require_post();');
	$work  = strpos($body, $call);

	expect($guard)->not->toBeFalse();
	expect($work)->not->toBeFalse();
	expect($guard)->toBeLessThan($work);
})->with(array(
	array('clear_poller_cache', 'repopulate_poller_cache('),
	array('rebuild_resource_cache', 'rebuild_resource_cache('),
	array('clear_logfile', 'utilities_clear_logfile('),
	array('purge_logfile', 'clog_purge_logfile('),
	array('purge_data_source_statistics', 'purge_data_source_statistics('),
	array('rebuild_snmpagent_cache', 'snmpagent_cache_rebuilt('),
));

test('utilities.php submits the state-changing links as POST', function (string $case) use ($utilities_src) {
	$link = "'link'  => 'utilities.php?action=" . $case . "',";
	$pos  = strpos($utilities_src, $link);

	expect($pos)->not->toBeFalse();
	expect(substr($utilities_src, $pos + strlen($link), 40))->toContain("'post'  => true,");
	expect($utilities_src)->toContain("'hyperLink cactiPostAction'");
	expect($utilities_src)->not->toContain("action=purge_logfile&header=false&filename='+$('#filename').val();\n\t\tloadPageNoHeader(strURL);");
})->with(array(
	'clear_poller_cache',
	'rebuild_resource_cache',
	'purge_data_source_statistics',
	'rebuild_snmpagent_cache',
));

test('rrdcleaner.php requires POST to purge or archive all unknown files', function () use ($rrdclean_src) {
	$body  = utility_post_case_body($rrdclean_src, 'arcall');
	$guard = strpos($body, 'csrf_require_post();');
	$work  = strpos($body, 'remove_all_rrds(');

	expect($rrdclean_src)->toContain("case 'remall' :\ncase 'arcall' :");
	expect($guard)->not->toBeFalse();
	expect($work)->not->toBeFalse();
	expect($guard)->toBeLessThan($work);
	expect($rrdclean_src)->not->toMatch('/\$\.get\(\'rrdcleaner\.php\?[^\']*action=(remall|arcall)/');
});

test('the log purge continue step requires POST', function () use ($clog_src) {
	$start = strpos($clog_src, "isset_request_var('purge_continue')) {");
	expect($start)->not->toBeFalse();

	$guard = strpos($clog_src, 'csrf_require_post();', $start);
	$work  = strpos($clog_src, 'clog_purge_logfile();', $start);

	expect($guard)->not->toBeFalse();
	expect($guard)->toBeLessThan($work);
	expect($clog_src)->toContain("purge_continue=1&header=false&filename=\" . basename(\$logfile) . \"';\n\t\t\t\t\tloadPageUsingPostUrl(strURL, 'main');");
});
