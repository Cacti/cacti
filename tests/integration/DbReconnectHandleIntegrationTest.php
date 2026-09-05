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

namespace DbReconnectHandleIntegrationTest;

function cacti_sizeof($value) {
	return count($value);
}

function db_fetch_cell($sql, $column = '', $log = true, $connection = false) {
	return $GLOBALS['db_reconnect_connection_is_healthy'] ? 1 : false;
}

function db_close(&$connection = false) {
	return true;
}

function db_connect_real(...$args) {
	return $GLOBALS['db_reconnect_replacement'];
}

$source = file_get_contents(dirname(__DIR__, 2) . '/lib/database.php');

if ($source === false) {
	throw new \RuntimeException('Unable to read lib/database.php for the reconnect integration test.');
}

if (preg_match('/function db_check_reconnect\(.*?^}\R/ms', $source, $matches) !== 1) {
	throw new \RuntimeException('Unable to extract db_check_reconnect() for the reconnect integration test.');
}

eval('namespace DbReconnectHandleIntegrationTest;' . $matches[0]);

beforeEach(function () {
	$old_connection = new \stdClass();

	$GLOBALS['config']                             = array('base_path' => '/nonexistent-cacti-test');
	$GLOBALS['database_hostname']                  = 'database';
	$GLOBALS['database_username']                  = 'cacti';
	$GLOBALS['database_password']                  = 'secret';
	$GLOBALS['database_default']                   = 'cacti';
	$GLOBALS['database_type']                      = 'mysql';
	$GLOBALS['database_port']                      = 3306;
	$GLOBALS['database_retries']                   = 2;
	$GLOBALS['database_ssl']                       = false;
	$GLOBALS['database_ssl_key']                   = '';
	$GLOBALS['database_ssl_cert']                  = '';
	$GLOBALS['database_ssl_ca']                    = '';
	$GLOBALS['db_reconnect_connection_is_healthy'] = false;
	$GLOBALS['db_reconnect_old']                   = $old_connection;
	$GLOBALS['db_reconnect_replacement']           = new \stdClass();
	$GLOBALS['database_details']                   = array(array(
		'database_conn'     => $old_connection,
		'database_hostname' => 'database',
		'database_username' => 'cacti',
		'database_password' => 'secret',
		'database_default'  => 'cacti',
		'database_type'     => 'mysql',
		'database_port'     => 3306,
		'database_retries'  => 2,
		'database_ssl'      => false,
		'database_ssl_key'  => '',
		'database_ssl_cert' => '',
		'database_ssl_ca'   => ''
	));
});

test('a successful reconnect replaces the callers dead connection', function () {
	$caller = $GLOBALS['db_reconnect_old'];
	$result = db_check_reconnect($caller, false);

	expect($result)->toBeTrue()
		->and($caller)->toBe($GLOBALS['db_reconnect_replacement'])
		->and($caller)->not->toBe($GLOBALS['db_reconnect_old']);
});

test('a default connection probe remains in default mode after reconnecting', function () {
	$caller = false;
	$result = db_check_reconnect($caller, false);

	expect($result)->toBeTrue()
		->and($caller)->toBeFalse();
});

test('a healthy caller connection is retained without reconnecting', function () {
	$GLOBALS['db_reconnect_connection_is_healthy'] = true;
	$caller                                         = $GLOBALS['db_reconnect_old'];
	$result                                         = db_check_reconnect($caller, false);

	expect($result)->toBeTrue()
		->and($caller)->toBe($GLOBALS['db_reconnect_old']);
});
