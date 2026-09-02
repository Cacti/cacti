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

/**
 * Shared option handling for the scripts under cli/.
 *
 * Each script declares its options once and this file parses them, renders
 * --help from the same declaration so the two cannot drift, and prints
 * --version. The option contract is unchanged: options are --name or
 * --name=value, -v and -V print the version, -h and -H print the help.
 * @param mixed $title
 */

/**
 * cacti_cli_version - prints the standard version banner.
 *
 * @param string $title The utility name, for example 'Add Device Utility'.
 *
 * @return void
 */
function cacti_cli_version($title) {
	print 'Cacti ' . $title . ', Version ' . get_cacti_cli_version() . ', ' . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * cacti_cli_help - prints the version banner, the usage lines, and the
 * options rendered from the same declaration the parser uses.
 *
 * @param string $title   The utility name.
 * @param array  $usage   Usage lines, printed one per line, without a trailing newline.
 * @param array  $options The option declaration, see cacti_cli_parse().
 * @param array  $extra   Optional lines printed after the options.
 *
 * @return void
 */
function cacti_cli_help($title, $usage, $options, $extra = []) {
	cacti_cli_version($title);

	print PHP_EOL;

	foreach ($usage as $line) {
		print $line . PHP_EOL;
	}

	$required = [];
	$optional = [];

	foreach ($options as $name => $option) {
		if (isset($option['help'])) {
			if (isset($option['required']) && $option['required']) {
				$required[$name] = $option;
			} else {
				$optional[$name] = $option;
			}
		}
	}

	/* the width is computed from the longest name so the columns line up
	 * whatever the script declares */
	$width = 0;

	foreach (array_merge(array_keys($required), array_keys($optional)) as $name) {
		if (strlen($name) > $width) {
			$width = strlen($name);
		}
	}

	if (cacti_sizeof($required)) {
		print PHP_EOL . __('Required:') . PHP_EOL;
		cacti_cli_help_options($required, $width);
	}

	if (cacti_sizeof($optional)) {
		print PHP_EOL . __('Optional:') . PHP_EOL;
		cacti_cli_help_options($optional, $width);
	}

	foreach ($extra as $line) {
		print $line . PHP_EOL;
	}
}

/**
 * cacti_cli_help_options - prints one aligned line per option.
 *
 * @param array $options The options to print.
 * @param int   $width   The column width taken from the longest option name.
 *
 * @return void
 */
function cacti_cli_help_options($options, $width) {
	foreach ($options as $name => $option) {
		$flag = '--' . $name;

		if (isset($option['value']) && $option['value'] != '') {
			$flag .= '=' . $option['value'];
		}

		print '    ' . str_pad($flag, $width + 10) . $option['help'] . PHP_EOL;
	}
}

/**
 * cacti_cli_parse - parses the command line against a declaration.
 *
 * Options are --name or --name=value. A declaration entry may set:
 *
 *   value    the placeholder shown in help; absent or '' means the option
 *            takes no value and is returned as boolean true
 *   default  the value returned when the option is absent
 *   required whether the option must be present
 *   help     the help text; an entry without it is accepted but hidden
 *
 * --help, -h and -H print the help and exit 0. --version, -v and -V print
 * the version and exit 0, matching what the scripts did individually.
 *
 * @param array  $argv    The raw argument vector, including the script name.
 * @param array  $options The option declaration.
 * @param string $title   The utility name, for --version and --help.
 * @param array  $usage   Usage lines for --help.
 * @param array  $extra   Optional trailing help lines.
 *
 * @return array The parsed values, keyed by option name.
 */
function cacti_cli_parse($argv, $options, $title, $usage, $extra = []) {
	$parms  = $argv;
	$values = [];

	array_shift($parms);

	foreach ($options as $name => $option) {
		if (isset($option['default'])) {
			$values[$name] = $option['default'];
		} elseif (isset($option['value']) && $option['value'] != '') {
			$values[$name] = '';
		} else {
			$values[$name] = false;
		}
	}

	foreach ($parms as $parameter) {
		if (strpos($parameter, '=') !== false) {
			[$arg, $value] = explode('=', $parameter, 2);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-h':
			case '-H':
			case '--help':
				cacti_cli_help($title, $usage, $options, $extra);

				exit(0);
			case '-v':
			case '-V':
			case '--version':
				cacti_cli_version($title);

				exit(0);
		}

		$name = ltrim($arg, '-');

		if (!isset($options[$name])) {
			print __('ERROR: Invalid Argument: (%s)', $arg) . PHP_EOL . PHP_EOL;

			cacti_cli_help($title, $usage, $options, $extra);

			exit(1);
		}

		if (isset($options[$name]['value']) && $options[$name]['value'] != '') {
			$values[$name] = trim($value);
		} else {
			$values[$name] = true;
		}
	}

	foreach ($options as $name => $option) {
		if (isset($option['required']) && $option['required'] && $values[$name] === '') {
			print __('ERROR: Missing Required Argument: (--%s)', $name) . PHP_EOL . PHP_EOL;

			cacti_cli_help($title, $usage, $options, $extra);

			exit(1);
		}
	}

	return $values;
}
