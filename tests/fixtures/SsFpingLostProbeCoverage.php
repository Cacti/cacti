<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Request-coverage probe for the Advanced Ping lost-probe setting in
// scripts/ss_fping.php. The unit test loads ss_fping() into a fixture namespace
// from a temporary file, which the unit coverage report cannot attribute to the
// real script. This probe loads the unchanged function at its original line
// numbers, runs it against a scripted Net_Ping, prints each result for
// SsFpingLostProbeTest to assert, and lets RequestCoverage remap the hits to
// the real file.

namespace SsFpingLostProbeCoverageFixture;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__) . '/Helpers/RequestCoverage.php';
require_once dirname(__DIR__) . '/Helpers/SourceSlice.php';

const AVAIL_PING = 3;

const PING_ICMP = 1;

const PING_UDP = 2;

const PING_TCP = 3;

class Net_Ping {
	public array $host = [];

	public int $retries = 0;

	public int $port = 0;

	public mixed $ping_status = '';

	public function ping(int $avail, int $method, mixed $timeout, int $retries) : bool {
		$reply = array_shift($GLOBALS['ss_fping_replies']);

		if ($reply === null) {
			$this->ping_status = 'down';

			return false;
		}

		$this->ping_status = $reply;

		return true;
	}
}

function read_config_option(string $name, bool $force = false) : mixed {
	return $GLOBALS['ss_fping_settings'][$name] ?? '';
}

function db_fetch_cell_prepared(string $sql, array $params = []) : mixed {
	return 400;
}

function cacti_strtoupper(string $value) : string {
	return strtoupper($value);
}

function cacti_sizeof(mixed $value) : int {
	return is_countable($value) ? count($value) : 0;
}

function cacti_count(mixed $value) : int {
	return is_countable($value) ? count($value) : 0;
}

cacti_fixture_source_function(dirname(__DIR__, 2) . '/scripts/ss_fping.php', 'ss_fping', __NAMESPACE__);

function sweep(array $replies, string $exclude_lost) : string {
	$GLOBALS['ss_fping_replies']  = $replies;
	$GLOBALS['ss_fping_settings'] = ['ping_rtt_exclude_lost' => $exclude_lost, 'script_timeout' => 25];

	return ss_fping('127.0.0.1', cacti_count($replies), 'ICMP');
}

$results = [
	'default'          => sweep([10, null, 30, 20], ''),
	'exclude_lost'     => sweep([10, null, 30, 20], 'on'),
	'all_lost'         => sweep([null, null, null, null], ''),
	'all_lost_exclude' => sweep([null, null, null, null], 'on'),
];

print json_encode($results, JSON_THROW_ON_ERROR) . "\n";
