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
 * path_php_binary and path_rrdtool are administrator settings that are placed in
 * front of a command line handed to a shell, so they go through
 * cacti_escapeshellcmd(). The CLI helpers escape every operand the same way.
 */

$root = dirname(__DIR__, 4);

test('setting based binaries are escaped before they reach a shell', function (string $file, string $needle) use ($root) {
	expect(file_get_contents($root . '/' . $file))->toContain($needle);
})->with([
	'cmd_realtime'    => ['cmd_realtime.php', "proc_open(cacti_escapeshellcmd((string) read_config_option('path_php_binary'))"],
	'remote_agent'    => ['remote_agent.php', "proc_open(cacti_escapeshellcmd((string) read_config_option('path_php_binary'))"],
	'cmd'             => ['cmd.php', "\$php_path = cacti_escapeshellcmd((string) read_config_option('path_php_binary'));"],
	'poller'          => ['lib/poller.php', "\$command = cacti_escapeshellcmd((string) read_config_option('path_php_binary')) . ' ' . \$command;"],
	'dsstats'         => ['lib/dsstats.php', "\$command = cacti_escapeshellcmd((string) read_config_option('path_rrdtool')) . ' - ';"],
	'rrdcheck tune'   => ['lib/rrdcheck.php', "\$command = cacti_escapeshellcmd((string) read_config_option('path_rrdtool')) . ' tune '"],
	'rrdcheck pipe'   => ['lib/rrdcheck.php', "\$command = cacti_escapeshellcmd((string) read_config_option('path_rrdtool')) . ' - ';"],
]);

test('no listed site still concatenates a raw path setting into a command', function (string $file, string $pattern) use ($root) {
	expect(file_get_contents($root . '/' . $file))->not->toMatch($pattern);
})->with([
	'cmd_realtime' => ['cmd_realtime.php', "/proc_open\(read_config_option\('path_php_binary'\)/"],
	'remote_agent' => ['remote_agent.php', "/proc_open\(read_config_option\('path_php_binary'\)/"],
	'cmd'          => ['cmd.php', '/\\$php_path = read_config_option/'],
	'poller'       => ['lib/poller.php', "/\\\$command = read_config_option\('path_php_binary'\)/"],
	'dsstats'      => ['lib/dsstats.php', "/\\\$command = read_config_option\('path_rrdtool'\)/"],
	'rrdcheck'     => ['lib/rrdcheck.php', "/\\\$command = read_config_option\('path_rrdtool'\)/"],
]);

test('the CLI helpers escape the binary and every operand', function () use ($root) {
	$splice = file_get_contents($root . '/cli/splice_rrd.php');
	$resize = file_get_contents($root . '/cli/rrdresize.php');
	$push   = file_get_contents($root . '/cli/push_out_hosts.php');
	$audit  = file_get_contents($root . '/cli/audit_database.php');

	expect($splice)->toContain("cacti_escapeshellcmd(\$rrdtool) . ' restore -f -r ' . cacti_escapeshellarg(\$xmlfile) . ' ' . cacti_escapeshellarg(\$rrdfile)")
		->and($splice)->not->toContain('"$rrdtool restore -f -r $xmlfile $rrdfile"')
		->and($resize)->toContain("proc_open(cacti_escapeshellcmd(\$path_rrdtool) . ' -'")
		->and($push)->toContain("array_map('cacti_escapeshellarg', \$args)")
		->and($push)->not->toContain("passthru(\$php_binary . ' '")
		->and($audit)->toContain("cacti_escapeshellarg(CACTI_PATH_PLUGINS . '/' . \$pname . '/database_upgrade.php')")
		->and($audit)->toContain("' --type=large --force-ver=' . cacti_escapeshellarg((string) \$old)")
		->and($audit)->not->toContain("exec('php ' . CACTI_PATH_PLUGINS");
});
