<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * scripts/ss_unix_tcp_connections.php, the PHP port of
 * unix_tcp_connections.pl.
 */

$called_by_script_server = true;

require_once dirname(__DIR__, 4) . '/scripts/ss_unix_tcp_connections.php';

const SS_TCP_NETSTAT = "Active Internet connections (w/o servers)\n"
	. "Proto Recv-Q Send-Q Local Address           Foreign Address         State\n"
	. "tcp        0      0 10.0.0.2:22             10.0.0.5:51234          ESTABLISHED\n"
	. "tcp        0      0 10.0.0.2:80             10.0.0.9:40112          TIME_WAIT\n"
	. "tcp6       0      0 ::1:8080                ::1:43210               ESTABLISHED\n"
	. "udp        0      0 10.0.0.2:68             10.0.0.1:67             ESTABLISHED\n"
	. "Active UNIX domain sockets (w/o servers)\n"
	. "unix  3      [ ]         STREAM     CONNECTED     18231\n";

const SS_TCP_SS = "State      Recv-Q Send-Q Local Address:Port  Peer Address:Port Process\n"
	. "LISTEN     0      128          0.0.0.0:22         0.0.0.0:*\n"
	. "ESTAB      0      0           10.0.0.2:22        10.0.0.5:51234\n"
	. "TIME-WAIT  0      0           10.0.0.2:80        10.0.0.9:40112\n"
	. "SYN-RECV   0      0           10.0.0.2:80        10.0.0.7:40999\n"
	. "ESTAB      0      0              [::1]:8080         [::1]:43210\n";

test('netstat output counts tcp and tcp6 lines only', function () {
	expect(ss_unix_tcp_connections_count(SS_TCP_NETSTAT, 'netstat', ''))->toBe(3)
		->and(ss_unix_tcp_connections_count(SS_TCP_NETSTAT, 'netstat', 'ESTABLISHED'))->toBe(2)
		->and(ss_unix_tcp_connections_count(SS_TCP_NETSTAT, 'netstat', ':80'))->toBe(2);
});

test('ss output leaves out the header and listeners', function () {
	expect(ss_unix_tcp_connections_count(SS_TCP_SS, 'ss', ''))->toBe(4);
});

test('ss states are rewritten to the netstat names before filtering', function () {
	expect(ss_unix_tcp_connections_count(SS_TCP_SS, 'ss', 'ESTABLISHED'))->toBe(2)
		->and(ss_unix_tcp_connections_count(SS_TCP_SS, 'ss', 'TIME_WAIT'))->toBe(1)
		->and(ss_unix_tcp_connections_count(SS_TCP_SS, 'ss', 'SYN_RECV'))->toBe(1)
		->and(ss_unix_tcp_connections_count(SS_TCP_SS, 'ss', 'LISTEN'))->toBe(0);
});

test('filters match literally, so a dot is not a wildcard', function () {
	expect(ss_unix_tcp_connections_count(SS_TCP_NETSTAT, 'netstat', '10.0.0.2:22'))->toBe(1)
		->and(ss_unix_tcp_connections_count(SS_TCP_NETSTAT, 'netstat', '10x0x0x2'))->toBe(0);
});

test('the state map covers every hyphenated ss state', function () {
	expect(ss_unix_tcp_connections_states())->toMatchArray([
		'ESTAB'      => 'ESTABLISHED',
		'FIN-WAIT-1' => 'FIN_WAIT1',
		'CLOSE-WAIT' => 'CLOSE_WAIT'
	]);
});

test('rejected filters return U instead of an unfiltered count', function (string $filter) {
	expect(ss_unix_tcp_connections_collect($filter))->toBe(['U', 1])
		->and(ss_unix_tcp_connections($filter))->toBe('U');
})->with(['a b', 'ESTAB;id', '$(id)', ':80|:443', 'x/y']);

test('the running host is counted with netstat or ss', function (string $filter) {
	[$count, $status] = ss_unix_tcp_connections_collect($filter);

	if (poller_script_find('netstat', ['/bin', '/usr/bin', '/usr/sbin']) === null
		&& poller_script_find('ss', ['/bin', '/usr/bin', '/usr/sbin']) === null) {
		expect([$count, $status])->toBe(['U', 1]);
	} else {
		expect($status)->toBe(0)->and($count)->toMatch('/^\d+$/');
	}
})->with(['', 'ESTAB', '10.0.0.1:22']);
