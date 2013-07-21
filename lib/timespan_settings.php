<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2013 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/* ================= input validation ================= */
input_validate_input_number(get_request_var_request("predefined_timespan"));
input_validate_input_number(get_request_var_request("predefined_timeshift"));
/* ==================================================== */

/* clean up date1 string */
if (isset($_REQUEST["date1"])) {
	$_REQUEST["date1"] = sanitize_search_string(get_request_var("date1"));
}

/* clean up date2 string */
if (isset($_REQUEST["date2"])) {
	$_REQUEST["date2"] = sanitize_search_string(get_request_var("date2"));
}

include_once($config["base_path"] . "/lib/time.php");

/* initialize the timespan array */
$timespan = array();

/* set variables for first time use */
initialize_timespan($timespan);
$timeshift = set_timeshift();

/* if the user does not want to see timespan selectors */
if (read_graph_config_option("timespan_sel") == "") {
	set_preset_timespan($timespan);
/* the user does want to see them */
}else {
	process_html_variables();
	process_user_input($timespan, $timeshift);
}
/* save session variables */
finalize_timespan($timespan);

/* initialize the timespan selector for first use */
function initialize_timespan(&$timespan) {
	/* initialize the default timespan if not set */
	if ((!isset($_SESSION["sess_current_timespan"])) || (isset($_POST["button_clear_x"]))) {
		$_SESSION["sess_current_timespan"] = read_graph_config_option("default_timespan");
		$_REQUEST["predefined_timespan"] = read_graph_config_option("default_timespan");
		$_SESSION["custom"] = 0;
	}

	/* initialize the date sessions if not set */
	if (!isset($_SESSION["sess_current_date1"])) {
		set_preset_timespan($timespan);
	}
}

/* preformat for timespan selector */
function process_html_variables() {
	if (isset($_REQUEST["predefined_timespan"])) {
		if (!is_numeric($_REQUEST["predefined_timespan"])) {
			if (isset($_SESSION["sess_current_timespan"])) {
				if ($_SESSION["custom"]) {
					$_REQUEST["predefined_timespan"] = GT_CUSTOM;
					$_SESSION["sess_current_timespan"] = GT_CUSTOM;
				}else {
					$_REQUEST["predefined_timespan"] = $_SESSION["sess_current_timespan"];
				}
			}else {
				$_REQUEST["predefined_timespan"] = read_graph_config_option("default_timespan");
				$_SESSION["sess_current_timespan"] = read_graph_config_option("default_timespan");
			}
		}
	} else {
		if (isset($_SESSION["sess_current_timespan"])) {
			$_REQUEST["predefined_timespan"] = $_SESSION["sess_current_timespan"];
		}else {
			$_REQUEST["predefined_timespan"] = read_graph_config_option("default_timespan");
			$_SESSION["sess_current_timespan"] = read_graph_config_option("default_timespan");
		}
	}
	load_current_session_value("predefined_timespan", "sess_current_timespan", read_graph_config_option("default_timespan"));

	# process timeshift
	if (isset($_REQUEST["predefined_timeshift"])) {
		if (!is_numeric($_REQUEST["predefined_timeshift"])) {
			if (isset($_SESSION["sess_current_timeshift"])) {
				$_REQUEST["predefined_timeshift"] = $_SESSION["sess_current_timeshift"];
			}else {
				$_REQUEST["predefined_timeshift"] = read_graph_config_option("default_timeshift");
				$_SESSION["sess_current_timeshift"] = read_graph_config_option("default_timeshift");
			}
		}
	} else {
		if (isset($_SESSION["sess_current_timeshift"])) {
			$_REQUEST["predefined_timeshift"] = $_SESSION["sess_current_timeshift"];
		}else {
			$_REQUEST["predefined_timeshift"] = read_graph_config_option("default_timeshift");
			$_SESSION["sess_current_timeshift"] = read_graph_config_option("default_timeshift");
		}
	}
	load_current_session_value("predefined_timeshift", "sess_current_timeshift", read_graph_config_option("default_timeshift"));
}

