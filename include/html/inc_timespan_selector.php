<?php
/* set default timespan if there isn't one set */
if ((!isset($_SESSION["sess_current_timespan"])) || (isset($_POST["button_default_x"]))) {
	$_SESSION["sess_current_timespan"] = read_graph_config_option("default_timespan");
}

/* when a span time preselection has been defined update the span time fields */
if ($_SESSION["sess_current_timespan"] != GT_CUSTOM) {
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
}else {
	if (isset($_POST["date1"]) and ($_POST["date1"]!="")) {
		$current_value_date1 = $_POST["date1"];
		$begin_now =strtotime($current_value_date1);
	} else {
		if (isset($_SESSION["sess_current_timespan_begin_now"])) {
			$begin_now = $_SESSION["sess_current_timespan_begin_now"];
		}else {
			$begin_now = $end_now - DEFAULT_TIMESPAN;
		}
	}

	if (isset($_POST["date2"]) && ($_POST["date2"] != "")) {
		$current_value_date2 = $_POST["date2"];
		$end_now=strtotime($current_value_date2);
	} else {
		if (isset($_SESSION["sess_current_timespan_end_now"])) {
			$end_now = $_SESSION["sess_current_timespan_end_now"];
		}else {
			$end_now = time();
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

	/* change session settings */
	$_SESSION["sess_current_timespan"] = GT_CUSTOM;
}

/* correct bad dates on calendar */
if ($end_now < $begin_now) {
	$begin_now = $end_now - DEFAULT_TIMESPAN;
	$end_now = time();

	$current_value_date1 = date("Y", $begin_now) . "-" . date("m", $begin_now) . "-" . date("d", $begin_now) . " " . date("H", $begin_now) . ":".date("i", $begin_now);
	$current_value_date2 = date("Y", $end_now) . "-" . date("m", $end_now) . "-" . date("d", $end_now) . " " . date("H", $end_now) . ":" . date("i", $end_now);
}

$_SESSION["sess_current_timespan_end_now"] = $end_now;
$_SESSION["sess_current_timespan_begin_now"] = $begin_now;
?>

<script type='text/javascript'>
	// Initialize the calendar
	calendar=null;

	// This function displays the calendar associated to the input field 'id'
	function showCalendar(id) {
		var el = document.getElementById(id);
		if (calendar != null) {
			// we already have some calendar created
			calendar.hide();  // so we hide it first.
		} else {
			// first-time call, create the calendar.
			var cal = new Calendar(true, null, selected, closeHandler);
			cal.weekNumbers = false;  // Do not display the week number
			cal.showsTime = true;     // Display the time
			cal.time24 = true;        // Hours have a 24 hours format
			cal.showsOtherMonths = false;    // Just the current month is displayed
			calendar = cal;                  // remember it in the global var
			cal.setRange(1900, 2070);        // min/max year allowed.
			cal.create();
		}

		calendar.setDateFormat('%Y-%m-%d %H:%M');    // set the specified date format
		calendar.parseDate(el.value);                // try to parse the text in field
		calendar.sel = el;                           // inform it what input field we use

		// Display the calendar below the input field
		calendar.showAtElement(el, "Br");        // show the calendar

		return false;
	}

	// This function update the date in the input field when selected
	function selected(cal, date) {
		cal.sel.value = date;      // just update the date in the input field.
	}

	// This function gets called when the end-user clicks on the 'Close' button.
	// It just hides the calendar without destroying it.
	function closeHandler(cal) {
		cal.hide();                        // hide the calendar
		calendar = null;
	}
</script>

	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_timespan_selector" method="post">
		<td>
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td width="80" class="textHeader">
						Presets:&nbsp;
					</td>
					<td width="140">
						<select name='predefined_timespan' onChange="window.location=document.form_timespan_selector.predefined_timespan.options[document.form_timespan_selector.predefined_timespan.selectedIndex].value">
						<?php
						$graph_timespans[GT_CUSTOM] = "Custom";

						if (sizeof($graph_timespans) > 0) {
							$value = 0;
							for ($value=0; $value < sizeof($graph_timespans); $value++) {
								print "<option value='" . $_SESSION['sess_graph_view_url_cache'] . "&predefined_timespan=" . $value . "'"; if ($_SESSION["sess_current_timespan"] == $value) { print " selected"; } print ">" . title_trim($graph_timespans[$value], 40) . "</option>\n";
							}
						}
						?>
						</select>
					</td>
					<td width="60" class="textHeader">
						<strong>From:&nbsp;</strong>
					</td>
					<td width="180">
						<input type='text' name='date1' id='date1' size='16' value='<?php print (isset($current_value_date1) ? $current_value_date1 : "");?>'>
						&nbsp;<input type='image' src='images/calendar.gif' alt='Start date selector' border='0' align='absmiddle' onclick="return showCalendar('date1');">&nbsp;
					</td>
					<td width="40" class="textHeader">
						<strong>To:&nbsp;</strong>
					</td>
					<td width="180">
						<input type='text' name='date2' id='date2' size='16' value='<?php print (isset($current_value_date2) ? $current_value_date2 : "");?>'>
						&nbsp;<input type='image' src='images/calendar.gif' alt='End date selector' border='0' align='absmiddle' onclick="return showCalendar('date2');">
					</td>
					<td width="80">
						<input type='image' src='images/button_refresh.gif' alt='Refresh selected time span' border='0' align='absmiddle'>
					</td>
					<td>
						&nbsp;<input type='image' name='button_default' src='images/button_default.gif' alt='Return to the default time span' border='0' align='absmiddle' action='submit'>
					</td>
				</tr>
			</table>
		</td>
		</form>
	</tr>