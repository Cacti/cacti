<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiStructuredPathFixture;

require_once dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$GLOBALS['config'] = array('cacti_server_os' => 'unix', 'rra_path' => '/rra');
function structured_path_reset() {
	$GLOBALS['structured_path_state'] = array(
		'owners'         => array('/rra' => 33, '/rra/36' => 0, '/rra/36/88' => 33),
		'groups'         => array('/rra' => 33, '/rra/36' => 33, '/rra/36/88' => 0),
		'modes'          => array('/rra' => 02777, '/rra/36' => 0755, '/rra/36/88' => 0700),
		'owner_read_fail' => array(),
		'group_read_fail' => array(),
		'mode_read_fail' => array(),
		'chown_fail'     => array(),
		'chgrp_fail'     => array(),
		'chmod_fail'     => array(),
		'chown'          => array(),
		'chgrp'          => array(),
		'chmod'          => array(),
		'logs'           => array(),
	);
}

structured_path_reset();

function posix_getuid() {
	return 0;
}

function realpath($path) {
	return $path;
}

function fileowner($path) {
	if (isset($GLOBALS['structured_path_state']['owner_read_fail'][$path])) {
		return false;
	}

	return $GLOBALS['structured_path_state']['owners'][$path] ?? false;
}

function filegroup($path) {
	if (isset($GLOBALS['structured_path_state']['group_read_fail'][$path])) {
		return false;
	}

	return $GLOBALS['structured_path_state']['groups'][$path] ?? false;
}

function fileperms($path) {
	if (isset($GLOBALS['structured_path_state']['mode_read_fail'][$path])) {
		return false;
	}

	return $GLOBALS['structured_path_state']['modes'][$path] ?? false;
}

function chown($path, $owner) {
	$GLOBALS['structured_path_state']['chown'][] = array($path, $owner);

	if (isset($GLOBALS['structured_path_state']['chown_fail'][$path])) {
		return false;
	}

	$GLOBALS['structured_path_state']['owners'][$path] = $owner;
	$GLOBALS['structured_path_state']['modes'][$path] &= ~06000;

	return true;
}

function chgrp($path, $group) {
	$GLOBALS['structured_path_state']['chgrp'][] = array($path, $group);

	if (isset($GLOBALS['structured_path_state']['chgrp_fail'][$path])) {
		return false;
	}

	$GLOBALS['structured_path_state']['groups'][$path] = $group;
	$GLOBALS['structured_path_state']['modes'][$path] &= ~06000;

	return true;
}

function chmod($path, $mode) {
	$GLOBALS['structured_path_state']['chmod'][] = array($path, $mode);

	if (isset($GLOBALS['structured_path_state']['chmod_fail'][$path])) {
		return false;
	}

	$GLOBALS['structured_path_state']['modes'][$path] = $mode;

	return true;
}

function cacti_log($message, $output = false, $facility = '') {
	$GLOBALS['structured_path_state']['logs'][] = array($message, $facility);
}

\cacti_fixture_source_function(
	dirname(__DIR__, 3) . '/lib/rrd.php',
	'rrdtool_set_structured_path_permissions',
	__NAMESPACE__
);

$repaired       = rrdtool_set_structured_path_permissions('/rra/36/88', 'BOOST');
$outside        = rrdtool_set_structured_path_permissions('/outside', 'BOOST');
$repaired_state = $GLOBALS['structured_path_state'];

$GLOBALS['config']['cacti_server_os'] = 'win32';
$skipped = rrdtool_set_structured_path_permissions('/rra/36/88', 'BOOST');
$GLOBALS['config']['cacti_server_os'] = 'unix';

$failures = array();
foreach (array(
	'owner_read_fail' => '/rra',
	'group_read_fail' => '/rra/36',
	'chown_fail'      => '/rra/36',
	'chgrp_fail'      => '/rra/36/88',
	'mode_read_fail'  => '/rra/36',
	'chmod_fail'      => '/rra/36',
) as $failure => $path) {
	structured_path_reset();
	$GLOBALS['structured_path_state'][$failure][$path] = true;
	$failures[$failure] = rrdtool_set_structured_path_permissions('/rra/36/88', 'BOOST');
}

print json_encode(array(
	'repaired' => $repaired,
	'outside'  => $outside,
	'skipped'  => $skipped,
	'failures' => $failures,
	'owners'   => $repaired_state['owners'],
	'groups'   => $repaired_state['groups'],
	'modes'    => $repaired_state['modes'],
	'chown'    => $repaired_state['chown'],
	'chgrp'    => $repaired_state['chgrp'],
	'chmod'    => $repaired_state['chmod'],
), JSON_THROW_ON_ERROR);
