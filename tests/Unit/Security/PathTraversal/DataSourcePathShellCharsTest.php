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
 * rrdtool re-tokenizes its arguments and does not decode the quoting that
 * cacti_escapeshellarg() adds, so whitespace or a quote in the file part of a
 * data source path splits one RRD path into several arguments. The file part is
 * refused when a data source is saved, and the generated path is used when a
 * stored value contains such a character. The RRA directory itself may contain
 * spaces.
 */

test('the file part of a data source path may not hold whitespace, quotes or control characters', function (string $path, bool $clean) {
	expect(data_source_path_is_clean($path))->toBe($clean);
})->with([
	'token path'      => ['<path_rra>/1/22.rrd', true],
	'relative name'   => ['host_traffic_22.rrd', true],
	'dashes'          => ['<path_rra>/1/if-in_octets.rrd', true],
	'space'           => ['<path_rra>/1/a b.rrd', false],
	'apostrophe'      => ["<path_rra>/1/a'b.rrd", false],
	'double quote'    => ['<path_rra>/1/a"b.rrd', false],
	'tab'             => ["<path_rra>/1/a\tb.rrd", false],
	'newline'         => ["<path_rra>/1/a\nb.rrd", false],
	'carriage return' => ["<path_rra>/1/a\rb.rrd", false],
	'nul'             => ["<path_rra>/1/a\0b.rrd", false],
	'bare space'      => ['a b.rrd', false],
]);

test('spaces inside the configured RRA directory are not rejected', function () {
	$src = file_get_contents(dirname(__DIR__, 4) . '/lib/functions.php');

	preg_match('/^function data_source_path_is_clean\(.*?^\}/sm', $src, $m);

	expect($m)->not->toBeEmpty();

	$GLOBALS['dsp_test_rra'] = '/var/www/my site/rra';

	eval(str_replace(
		['data_source_path_is_clean(', "defined('CACTI_PATH_RRA')", '(string) CACTI_PATH_RRA'],
		['dsp_test_is_clean(', 'true', "\$GLOBALS['dsp_test_rra']"],
		$m[0]
	));

	expect(dsp_test_is_clean('/var/www/my site/rra/1/22.rrd'))->toBeTrue()
		->and(dsp_test_is_clean('/var/www/my site/rra/1/2 2.rrd'))->toBeFalse()
		->and(dsp_test_is_clean("/var/www/my site/rra/1/2'2.rrd"))->toBeFalse();
});

test('the save validator and the path resolver both apply the check', function () {
	$root = dirname(__DIR__, 4);
	$save = file_get_contents($root . '/data_sources.php');
	$lib  = file_get_contents($root . '/lib/functions.php');

	expect($save)->toContain('!data_source_path_is_clean($value)');

	$body = substr($lib, strpos($lib, 'function get_data_source_path('), 2500);

	expect($body)->toContain('!data_source_path_within_rra($data_source_path) || !data_source_path_is_clean($data_source_path)')
		->and($body)->toContain('generate_data_source_path($local_data_id)');
});
