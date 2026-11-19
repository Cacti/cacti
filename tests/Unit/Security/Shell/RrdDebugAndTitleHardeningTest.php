<?php
declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 */

/*
 * RRDtool's error text repeats graph item text, so the debug views must
 * escape it; titles must not expand device credentials; and the RRA
 * restructure tool must only move RRD files that are inside the RRA directory.
 */

require_once dirname(__DIR__, 3) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 4) . '/lib/variables.php';

test('the graph debug views return RRDtool output so they can escape it', function () {
	$root = dirname(__DIR__, 4);

	foreach (['graphs.php', 'aggregate_graphs.php'] as $file) {
		$source = (string) file_get_contents($root . '/' . $file);

		expect($source)->toContain("\$graph_data_array['output_flag']  = RRDTOOL_OUTPUT_RETURN_STDERR;")
			->not->toContain("\$graph_data_array['output_flag']  = RRDTOOL_OUTPUT_STDERR;")
			->toContain('htmle((string) ');
	}

	expect((string) file_get_contents($root . '/lib/boost.php'))
		->toContain("\$graph_data_array['output_flag'] == RRDTOOL_OUTPUT_RETURN_STDERR");
});

test('titles and graph text do not expand device credentials', function () {
	expect(strip_credential_variables('T |host_snmp_community| |HOST_SNMP_PASSWORD| |host_snmp_priv_passphrase| |host_snmp_username|'))
		->toBe('T    ')
		->and(strip_credential_variables('|host_description| |host_snmp_context| |host_snmp_engine_id| |host_hostname|'))
		->toBe('|host_description|   |host_hostname|');

	$variables = (string) file_get_contents(dirname(__DIR__, 4) . '/lib/variables.php');
	$start     = (int) strpos($variables, 'function expand_title(');

	expect(substr($variables, $start, 200))->toContain('$title = strip_credential_variables($title);');

	expect((string) file_get_contents(dirname(__DIR__, 4) . '/lib/rrd.php'))->toContain('$value = strip_credential_variables($value);');
});

test('the RRA restructure tool only moves RRD files inside the RRA directory', function () {
	$source = (string) file_get_contents(dirname(__DIR__, 4) . '/cli/structure_rra_paths.php');
	$guard  = strpos($source, "if (file_exists(\$old_rrd_path) && (!data_source_path_within_rra(\$old_rrd_path) || strtolower(substr(\$old_rrd_path, -4)) !== '.rrd')) {");

	expect($guard)->not->toBeFalse()
		->and($guard)->toBeLessThan(strpos($source, 'rename($old_rrd_path, $new_rrd_path)'));
});
