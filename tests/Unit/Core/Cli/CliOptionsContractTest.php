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

require_once dirname(__DIR__, 4) . '/cli/lib/cli_options.php';

test('required value and flag options use their type-specific missing sentinel', function () {
	expect(cacti_cli_option_missing(array('value' => 'id'), ''))->toBeTrue()
		->and(cacti_cli_option_missing(array('value' => 'id'), '42'))->toBeFalse()
		->and(cacti_cli_option_missing(array(), false))->toBeTrue()
		->and(cacti_cli_option_missing(array(), true))->toBeFalse();
});

test('help columns account for rendered value placeholders', function () {
	$options = array(
		'short' => array('help' => 'short help'),
		'long'  => array('value' => 'descriptive-placeholder', 'help' => 'long help'),
	);
	$width = max(array_map('strlen', array(
		cacti_cli_option_flag('short', $options['short']),
		cacti_cli_option_flag('long', $options['long']),
	)));

	ob_start();
	cacti_cli_help_options($options, $width);
	$output = ob_get_clean();
	$lines  = explode(PHP_EOL, rtrim($output));

	expect(strpos($lines[0], 'short help'))->toBe(strpos($lines[1], 'long help'));
});
