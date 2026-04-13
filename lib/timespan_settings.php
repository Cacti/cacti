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

// ================= input validation =================
gfrv('predefined_timespan');
gfrv('predefined_timeshift');
gfrv('date1', FILTER_CALLBACK, ['options' => 'sanitize_search_string']);
gfrv('date2', FILTER_CALLBACK, ['options' => 'sanitize_search_string']);
// ====================================================

include_once(CACTI_PATH_LIBRARY . '/time.php');

// initialize the timespan array
$timespan = [];

// set variables for first time use
$reset     = initialize_timespan($timespan);
$timeshift = set_timeshift();

// if the user does not want to see timespan selectors
process_html_variables();
process_user_input($timespan, $timeshift);

// save session variables
finalize_timespan($timespan);

// initialize the timespan selector for first use
function initialize_timespan(array &$timespan) : bool {
	// initialize the default timespan if not set
	if (isrv('button_clear')) {
		reset_timespan_settings();

		$default_timespan  = read_user_setting('default_timespan');
		$default_timeshift = read_user_setting('default_timeshift');

		if (empty($default_timespan)) {
			$default_timespan = 7;
			set_user_setting('predefined_timespan', $default_timespan);
		}

		if (empty($default_timeshift)) {
			$default_timeshift = 7;
			set_user_setting('predefined_timeshift', $default_timeshift);
		}

		$_SESSION['sess_current_timespan']  = $default_timespan;
		$_SESSION['sess_current_timeshift'] = $default_timeshift;

		srv('predefined_timespan', $default_timespan);
		srv('predefined_timeshift', $default_timeshift);

		return true;
	}

	return false;
}

function reset_timespan_settings() : void {
	unset($_SESSION['sess_current_timespan']);
	unset($_SESSION['sess_current_timeshift']);
	unset($_SESSION['sess_current_date1']);
	unset($_SESSION['sess_current_date2']);
	unset($_SESSION['custom']);
	unset($_SESSION['sess_current_timespan_begin_now']);
	unset($_SESSION['sess_current_timespan_end_now']);
}

function process_span_shift(string $type, array &$allvals) : void {
	$default    = read_user_setting("default_$type");
	$predefined = gfrv("predefined_$type");

	if (isrv("predefined_$type")) {
		if (!is_numeric($predefined)) {
			srv("predefined_$type", $default);
		} elseif (!array_key_exists($predefined, $allvals) &&
			$predefined != 0) {
			srv("predefined_$type", $default);
		}
	} elseif (isset($_SESSION["sess_current_$type"])) {
		srv("predefined_$type", $_SESSION["sess_current_$type"]);
	} elseif (!array_key_exists($predefined, $allvals) &&
		$predefined != 0) {
		srv("predefined_$type", $default);
	} else {
		srv("predefined_$type", $default);
	}
	$_SESSION["sess_current_$type"] = grv("predefined_$type");
}

// preformat for timespan selector
function process_html_variables() : void {
	global $graph_timespans, $graph_timeshifts;

	if (!isrv('date1') && !isrv('date2')) {
		process_span_shift('timespan', $graph_timespans);
		process_span_shift('timeshift', $graph_timeshifts);
	}
}

/**
 * when a span time preselection has been defined update the span time fields
 * someone hit a button and not a dropdown
 *
 * @param array $timespan
 * @param int   $timeshift
 *
 * @return void
 */
function process_user_input(array &$timespan, int $timeshift) : void {
	/**
	 * perform cursory time checks to invalidate dates before 1993.  I picked
	 * 1993 as that is the year that my son was born.
	 */
	if ((!isrv('date1') && !isrv('date2')) || isrv('button_clear')) {
		set_preset_timespan($timespan);
	} else {
		$early_date = strtotime(date('1993-01-01'));
		$date1      = gnrv('date1');
		$date2      = gnrv('date2');

		if (!is_numeric($date1)) {
			$date1 = strtotime($date1);
		}

		if (!is_numeric($date2)) {
			$date2 = strtotime($date2);
		}

		$errors = 0;

		if ($date1 < $early_date) {
			raise_message('start_too_early', __('Your Start Date \'%s\' is before January 1993.  Please pick a more recent Start Date.', date('Y-m-d H:i:s', $date1)), MESSAGE_LEVEL_WARN);
			$errors++;
		}

		if ($date2 < $early_date) {
			raise_message('start_too_early', __('Your End Date \'%s\' is before January 1993.  Please pick a more recent End Date.', date('Y-m-d H:i:s', $date2)), MESSAGE_LEVEL_WARN);
			$errors++;
		}

		if ($errors) {
			if (isset($_SESSION['sess_current_date1'])) {
				srv('date1', $_SESSION['sess_current_date1']);
			} else {
				srv('date1', date('Y-m-d H:i:s', time() - 86400));
			}

			if (isset($_SESSION['sess_current_date2'])) {
				srv('date2', $_SESSION['sess_current_date2']);
			} else {
				srv('date2', date('Y-m-d H:i:s', time()));
			}
		}

		// catch the case where the session is not set for some reason
		$custom     = false;
		$sess_date1 = false;
		$sess_date2 = false;

		if (isset($_SESSION['sess_current_date1'])) {
			$sess_date1 = $_SESSION['sess_current_date1'];
		}

		if (isset($_SESSION['sess_current_date2'])) {
			$sess_date2 = $_SESSION['sess_current_date2'];
		}

		if (isrv('date1') && gnrv('date1') != $sess_date1) {
			$custom = true;
		} elseif (isrv('date2') && gnrv('date2') != $sess_date2) {
			$custom = true;
		}

		if ($custom) {
			$timespan['current_value_date1']   = sanitize_search_string(gnrv('date1'));
			$timespan['begin_now']             = strtotime($timespan['current_value_date1']);
			$timespan['current_value_date2']   = sanitize_search_string(gnrv('date2'));
			$timespan['end_now']               = strtotime($timespan['current_value_date2']);

			$_SESSION['sess_current_timespan'] = GT_CUSTOM;
			$_SESSION['custom']                = 1;

			srv('predefined_timespan', GT_CUSTOM);
		} else {
			// the default button wasn't pushed
			$timespan['current_value_date1'] = sanitize_search_string(gnrv('date1'));
			$timespan['current_value_date2'] = sanitize_search_string(gnrv('date2'));
			$timespan['begin_now']           = intval($_SESSION['sess_current_timespan_begin_now']);
			$timespan['end_now']             = intval($_SESSION['sess_current_timespan_end_now']);

			// time shifter: shift left
			if (isrv('move_left_x')) {
				shift_time($timespan, '-', $timeshift);
			}

			// time shifter: shift right
			if (isrv('move_right_x')) {
				shift_time($timespan, '+', $timeshift);
			}

			// custom display refresh
			if (isset($_SESSION['custom'])) {
				$_SESSION['sess_current_timespan'] = GT_CUSTOM;
			} else {
				// refresh the display
				$_SESSION['custom'] = 0;
			}
		}
	}
}

