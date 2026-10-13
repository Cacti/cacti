<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.
// Execute production version functions with only configuration/process I/O replaced.
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 4);
require $root . '/include/global_constants.php';
require $root . '/lib/functions.php';
$config = array('cacti_server_os' => 'unix');
$state = array('path' => '', 'output' => null, 'commands' => array());

function version_fixture_config($name) {
	return $GLOBALS['state']['path'];
}

function version_fixture_shell($command) {
	$GLOBALS['state']['commands'][] = $command;

	return empty($GLOBALS['state']['real']) ? $GLOBALS['state']['output'] : shell_exec($command);
}

$source = file_get_contents($root . '/lib/functions.php');
$map = array(
	'get_rrdtool_binary_version' => 'version_fixture_binary',
	'get_installed_rrdtool_version' => 'version_fixture_installed',
	'read_config_option' => 'version_fixture_config',
	'shell_exec' => 'version_fixture_shell'
);
$compiled = '';
foreach (array('get_rrdtool_binary_version', 'get_installed_rrdtool_version') as $name) {
	if (!preg_match('/^function ' . $name . '\([^\n]*.*?^\}/ms', $source, $match)) {
		throw new RuntimeException('Missing production function ' . $name);
	}
	foreach (token_get_all('<?php ' . $match[0]) as $token) {
		if (is_array($token)) {
			if ($token[0] !== T_OPEN_TAG) {
				$compiled .= $token[0] === T_STRING ? ($map[$token[1]] ?? $token[1]) : $token[1];
			}
		} else {
			$compiled .= $token;
		}
	}
}
eval($compiled);

// Use the actual installer version choices, including legacy capability levels.
$arrays = file_get_contents($root . '/include/global_arrays.php');
preg_match('/\$rrdtool_versions = array\(.*?\n\);/s', $arrays, $match);
eval($match[0]);
$cases = array();
foreach (array('1.3.0', '1.7.1', '1.7.2', '1.8.1', '1.9.0', '1.10.3', '1.11.0', '1.11.1', '1.12.0', '1.2.30') as $release) {
	$state = array('path' => '/opt/RRD tools/' . $release . '/rrdtool', 'output' => 'RRDtool ' . $release . "  Copyright\n", 'commands' => array());
	$cases[$release] = array('exact' => version_fixture_binary(), 'supported' => version_fixture_installed());
	version_fixture_installed();
	version_fixture_binary();
	$cases[$release]['commands'] = $state['commands'];
}

$failures = array();
foreach (array(null, '', 'not RRDtool', 'RRDtool 1..11 Copyright', 'RRDtool 1.11 Copyright', 'RRDtool 1.11.0-rc1 Copyright') as $index => $output) {
	$state = array('path' => '/opt/rrdtool-failure-' . $index, 'output' => $output, 'commands' => array());
	$failures[] = array('first' => version_fixture_binary(), 'second' => version_fixture_installed());
	$failures[$index]['probes'] = count($state['commands']);
}

$state = array('path' => '/opt/rrdtool-replaced', 'output' => null, 'commands' => array());
$retry = array('failed' => version_fixture_binary());
$state['output'] = "RRDtool 1.11.0  Copyright\n";
$retry['cached_failure'] = version_fixture_binary();
$retry['forced'] = version_fixture_binary(true);
$state['output'] = "RRDtool 1.10.3  Copyright\n";
$state['path'] = '/opt/another-rrdtool';
$retry['changed_path'] = version_fixture_binary();
$retry['probes'] = count($state['commands']);

$state = array('path' => '', 'output' => null, 'commands' => array());
$empty = array('version' => version_fixture_installed(), 'probes' => count($state['commands']));
$result = array('cases' => $cases, 'failures' => $failures, 'retry' => $retry, 'empty' => $empty);
if (isset($argv[1])) {
	$state = array('path' => $argv[1], 'real' => true, 'commands' => array());
	$result['real'] = array('exact' => version_fixture_binary(), 'supported' => version_fixture_installed());
	$result['real']['probes'] = count($state['commands']);
}
echo json_encode($result, JSON_THROW_ON_ERROR), "\n";
