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
 * Runs the Perl helpers ping.pl, unix_processes.pl, unix_users.pl and
 * loadavg_multi.pl against canned command output. Each script resets PATH
 * itself, so a copy is run with only that line pointed at a directory of
 * stand-in commands; the rest of the script is the shipped code.
 */

function unix_helper_run(string $script, array $commands, array $args = []): string {
	$dir = sys_get_temp_dir() . '/cacti-unix-helper-' . getmypid() . '-' . bin2hex(random_bytes(4));
	mkdir($dir . '/bin', 0700, true);

	foreach ($commands as $name => $body) {
		file_put_contents("$dir/bin/$name", "#!/bin/sh\n" . $body);
		chmod("$dir/bin/$name", 0700);
	}

	$source = file_get_contents(dirname(__DIR__, 3) . '/scripts/' . $script);
	$source = preg_replace('/^\$ENV\{PATH\} = .*$/m', '$ENV{PATH} = \'' . $dir . '/bin:/usr/bin:/bin\';', $source, -1, $replaced);
	expect($replaced)->toBe(1);
	file_put_contents("$dir/$script", $source);

	$output = shell_exec('perl ' . escapeshellarg("$dir/$script") . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>/dev/null');

	foreach (glob("$dir/bin/*") as $file) {
		unlink($file);
	}
	unlink("$dir/$script");
	rmdir("$dir/bin");
	rmdir($dir);

	return (string) $output;
}

beforeEach(function () {
	if (PHP_OS_FAMILY === 'Windows' || trim((string) shell_exec('command -v perl')) === '') {
		$this->markTestSkipped('perl and a POSIX shell are required');
	}
});

function unix_helper_ping(string $reply): array {
	return ['ping' => "cat <<'EOF'\nPING 192.0.2.1 (192.0.2.1) 56(84) bytes of data.\n$reply\nEOF\n"];
}

test('ping.pl reports a zero millisecond reply as 0, not U', function () {
	expect(unix_helper_run('ping.pl', unix_helper_ping('64 bytes from 192.0.2.1: icmp_seq=1 ttl=64 time=0 ms'), ['192.0.2.1']))->toBe('0');
	expect(unix_helper_run('ping.pl', unix_helper_ping('64 bytes from 192.0.2.1: icmp_seq=1 ttl=64 time=0.000 ms'), ['192.0.2.1']))->toBe('0.000');
});

test('ping.pl still reports a measured reply and U without one', function () {
	expect(unix_helper_run('ping.pl', unix_helper_ping('64 bytes from 192.0.2.1: icmp_seq=1 ttl=64 time=0.412 ms'), ['192.0.2.1']))->toBe('0.412');
	expect(unix_helper_run('ping.pl', unix_helper_ping('Request timeout for icmp_seq 0'), ['192.0.2.1']))->toBe('U');
});

test('unix_processes.pl leaves out itself and its ps child', function () {
	/* the stand-in ps is the child itself, and its parent is the script */
	$ps = "echo '  PID TTY      STAT   TIME COMMAND'\n"
		. "echo '    1 ?        Ss     0:01 /sbin/init'\n"
		. "echo '  200 ?        S      0:00 sshd: user'\n"
		. "echo \"  \$PPID pts/0    S+     0:00 perl unix_processes.pl\"\n"
		. "echo \"  \$\$ pts/0    R+     0:00 ps ax\"\n";

	expect(unix_helper_run('unix_processes.pl', ['ps' => $ps]))->toBe('2');
});

test('unix_users.pl counts a user by the user column only', function () {
	$who = ['who' => "cat <<'EOF'\n"
		. "alice    pts/0        2026-11-23 10:00 (192.0.2.5)\n"
		. "bob      pts/1        2026-11-23 10:05 (alice-laptop)\n"
		. "alicex   pts/2        2026-11-23 10:06 (192.0.2.6)\n"
		. "EOF\n"];

	expect(unix_helper_run('unix_users.pl', $who, ['alice']))->toBe('1');
	expect(unix_helper_run('unix_users.pl', $who, ['carol']))->toBe('0');
	expect(unix_helper_run('unix_users.pl', $who))->toBe('3');
});

test('loadavg_multi.pl reports U when uptime cannot be parsed', function () {
	expect(unix_helper_run('loadavg_multi.pl', ['uptime' => "echo 'uptime: cannot read load average'\n"]))->toBe('1min:U 5min:U 10min:U');
	expect(unix_helper_run('loadavg_multi.pl', ['uptime' => "echo ' 10:01:02 up 3 days,  2:03,  1 user,  load average: 0.14, 0.13, 0.10'\n"]))->toBe('1min:0.14 5min:0.13 10min:0.10');
});
