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

/* initialize the default timespan if not set */
if ((!isset($_SESSION["sess_current_timespan"])) || (isset($_POST["button_default_x"]))) {
   $_SESSION["sess_current_timespan"] = read_graph_config_option("default_timespan");
   $_SESSION["custom"] = 0;
}

/* initialize the date sessions if not set */
if (!isset($_SESSION["sess_current_date1"])) {
   $end_now = time();
   $begin_now = $end_now - DEFAULT_TIMESPAN;
   $_SESSION["sess_current_date1"] = date("Y", $begin_now) . "-" . date("m", $begin_now) . "-" . date("d", $begin_now) . " " . date("H", $begin_now) . ":".date("i", $begin_now);
   $_SESSION["sess_current_date2"] = date("Y", $end_now) . "-" . date("m", $end_now) . "-" . date("d", $end_now) . " " . date("H", $end_now) . ":" . date("i", $end_now);
   $_SESSION["sess_current_timespan_end_now"] = $end_now;
   $_SESSION["sess_current_timespan_begin_now"] = $begin_now;
}

/* preformat for timespan selector */
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

//	print $_REQUEST["predefined_timespan"];
//	print $_SESSION["custom"];

	load_current_session_value("predefined_timespan", "sess_current_timespan", read_graph_config_option("default_timespan"));

/* when a span time preselection has been defined update the span time fields */
/* someone hit a button and not a dropdown */
if (isset($_POST["date1"])) {
   /* the dates have changed, therefore, I am now custom */
   if (($_SESSION["sess_current_date1"] != $_POST["date1"]) || ($_SESSION["sess_current_date2"] != $_POST["date2"])) {
      $current_value_date1 = $_POST["date1"];
      $begin_now =strtotime($current_value_date1);
      $current_value_date2 = $_POST["date2"];
      $end_now=strtotime($current_value_date2);
      $_SESSION["sess_current_timespan"] = GT_CUSTOM;
      $_SESSION["custom"] = 1;
      $_POST["predefined_timespan"] = GT_CUSTOM;
   }else {
      /* the default button wasn't pushed */
      if (!isset($_POST["button_default_x"])) {
         $current_value_date1 = $_POST["date1"];
         $current_value_date2 = $_POST["date2"];
         $begin_now = $_SESSION["sess_current_timespan_begin_now"];
         $end_now = $_SESSION["sess_current_timespan_end_now"];
         /* custom display refresh */
         if ($_SESSION["custom"]) {
            $_SESSION["sess_current_timespan"] = GT_CUSTOM;
         /* refresh the display */
         }else {
            $_SESSION["custom"] = 0;
         }
      } else {
         /* first time in */
         $end_now = time();
         $begin_now = $end_now - DEFAULT_TIMESPAN;
         $_SESSION["sess_current_timespan"] = read_graph_config_option("default_timespan");
         $_SESSION["custom"] = 0;
      }
   }
}else {
   if (isset($_GET["predefined_timespan"]) &&
      ($_GET["predefined_timespan"] != GT_CUSTOM)) {

      $end_now = time();
      $end_year = date("Y",$end_now);
      $end_month = date("m",$end_now);
      $end_day = date("d",$end_now);
      $end_hour = date("H",$end_now);
      $end_min = date("i",$end_now);
      $end_sec = 00;

      switch ($_SESSION["sess_current_timespan"])  {
         case GT_LAST_HALF_HOUR:
            $begin_now = $end_now - 60*30;
            break;
         case GT_LAST_HOUR:
            $begin_now = $end_now - 60*60;
            break;
         case GT_LAST_2_HOURS:
            $begin_now = $end_now - 2*60*60;
            break;
         case GT_LAST_4_HOURS:
            $begin_now = $end_now - 4*60*60;
            break;
         case GT_LAST_6_HOURS:
            $begin_now = $end_now - 6*60*60;
            break;
         case GT_LAST_12_HOURS:
            $begin_now = $end_now - 12*60*60;
            break;
         case GT_LAST_DAY:
            $begin_now = $end_now - 24*60*60;
            break;
         case GT_LAST_2_DAYS:
            $begin_now = $end_now - 2*24*60*60;
            break;
         case GT_LAST_3_DAYS:
            $begin_now = $end_now - 3*24*60*60;
            break;
         case GT_LAST_4_DAYS:
            $begin_now = $end_now - 4*24*60*60;
            break;
         case GT_LAST_WEEK:
            $begin_now = $end_now - 7*24*60*60;
            break;
         case GT_LAST_2_WEEKS:
            $begin_now = $end_now - 2*7*24*60*60;
            break;
         case GT_LAST_MONTH:
            $begin_now = strtotime("-1 month");
            break;
         case GT_LAST_2_MONTHS:
            $begin_now = strtotime("-2 months");
            break;
         case GT_LAST_3_MONTHS:
            $begin_now = strtotime("-3 months");
            break;
         case GT_LAST_4_MONTHS:
            $begin_now = strtotime("-4 months");
            break;
         case GT_LAST_6_MONTHS:
            $begin_now = strtotime("-6 months");
            break;
         case GT_LAST_YEAR:
            $begin_now = strtotime("-1 year");
            break;
         case GT_LAST_2_YEARS:
            $begin_now = strtotime("-2 years");
            break;
         default:
            $begin_now = $end_now - DEFAULT_TIMESPAN;
            break;
      }

      $start_year = date("Y",$begin_now);
      $start_month = date("m",$begin_now);
      $start_day = date("d",$begin_now);
      $start_hour = date("H",$begin_now);
      $start_min = date("i",$begin_now);
      $start_sec = 00;

      $current_value_date1 = $start_year . "-" . $start_month . "-" . $start_day . " " . $start_hour . ":" . $start_min;
      $current_value_date2 = $end_year . "-" . $end_month . "-".$end_day . " ".$end_hour . ":" . $end_min;

      $_SESSION["sess_current_timespan"] = $_GET["predefined_timespan"];
      $_SESSION["custom"] = 0;
   }else {
      $current_value_date1 = $_SESSION["sess_current_date1"];
      $current_value_date2 = $_SESSION["sess_current_date2"];

      $begin_now = $_SESSION["sess_current_timespan_begin_now"];
      $end_now = $_SESSION["sess_current_timespan_end_now"];
         /* custom display refresh */
      if ($_SESSION["custom"]) {
         $_SESSION["sess_current_timespan"] = GT_CUSTOM;
      }
   }
}

