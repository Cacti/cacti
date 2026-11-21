<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The log path settings are shown in the log viewer, so settings.php refuses
 * markup and control characters in them while accepting the paths installs
 * really use. The viewer encodes the file name in the URLs it builds.
 */

$settings = file_get_contents(dirname(__DIR__, 4) . '/settings.php');

test('the log path settings refuse markup and control characters only', function () use ($settings) {
	expect(preg_match("/elseif \\(preg_match\\('(.+?)', get_nfilter_request_var\\(\\\$field_name\\)\\)\\) \\{/", $settings, $match))->toBe(1);

	$pattern = stripcslashes(str_replace("\\'", "'", $match[1]));

	foreach (array('/var/log/cacti/cacti.log', 'log/cacti.log', '/var/log/cacti app/cacti.log',
		'C:\\Program Files (x86)\\Cacti\\log\\cacti.log', 'C:\\PROGRA~2\\Cacti\\log\\cacti.log',
		'\\\\fileserver\\share$\\cacti\\cacti.log', '/volume1/@appstore/cacti/log/cacti.log', '/var/log/cacti+a,b=c#d.log') as $path) {
		expect(preg_match($pattern, $path))->toBe(0, $path);
	}

	foreach (array("cacti'quoted.log", 'cacti"quoted.log', 'cacti<value>.log', 'cacti`value.log', "\ncacti.log", "cacti\tvalue.log") as $path) {
		expect(preg_match($pattern, $path))->toBe(1, $path);
	}
});

test('the log viewer encodes the file name in the URLs it builds', function () {
	$clog      = file_get_contents(dirname(__DIR__, 4) . '/lib/clog_webapi.php');
	$utilities = file_get_contents(dirname(__DIR__, 4) . '/utilities.php');

	expect($clog)->toContain('filename=" . rawurlencode(basename($logfile)) . "\'')
		->and($clog)->not->toContain('filename=" . basename($logfile) . "\'')
		->and($utilities)->toContain("filename='+encodeURIComponent(\$('#filename').val())");
});
