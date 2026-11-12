<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
*/

namespace SsFpingLostProbeFixture;

/*
 * ss_fping() runs against a scripted Net_Ping.  With the default settings a
 * failed probe keeps adding its elapsed time to the samples; with
 * ping_rtt_exclude_lost on it only counts toward loss.
 */

require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

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

cacti_fixture_source_function(dirname(__DIR__, 3) . '/scripts/ss_fping.php', 'ss_fping', __NAMESPACE__);

function sweep(array $replies, string $exclude_lost) : string {
	$GLOBALS['ss_fping_replies']  = $replies;
	$GLOBALS['ss_fping_settings'] = ['ping_rtt_exclude_lost' => $exclude_lost, 'script_timeout' => 25];

	return ss_fping('127.0.0.1', cacti_count($replies), 'ICMP');
}

test('a lost probe still adds a sample by default', function () {
	expect(sweep([10, null, 30, 20], ''))->toBe('min:10.0000 avg:20.0000 max:30.0000 dev:12.2474 loss:25.0000');
});

test('with the setting on a lost probe only counts toward loss', function () {
	expect(sweep([10, null, 30, 20], 'on'))->toBe('min:10.0000 avg:20.0000 max:30.0000 dev:8.1650 loss:25.0000');
});

test('every probe lost reports unknown times in both modes', function () {
	expect(sweep([null, null, null, null], ''))->toBe('min:U avg:U max:U dev:U loss:100.00')
		->and(sweep([null, null, null, null], 'on'))->toBe('min:U avg:U max:U dev:U loss:100.00');
});
