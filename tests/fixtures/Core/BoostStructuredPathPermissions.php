<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiStructuredPathFixture;

require_once dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

$GLOBALS['config'] = array('cacti_server_os' => 'unix', 'rra_path' => '/rra');
$GLOBALS['structured_path_state'] = array(
	'owners' => array('/rra' => 33, '/rra/36' => 0, '/rra/36/88' => 33),
	'groups' => array('/rra' => 33, '/rra/36' => 33, '/rra/36/88' => 0),
	'modes'  => array('/rra' => 02777, '/rra/36' => 0755, '/rra/36/88' => 0700),
	'chown'  => array(),
	'chgrp'  => array(),
	'chmod'  => array(),
	'logs'   => array(),
);

function posix_getuid() {
	return 0;
}

function realpath($path) {
	return $path;
}

function fileowner($path) {
	return $GLOBALS['structured_path_state']['owners'][$path] ?? false;
}

function filegroup($path) {
	return $GLOBALS['structured_path_state']['groups'][$path] ?? false;
}

function fileperms($path) {
	return $GLOBALS['structured_path_state']['modes'][$path] ?? false;
}

function chown($path, $owner) {
	$GLOBALS['structured_path_state']['chown'][] = array($path, $owner);
	$GLOBALS['structured_path_state']['owners'][$path] = $owner;
	$GLOBALS['structured_path_state']['modes'][$path] &= ~06000;

	return true;
}

function chgrp($path, $group) {
	$GLOBALS['structured_path_state']['chgrp'][] = array($path, $group);
	$GLOBALS['structured_path_state']['groups'][$path] = $group;
	$GLOBALS['structured_path_state']['modes'][$path] &= ~06000;

	return true;
}

function chmod($path, $mode) {
	$GLOBALS['structured_path_state']['chmod'][] = array($path, $mode);
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

$repaired = rrdtool_set_structured_path_permissions('/rra/36/88', 'BOOST');
$outside  = rrdtool_set_structured_path_permissions('/outside', 'BOOST');

print json_encode(array(
	'repaired' => $repaired,
	'outside'  => $outside,
	'owners'   => $GLOBALS['structured_path_state']['owners'],
	'groups'   => $GLOBALS['structured_path_state']['groups'],
	'modes'    => $GLOBALS['structured_path_state']['modes'],
	'chown'    => $GLOBALS['structured_path_state']['chown'],
	'chgrp'    => $GLOBALS['structured_path_state']['chgrp'],
	'chmod'    => $GLOBALS['structured_path_state']['chmod'],
), JSON_THROW_ON_ERROR);
