<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * An empty OID must never reach the Net-SNMP walk binary (#8440).
 *
 * cacti_exec() passes each argument separately, so an empty OID became its
 * own argument and snmpbulkwalk failed with "Unknown Object Identifier
 * (Sub-id not found: (top) -> )".  The walk now refuses an empty OID before
 * choosing a transport.  The walk binaries here are a stub that records the
 * arguments it receives.
 */

if (!defined('SNMP_POLLER')) {
	define('SNMP_POLLER', 'SNMP');
}

if (!function_exists('cacti_snmp_validate_oid')) {
	$config['php_snmp_support'] = false;
	$config['include_path']     = dirname(__DIR__, 4) . '/include';

	require_once dirname(__DIR__, 4) . '/lib/snmp.php';
}

function _snmp_walk_stub_dir() {
	static $dir = null;

	if ($dir === null) {
		$dir = sys_get_temp_dir() . '/cacti-walk-stub-' . getmypid();

		if (!is_dir($dir)) {
			mkdir($dir, 0700, true);
		}

		file_put_contents($dir . '/walk', "#!/bin/sh\nprintf '%s\\n' \"\$@\" > '" . $dir . "/args'\n");
		chmod($dir . '/walk', 0700);
	}

	return $dir;
}

function _snmp_walk_with_stub($oid) {
	global $config;

	$dir = _snmp_walk_stub_dir();

	if (file_exists($dir . '/args')) {
		unlink($dir . '/args');
	}

	$config['is_web']                                               = false;
	$config['config_options_array']['path_snmpbulkwalk']            = $dir . '/walk';
	$config['config_options_array']['path_snmpwalk']                = $dir . '/walk';
	$config['config_options_array']['oid_increasing_check_disable'] = '';
	$config['config_options_array']['log_verbosity']                = POLLER_VERBOSITY_NONE;

	$result = cacti_snmp_walk('192.0.2.1', 'public', $oid, 2, '', '', '', '', '', '', 161, 500, 0, 10);

	return array(
		'result' => $result,
		'args'   => file_exists($dir . '/args') ? file($dir . '/args', FILE_IGNORE_NEW_LINES) : null,
	);
}

test('a walk with an OID runs the binary with the OID as the last argument', function () {
	$run = _snmp_walk_with_stub('.1.3.6.1.2.1.2.2.1.1');

	expect($run['args'])->not->toBeNull('the stub binary must have run')
		->and(end($run['args']))->toBe('.1.3.6.1.2.1.2.2.1.1');
})->skip(PHP_OS_FAMILY === 'Windows', 'the stub binary is a POSIX shell script');

test('a walk with an empty OID never runs the binary', function (string $oid) {
	$run = _snmp_walk_with_stub($oid);

	expect($run['args'])->toBeNull()
		->and($run['result'])->toBe(array());
})->with(array('empty' => array(''), 'whitespace' => array('  ')))
	->skip(PHP_OS_FAMILY === 'Windows', 'the stub binary is a POSIX shell script');

test('the index count reindex check needs an index OID', function () {
	$src = file_get_contents(dirname(__DIR__, 4) . '/lib/poller.php');

	expect($src)->not->toBeFalse('Failed to read lib/poller.php');

	$start = strpos($src, 'POLLER_ACTION_SNMP_COUNT');
	$guard = strrpos(substr($src, 0, $start), "elseif (!empty(\$data_query_xml['oid_index']))");

	expect($start)->not->toBeFalse()
		->and($guard)->not->toBeFalse('the count row must only be stored when oid_index is set');
});
