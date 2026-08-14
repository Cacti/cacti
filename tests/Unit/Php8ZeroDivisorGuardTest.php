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
 * Three divisors reached zero from ordinary data: a colour template with no
 * items, a database account that cannot read max_connections, and an unset
 * poller_interval. PHP 7 warned and carried on. PHP 8 raises
 * DivisionByZeroError, which ends the request instead.
 */

$base = dirname(__DIR__, 2);

test('the sources under test are readable', function () use ($base) {
	foreach (array('/lib/api_aggregate.php', '/lib/utility.php', '/lib/html_reports.php') as $rel) {
		expect(file_get_contents($base . $rel))->toBeString()->not->toBeEmpty();
	}
});

test('PHP 8 makes both division and modulo by zero fatal', function () {
	// the behaviour change these guards exist for
	expect(function () { return 5 % 0; })->toThrow(DivisionByZeroError::class)
		->and(function () { return 5 / 0; })->toThrow(DivisionByZeroError::class);
});

test('the aggregate colour round robin checks the template has colours', function () use ($base) {
	$src = file_get_contents($base . '/lib/api_aggregate.php');

	expect($src)->not->toContain("WHERE color_template_id=' . \$_color_templates[\$i] . '")
		->and($src)->toContain('WHERE color_template_id = ?');

	$guard = strpos($src, 'if ($num_colors > 0) {');
	$use   = strpos($src, '$offset = $_selected_graph_index % $num_colors;');

	expect($guard)->not->toBeFalse()
		->and($use)->not->toBeFalse()
		->and($guard)->toBeLessThan($use);
});

test('the connection recommendation checks max_connections came back', function () use ($base) {
	$src = file_get_contents($base . '/lib/utility.php');

	expect($src)->not->toContain('$recommendation = $remainingMem / $maxConnections;')
		->and($src)->toContain('$maxConnections > 0 ? $remainingMem / $maxConnections : 0');
});

test('the report mail time falls back to the declared poller interval', function () use ($base) {
	$src = file_get_contents($base . '/lib/html_reports.php');

	expect($src)->not->toContain("floor(time() / read_config_option('poller_interval'))")
		->and($src)->toContain('$interval = (int) read_config_option(\'poller_interval\');')
		->and($src)->toContain('$interval = 300;');
});

test('the fallback matches the default the setting itself declares', function () use ($base) {
	/* a fallback that disagreed with global_settings would round report times
	   to a different grid than the poller actually runs on */
	$settings = file_get_contents($base . '/include/global_settings.php');

	expect(preg_match("/'poller_interval' => array\(.*?'default' => (\d+)/s", $settings, $m))->toBe(1)
		->and($m[1])->toBe('300');
});
