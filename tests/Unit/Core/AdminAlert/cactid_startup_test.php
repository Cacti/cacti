<?php
// Run from the repository root: php tests/Unit/Core/AdminAlert/cactid_startup_test.php
namespace {
	if (PHP_SAPI !== 'cli') {
		http_response_code(404);
		exit;
	}
	$root = dirname(__DIR__, 4);

	if (($argv[1] ?? '') !== '--case') {
		foreach (array('child', 'parent', 'fork_failure', 'foreground', 'no_fork', 'reconnect_failure') as $mode) {
			foreach (array('on', '') as $enabled) {
				$process = proc_open(array(PHP_BINARY, __FILE__, '--case', $mode, $enabled),
					array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
				if (!is_resource($process)) { throw new RuntimeException('Cannot start isolated case.'); }
				fclose($pipes[0]);
				$output = stream_get_contents($pipes[1]);
				$error = stream_get_contents($pipes[2]);
				fclose($pipes[1]);
				fclose($pipes[2]);
				$status = proc_close($process);
				if ($status !== ($mode === 'fork_failure' ? 1 : 0) || $error !== '' || strpos($output, 'STARTUP CASE PASSED') === false) {
					throw new RuntimeException("$mode/$enabled failed: $output $error");
				}
			}
		}
		echo "12 startup scenarios passed\n";
		exit;
	}

	require $root . '/include/global_constants.php';
	require $root . '/lib/functions.php';
	$mode = $argv[2];
	$enabled = $argv[3];
	$config = array('is_web' => false, 'poller_id' => 7, 'base_path' => $root, 'config_options_array' => array(
		'selective_debug' => '', 'client_timezone_support' => '', 'log_destination' => 0, 'path_cactilog' => '',
	));
	$settings = array('mail' => array('notify_admin' => array('default' => '')));
	$database_hostname = 'fixture';
	$database_port = 3306;
	$database_default = 'fixture';
	$database_sessions = array('fixture:3306:fixture' => new stdClass());
	$events = array();
	$hooks = array();
	$reads = 0;

	function check_startup($condition, $label) {
		if (!$condition) { throw new RuntimeException($label); }
	}
	function db_fetch_row_prepared($sql, $args, $log = true) {
		check_startup(isset($GLOBALS['database_sessions']['fixture:3306:fixture']), 'Query on closed connection');
		check_startup($args === array('notify_admin'), 'Unexpected query: ' . $sql);
		$GLOBALS['reads']++;
		return array('value' => $GLOBALS['enabled']);
	}
	function api_plugin_hook_function($hook, $alert) {
		check_startup($hook === 'admin_alert', 'Generic hook required');
		$GLOBALS['hooks'][] = $alert;
		return array('status' => 'accepted');
	}
	function __($message, ...$args) { return $message; }

	register_shutdown_function(static function () use ($mode, $enabled) {
		$continuing = !in_array($mode, array('parent', 'fork_failure'), true);
		$connected = $continuing && $mode !== 'reconnect_failure';
		$forked = !in_array($mode, array('foreground', 'no_fork'), true);
		$expected = $forked ? array('close', 'fork') : array();
		if ($continuing) { $expected = array_merge($expected, array('reconnect', 'sleep')); }
		check_startup($GLOBALS['events'] === $expected, 'Startup ordering/dispatch differs: ' . json_encode($GLOBALS['events']));
		check_startup($GLOBALS['reads'] === ($connected ? 1 : 0), 'Uncached setting must be queried only by the continuing process');
		check_startup(count($GLOBALS['hooks']) === ($connected && $enabled === 'on' ? 1 : 0), 'Notify switch or single dispatch failed');
		if ($GLOBALS['hooks']) {
			check_startup(strpos($GLOBALS['hooks'][0]['message'], 'PID[' . getmypid() . '] Started') !== false, 'Notification uses continuing process PID');
		}
		echo "STARTUP CASE PASSED\n";
	});
}

namespace CactiDaemonStartupTest {
	// Exercise the actual startup block, with process and connection boundaries isolated.
	function function_exists($name) {
		return $name === 'pcntl_fork' ? $GLOBALS['mode'] !== 'no_fork' : \function_exists($name);
	}
	function db_close() {
		$GLOBALS['events'][] = 'close';
		$GLOBALS['database_sessions']['fixture:3306:fixture'] = null;
		\check_startup(!isset($GLOBALS['config']['config_options_array']['notify_admin']), 'notify_admin must start uncached');
		\check_startup(\read_config_option('notify_admin') === '', 'Disconnected lookup reproduces disabled default');
	}
	function pcntl_fork() {
		$GLOBALS['events'][] = 'fork';
		return $GLOBALS['mode'] === 'fork_failure' ? -1 : ($GLOBALS['mode'] === 'parent' ? 12345 : 0);
	}
	function db_check_reconnect(&$connection, $log) {
		\check_startup($connection === false && $log === false, 'Use native default connection without reconnect noise');
		$GLOBALS['events'][] = 'reconnect';
		if ($GLOBALS['mode'] === 'reconnect_failure') { return false; }
		$GLOBALS['database_sessions']['fixture:3306:fixture'] = new \stdClass();
		return true;
	}
	function sleep($seconds) {
		\check_startup($seconds === 2, 'Startup delay changed');
		$GLOBALS['events'][] = 'sleep';
	}
	$foreground = $GLOBALS['mode'] === 'foreground';
	$source = file_get_contents($GLOBALS['root'] . '/cactid.php');
	$start = strpos($source, "print 'Starting Cacti Daemon ... ';");
	$end = strpos($source, 'while (true) {', $start);
	\check_startup($start !== false && $end !== false, 'Cannot locate production startup block');
	eval('namespace ' . __NAMESPACE__ . ';' . substr($source, $start, $end - $start));
}
