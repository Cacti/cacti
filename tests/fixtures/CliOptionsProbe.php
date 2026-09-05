<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

define('COPYRIGHT_YEARS', 'test');

function get_cacti_cli_version() {
	return 'test';
}

function __($format, ...$arguments) {
	return $arguments ? vsprintf($format, $arguments) : $format;
}

function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}

require dirname(__DIR__, 2) . '/cli/lib/cli_options.php';

$options = [
	'name' => [
		'value'    => 'string',
		'required' => true,
		'help'     => 'Name',
	],
	'force' => [
		'help' => 'Force',
	],
];

$values = cacti_cli_parse($_SERVER['argv'], $options, 'Probe', ['probe --name=value']);
print json_encode($values, JSON_THROW_ON_ERROR);
