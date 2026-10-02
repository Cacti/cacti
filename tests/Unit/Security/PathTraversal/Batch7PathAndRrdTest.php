<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Batch 7 develop-only path/RRD consolidation: the imported data-query xml_path
 * is confined to the Cacti tree (GHSA-m67r), rrdtool_create_error_image routes
 * the theme through the allowlist (GHSA-mpfm), path_dsstats_log is escaped
 * before the dsstats shell redirect (GHSA-pjq5), and the data_templates
 * rrd_maximum/rrd_minimum validator is anchored (GHSA-wp33).
 */

$root = dirname(__DIR__, 4);

test('imported data-query xml_path is confined to the Cacti tree (GHSA-m67r)', function () use ($root) {
	$s = file_get_contents($root . '/lib/import.php');
	expect($s)->toContain('realpath(CACTI_PATH_BASE)')
		->and($s)->toContain('$contained');
});

test('rrdtool_create_error_image validates the theme (GHSA-mpfm)', function () use ($root) {
	$s = file_get_contents($root . '/lib/rrd.php');
	expect($s)->toContain('cacti_validate_theme(get_selected_theme())');
});

test('path_dsstats_log is escaped before the shell redirect (GHSA-pjq5)', function () use ($root) {
	$s = file_get_contents($root . '/lib/dsstats.php');
	expect($s)->toContain(">> ' . cacti_escapeshellarg");
});

test('data_templates rrd_maximum/rrd_minimum validator is anchored (GHSA-wp33)', function () use ($root) {
	$s = file_get_contents($root . '/data_templates.php');
	expect($s)->toContain("'rrd_maximum', [new Assert")
		->and($s)->toContain('\z/');
});