// establish graph timespan from either a user select or the default
function set_preset_timespan(array &$timespan) : void {
	// no current timespan: get default timespan
	if (!isset($_SESSION['sess_current_timespan'])) {
		$_SESSION['sess_current_timespan'] = read_user_setting('default_timespan');
	}

	if (preg_match('/graph/i', get_current_page())) {
		$graph = true;
	} else {
		$graph = false;
	}

	// get config option for first-day-of-the-week
	$first_weekdayid = read_user_setting('first_weekdayid');

	// operate like graphs if graphs is set
	if ($graph) {
		$time = read_config_option('poller_lastrun_1', true);

		if (empty($time)) {
			$time = time();
		}
	} else {
		$time = time();
	}

	// get start/end time-since-epoch for actual time (now()) and given current-session-timespan
	get_timespan($timespan, $time, $_SESSION['sess_current_timespan'], $first_weekdayid);

	$_SESSION['custom'] = 0;
}

function finalize_timespan(array &$timespan) : void {
	if (!isset($timespan['current_value_date1'])) {
		// default end date is now default time span
		$timespan['current_value_date1'] = date('Y-m-d H:i', $timespan['begin_now']);
	}

	if (!isset($timespan['current_value_date2'])) {
		// default end date is now
		$timespan['current_value_date2'] = date('Y-m-d H:i', $timespan['end_now']);
	}

	// correct bad dates on calendar
	if ($timespan['end_now'] < $timespan['begin_now']) {
		set_preset_timespan($timespan);

		$_SESSION['sess_current_timespan'] = read_user_setting('default_timespan');

		$timespan['current_value_date1'] = date('Y-m-d H:i', $timespan['begin_now']);
		$timespan['current_value_date2'] = date('Y-m-d H:i', $timespan['end_now']);
	}

	// if moved to future although not allow by settings, stop at current time
	if (($timespan['end_now'] > time()) && (read_user_setting('allow_graph_dates_in_future') == '')) {
		$timespan['end_now'] = time();
		// convert end time to human readable format
		$timespan['current_value_date2'] = date('Y-m-d H:i', $timespan['end_now']);
	}

	$_SESSION['sess_current_timespan_end_now']   = intval($timespan['end_now']);
	$_SESSION['sess_current_timespan_begin_now'] = intval($timespan['begin_now']);
	$_SESSION['sess_current_date1']              = $timespan['current_value_date1'];
	$_SESSION['sess_current_date2']              = $timespan['current_value_date2'];

	$timespan_sel_pos = strpos(get_browser_query_string(),'&predefined_timespan');

	if ($timespan_sel_pos) {
		$_SESSION['urlval'] = substr(get_browser_query_string(),0,$timespan_sel_pos);
	} else {
		$_SESSION['urlval'] = get_browser_query_string();
	}
}

// establish graph timeshift from either a user select or the default
function set_timeshift() : int {
	global $graph_timeshifts_vals;

	// no current timeshift: get default timeshift
	if (!isset($_SESSION['sess_current_timeshift']) || isrv('button_clear')) {
		$_SESSION['sess_current_timeshift'] = read_user_setting('default_timeshift');
		srv('predefined_timeshift', read_user_setting('default_timeshift'));
		$_SESSION['custom'] = 0;
	}

	if (isset($graph_timeshifts_vals[$_SESSION['sess_current_timeshift']])) {
		return intval($graph_timeshifts_vals[$_SESSION['sess_current_timeshift']]);
	} else {
		return DEFAULT_TIMESHIFT;
	}
}
