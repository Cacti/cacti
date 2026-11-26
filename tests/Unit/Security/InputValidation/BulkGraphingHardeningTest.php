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

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

$root = dirname(__DIR__, 4);

test('template, VDEF, data source suggested value, and dsstats hardening guards enforce allowlists and bounds', function () {
	$probe = dirname(__DIR__, 3) . '/fixtures/Core/BulkGraphingHardeningProbe.php';
	$res   = cacti_test_isolated_probe($probe);

	expect($res['tpl_allowed']['gtg_title'])->toBeTrue()
		->and($res['tpl_allowed']['gtg_height'])->toBeTrue()
		->and($res['tpl_allowed']['gtg_blocked'])->toBeFalse()
		->and($res['tpl_allowed']['gti_color'])->toBeTrue()
		->and($res['tpl_allowed']['gti_task'])->toBeFalse()
		->and($res['tpl_allowed']['gti_blocked'])->toBeFalse()
		->and($res['tpl_allowed']['dtd_name'])->toBeTrue()
		->and($res['tpl_allowed']['dtd_active'])->toBeTrue()
		->and($res['tpl_allowed']['dtd_blocked'])->toBeFalse()
		->and($res['tpl_allowed']['dtr_max_u'])->toBeTrue()
		->and($res['tpl_allowed']['dtr_bad_max'])->toBeFalse()
		->and($res['tpl_allowed']['dtr_blocked'])->toBeFalse()
		->and($res['tpl_allowed']['unknown_table'])->toBeFalse()
		->and($res['wl_corrupt'])->toBeFalse()
		->and($res['wl_mixed'])->toBeFalse()
		->and($res['vdef_missing_item'])->toBe('')
		->and($res['vdef_zero'])->toBe('')
		->and($res['vdef_direct_cyc'])->toBe('')
		->and($res['vdef_indirect_cyc'])->toBe('')
		->and($res['vdef_too_large'])->toBe('')
		->and($res['vdef_too_deep'])->toBe('')
		->and($res['vdef_too_exp'])->toBe('')
		->and($res['ds_sv']['name'])->toBe('data_template_data')
		->and($res['ds_sv']['active'])->toBe('data_template_data')
		->and($res['ds_sv']['active_bad'])->toBeFalse()
		->and($res['ds_sv']['rrd_step'])->toBe('data_template_data')
		->and($res['ds_sv']['rrd_max_u'])->toBe('data_template_rrd')
		->and($res['ds_sv']['rrd_min_0'])->toBe('data_template_rrd')
		->and($res['ds_sv']['rrd_hb'])->toBe('data_template_rrd')
		->and($res['ds_sv']['path_block'])->toBeFalse()
		->and($res['ds_sv']['bad_max'])->toBeFalse()
		->and($res['dsstats_fmt']['numstr'])->toBe('42.5')
		->and($res['dsstats_fmt']['nan'])->toBe('NULL')
		->and($res['dsstats_fmt']['u'])->toBe('NULL')
		->and($res['dsstats_fmt']['sqli'])->toBe('NULL')
		->and($res['ds_queries_after_invalid'])->toBe(0)
		->and($res['ds_query_daily'])->toContain("(15,'traffic\\'in',12.5,NULL)")
		->and($res['ds_query_daily'])->not->toContain("bad'sql")
		->and($res['vdef_save_errors']['bad_type'])->toBe(array('type'))
		->and($res['vdef_save_errors']['bad_fn'])->toBe(array('value'))
		->and($res['vdef_save_errors']['bad_ds'])->toBe(array('value'))
		->and($res['vdef_save_errors']['bad_str'])->toBe(array('value'))
		->and($res['vdef_save_errors']['ok_str'])->toBe(array())
		->and($res['logs_count'])->toBeGreaterThan(0);

	$ruleSqli = cacti_test_isolated_probe($probe, array('automation_rule_sqli'));
	expect($ruleSqli['messages'])->toContain('sql_injection');

	$matchSqli = cacti_test_isolated_probe($probe, array('automation_match_sqli'));
	expect($matchSqli['messages'])->toContain('sql_injection');
});

test('external links enforce enabled state, preserve disabled state on edit, and sandbox console iframes', function () use ($root) {
	$linkSource  = file_get_contents($root . '/link.php');
	$linksSource = file_get_contents($root . '/links.php');
	$indexSource = file_get_contents($root . '/index.php');

	expect($linkSource)->toContain('AND enabled = "on"')
		->and($linksSource)->toContain("(isset(\$data['enabled']) ? \$data['enabled']:'')")
		->and($indexSource)->toContain("filter_var(\$page['contentfile'], FILTER_VALIDATE_URL)")
		->and($indexSource)->toContain('sandbox="allow-scripts allow-popups allow-forms"');
});
