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

/* initialize the timespan array */
$timespan = array();

/* set variables for first time use */
initialize_timespan($timespan);

/* if the user does not want to see timespan selectors */
if (read_graph_config_option("timestamp_sel") == "") {
	set_preset_timespan($timespan);
/* the user does want to see them */
}else {
	process_html_variables();
	process_user_input($timespan);
}
/* save session variables */
finalize_timespan(&$timespan);

/* initialize the timespan selector for first use */
function initialize_timespan(&$timespan) {
	/* initialize the default timespan if not set */
	if ((!isset($_SESSION["sess_current_timespan"])) || (isset($_POST["button_clear_x"]))) {
	   $_SESSION["sess_current_timespan"] = read_graph_config_option("default_timespan");
	   $_SESSION["custom"] = 0;
	}

	/* initialize the date sessions if not set */
	if (!isset($_SESSION["sess_current_date1"])) {
		set_preset_timespan(&$timespan);
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
}

/* when a span time preselection has been defined update the span time fields */
/* someone hit a button and not a dropdown */
function process_user_input(&$timespan) {
	if (isset($_POST["date1"])) {
	   /* the dates have changed, therefore, I am now custom */
	   if (($_SESSION["sess_current_date1"] != $_POST["date1"]) || ($_SESSION["sess_current_date2"] != $_POST["date2"])) {
	      $timespan["current_value_date1"] = $_POST["date1"];
	      $timespan["begin_now"] =strtotime($timespan["current_value_date1"]);
	      $timespan["current_value_date2"] = $_POST["date2"];
	      $timespan["end_now"]=strtotime($timespan["current_value_date2"]);
	      $_SESSION["sess_current_timespan"] = GT_CUSTOM;
	      $_SESSION["custom"] = 1;
	      $_POST["predefined_timespan"] = GT_CUSTOM;
	   }else {
	      /* the default button wasn't pushed */
	      if (!isset($_POST["button_clear_x"])) {
	         $timespan["current_value_date2"] = $_POST["date1"];
	         $timespan["current_value_date2"] = $_POST["date2"];
	         $timespan["begin_now"] = $_SESSION["sess_current_timespan_begin_now"];
	         $timespan["end_now"] = $_SESSION["sess_current_timespan_end_now"];
	         /* custom display refresh */
	         if ($_SESSION["custom"]) {
	            $_SESSION["sess_current_timespan"] = GT_CUSTOM;
	         /* refresh the display */
	         }else {
	            $_SESSION["custom"] = 0;
	         }
	      } else {
	         /* first time in */
				set_preset_timespan(&$timespan);
	      }
	   }
	}else {
	   if ((isset($_GET["predefined_timespan"]) && ($_GET["predefined_timespan"] != GT_CUSTOM)) ||
			(!isset($_GET["predefined_timespan"]) && ($_SESSION["custom"] == 0)) ||
			(!isset($_SESSION["sess_current_date1"]))) {
	      set_preset_timespan(&$timespan);
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
   $timespan["end_now"] = time();
   $end_year = date("Y",$timespan["end_now"]);
   $end_month = date("m",$timespan["end_now"]);
   $end_day = date("d",$timespan["end_now"]);
   $end_hour = date("H",$timespan["end_now"]);
   $end_min = date("i",$timespan["end_now"]);
   $end_sec = 00;

	if ((!isset($_SESSION["sess_current_timespan"])) || (read_graph_config_option("timestamp_sel") == "")) {
		$_SESSION["sess_current_timespan"] = read_graph_config_option("default_timespan");
	}

   switch ($_SESSION["sess_current_timespan"])  {
      case GT_LAST_HALF_HOUR:
         $timespan["begin_now"] = $timespan["end_now"] - 60*30;
         break;
      case GT_LAST_HOUR:
         $timespan["begin_now"] = $timespan["end_now"] - 60*60;
         break;
      case GT_LAST_2_HOURS:
         $timespan["begin_now"] = $timespan["end_now"] - 2*60*60;
         break;
      case GT_LAST_4_HOURS:
         $timespan["begin_now"] = $timespan["end_now"] - 4*60*60;
         break;
      case GT_LAST_6_HOURS:
         $timespan["begin_now"] = $timespan["end_now"] - 6*60*60;
         break;
      case GT_LAST_12_HOURS:
         $timespan["begin_now"] = $timespan["end_now"] - 12*60*60;
         break;
      case GT_LAST_DAY:
         $timespan["begin_now"] = $timespan["end_now"] - 24*60*60;
         break;
      case GT_LAST_2_DAYS:
         $timespan["begin_now"] = $timespan["end_now"] - 2*24*60*60;
         break;
      case GT_LAST_3_DAYS:
         $timespan["begin_now"] = $timespan["end_now"] - 3*24*60*60;
         break;
      case GT_LAST_4_DAYS:
         $timespan["begin_now"] = $timespan["end_now"] - 4*24*60*60;
         break;
      case GT_LAST_WEEK:
         $timespan["begin_now"] = $timespan["end_now"] - 7*24*60*60;
         break;
      case GT_LAST_2_WEEKS:
         $timespan["begin_now"] = $timespan["end_now"] - 2*7*24*60*60;
         break;
      case GT_LAST_MONTH:
         $timespan["begin_now"] = strtotime("-1 month");
         break;
      case GT_LAST_2_MONTHS:
         $timespan["begin_now"] = strtotime("-2 months");
         break;
      case GT_LAST_3_MONTHS:
         $timespan["begin_now"] = strtotime("-3 months");
         break;
      case GT_LAST_4_MONTHS:
         $timespan["begin_now"] = strtotime("-4 months");
         break;
      case GT_LAST_6_MONTHS:
         $timespan["begin_now"] = strtotime("-6 months");
         break;
      case GT_LAST_YEAR:
         $timespan["begin_now"] = strtotime("-1 year");
         break;
      case GT_LAST_2_YEARS:
         $timespan["begin_now"] = strtotime("-2 years");
         break;
      default:
         $timespan["begin_now"] = $timespan["end_now"] - DEFAULT_TIMESPAN;
         break;
   }

   $start_year = date("Y",$timespan["begin_now"]);
   $start_month = date("m",$timespan["begin_now"]);
   $start_day = date("d",$timespan["begin_now"]);
   $start_hour = date("H",$timespan["begin_now"]);
   $start_min = date("i",$timespan["begin_now"]);
   $start_sec = 00;

   $timespan["current_value_date1"] = $start_year . "-" . $start_month . "-" . $start_day . " " . $start_hour . ":" . $start_min;
   $timespan["current_value_date2"] = $end_year . "-" . $end_month . "-".$end_day . " ".$end_hour . ":" . $end_min;

   $_SESSION["custom"] = 0;
}

function finalize_timespan(&$timespan) {
	if (!isset($timespan["current_value_date1"])) {
	   /* Default end date is now default time span */
	   $timespan["current_value_date1"] = date("Y", $timespan["begin_now"]) . "-" . date("m", $timespan["begin_now"]) . "-" . date("d", $timespan["begin_now"]) . " " . date("H", $timespan["begin_now"]) . ":".date("i", $timespan["begin_now"]);
	}

	if (!isset($timespan["current_value_date2"])) {
	   /* Default end date is now */
	   $timespan["current_value_date2"] = date("Y", $timespan["end_now"]) . "-" . date("m", $timespan["end_now"]) . "-" . date("d", $timespan["end_now"]) . " " . date("H", $timespan["end_now"]) . ":" . date("i", $timespan["end_now"]);
	}

	/* correct bad dates on calendar */
	if ($timespan["end_now"] < $timespan["begin_now"]) {
		set_preset_timespan(&$timespan);
	   $_SESSION["sess_current_timespan"] = read_graph_config_option("default_timespan");

	   $timespan["current_value_date1"] = date("Y", $timespan["begin_now"]) . "-" . date("m", $timespan["begin_now"]) . "-" . date("d", $timespan["begin_now"]) . " " . date("H", $timespan["begin_now"]) . ":".date("i", $timespan["begin_now"]);
	   $timespan["current_value_date2"] = date("Y", $timespan["end_now"]) . "-" . date("m", $timespan["end_now"]) . "-" . date("d", $timespan["end_now"]) . " " . date("H", $timespan["end_now"]) . ":" . date("i", $timespan["end_now"]);
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

?>