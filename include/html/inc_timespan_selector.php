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
					<td width="40" class="textHeader">
						Presets:&nbsp;
					</td>
					<td width="100">
						<select name='predefined_timespan' onChange="window.location=document.form_timespan_selector.predefined_timespan.options[document.form_timespan_selector.predefined_timespan.selectedIndex].value">
						<?php
						if ($_SESSION["custom"]) {
							$graph_timespans[GT_CUSTOM] = "Custom";
							$start_val = 0;
						} else {
							$start_val = 1;
						}

						if (sizeof($graph_timespans) > 0) {
							for ($value=$start_val; $value < sizeof($graph_timespans); $value++) {
								print "<option value='" . $_SESSION["urlval"] . "&predefined_timespan=" . $value . "'"; if ($_SESSION["sess_current_timespan"] == $value) { print " selected"; } print ">" . title_trim($graph_timespans[$value], 40) . "</option>\n";
							}
						}
						?>
						</select>
					</td>
					<td width="55" class="textHeader">
						<strong>&nbsp;From:&nbsp;</strong>
					</td>
					<td width="140" nowrap>
						<input type='text' name='date1' id='date1' size='14' value='<?php print (isset($_SESSION["sess_current_date1"]) ? $_SESSION["sess_current_date1"] : "");?>'>
						&nbsp;<input type='image' src='images/calendar.gif' alt='Start date selector' border='0' align='absmiddle' onclick="return showCalendar('date1');">&nbsp;
					</td>
					<td width="30" class="textHeader">
						<strong>To:&nbsp;</strong>
					</td>
					<td width="140" nowrap>
						<input type='text' name='date2' id='date2' size='14' value='<?php print (isset($_SESSION["sess_current_date2"]) ? $_SESSION["sess_current_date2"] : "");?>'>
						&nbsp;<input type='image' src='images/calendar.gif' alt='End date selector' border='0' align='absmiddle' onclick="return showCalendar('date2');">
					</td>
					<td width="80" nowrap>
						<input type='image' name='button_refresh' src='images/button_refresh.gif' alt='Refresh selected time span' border='0' align='absmiddle' action='submit' value='refresh'>
					</td>
					<td nowrap>
						<input type='image' name='button_clear' src='images/button_clear.gif' alt='Return to the default time span' border='0' align='absmiddle' action='submit'>
					</td>
				</tr>
			</table>
		</td>
		</form>
	</tr>