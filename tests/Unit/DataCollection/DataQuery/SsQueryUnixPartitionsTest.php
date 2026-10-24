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

/*
 * scripts/ss_query_unix_partitions.php, the PHP port of the
 * query_unix_partitions.pl data query script.
 */

$called_by_script_server = true;

require_once dirname(__DIR__, 4) . '/scripts/ss_query_unix_partitions.php';

const SS_QUERY_UNIX_PARTITIONS_DF = "Filesystem         1024-blocks      Used Available Capacity Mounted on\n"
	. "/dev/hda2             20157744  18553884    579860      97% /var\n"
	. "/dev/mapper/vg.root   41284928  10485760  28672000      27% /\n"
	. "tmpfs                   816344      1308    815036       1% /run\n"
	. "/dev/sdc1             1000000    500000    500000      50% /mnt/with space\n";

test('parse keeps device-backed filesystems with a mount point', function () {
	$rows = ss_query_unix_partitions_parse(SS_QUERY_UNIX_PARTITIONS_DF);

	expect($rows)->toHaveCount(2)
		->and($rows[0])->toBe([
			'device'    => '/dev/hda2',
			'mount'     => '/var',
			'total'     => '20157744',
			'used'      => '18553884',
			'available' => '579860',
			'percent'   => '97'
		])
		->and($rows[1]['device'])->toBe('/dev/mapper/vg.root');
});

test('index, num_indexes and query print the data query lines', function () {
	$rows = ss_query_unix_partitions_parse(SS_QUERY_UNIX_PARTITIONS_DF);

	expect(ss_query_unix_partitions_format($rows, 'index', '', ''))->toBe("/dev/hda2\n/dev/mapper/vg.root\n")
		->and(ss_query_unix_partitions_format($rows, 'num_indexes', '', ''))->toBe("2\n")
		->and(ss_query_unix_partitions_format($rows, 'query', 'mount', ''))->toBe("/dev/hda2:/var\n/dev/mapper/vg.root:/\n")
		->and(ss_query_unix_partitions_format($rows, 'query', 'percent', ''))->toBe("/dev/hda2:97\n/dev/mapper/vg.root:27\n");
});

test('get returns one field of one device', function () {
	$rows = ss_query_unix_partitions_parse(SS_QUERY_UNIX_PARTITIONS_DF);

	expect(ss_query_unix_partitions_format($rows, 'get', 'available', '/dev/hda2'))->toBe('579860')
		->and(ss_query_unix_partitions_format($rows, 'get', 'used', '/dev/mapper/vg.root'))->toBe('10485760')
		->and(ss_query_unix_partitions_format($rows, 'get', 'used', '/dev/sdz9'))->toBe('');
});

test('rejected commands, fields and devices return U', function (string $cmd, string $field, string $device) {
	expect(ss_query_unix_partitions_collect($cmd, $field, $device))->toBe(['U', 1])
		->and(ss_query_unix_partitions($cmd, $field, $device))->toBe('U');
})->with([
	'unknown command'  => ['reindex', '', ''],
	'empty command'    => ['', '', ''],
	'query bad field'  => ['query', 'password', ''],
	'get no field'     => ['get', '', '/dev/hda2'],
	'get bad device'   => ['get', 'used', '/dev/hda2;id'],
	'get empty device' => ['get', 'used', '']
]);

test('the running host answers every data query command', function () {
	[$count, $status] = ss_query_unix_partitions_collect('num_indexes');

	expect($status)->toBe(0)->and($count)->toMatch('/^\d+\n$/');

	[$index, $status] = ss_query_unix_partitions_collect('index');

	expect($status)->toBe(0)->and(substr_count($index, "\n"))->toBe((int) $count);

	[$query] = ss_query_unix_partitions_collect('query', 'device');

	if ((int) $count > 0) {
		$device = strtok($index, "\n");

		expect($query)->toContain($device . ':' . $device)
			->and(ss_query_unix_partitions_collect('get', 'percent', $device)[0])->toMatch('/^\d+$/');
	}

	expect(ss_query_unix_partitions_collect('get', 'used', '/dev/cacti-missing'))->toBe(['U', 1]);
});
