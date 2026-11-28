<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace ScriptPathRejectionFixture;

$root = dirname(__DIR__, 4);
require_once $root . '/tests/Helpers/SourceSlice.php';

function db_fetch_row_prepared($sql, $params = array()) {
	return array_shift($GLOBALS['script_path_rejection']['rows']);
}

function db_fetch_assoc_prepared($sql, $params = array()) {
	return $GLOBALS['script_path_rejection']['fields'];
}

function read_config_option($name) {
	return '/usr/bin/' . $name;
}

function cacti_log($message, $output = false, $environ = 'CMDPHP', $level = '') {
	$GLOBALS['script_path_rejection']['logs'][] = $message;
}

if (!function_exists(__NAMESPACE__ . '\\get_full_script_path')) {
	\cacti_fixture_source_function($root . '/lib/functions.php', 'get_full_script_path', __NAMESPACE__);
	\cacti_fixture_source_function($root . '/lib/functions.php', 'get_full_test_script_path', __NAMESPACE__);
}

beforeEach(function () {
	$GLOBALS['script_path_rejection_config'] = $GLOBALS['config'];
	$GLOBALS['config']['cacti_server_os']    = 'unix';
	$GLOBALS['config']['base_path']          = '/var/www/cacti';
	$GLOBALS['script_path_rejection']        = array(
		'rows'   => array(),
		'fields' => array(array('data_name' => 'field', 'value' => 'v')),
		'logs'   => array(),
	);
});

afterEach(function () {
	$GLOBALS['config'] = $GLOBALS['script_path_rejection_config'];
});

function script_path_rejection_poller($input_string) {
	$GLOBALS['script_path_rejection']['rows'] = array(
		array('id' => 7, 'data_input_id' => 3, 'type_id' => 1, 'input_string' => $input_string),
	);

	return get_full_script_path(42);
}

function script_path_rejection_test($input_string) {
	$GLOBALS['script_path_rejection']['rows'] = array(
		array('id' => 7, 'data_input_id' => 3, 'type_id' => 1, 'input_string' => $input_string),
		array('id' => 5, 'hostname' => 'host'),
	);

	return get_full_test_script_path(9, 5);
}

test('rejected input strings are logged and return false instead of an empty command', function (string $template) {
	expect(script_path_rejection_poller($template))->toBeFalse()
		->and($GLOBALS['script_path_rejection']['logs'])->toHaveCount(1)
		->and($GLOBALS['script_path_rejection']['logs'][0])->toStartWith('WARNING: Data Input[3] for Data Source[42] produced an empty command');

	$GLOBALS['script_path_rejection']['logs'] = array();

	expect(script_path_rejection_test($template))->toBeFalse()
		->and($GLOBALS['script_path_rejection']['logs'])->toHaveCount(1)
		->and($GLOBALS['script_path_rejection']['logs'][0])->toStartWith('WARNING: Data Input[3] for Data Template[9] and Device[5] produced an empty command');
})->with(array(
	'redirection in a substitution' => array('<path_php_binary> $(cmd 2>/dev/null) <field>'),
	'${...} operator'               => array('cmd ${FOO:-x} <field>'),
	'arithmetic expansion'          => array('cmd $((1+2)) <field>'),
	'field inside backticks'        => array('`printf %s <field>`'),
));

test('accepted input strings are substituted without logging', function () {
	expect(script_path_rejection_poller('<path_php_binary> -q <path_cacti>/scripts/ss.php "$(printf x)<field>" <unset>'))
		->toBe("/usr/bin/path_php_binary -q /var/www/cacti/scripts/ss.php \"\$(printf x)\"'v'\"\" ")
		->and(script_path_rejection_test('perl <path_cacti>/scripts/x.pl <field>'))
		->toBe("perl /var/www/cacti/scripts/x.pl 'v'")
		->and($GLOBALS['script_path_rejection']['logs'])->toBe(array());
});

test('callers skip a script data source whose command could not be built', function () use ($root) {
	$utility   = file_get_contents($root . '/lib/utility.php');
	$functions = file_get_contents($root . '/lib/functions.php');

	expect($utility)->toMatch('/if \(\$script_path !== false\) \{\s*\$poller_items\[\] = api_poller_cache_item_add\(\$data_source\[\'host_id\'\], array\(\), \$data_source\[\'id\'\], \$data_input\[\'rrd_step\'\], \$action, \$data_source_item_name, 1, \$script_path\);/')
		->and($functions)->toMatch('/\$script_path = get_full_test_script_path\(\$data_template_id, \$host_id\);\s*dsv_log\(\'script_path\', \$script_path\);\s*if \(\$script_path === false\) \{\s*return false;/');
});
