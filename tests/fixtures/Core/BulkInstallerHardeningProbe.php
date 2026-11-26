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

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$root = dirname(__DIR__, 3);
require_once $root . '/include/global_constants.php';

\cacti_fixture_source_function($root . '/lib/functions.php', 'cacti_path_is_within', '');
\cacti_fixture_source_function($root . '/lib/functions.php', 'data_source_path_within_rra', '');

if (!defined('CACTI_VERSION')) {
	define('CACTI_VERSION', '1.2.30');
}

$GLOBALS['inst_exec_calls'] = array();
$GLOBALS['inst_opts']       = array();
$GLOBALS['inst_logs']       = array();
$GLOBALS['inst_php_bin']    = PHP_BINARY;
$GLOBALS['inst_cli_bad']    = false;

function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

function cacti_count($value) {
	return is_array($value) ? count($value) : 0;
}

function cacti_log($message, $output = false, $facility = '', $level = 0) {
	$GLOBALS['inst_logs'][] = $facility . ': ' . $message;
}

function __($text) {
	$args = func_get_args();
	array_shift($args);
	return count($args) ? vsprintf($text, $args) : $text;
}

function __esc($text) {
	$args = func_get_args();
	array_shift($args);
	return htmlspecialchars(count($args) ? vsprintf($text, $args) : $text, ENT_QUOTES, 'UTF-8');
}

