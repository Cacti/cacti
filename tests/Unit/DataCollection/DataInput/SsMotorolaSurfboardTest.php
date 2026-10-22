<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * scripts/ss_motorola_surfboard_SB6141.php, the PHP port of
 * motorola_surfboard_SB6141.py. The fixture is a cmSignalData.htm page with
 * channels out of order, a blank reading and short rows.
 */

$called_by_script_server = true;

require_once dirname(__DIR__, 4) . '/scripts/ss_motorola_surfboard_SB6141.php';
require_once dirname(__DIR__, 3) . '/Helpers/DataInputHttpServer.php';

function _sb6141_page() : string {
	return (string) file_get_contents(dirname(__DIR__, 3) . '/fixtures/DataInput/sb6141_cmSignalData.htm');
}

const SS_MOTOROLA_DOWNSTREAM = 'DownstreamPower9:0 DownstreamPower10:-1 DownstreamPower11:1 DownstreamPower12:-2 DownstreamPower13:0 DownstreamPower14:-1 DownstreamPower15:-3 DownstreamPower16:2 '
	. 'DownstreamNoiseRatio9:37 DownstreamNoiseRatio10:38 DownstreamNoiseRatio11:38 DownstreamNoiseRatio12:37 DownstreamNoiseRatio13:38 DownstreamNoiseRatio14:38 DownstreamNoiseRatio15:38 DownstreamNoiseRatio16:U';

test('parse reads the channel ids from each table', function () {
	$data = ss_motorola_surfboard_SB6141_parse(_sb6141_page());

	expect(array_keys($data))->toBe(['Downstream', 'Upstream', 'Stats'])
		->and($data['Downstream']['channels'])->toBe(['14', '9', '10', '11', '12', '13', '15', '16'])
		->and($data['Upstream']['channels'])->toBe(['6', '5', '7', '8']);
});

test('parse takes a label from the text before a nested table', function () {
	$data = ss_motorola_surfboard_SB6141_parse(_sb6141_page());

	expect($data['Downstream'])->toHaveKey('Power Level')
		->and($data['Downstream']['Power Level'][14])->toBe('-1 dBmV');
});

test('downstream power and noise ratio are listed in channel order with units removed', function () {
	expect(ss_motorola_surfboard_SB6141_format(ss_motorola_surfboard_SB6141_parse(_sb6141_page()), 'Downstream'))
		->toBe(SS_MOTOROLA_DOWNSTREAM);
});

test('a channel missing a reading is reported as U', function () {
	expect(ss_motorola_surfboard_SB6141_format(ss_motorola_surfboard_SB6141_parse(_sb6141_page()), 'Upstream'))
		->toBe('UpstreamPower5:43 UpstreamPower6:44 UpstreamPower7:45 UpstreamPower8:U');
});

test('codeword stats are grouped by channel', function () {
	$stats = ss_motorola_surfboard_SB6141_format(ss_motorola_surfboard_SB6141_parse(_sb6141_page()), 'Stats');

	expect($stats)->toStartWith('StatsUnerrored9:1428813672 StatsCorrectable9:7 StatsUncorrectable9:498 StatsUnerrored10:')
		->and($stats)->toEndWith('StatsUnerrored16:1428813833 StatsCorrectable16:21 StatsUncorrectable16:U');
});

test('non-numeric channel ids sort after numeric ones', function () {
	$data = ['Upstream' => ['channels' => ['b', '10', 'a', '2'], 'Power Level' => ['2' => '1 dBmV', 'a' => '3 dBmV']]];

	expect(ss_motorola_surfboard_SB6141_format($data, 'Upstream'))
		->toBe('UpstreamPower2:1 UpstreamPower10:U UpstreamPowera:3 UpstreamPowerb:U');
});

test('a page without the signal tables yields no readings', function () {
	expect(ss_motorola_surfboard_SB6141_parse('<html><body>Login</body></html>'))->toBe([])
		->and(ss_motorola_surfboard_SB6141_parse(''))->toBe([])
		->and(ss_motorola_surfboard_SB6141_format([], 'Stats'))->toBe('');
});

test('strip removes the non-breaking spaces Python strip removes', function () {
	expect(ss_motorola_surfboard_SB6141_strip("\u{a0} 14\u{a0} \t"))->toBe('14')
		->and(ss_motorola_surfboard_SB6141_strip(" bad \xff "))->toBe("bad \xff");
});

test('rejected arguments return U with a message for stderr', function (string $category, string $host) {
	[$output, $status, $error] = ss_motorola_surfboard_SB6141_collect($category, $host);

	expect($output)->toBe('U')
		->and($status)->toBe(1)
		->and($error)->toContain('usage:')
		->and(ss_motorola_surfboard_SB6141($category, $host))->toBe('U');
})->with([
	'unknown category'  => ['Sideways', '192.168.100.1'],
	'missing host'      => ['Stats', ''],
	'host with a path'  => ['Stats', '192.168.100.1/admin'],
	'host with userinfo' => ['Stats', 'user@192.168.100.1'],
	'leading dash'      => ['Stats', '-x']
]);

test('the readings are fetched over HTTP and a failed fetch reports U', function () {
	$server = data_input_http_server_start();

	if ($server === null) {
		$this->markTestSkipped('the PHP built-in web server could not start');
	}

	try {
		expect(ss_motorola_surfboard_SB6141_collect('Downstream', $server['address']))->toBe([SS_MOTOROLA_DOWNSTREAM, 0, '']);
	} finally {
		data_input_http_server_stop($server);
	}

	expect(ss_motorola_surfboard_SB6141_collect('Stats', $server['address']))->toBe(['U', 1, 'Unable to retrieve data from modem!']);
});
