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

function format_timespan(&$param_graph_start, &$param_graph_end) {
	/* Compute the time start and end */
	/* When a span time preselection has been defined update the span time fields */
	if (isset($_POST["predefined_timespan"]) and ($_POST["predefined_timespan"]!="") and ($_POST["predefined_timespan"]!="No Preset"))  {
		$end_now=strtotime("now");
		$end_year = date("Y",$end_now);
		$end_month = date("m",$end_now);
		$end_day = date("d",$end_now);
		$end_hour = date("H",$end_now);
		$end_min = date("i",$end_now);
		$end_sec = 00;

		switch ($_POST["predefined_timespan"])  {
			case 'Default':
				$begin_now = $end_now - read_graph_config_option("timespan");
				break;
			case 'Last Half Hour':
				$begin_now = $end_now - 60*30;
				break;
			case 'Last Hour':
				$begin_now = $end_now - 60*60;
				break;
			case 'Last 2 Hours':
				$begin_now = $end_now - 2*60*60;
				break;
			case 'Last 4 Hours':
				$begin_now = $end_now - 4*60*60;
				break;
			case 'Last 6 Hours':
				$begin_now = $end_now - 6*60*60;
				break;
			case 'Last 12 Hours':
				$begin_now = $end_now - 12*60*60;
				break;
			case 'Last Day':
				$begin_now = $end_now - 24*60*60;
				break;
			case 'Last 2 Days':
				$begin_now = $end_now - 2*24*60*60;
				break;
			case 'Last 3 Days':
				$begin_now = $end_now - 3*24*60*60;
				break;
			case 'Last 4 Days':
				$begin_now = $end_now - 4*24*60*60;
				break;
			case 'Last Week':
				$begin_now = $end_now - 7*24*60*60;
				break;
			case 'Last Week':
				$begin_now = $end_now - 7*24*60*60;
				break;
			case 'Last 2 Weeks':
				$begin_now = $end_now - 2*7*24*60*60;
				break;
			case 'Last Month':
				$begin_now = strtotime("-1 month");
				break;
			case 'Last 2 Months':
				$begin_now = strtotime("-2 months");
				break;
			case 'Last 3 Months':
				$begin_now = strtotime("-3 months");
				break;
			case 'Last 4 Months':
				$begin_now = strtotime("-4 months");
				break;
			case 'Last 6 Months':
				$begin_now = strtotime("-6 months");
				break;
			case 'Last Year':
				$begin_now = strtotime("-1 year");
				break;
			case 'Last 2 Years':
				$begin_now = strtotime("-2 years");
				break;
			default:
				$begin_now = $end_now - read_graph_config_option("timespan");
				break;
		}

		$start_year = date("Y",$begin_now);
		$start_month = date("m",$begin_now);
		$start_day = date("d",$begin_now);
		$start_hour = date("H",$begin_now);
		$start_min = date("i",$begin_now);
		$start_sec = 00;

		$date1=$start_year."-".$start_month ."-".$start_day." ".$start_hour.":".$start_min;
		$date2=$end_year."-".$end_month ."-".$end_day." ".$end_hour.":".$end_min;
	}else {
		$date1= "";
		$date2= "";

		if (isset($_POST["date1"]) and ($_POST["date1"]!="")) {
			$date1 = $_POST["date1"];
		}

		if (isset($_POST["date2"]) and ($_POST["date2"]!="")) {
			$date2 = $_POST["date2"];
		}

		$end_now=strtotime("now");
		$begin_now = $end_now - read_graph_config_option("timespan");
		if ($date2=="") {
			/* Default end date is now */
			$date2=date("Y",$end_now)."-".date("m",$end_now)."-".date("d",$end_now)." ".date("H",$end_now).":".date("i",$end_now);
		}

		if ($date1=="") {
			/* Default end date is now default time span */
			$date1=date("Y",$begin_now)."-".date("m",$begin_now)."-".date("d",$begin_now)." ".date("H",$begin_now).":".date("i",$begin_now);
		}
	}

	/* Compute graph start and end date */
	$param_graph_start=strtotime($date1);
	$param_graph_end=strtotime($date2);

	/* Reverse the 2 dates when defined incorrectly */
	if ($param_graph_end < $param_graph_start) {
		$date1=$param_graph_end;
		$param_graph_end=$param_graph_start;
		$param_graph_start=$date1;
	}

	/* Rebuild date start and end in case of format error above */
	$date1=date("Y",$param_graph_start)."-".date("m",$param_graph_start)."-".date("d",$param_graph_start)." ".date("H",$param_graph_start).":".date("i",$param_graph_start);
	$date2=date("Y",$param_graph_end)."-".date("m",$param_graph_end)."-".date("d",$param_graph_end)." ".date("H",$param_graph_end).":".date("i",$param_graph_end);

	/* Update start and end date field */
	print "
		<script type='text/javascript'>
		setDateField('date1',\"$date1\");
		setDateField('date2',\"$date2\");
		</script>";

	/* $param_graph_start=mktime($start_hour, $start_min, $start_sec, $start_month, $start_day, $start_year); */
	/* $param_graph_end=mktime($end_hour, $end_min, $end_sec, $end_month, $end_day, $end_year); */
}

?>