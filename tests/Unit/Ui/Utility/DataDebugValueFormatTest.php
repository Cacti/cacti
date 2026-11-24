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

/*
 * The data source troubleshooter shows one poller result per line. The
 * values are escaped once, after shortening, so the line breaks are tags
 * rather than escaped text, and the repair box stops waiting once the check
 * has compared the RRDfile or finished.
 */

require_once dirname(__DIR__, 4) . '/lib/dsdebug.php';

test('poller results are shown one per line and escaped once', function () {
	[$value, $title] = dsdebug_format_value("traffic_in = 10\ntraffic_out = <b>&20");

	expect($value)->toBe("traffic_in = 10<br>\ntraffic_out = &lt;b&gt;&amp;20");
	expect($title)->toBe($value);
	expect($value)->not->toContain('&lt;br&gt;');
	expect($value)->not->toContain('&amp;amp;');
});

test('a long value is shortened before it is escaped', function () {
	[$value, $title] = dsdebug_format_value(str_repeat('a', 98) . "&\nb", 100);

	expect($value)->toBe(str_repeat('a', 98) . '&amp;<br>' . "\n");
	expect($title)->toBe(str_repeat('a', 98) . "&amp;<br>\nb");
});

test('a value that is not text is shown as text', function () {
	expect(dsdebug_format_value(1234))->toBe(['1234', '1234']);
	expect(dsdebug_format_value('<not set>'))->toBe(['&lt;not set&gt;', '&lt;not set&gt;']);
});

test('the repair box waits only while the check is running', function () {
	expect(dsdebug_repair_status([]))->toBe('Waiting on Data Source Check to Complete');
	expect(dsdebug_repair_status(['done' => 0, 'info' => ['rrd_exists' => 1]]))->toBe('Waiting on Data Source Check to Complete');
	expect(dsdebug_repair_status(['done' => 0, 'info' => ['rrd_match' => 1]]))->toBe('No Repair Recommendations');
	expect(dsdebug_repair_status(['done' => 1, 'info' => []]))->toBe('No Repair Recommendations');
});
