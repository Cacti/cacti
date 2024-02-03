<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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

function input_validate_input_equals($value, $c_value, $variable = '') {
	if ($value != $c_value) {
		die_html_input_error($variable, $value);
	}
}

function input_validate_input_number($value, $variable = '') {
	if ((!is_numeric($value)) && ($value != '')) {
		die_html_input_error($variable, $value);
	}
}

function input_validate_input_regex($value, $regex, $variable = '') {
	if ($value != null && $value != '' && (!preg_match('/' . $regex . '/', $value))) {
		die_html_input_error($variable, $value);
	}
}

function html_log_input_error($variable) {
	cacti_debug_backtrace("Input Validation Not Performed for '$variable'");
}

function die_html_input_error($variable = null, $value = null, $message = '') {
	$func = CACTI_CLI ? 'trim' : 'html_escape';

	if ($message == '') {
		$message = __('Validation error for variable %s with a value of %s.  See backtrace below for more details.', $variable, $value);
	}

	$variable = ($variable !== null ? ', Variable:' . $func($variable) : '');
	$value    = ($value !== null ? ', Value:'    . $func($value)    : '');

	$isWeb = CACTI_WEB || isset_request_var('json');
	cacti_debug_backtrace('Validation Error' . $variable . $value, $isWeb);

	if (!$isWeb) {
		print $message . PHP_EOL;

		exit(1);
	}

	if (isset_request_var('json')) {
		print json_encode(
			array(
				'status'       => '500',
				'statusText'   => __('Validation Error'),
				'responseText' => $message
			)
		);
	} else {
		print "<table style='width:100%;text-align:center;'><tr><td>$message</td></tr></table>";
		bottom_footer();
	}

	exit;
}