function html_escape($text) {
	return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function cacti_exec($binary, $args, &$output = null, $host = '', $timeout = 0) {
	$GLOBALS['inst_exec_calls'][] = array($binary, $args);
	if (is_array($args) && isset($args[1]) && strpos($args[1], 'cli_test.php') !== false) {
		$n = (int) $args[2];
		$output = array($GLOBALS['inst_cli_bad'] ? '0' : (string) ($n * $n));
		return 0;
	}
	$output = array('Converting table Successful');
	return 0;
}

function read_config_option($name, $force = false) {
	if ($name === 'path_php_binary') {
		return $GLOBALS['inst_php_bin'];
	}
	if ($name === 'path_rrdtool') {
		return '/usr/bin/rrdtool';
	}
	if ($name === 'spikekill_backupdir') {
		return $GLOBALS['inst_sk_backupdir'];
	}
	return '';
}

function read_user_setting($name, $default = '', $force = false) {
	return $default;
}

function get_execution_user() {
	return 'www-data';
}

function set_install_config_option($name, $value) {
	$GLOBALS['inst_opts'][$name] = $value;
}

function log_install_debug($sec, $msg, $force = false) {
}

function log_install_low($sec, $msg, $force = false) {
}

function log_install_medium($sec, $msg, $force = false) {
}

function log_install_high($sec, $msg, $force = false) {
}

function log_install_always($sec, $msg, $force = false) {
	$GLOBALS['inst_logs'][] = $msg;
}

function db_fetch_cell_prepared_stub_noop() {
}

function install_test_local_database_connection() {
	return true;
}

function get_install_config_option($name, $default = '') {
	return isset($GLOBALS['inst_opts'][$name]) ? $GLOBALS['inst_opts'][$name] : $default;
}

function clean_up_lines($str) {
	return $str;
}

function automation_get_network_info($network) {
	return ($network === '10.0.0.0/24') ? array('network' => $network) : false;
}

function db_fetch_row_prepared($sql, $params = array()) {
	return array('id' => 1, 'name' => 'Default', 'step' => 300, 'heartbeat' => 600);
}

function db_fetch_row($sql) {
	return array();
}

function db_fetch_assoc($sql) {
	if (strpos($sql, 'install_table_') !== false) {
		return array(array('name' => 'install_table_1', 'value' => 'host'));
	}
	return array();
}

function db_fetch_assoc_prepared($sql, $params = array()) {
	if (strpos($sql, 'FROM host_graph') !== false) {
		return array(array('graph_template_id' => 1));
	}
	return array();
}

function db_fetch_cell($sql) {
	return '';
}

function db_fetch_cell_prepared($sql, $params = array()) {
	if (strpos($sql, 'FROM host_template') !== false) {
		return 1;
	}
	if (strpos($sql, 'FROM host') !== false) {
		return 1;
	}
	return 1;
}

function db_execute($sql) {
	return true;
}

function db_execute_prepared($sql, $params = array()) {
	return true;
}

function automation_execute_graph_template($host_id, $gt_id) {
}

function repopulate_poller_cache() {
}

function snmpagent_cache_rebuilt() {
}

function rsa_check_keypair() {
}

require_once $root . '/lib/installer.php';
require_once $root . '/lib/spikekill.php';

$tmpBase = sys_get_temp_dir() . '/cacti-inst-probe-' . bin2hex(random_bytes(6));
mkdir($tmpBase . '/rra', 0700, true);
$notPhpBin = $tmpBase . '/evil_bin';
file_put_contents($notPhpBin, "#!/bin/sh\necho 1\n");
chmod($notPhpBin, 0700);
$okRrd = $tmpBase . '/rra/ok.rrd';
file_put_contents($okRrd, 'rrd');
$GLOBALS['inst_sk_backupdir'] = '-badbackup';

$GLOBALS['config'] = array(
	'base_path'       => $tmpBase,
	'rra_path'        => $tmpBase . '/rra',
	'cacti_server_os' => 'unix',
);
$GLOBALS['database_username'] = 'user<script>';
$GLOBALS['database_default']  = 'cacti<b>';

try {
	$rc   = new ReflectionClass('Installer');
	$inst = $rc->newInstanceWithoutConstructor();

	$setProp = static function ($name, $val) use ($rc, $inst) {
		$p = $rc->getProperty($name);
		$p->setAccessible(true);
		$p->setValue($inst, $val);
	};
	$getProp = static function ($name) use ($rc, $inst) {
		$p = $rc->getProperty($name);
		$p->setAccessible(true);
		return $p->getValue($inst);
	};

	$setProp('stepCurrent', Installer::STEP_BINARY_LOCATIONS);
	$setProp('errors', array());
	$setProp('profile', 1);
	$setProp('automationMode', false);
	$setProp('automationRange', '');
	$setProp('automationOverride', false);
	$setProp('paths', array(
		'path_php_binary' => array('install_check' => 'file_exists', 'install_optional' => false, 'install_blank' => false),
	));

	// 1. setPaths: reject non-PHP binary name, reject wrong cli_test output, accept valid PHP binary
	$mSetPaths = $rc->getMethod('setPaths');
	$mSetPaths->setAccessible(true);
	$mSetPaths->invoke($inst, array('path_php_binary' => $notPhpBin));
	$errNonPhp = $getProp('errors');

	$setProp('errors', array());
	$GLOBALS['inst_cli_bad'] = true;
	$mSetPaths->invoke($inst, array('path_php_binary' => PHP_BINARY));
	$errBadCli = $getProp('errors');

	$setProp('errors', array());
	$GLOBALS['inst_cli_bad'] = false;
	$mSetPaths->invoke($inst, array('path_php_binary' => PHP_BINARY));
	$errGoodPhp = $getProp('errors');

	// 2. setAutomationRange: reject invalid range, accept valid range
	$mSetRange = $rc->getMethod('setAutomationRange');
	$mSetRange->setAccessible(true);
	$mSetRange->invoke($inst, 'invalid-cidr');
	$rangeAfterBad = $getProp('automationRange');
	$mSetRange->invoke($inst, '10.0.0.0/24');
	$rangeAfterGood = $getProp('automationRange');

	// 3. exitDbTooOld escapes database_username & database_default
	$mTooOld = $rc->getMethod('exitDbTooOld');
	$mTooOld->setAccessible(true);
	$tooOldHtml = $mTooOld->invoke($inst);

	// 4. installServer with valid PHP binary and invalid PHP binary
	$mInstallServer = $rc->getMethod('installServer');
	$mInstallServer->setAccessible(true);
	$GLOBALS['inst_php_bin'] = PHP_BINARY;
	$mInstallServer->invoke($inst);
	$GLOBALS['inst_php_bin'] = $notPhpBin;
	$mInstallServer->invoke($inst);

	// 5. convertDatabase with valid PHP binary
	$mConvert = $rc->getMethod('convertDatabase');
	$mConvert->setAccessible(true);
	$GLOBALS['inst_php_bin'] = PHP_BINARY;
	$mConvert->invoke($inst);

	// 6. spikekill::initialize_spikekill, dumpRRDFileToXML, createRRDFileFromXML
	$src = new ReflectionClass('spikekill');
	$sk  = $src->newInstanceWithoutConstructor();
	foreach (array('rrdfile' => '-dash.rrd', 'errors' => array(), 'strout' => '', 'html' => false, 'avgnan' => '', 'dmethod' => 1, 'dnumspike' => 10, 'dstddev' => 10, 'dpercent' => 500, 'doutliers' => 5, 'davgnan' => 'last', 'method' => '', 'numspike' => '', 'stddev' => '', 'percent' => '', 'outliers' => '', 'dsfilter' => '') as $k => $v) {
		if ($src->hasProperty($k)) {
			$pr = $src->getProperty($k);
			$pr->setAccessible(true);
			$pr->setValue($sk, $v);
		}
	}
	$mInit = $src->getMethod('initialize_spikekill');
	$mInit->setAccessible(true);
	$mInit->invoke($sk);
	$skDashErr = $sk->get_errors();

	$prRrd = $src->getProperty('rrdfile');
	$prRrd->setAccessible(true);
	$prErr = $src->getProperty('errors');
	$prErr->setAccessible(true);
	$prErr->setValue($sk, array());
	$prRrd->setValue($sk, '/etc/passwd');
	$mInit->invoke($sk);
	$skOutsideErr = $sk->get_errors();

	$mCreate = $src->getMethod('createRRDFileFromXML');
	$mCreate->setAccessible(true);

	$skCreateDash = $mCreate->invoke($sk, '-bad.xml', $tmpBase . '/rra/a.rrd');
	$GLOBALS['config']['cacti_server_os'] = 'win32';
	$skCreateWin  = $mCreate->invoke($sk, $tmpBase . '/rra/a&b.xml', $tmpBase . '/rra/a.rrd');
	$GLOBALS['config']['cacti_server_os'] = 'unix';

	// Also exercise remove_spikes() guard on invalid xmlfile path
	$prErr->setValue($sk, array());
	$prRrd->setValue($sk, $okRrd);
	foreach (array('dryrun' => true, 'method' => 1, 'username' => 'admin', 'outliers' => 5) as $k => $v) {
		if ($src->hasProperty($k)) {
			$pr = $src->getProperty($k);
			$pr->setAccessible(true);
			$pr->setValue($sk, $v);
		}
	}
	$skRemoveBadXml = $sk->remove_spikes();

	print json_encode(array(
		'err_non_php'     => !empty($errNonPhp),
		'err_bad_cli'     => !empty($errBadCli),
		'err_good_php'    => empty($errGoodPhp),
		'range_after_bad' => $rangeAfterBad,
		'range_after_good'=> $rangeAfterGood,
		'too_old_escaped' => strpos($tooOldHtml, 'user&lt;script&gt;') !== false,
		'exec_calls'      => count($GLOBALS['inst_exec_calls']),
		'sk_dash_err'     => strpos($skDashErr, 'not a valid RRDfile path') !== false,
		'sk_outside_err'  => strpos($skOutsideErr, 'outside the RRA directory') !== false,
		'sk_create_dash'  => $skCreateDash,
		'sk_create_win'   => $skCreateWin,
		'sk_remove_badxml'=> $skRemoveBadXml,
	), JSON_THROW_ON_ERROR);
} finally {
	@unlink($okRrd);
	@unlink($notPhpBin);
	@rmdir($tmpBase . '/rra');
	@rmdir($tmpBase);
}
