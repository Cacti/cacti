<?php
// Isolate storage failure paths from a running Cacti installation.
namespace Cacti\Auth;

require dirname(__DIR__) . '/Helpers/RequestCoverage.php';
require dirname(__DIR__, 2) . '/include/vendor/autoload.php';

function read_config_option(string $name) : int {
	return 10;
}

function db_begin_transaction() : bool {
	return $GLOBALS['mode'] !== 'begin';
}

function db_execute_prepared(string $sql, array $parameters) : bool {
	return false;
}

function db_rollback_transaction() : void {
	$GLOBALS['rolled_back'] = true;
}

function cacti_log(string $message, bool $echo, string $subsystem) : void {
	$GLOBALS['logged'] = $subsystem === 'AUTH';
}

$mode        = $argv[1];
$config      = ['poller_id' => 2, 'connection' => $mode === 'disconnected' ? 'recovery' : 'online'];
$rolled_back = false;
$logged      = false;
$allowed     = LoginThrottle::allow($mode === 'invalid-address' ? 'unknown' : '::ffff:192.0.2.10', 'account');

if ($allowed || $rolled_back !== ($mode === 'write') || $logged !== $rolled_back) {
	throw new \RuntimeException('A login budget must be reserved on the authoritative database.');
}
print 'ok';
