<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Graph commands reach RRDtool through its own command parser (rrdtool -),
 * not a shell.  Arguments must be quoted in a form both read as one argument,
 * RRDtool output shown in the debug views must be escaped, graph and data
 * source titles must not expand device credentials, and the RRA restructure
 * tool must only move RRD files that are inside the RRA directory.
 */

$root = dirname(__DIR__, 4);

/**
 * Run a PHP snippet in a separate process with the Cacti root as the working
 * directory, so library files can be loaded without the unit-test stubs.
 *
 * @param string $root The Cacti root directory
 * @param string $code The PHP code to run, without an opening tag
 *
 * @return string The process output
 */
function rrdArgumentQuotingRun($root, $code) {
	$file = tempnam(sys_get_temp_dir(), 'cacti_raq_');

	file_put_contents($file, "<?php\n" . $code);

	$output = shell_exec('cd ' . escapeshellarg($root) . ' && ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1');

	unlink($file);

	return (string) $output;
}

/**
 * Quote each value with cacti_escapeshellarg() as Cacti does on Unix and
 * return the quoted forms keyed by the original value.
 *
 * @param string $root   The Cacti root directory
 * @param array  $values The values to quote
 *
 * @return array The quoted form of each value
 */
function rrdArgumentQuotingQuote($root, array $values) {
	$output = rrdArgumentQuotingRun($root, '
		$config = array("cacti_server_os" => "unix");
		require_once "include/global_constants.php";
		require_once "lib/functions.php";
		$out = array();
		foreach (json_decode(' . var_export(json_encode($values), true) . ', true) as $value) {
			$out[] = cacti_escapeshellarg($value);
		}
		print json_encode($out);
	');

	$quoted = json_decode($output, true);

	expect($quoted)->toBeArray($output);

	return array_combine($values, $quoted);
}

$quotingValues = array('plain', "it's", "a'b'c", "x' -w 177 -t '", "''", 'say "hi"', 'back\\slash', 'ends with\\');

test('an apostrophe is quoted so RRDtool reads one argument', function () use ($root, $quotingValues) {
	$quoted = rrdArgumentQuotingQuote($root, $quotingValues);

	expect($quoted["it's"])->toBe("'it'\"'\"'s'")
		->and($quoted['plain'])->toBe("'plain'");

	foreach ($quoted as $value) {
		expect($value)->not->toContain("'\\''");
	}
});

test('the shell still reads every quoted value back unchanged', function () use ($root, $quotingValues) {
	foreach (rrdArgumentQuotingQuote($root, $quotingValues) as $value => $quoted) {
		$read = rtrim((string) shell_exec('printf %s ' . $quoted . '; printf "|"'), '|');

		expect($read)->toBe((string) $value, json_encode($value));
	}
});

test('RRDtool reads every quoted value back as one unchanged argument', function () use ($root, $quotingValues) {
	$rrdtool = trim((string) shell_exec('command -v rrdtool 2>/dev/null'));

	if ($rrdtool === '') {
		$this->markTestSkipped('rrdtool is not installed');
	}

	$dir = sys_get_temp_dir() . '/cacti-raq-' . bin2hex(random_bytes(6));
	mkdir($dir, 0700);

	shell_exec('cd ' . escapeshellarg($dir) . ' && ' . escapeshellarg($rrdtool) . ' create a.rrd --step 300 DS:v:GAUGE:600:U:U RRA:AVERAGE:0.5:1:10');

	try {
		foreach (rrdArgumentQuotingQuote($root, $quotingValues) as $value => $quoted) {
			/* the value ends the command line, so RRDtool reports the argument
			 * it parsed in its "not a vname" error */
			$command = 'graph ' . $dir . '/o.png DEF:x=' . $dir . '/a.rrd:v:AVERAGE LINE1:' . $quoted;
			$output  = (string) shell_exec('printf "%s\n" ' . escapeshellarg($command) . ' | ' . escapeshellarg($rrdtool) . ' - 2>&1');

			expect($output)->toContain('ERROR: ' . $value . ' is not a vname nor a number');
		}
	} finally {
		foreach (glob($dir . '/*') as $file) {
			unlink($file);
		}

		rmdir($dir);
	}
});

test('the poller log masks a credential in the new quoted form', function () use ($root) {
	$output = rrdArgumentQuotingRun($root, '
		$config = array("cacti_server_os" => "unix");
		require_once "include/global_constants.php";
		require_once "lib/functions.php";
		require_once "lib/poller.php";
		$item = array("snmp_community" => "o\'neil", "snmp_password" => "", "snmp_priv_passphrase" => "");
		print poller_redact_snmp_secrets("snmpget -c " . cacti_escapeshellarg("o\'neil") . " host", $item);
	');

	expect($output)->toContain("snmpget -c '********' host")
		->and($output)->not->toContain('neil');
});

test('the graph debug views escape what RRDtool says', function () use ($root) {
	/* RRDTOOL_OUTPUT_STDERR prints RRDtool's output inside the library, so the
	 * views must ask for it back with RRDTOOL_OUTPUT_RETURN_STDERR to escape it */
	$views = array(
		'graphs.php'           => "html_escape((string) @rrdtool_function_graph(\$graph['local_graph_id'], 1, \$graph_data_array, '', \$null_param, \$_SESSION['sess_user_id']))",
		'aggregate_graphs.php' => "html_escape((string) @rrdtool_function_graph(\$graphs['local_graph_id'], 1, \$graph_data_array, '', \$null_param, \$_SESSION['sess_user_id']))",
		'graph.php'            => "print html_escape((string) @rrdtool_function_graph(\$local_graph_id, get_request_var('rra_id'), \$graph_data_array, '', \$null_param, \$_SESSION['sess_user_id']));",
	);

	foreach ($views as $file => $escaped) {
		$source = file_get_contents($root . '/' . $file);

		expect($source)->toContain($escaped)
			->toContain("\$graph_data_array['output_flag'] = RRDTOOL_OUTPUT_RETURN_STDERR;")
			->not->toContain("\$graph_data_array['output_flag'] = RRDTOOL_OUTPUT_STDERR;");
	}

	expect(file_get_contents($root . '/lib/boost.php'))
		->toContain("\$graph_data_array['output_flag'] == RRDTOOL_OUTPUT_RETURN_STDERR");
});

test('titles and graph text do not expand device credentials', function () use ($root) {
	$output = rrdArgumentQuotingRun($root, '
		require_once "lib/variables.php";
		print json_encode(array(
			strip_credential_variables("T |host_snmp_community| |HOST_SNMP_PASSWORD| |host_snmp_priv_passphrase| |host_snmp_username|"),
			strip_credential_variables("|host_description| |host_snmp_context| |host_snmp_engine_id| |host_hostname|"),
			strip_credential_variables("|host_snmp_|host_snmp_community|community|"),
		));
	');

	expect(json_decode($output, true))->toBe(array('T    ', '|host_description|   |host_hostname|', ''), $output);

	$variables = file_get_contents($root . '/lib/variables.php');
	$start     = strpos($variables, 'function expand_title(');

	expect(substr($variables, $start, 200))->toContain('$title = strip_credential_variables($title);');

	$rrd   = file_get_contents($root . '/lib/rrd.php');
	$start = strpos($rrd, 'function rrd_substitute_host_query_data(');
	$body  = substr($rrd, $start, strpos($rrd, "\nfunction ", $start + 1) - $start);

	expect(strpos($body, 'strip_credential_variables($txt_graph_item)'))->toBeLessThan(strpos($body, 'substitute_host_data($txt_graph_item'));
});

test('the RRA restructure tool only moves RRD files inside the RRA directory', function () use ($root) {
	$source = file_get_contents($root . '/cli/structure_rra_paths.php');
	$guard  = strpos($source, "if (file_exists(\$old_rrd_path) && (!data_source_path_within_rra(\$old_rrd_path) || strtolower(substr(\$old_rrd_path, -4)) !== '.rrd')) {");

	expect($guard)->not->toBeFalse()
		->and($guard)->toBeLessThan(strpos($source, 'rename($old_rrd_path, $new_rrd_path)'));
});

test('a device field cannot inject a credential variable into a title', function () use ($root) {
	/* The device description is attacker-influenced. If it carries a literal
	 * |host_snmp_community| token, a sequential str_replace() would expand it on
	 * the credential pass and leak the community. substitute_host_data() must
	 * substitute in a single pass so an injected token is never re-scanned. */
	$output = rrdArgumentQuotingRun($root, '
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
				"ping_retries" => 1, "max_oids" => 10, "external_id" => "",
			);
		}
		function cacti_sizeof($a) { return is_array($a) ? count($a) : 0; }
		function get_uptime($h) { return "0"; }
		function api_plugin_hook_function($n, $d) { return $d; }
		function read_config_option($x, $y = false) { return ""; }
		require "lib/variables.php";
		print json_encode(array(
			"out"  => substitute_host_data("|host_description| at |host_hostname|", "|", "|", 5),
		));
	');

	$result = json_decode($output, true);

	expect($result)->toBeArray($output)
		->and($result['out'])->not->toContain('TOPSECRETCOMMUNITY')
		->and($result['out'])->toContain('1.2.3.4');

	$source = file_get_contents($root . '/lib/variables.php');
	expect($source)->toContain('strtr($string, array_combine($search, $replace))')
		->not->toContain('str_replace($search, $replace, $string)');
});
