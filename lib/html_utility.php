<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004 Ian Berry                                            |
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
 | cacti: a php-based graphing solution                                    |
 +-------------------------------------------------------------------------+
 | Most of this code has been designed, written and is maintained by       |
 | Ian Berry. See about.php for specific developer credit. Any questions   |
 | or comments regarding this code should be directed to:                  |
 | - iberry@raxnet.net                                                     |
 +-------------------------------------------------------------------------+
 | - raXnet - http://www.raxnet.net/                                       |
 +-------------------------------------------------------------------------+
*/

/* inject_form_variables - replaces all variables contained in $form_array with
     their actual values
   @arg $form_array - an array that contains all of the information needed to draw
     the html form. see the arrays contained in include/config_settings.php
     for the extact syntax of this array
   @arg $arg1 - an array that represents the |arg1:| variable (see
     include/config_form.php for more details)
   @arg $arg2 - an array that represents the |arg2:| variable (see
     include/config_form.php for more details)
   @arg $arg3 - an array that represents the |arg3:| variable (see
     include/config_form.php for more details)
   @arg $arg4 - an array that represents the |arg4:| variable (see
     include/config_form.php for more details)
   @returns - $form_array with all available variables substituted with their
     proper values */
function inject_form_variables(&$form_array, $arg1 = array(), $arg2 = array(), $arg3 = array(), $arg4 = array()) {
	$check_fields = array("value", "array", "friendly_name", "description", "sql", "sql_print", "form_id", "items");

	/* loop through each available field */
	while (list($field_name, $field_array) = each($form_array)) {
		/* loop through each sub-field that we are going to check for variables */
		foreach ($check_fields as $field_to_check) {
			if (isset($field_array[$field_to_check]) && (is_array($form_array[$field_name][$field_to_check]))) {
				/* if the field/sub-field combination is an array, resolve it recursively */
				$form_array[$field_name][$field_to_check] = inject_form_variables($form_array[$field_name][$field_to_check], $arg1);
			}elseif (isset($field_array[$field_to_check]) && (!is_array($field_array[$field_to_check])) && (ereg("\|(arg[123]):([a-zA-Z0-9_]*)\|", $field_array[$field_to_check], $matches))) {
				/* an empty field name in the variable means don't treat this as an array */
				if ($matches[2] == "") {
					if (is_array(${$matches[1]})) {
						/* the existing value is already an array, leave it alone */
						$form_array[$field_name][$field_to_check] = ${$matches[1]};
					}else{
						/* the existing value is probably a single variable */
						$form_array[$field_name][$field_to_check] = str_replace($matches[0], ${$matches[1]}, $field_array[$field_to_check]);
					}
				}else{
					/* copy the value down from the array/key specified in the variable */
					$form_array[$field_name][$field_to_check] = str_replace($matches[0], ((isset(${$matches[1]}{$matches[2]})) ? ${$matches[1]}{$matches[2]} : ""), $field_array[$field_to_check]);
				}
			}
		}
	}

	return $form_array;
}

/* form_alternate_row_color - starts an HTML row with an alternating color scheme
   @arg $row_color1 - the first color to use
   @arg $row_color2 - the second color to use
   @arg $row_value - the value of the row which will be used to evaluate which color
     to display for this particular row. must be an integer
   @returns - the background color used for this particular row */
function form_alternate_row_color($row_color1, $row_color2, $row_value) {
	if (($row_value % 2) == 1) {
		$current_color = $row_color1;
	}else{
		$current_color = $row_color2;
	}

	print "<tr bgcolor='#$current_color'>\n";

	return $current_color;
}

/* html_boolean - returns the boolean equivalent of an HTML checkbox value
   @arg $html_boolean - the value of the HTML checkbox
   @returns - true or false based on the value of the HTML checkbox */
function html_boolean($html_boolean) {
	if ($html_boolean == "on") {
		return true;
	}else{
		return false;
	}
}

/* html_boolean_friendly - returns the natural language equivalent of an HTML
     checkbox value
   @arg $html_boolean - the value of the HTML checkbox
   @returns - 'Selected' or 'Not Selected' based on the value of the HTML
     checkbox */
function html_boolean_friendly($html_boolean) {
	if ($html_boolean == "on") {
		return "Selected";
	}else{
		return "Not Selected";
	}
}

/* get_checkbox_style - finds the proper CSS padding to apply based on the
     current client browser in use
   @returns - a CSS style string which should be used with an HTML checkbox
     control */
function get_checkbox_style() {
	if (get_web_browser() == "moz") {
		return "padding: 4px; margin: 4px;";
	}elseif (get_web_browser() == "ie") {
		return "padding: 0px; margin: 0px;";
	}elseif (get_web_browser() == "other") {
		return "padding: 4px; margin: 4px;";
	}
}

/* load_current_session_value - finds the correct value of a variable that is being
     cached as a session variable on an HTML form
   @arg $request_var_name - the array index name for the request variable
   @arg $session_var_name - the array index name for the session variable
   @arg $default_value - the default value to use if values cannot be obtained using
     the session or request array */
function load_current_session_value($request_var_name, $session_var_name, $default_value) {
	if (isset($_REQUEST[$request_var_name])) {
		$_SESSION[$session_var_name] = $_REQUEST[$request_var_name];
	}elseif (isset($_SESSION[$session_var_name])) {
		$_REQUEST[$request_var_name] = $_SESSION[$session_var_name];
	}else{
		$_REQUEST[$request_var_name] = $default_value;
	}
}

/* get_colored_device_status - given a device's status, return the colored text in HTML
     format suitable for display
   @arg $disabled (bool) - true if the device is disabled, false is it is not
   @arg $status - the status type of the device as defined in config_constants.php
   @returns - a string containing html that represents the device's current status */
function get_colored_device_status($disabled, $status) {
	$disabled_color = "a1a1a1";

	$status_colors = array(
		HOST_DOWN => "ff0000",
		HOST_RECOVERING => "ff8f1e",
		HOST_UP => "198e32"
		);

	if ($disabled) {
		return "<span style='color: #$disabled_color'>Disabled</a>";
	}else{
		switch ($status) {
			case HOST_DOWN:
				return "<span style='color: #" . $status_colors[HOST_DOWN] . "'>Down</a>"; break;
			case HOST_RECOVERING:
				return "<span style='color: #" . $status_colors[HOST_RECOVERING] . "'>Recovering</a>"; break;
			case HOST_UP:
				return "<span style='color: #" . $status_colors[HOST_UP] . "'>Up</a>"; break;
			default:
				return "<span style='color: #0000ff'>Unknown</a>"; break;
		}
	}
}

/* get_current_graph_start - determine the correct graph start time selected using
     the timespan selector
   @returns - the number of seconds relative to now where the graph should begin */
function get_current_graph_start() {
	if (isset($_SESSION["sess_current_timespan_begin_now"])) {
		return $_SESSION["sess_current_timespan_begin_now"];
	}else{
		return "-" . DEFAULT_TIMESPAN;
	}
}

/* get_current_graph_end - determine the correct graph end time selected using
     the timespan selector
   @returns - the number of seconds relative to now where the graph should end */
function get_current_graph_end() {
	if (isset($_SESSION["sess_current_timespan_end_now"])) {
		return $_SESSION["sess_current_timespan_end_now"];
	}else{
		return "0";
	}
}

?>