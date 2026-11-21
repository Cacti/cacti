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
		->toBe('|host_description|   |host_hostname|')
		->and(strip_credential_variables('|host_snmp_|host_snmp_community|community|'))
		->toBe('');

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

/**
 * Run a PHP snippet in a separate process with the Cacti root as the working
 * directory, so lib/variables.php can be loaded with minimal stubs.
 *
 * @param string $code The PHP body to run, without an opening tag
 *
 * @return string The process output
 */
function titleSubstitutionRun(string $code) : string {
	$root = dirname(__DIR__, 4);
	$file = tempnam(sys_get_temp_dir(), 'cacti_tsr_');

	file_put_contents($file, "<?php\n" . $code);

	$output = shell_exec('cd ' . escapeshellarg($root) . ' && ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1');

	unlink($file);

	return (string) $output;
}

test('a device field cannot inject a credential variable into a title', function () {
	/* The device description is attacker-influenced. If it carries a literal
	 * |host_snmp_community| token, a sequential str_replace() would expand it on
	 * the credential pass and leak the community. substitute_host_data() must
	 * substitute in a single pass so an injected token is never re-scanned. */
	$output = titleSubstitutionRun('
		if (!defined("SQL_NO_CACHE")) define("SQL_NO_CACHE", "");
		function db_fetch_row_prepared($s, $p = array()) {
			return array(
				"id" => 5, "hostname" => "1.2.3.4",
				"description" => "pre |host_snmp_community| post",
				"site_name" => "", "notes" => "", "location" => "",
				"polling_time" => 0, "avg_time" => 0, "cur_time" => 0, "availability" => 100,
				"snmp_community" => "TOPSECRETCOMMUNITY", "snmp_version" => 2,
				"snmp_username" => "", "snmp_password" => "", "snmp_auth_protocol" => "",
				"snmp_priv_passphrase" => "", "snmp_priv_protocol" => "", "snmp_context" => "",
				"snmp_engine_id" => "", "snmp_port" => 161, "snmp_timeout" => 500,
				"snmp_sysDescr" => "", "snmp_sysObjectID" => "", "snmp_sysContact" => "",
				"snmp_sysLocation" => "", "snmp_sysName" => "", "snmp_sysUpTimeInstance" => "9",
				"ping_retries" => 1, "max_oids" => 10, "external_id" => "", "poller_id" => 1,
			);
		}
		function cacti_sizeof($a) { return is_array($a) ? count($a) : 0; }
		function get_uptime($h) { return "0"; }
		function api_plugin_hook_function($n, $d) { return $d; }
		function read_config_option($x, $y = false) { return ""; }
		require "lib/variables.php";
		print json_encode(array(
			"out" => substitute_host_data("|host_description| at |host_hostname|", "|", "|", 5),
		));
	');

	$result = json_decode($output, true);

	expect($result)->toBeArray($output)
		->and($result['out'])->not->toContain('TOPSECRETCOMMUNITY')
		->and($result['out'])->toContain('1.2.3.4');

	$source = (string) file_get_contents(dirname(__DIR__, 4) . '/lib/variables.php');
	expect($source)->toContain('strtr($string, array_combine($search, $replace))')
		->not->toContain('str_replace($search, $replace, $string)');
});
