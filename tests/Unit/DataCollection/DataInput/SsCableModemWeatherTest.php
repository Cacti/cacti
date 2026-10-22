<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * scripts/ss_3com_cable_modem.php and scripts/ss_weatherbug.php, the PHP
 * ports of 3com_cable_modem.pl and weatherbug.pl. Both poll fixed vendor
 * endpoints, so only the response handling is exercised here.
 */

$called_by_script_server = true;

require_once dirname(__DIR__, 4) . '/scripts/ss_3com_cable_modem.php';
require_once dirname(__DIR__, 4) . '/scripts/ss_weatherbug.php';

test('the 3com page is counted by lines mentioning the provisioning server', function () {
	$page = "<html>\n<td>TFTP Server</td><td>10.18.18.11</td>\n<td>Time Server</td><td>10.18.18.11</td>\n<td>Gateway</td><td>10.18.18.1</td>\n</html>\n";

	expect(ss_3com_cable_modem_count($page))->toBe(2)
		->and(ss_3com_cable_modem_count('10.18.18.11'))->toBe(1)
		->and(ss_3com_cable_modem_count(''))->toBe(0);
});

test('the 3com pattern keeps grep semantics, where a dot matches any character', function () {
	expect(ss_3com_cable_modem_count("10x18x18x11\n"))->toBe(1);
});

test('the weatherbug response keeps only digits, dots, dashes and bars', function () {
	expect(ss_weatherbug_parse('A|12:30 PM|10/22/2026|58.3 F|x|7 mph'))->toBe(['', '1230', '10222026', '58.3', '', '7']);
});

test('the weatherbug response drops trailing empty fields as Perl split does', function () {
	expect(ss_weatherbug_parse('1|2||'))->toBe(['1', '2'])
		->and(ss_weatherbug_parse(''))->toBe([]);
});

test('the weatherbug readings are labelled as the data input method expects', function () {
	$response = '0|1230|10222026|58.3|N|7|NW|12|x|y|29.92|65|61.2|44.1|46.5|57.0';

	expect(ss_weatherbug_format(ss_weatherbug_parse($response)))
		->toBe('current_temp:58.3 wind_speed:7 barometer:29.92 humidity:65 high_temp:61.2 low_temp:44.1 dew_point_temp:46.5 wind_chill_temp:57.0');
});

test('weatherbug fields the response did not carry print empty', function () {
	expect(ss_weatherbug_format(['0', '1', '2', '3']))
		->toBe('current_temp:3 wind_speed: barometer: humidity: high_temp: low_temp: dew_point_temp: wind_chill_temp:');
});

test('an unreachable endpoint reports every weatherbug field as U', function () {
	expect(ss_weatherbug_format(array_fill(0, 16, 'U')))
		->toBe('current_temp:U wind_speed:U barometer:U humidity:U high_temp:U low_temp:U dew_point_temp:U wind_chill_temp:U');
});