/* when a span time preselection has been defined update the span time fields */
/* someone hit a button and not a dropdown */
function process_user_input(&$timespan, $timeshift) {
	if (isset($_POST["date1"])) {
		/* the dates have changed, therefore, I am now custom */
		if (($_SESSION["sess_current_date1"] != $_POST["date1"]) || ($_SESSION["sess_current_date2"] != $_POST["date2"])) {
			$timespan["current_value_date1"] = sanitize_search_string($_POST["date1"]);
			$timespan["begin_now"] =strtotime($timespan["current_value_date1"]);
			$timespan["current_value_date2"] = sanitize_search_string($_POST["date2"]);
			$timespan["end_now"]=strtotime($timespan["current_value_date2"]);
			$_SESSION["sess_current_timespan"] = GT_CUSTOM;
			$_SESSION["custom"] = 1;
			$_POST["predefined_timespan"] = GT_CUSTOM;
		}else {
			/* the default button wasn't pushed */
			if (!isset($_POST["button_clear_x"])) {
				$timespan["current_value_date1"] = sanitize_search_string($_POST["date1"]);
				$timespan["current_value_date2"] = sanitize_search_string($_POST["date2"]);
				$timespan["begin_now"] = $_SESSION["sess_current_timespan_begin_now"];
				$timespan["end_now"] = $_SESSION["sess_current_timespan_end_now"];

				/* time shifter: shift left                                           */
				if (isset($_POST["move_left_x"])) {
					shift_time($timespan, "-", $timeshift);
				}
				/* time shifter: shift right                                          */
				if (isset($_POST["move_right_x"])) {
					shift_time($timespan, "+", $timeshift);
				}

				/* custom display refresh */
				if (isset($_SESSION["custom"])) {
					$_SESSION["sess_current_timespan"] = GT_CUSTOM;
				/* refresh the display */
				}else {
					$_SESSION["custom"] = 0;
				}
			} else {
				/* first time in */
				set_preset_timespan($timespan);
			}
		}
	}else {
		if ((isset($_GET["predefined_timespan"]) && ($_GET["predefined_timespan"] != GT_CUSTOM)) ||
			(!isset($_SESSION["custom"])) ||
			(!isset($_GET["predefined_timespan"]) && ($_SESSION["custom"] == 0)) ||
			(!isset($_SESSION["sess_current_date1"]))) {
			set_preset_timespan($timespan);
		}else {
			$timespan["current_value_date1"] = $_SESSION["sess_current_date1"];
			$timespan["current_value_date2"] = $_SESSION["sess_current_date2"];

			$timespan["begin_now"] = $_SESSION["sess_current_timespan_begin_now"];
			$timespan["end_now"] = $_SESSION["sess_current_timespan_end_now"];
				/* custom display refresh */
			if ($_SESSION["custom"]) {
				$_SESSION["sess_current_timespan"] = GT_CUSTOM;
			}
		}
	}
}

/* establish graph timespan from either a user select or the default */
function set_preset_timespan(&$timespan) {
	# no current timespan: get default timespan
	if ((!isset($_SESSION["sess_current_timespan"])) || (read_graph_config_option("timespan_sel") == "")) {
		$_SESSION["sess_current_timespan"] = read_graph_config_option("default_timespan");
	}

	# get config option for first-day-of-the-week
	$first_weekdayid = read_graph_config_option("first_weekdayid");
	# get start/end time-since-epoch for actual time (now()) and given current-session-timespan
	get_timespan( $timespan, time(),$_SESSION["sess_current_timespan"] , $first_weekdayid);

	$_SESSION["custom"] = 0;
}

function finalize_timespan(&$timespan) {
	if (!isset($timespan["current_value_date1"])) {
		/* Default end date is now default time span */
		$timespan["current_value_date1"] = date("Y-m-d H:i", $timespan["begin_now"]);
	}

	if (!isset($timespan["current_value_date2"])) {
		/* Default end date is now */
		$timespan["current_value_date2"] = date("Y-m-d H:i", $timespan["end_now"]);
	}

	/* correct bad dates on calendar */
	if ($timespan["end_now"] < $timespan["begin_now"]) {
		set_preset_timespan($timespan);
		$_SESSION["sess_current_timespan"] = read_graph_config_option("default_timespan");

		$timespan["current_value_date1"] = date("Y-m-d H:i", $timespan["begin_now"]);
		$timespan["current_value_date2"] = date("Y-m-d H:i", $timespan["end_now"]);
	}

	/* if moved to future although not allow by settings, stop at current time */
	if ( ($timespan["end_now"] > time()) && (read_graph_config_option("allow_graph_dates_in_future") == "") ) {
		$timespan["end_now"] = time();
		# convert end time to human readable format
		$timespan["current_value_date2"] = date("Y-m-d H:i", $timespan["end_now"]);
	}

	$_SESSION["sess_current_timespan_end_now"] = $timespan["end_now"];
	$_SESSION["sess_current_timespan_begin_now"] = $timespan["begin_now"];
	$_SESSION["sess_current_date1"] = $timespan["current_value_date1"];
	$_SESSION["sess_current_date2"] = $timespan["current_value_date2"];

	$timespan_sel_pos = strpos(get_browser_query_string(),"&predefined_timespan");
	if ($timespan_sel_pos) {
		$_SESSION["urlval"] = substr(get_browser_query_string(),0,$timespan_sel_pos);
	}else {
		$_SESSION["urlval"] = get_browser_query_string();
	}
}

/* establish graph timeshift from either a user select or the default */
function set_timeshift() {
	global $config, $graph_timeshifts;

	# no current timeshift: get default timeshift
	if ((!isset($_SESSION["sess_current_timeshift"])) ||
		(read_graph_config_option("timespan_sel") == "") ||
		(isset($_POST["button_clear_x"]))
		) {
		$_SESSION["sess_current_timeshift"] = read_graph_config_option("default_timeshift");
		$_REQUEST["predefined_timeshift"] = read_graph_config_option("default_timeshift");
		$_SESSION["custom"] = 0;
	}

	return $timeshift = $graph_timeshifts[$_SESSION["sess_current_timeshift"]];
}

?>
