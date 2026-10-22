#!/usr/bin/env php
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
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

error_reporting(0);

if (!isset($called_by_script_server)) {
	include_once(dirname(__FILE__) . '/../include/cli_check.php');

	list($output, $status) = ss_weatherbug_collect();

	print $output;

	exit($status);
}

/**
 * Returns current weather readings from the WeatherBug data API, as
 * weatherbug.pl does, for the fixed station the Perl script queried.
 *
 * @return string current_temp:N wind_speed:N ... wind_chill_temp:N
 */
function ss_weatherbug() {
	return ss_weatherbug_collect()[0];
}

/**
 * Fetches the readings and returns them with the exit status the command
 * line entry point uses.
 *
 * @return array{0: string, 1: int}
 */
function ss_weatherbug_collect() {
	$url = 'http://wisapidata.weatherbug.com/WxDataISAPI/WxDataISAPI.dll?Magic=10991&RegNum=3647055&ZipCode=17241&StationID=NWVLL&Units=0&Version=2.7&Fore=1&t=1015084854/';

	$response = cacti_http($url, 10);

	if ($response === false) {
		return array(ss_weatherbug_format(array_fill(0, 16, 'U')), 1);
	}

	return array(ss_weatherbug_format(ss_weatherbug_parse($response)), 0);
}

/**
 * Splits a WeatherBug response into its '|' separated fields.
 *
 * Everything other than digits, '-', '.' and '|' is removed first, and
 * trailing empty fields are dropped, as Perl's split does.
 *
 * @param string $response The response body.
 *
 * @return array<int,string>
 */
function ss_weatherbug_parse($response) {
	$fields = explode('|', (string) preg_replace('/[^-0-9|.]/', '', $response));

	while (count($fields) && end($fields) === '') {
		array_pop($fields);
	}

	return $fields;
}

/**
 * Formats the fields the data input method reads. A field the response did
 * not carry prints empty, as in weatherbug.pl.
 *
 * @param array<int,string> $fields The response fields.
 *
 * @return string
 */
function ss_weatherbug_format($fields) {
	$names = array(
		'current_temp'    => 3,
		'wind_speed'      => 5,
		'barometer'       => 10,
		'humidity'        => 11,
		'high_temp'       => 12,
		'low_temp'        => 13,
		'dew_point_temp'  => 14,
		'wind_chill_temp' => 15
	);

	$output = array();

	foreach ($names as $name => $index) {
		$output[] = $name . ':' . ($fields[$index] ?? '');
	}

	return implode(' ', $output);
}
