<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Data source paths are passed to RRDtool, validation errors copy the request
 * into the Cacti log, the login page shows LDAP failures, and several exports
 * print stored names.  Each now keeps the value to the form its consumer
 * expects.
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
function pathLogExportRun($root, $code) {
	$file = tempnam(sys_get_temp_dir(), 'cacti_plx_');

	file_put_contents($file, "<?php\n" . $code);

	$output = shell_exec('cd ' . escapeshellarg($root) . ' && ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1');

	unlink($file);

	return (string) $output;
}

test('RRA containment rejects file names RRDtool would split into several arguments', function () use ($root) {
	$output = pathLogExportRun($root, '
		$rra = sys_get_temp_dir() . "/cacti-plx-" . bin2hex(random_bytes(6)) . "/rra";
		mkdir($rra, 0700, true);
		$config = array("rra_path" => $rra);
		require_once "lib/functions.php";
		$cases = array(
			"plain.rrd"            => data_source_path_within_rra("$rra/plain.rrd"),
			"nested"               => data_source_path_within_rra("$rra/sub/dir/file.rrd"),
			"space"                => data_source_path_within_rra("$rra/a b.rrd"),
			"tab"                  => data_source_path_within_rra("$rra/a\tb.rrd"),
			"single quote"         => data_source_path_within_rra("$rra/a\'b.rrd"),
			"double quote"         => data_source_path_within_rra("$rra/a\"b.rrd"),
			"backtick"             => data_source_path_within_rra("$rra/a`b.rrd"),
			"space in directory"   => data_source_path_within_rra("$rra/a b/file.rrd"),
		);
		rmdir($rra);
		rmdir(dirname($rra));
		print json_encode($cases);
	');

	$cases = json_decode($output, true);

	expect($cases)->toBeArray($output)
		->and($cases['plain.rrd'])->toBeTrue()
		->and($cases['nested'])->toBeTrue();

	foreach (array('space', 'tab', 'single quote', 'double quote', 'backtick', 'space in directory') as $name) {
		expect($cases[$name])->toBeFalse($name);
	}
});

test('RRA containment still accepts an RRA directory whose own path holds a space', function () use ($root) {
	$output = pathLogExportRun($root, '
		$rra = sys_get_temp_dir() . "/cacti plx " . bin2hex(random_bytes(6)) . "/rra";
		mkdir($rra, 0700, true);
		$config = array("rra_path" => $rra);
		require_once "lib/functions.php";
		print json_encode(data_source_path_within_rra("$rra/file.rrd"));
		rmdir($rra);
		rmdir(dirname($rra));
	');

	expect(json_decode($output, true))->toBeTrue($output);
});

test('the data source form rejects whitespace and quotes in a custom path', function () use ($root) {
	$source = file_get_contents($root . '/data_sources.php');

	expect($source)->toContain("get_nfilter_request_var('data_source_path'), 'data_source_path', '^[^\\s\\'\"`]*$', true, 3);");

	$pattern = '/^[^\s\'"`]*$/';

	expect(preg_match($pattern, '<path_rra>/host_traffic_in_12.rrd'))->toBe(1)
		->and(preg_match($pattern, '<path_rra>/a b.rrd'))->toBe(0)
		->and(preg_match($pattern, "<path_rra>/a'b.rrd"))->toBe(0)
		->and(preg_match($pattern, "<path_rra>/a\nb.rrd"))->toBe(0);
});

test('a validation error backtrace masks credential and token fields', function () use ($root) {
	$output = pathLogExportRun($root, '
		require_once "lib/html_validate.php";
		$_REQUEST = array(
			"id"                   => "x",
			"login_password"       => "secret-one",
			"password_confirm"     => "secret-two",
			"snmp_community"       => "secret-three",
			"snmp_priv_passphrase" => "secret-four",
			"__csrf_magic"         => "secret-five",
			"description"          => "kept",
		);
		print html_validation_request_summary();
	');

	$request = json_decode($output, true);

	expect($request)->toBeArray($output)
		->and($request['id'])->toBe('x')
		->and($request['description'])->toBe('kept')
		->and($output)->not->toContain('secret-');

	expect(file_get_contents($root . '/lib/html_validate.php'))->not->toContain('json_encode($_REQUEST)');
});

test('the LDAP login reports a failure without the directory server error text', function () use ($root) {
	$source = file_get_contents($root . '/lib/auth.php');
	$start  = strpos($source, 'function ldap_login_process(');
	$body   = substr($source, $start, strpos($source, "\nfunction ", $start + 1) - $start);

	expect($body)->not->toContain("__('Access Denied!  LDAP Search Error: %s'")
		->not->toContain("__('Access Denied!  LDAP Error: %s'")
		->toContain("cacti_log('LOGIN FAILED: LDAP Error: ' . \$ldap_auth_response['error_text'], false, 'AUTH');");
});

test('the discovery and color exports neutralise spreadsheet formulas', function () use ($root) {
	$devices = file_get_contents($root . '/automation_devices.php');
	$colors  = file_get_contents($root . '/color.php');

	expect($devices)->toContain("cacti_csv_safe(\$host['hostname'])")
		->toContain("print cacti_csv_safe(\$host['ip']) . ',';")
		->toContain("\$host[\$h] = str_replace(',', '', (string) \$r);")
		->not->toContain("\$host['\$h']");

	expect($colors)->toContain("cacti_csv_safe(\$color['name'])")
		->toContain("cacti_csv_safe(\$color['hex'])");
});

test('the graph export table escapes an unexpected value', function () use ($root) {
	expect(file_get_contents($root . '/graph_xport.php'))
		->toContain("\$row_data .= '(unexpected: ' . html_escape(\$row['col' . \$i]) . ')';");
});
