	<?php print "
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
             calendar.showAtElement(el, \"Br\");        // show the calendar

             return false;
         }

         // This function update the date in the input field when selected
         function selected(cal, date) {
            cal.sel.value = date;      // just update the date in the input field.
            //if (cal.dateClicked) cal.callCloseHandler();  // to immedialely close the calendar after a selection uncomment
         }


         // This function gets called when the end-user clicks on the 'Close' button.
         // It just hides the calendar without destroying it.
         function closeHandler(cal) {
             cal.hide();                        // hide the calendar
             //  cal.destroy();
             calendar = null;
         }

         // This function set the date in the specified field
         function setDateField(id, date) {
           var el = document.getElementById(id);
           el.value = date;
         }";
	?>
   </script>

	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_timespan_selector" method="post">
		<td>
			<table>
				<tr>
					<td class="textHeader">
						Presets:&nbsp;
					</td>
					<td>
						<select name='predefined_timespan' onChange="window.location=document.form_timespan_selector.predefined_timespan.options[document.form_timespan_selector.predefined_timespan.selectedIndex].value">
						<?php
						if (sizeof($graph_timespans) > 0) {
							$value = 0;
							for ($value=0; $value < sizeof($graph_timespans); $value++) {
								print "<option value='" . $_SESSION['sess_graph_view_url_cache'] . "&predefined_timespan=" . $value . "'"; if ($_SESSION["sess_current_timespan"] == $value) { print " selected"; } print ">" . title_trim($graph_timespans[$value], 40) . "</option>\n";
							}
						}
						?>
						</select>
					</td>
					<td class="textHeader">
						<strong>&nbsp;From:&nbsp;</strong>
					</td>
					<td>
						<input type='text' name='date1' id='date1' size='16'>
						&nbsp;<input type='image' src='images/calendar.gif' alt='Start date selector' border='0' align='absmiddle' onclick="return showCalendar('date1');">&nbsp;
					</td>
					<td class="textHeader">
						<strong>&nbsp;To:&nbsp;</strong>
					</td>
					<td>
						<input type='text' name='date2' id='date2' size='16'>
						&nbsp;<input type='image' src='images/calendar.gif' alt='End date selector' border='0' align='absmiddle' onclick="return showCalendar('date2');">
					</td>
					<td>
						&nbsp;<input type='image' src='images/button_refresh.gif' alt='Refresh selected time span' border='0' align='absmiddle' action='submit'>
					</td>
				</tr>
			</table>
		</td>
	</form>
	</tr>