if (!isset($current_value_date1)) {
   /* Default end date is now default time span */
   $current_value_date1 = date("Y", $begin_now) . "-" . date("m", $begin_now) . "-" . date("d", $begin_now) . " " . date("H", $begin_now) . ":".date("i", $begin_now);
}

if (!isset($current_value_date2)) {
   /* Default end date is now */
   $current_value_date2 = date("Y", $end_now) . "-" . date("m", $end_now) . "-" . date("d", $end_now) . " " . date("H", $end_now) . ":" . date("i", $end_now);
}

/* correct bad dates on calendar */
if ($end_now < $begin_now) {
   $end_now = time();
   $begin_now = $end_now - DEFAULT_TIMESPAN;
   $_SESSION["sess_current_timespan"] = read_graph_config_option("default_timespan");

   $current_value_date1 = date("Y", $begin_now) . "-" . date("m", $begin_now) . "-" . date("d", $begin_now) . " " . date("H", $begin_now) . ":".date("i", $begin_now);
   $current_value_date2 = date("Y", $end_now) . "-" . date("m", $end_now) . "-" . date("d", $end_now) . " " . date("H", $end_now) . ":" . date("i", $end_now);
}

$_SESSION["sess_current_timespan_end_now"] = $end_now;
$_SESSION["sess_current_timespan_begin_now"] = $begin_now;
$_SESSION["sess_current_date1"] = $current_value_date1;
$_SESSION["sess_current_date2"] = $current_value_date2;

$timespan_sel_pos = strpos(get_browser_query_string(),"&predefined_timespan");
if ($timespan_sel_pos) {
	$_SESSION["urlval"] = substr(get_browser_query_string(),0,$timespan_sel_pos);
}else {
	$_SESSION["urlval"] = get_browser_query_string();
}

?>