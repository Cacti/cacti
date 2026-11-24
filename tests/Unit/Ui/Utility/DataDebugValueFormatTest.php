<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * The data source troubleshooter shows one poller result per line. The
 * values are escaped once, after shortening, so the line breaks are tags
 * rather than escaped text, and the repair box stops waiting once the check
 * has compared the RRDfile or finished.
 */

if (!function_exists('__')) {
	function __() {
		$args   = func_get_args();
		$format = array_shift($args);

		return $args ? vsprintf($format, $args) : $format;
	}
}

require_once dirname(__DIR__, 4) . '/lib/dsdebug.php';

test('poller results are shown one per line and escaped once', function () {
	list($value, $title) = dsdebug_format_value("traffic_in = 10\ntraffic_out = <b>&20");

	expect($value)->toBe("traffic_in = 10<br>\ntraffic_out = &lt;b&gt;&amp;20");
	expect($title)->toBe($value);
	expect($value)->not->toContain('&lt;br&gt;');
	expect($value)->not->toContain('&amp;amp;');
});

test('a long value is shortened before it is escaped', function () {
	list($value, $title) = dsdebug_format_value(str_repeat('a', 98) . "&\nb", 100);

	expect($value)->toBe(str_repeat('a', 98) . '&amp;<br>' . "\n");
	expect($title)->toBe(str_repeat('a', 98) . "&amp;<br>\nb");
});

test('a value that is not text is shown as text', function () {
	expect(dsdebug_format_value(1234))->toBe(array('1234', '1234'));
	expect(dsdebug_format_value('<not set>'))->toBe(array('&lt;not set&gt;', '&lt;not set&gt;'));
});

test('the repair box waits only while the check is running', function () {
	expect(dsdebug_repair_status(array()))->toBe('Waiting on Data Source Check to Complete');
	expect(dsdebug_repair_status(array('done' => 0, 'info' => array('rrd_exists' => 1))))->toBe('Waiting on Data Source Check to Complete');
	expect(dsdebug_repair_status(array('done' => 0, 'info' => array('rrd_match' => 1))))->toBe('No Repair Recommendations');
	expect(dsdebug_repair_status(array('done' => 1, 'info' => array())))->toBe('No Repair Recommendations');
});
