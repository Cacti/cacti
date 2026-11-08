<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Drives the production network save and IP priming paths with a recording
// database double, so the range limit and insert failure handling are checked
// without a MEMORY table.
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 3) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/lib/CactiValidator.php';

define('CACTI_WEB', true);
define('POLLER_VERBOSITY_NONE', 1);
define('POLLER_VERBOSITY_MEDIUM', 3);
define('MESSAGE_LEVEL_ERROR', 3);
define('SESS_FIELD_VALUES', 'sess_field_values');
define('SESS_ERROR_FIELDS', 'sess_error_fields');
$_SESSION = [];

$GLOBALS['probe'] = ['config' => [], 'network' => [], 'inserts' => [], 'fail_insert' => false, 'messages' => [], 'logs' => [], 'saved' => []];

/**
 * Settings double for read_config_option().
 *
 * @param string $name The setting name.
 *
 * @return mixed The configured value, or '' when unset.
 */
function read_config_option($name) {
	return $GLOBALS['probe']['config'][$name] ?? '';
}

/**
 * Database double answering the automation_networks column lookups.
 *
 * @param string $sql    The query.
 * @param array  $params The bound parameters.
 *
 * @return mixed The column value for the probed network.
 */
function db_fetch_cell_prepared($sql, $params = []) {
	foreach (['subnet_range', 'ignore_ips'] as $column) {
		if (str_contains($sql, 'SELECT ' . $column)) {
			return $GLOBALS['probe']['network'][$column];
		}
	}

	return '';
}

/**
 * Database double recording automation_ips inserts.
 *
 * @param string $sql    The statement.
 * @param array  $params The bound parameters.
 *
 * @return bool False when the probe simulates a full MEMORY table.
 */
function db_execute_prepared($sql, $params = []) {
	if ($GLOBALS['probe']['fail_insert']) {
		return false;
	}

	if (substr_count($sql, '?') !== count($params)) {
		throw new RuntimeException('placeholder count mismatch');
	}

	for ($i = 0; $i < count($params); $i += 2) {
		$GLOBALS['probe']['inserts'][] = $params[$i];
	}

	return true;
}

/**
 * Logging double.
 *
 * @param string $text The log line.
 *
 * @return void
 */
function cacti_log($text, ...$args) {
	$GLOBALS['probe']['logs'][] = $text;
}

/**
 * Message double recording raised UI messages.
 *
 * @param mixed  $id      The message key.
 * @param string $message The message text.
 *
 * @return void
 */
function raise_message($id, $message = '', ...$args) {
	$GLOBALS['probe']['messages'][] = $message;
}

/**
 * Translation double.
 *
 * @param string $text The format string.
 *
 * @return string The formatted string.
 */
function __esc($text, ...$args) {
	return htmlspecialchars(vsprintf($text, $args), ENT_QUOTES, 'UTF-8');
}

/**
 * Error state double; the probe raises no field errors.
 *
 * @return bool Always false.
 */
function is_error_message() {
	return false;
}

/**
 * Save double recording the row api_networks_save() writes.
 *
 * @param array  $save  The row.
 * @param string $table The table name.
 *
 * @return int The new network id.
 */
function sql_save($save, $table) {
	$GLOBALS['probe']['saved'][] = $save['total_ips'];

	return 7;
}

/**
 * Scheduler double passing the row through unchanged.
 *
 * @param array $save The row.
 * @param array $post The form post.
 *
 * @return array The unchanged row.
 */
function api_scheduler_augment_save($save, $post) {
	return $save;
}

/**
 * Hash double.
 *
 * @param int    $id    The row id.
 * @param string $table The table name.
 *
 * @return string A fixed hash.
 */
function get_hash_automation($id, $table) {
	return 'hash';
}

/**
 * Counting double matching cacti_sizeof() for arrays.
 *
 * @param mixed $value The value to count.
 *
 * @return int The element count, or 0 for non-arrays.
 */
function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

require_once dirname(__DIR__, 3) . '/lib/api_automation.php';

/**
 * Saves a network through api_networks_save() with the given ranges.
 *
 * @param string $ranges The subnet_range field.
 *
 * @return mixed The api_networks_save() result.
 */
function probe_save(string $ranges) {
	$post = array_fill_keys(['id', 'name', 'poller_id', 'site_id', 'ignore_ips', 'dns_servers', 'threads', 'run_limit', 'notification_email', 'notification_fromname', 'notification_fromemail', 'snmp_id', 'ping_method', 'ping_port', 'ping_timeout', 'ping_retries'], '1');

	$post['name']               = 'net';
	$post['notification_email'] = $post['notification_fromemail'] = $post['ignore_ips'] = $post['dns_servers'] = '';
	$post['subnet_range']       = $ranges;

	return api_networks_save($post);
}

/**
 * Primes a network with the given ranges and ignore list.
 *
 * @param string $ranges The subnet_range column.
 * @param string $ignore The ignore_ips column.
 *
 * @return array The prime result and the inserted addresses.
 */
function probe_prime(string $ranges, string $ignore = '') : array {
	$GLOBALS['probe']['network'] = ['subnet_range' => $ranges, 'ignore_ips' => $ignore];
	$GLOBALS['probe']['inserts'] = [];

	return [automation_primeIPAddressTable(7), $GLOBALS['probe']['inserts']];
}

$verdict = [];

// default limit (65536) admits a /16 but refuses a /15 at save time
$verdict['save_16']      = probe_save('10.0.0.0/16');
$verdict['save_15']      = probe_save('10.0.0.0/15');
$verdict['save_message'] = end($GLOBALS['probe']['messages']);

// a configured limit applies to the sum over every range of the network
$GLOBALS['probe']['config'] = ['automation_max_network_ips' => '300'];
$verdict['save_sum']        = probe_save('10.0.0.0/24,10.0.1.0/24');
$verdict['saved']           = $GLOBALS['probe']['saved'];

[$verdict['prime_over'], $over] = probe_prime('10.0.0.0/24,10.0.1.0/24');
$verdict['prime_over_rows']     = count($over);

// ignored addresses are skipped without stalling the walk
[$verdict['prime_ignore'], $rows] = probe_prime('192.168.1.0/29', '192.168.1.2, 192.168.1.5');
$verdict['prime_ignore_rows']     = $rows;

// a /22 crosses the 1000-row batch boundary and still primes every host
$GLOBALS['probe']['config']        = [];
[$verdict['prime_batches'], $rows] = probe_prime('10.2.0.0/22');
$verdict['prime_batch_rows']       = count($rows);
$verdict['prime_batch_unique']     = count(array_unique($rows));

$GLOBALS['probe']['fail_insert'] = true;
[$verdict['prime_fail'], $rows]  = probe_prime('10.3.0.0/24');
$verdict['logs']                 = count(array_filter($GLOBALS['probe']['logs'], fn ($line) => str_starts_with($line, 'ERROR:')));

print json_encode($verdict, JSON_THROW_ON_ERROR) . "\n";
