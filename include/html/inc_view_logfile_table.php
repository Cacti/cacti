	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_logfile">
		<td>
			<table cellpadding="0" cellspacing="0">
				<tr>
					<td width="80">
						Tail Lines:&nbsp;
					</td>
					<td width="1">
						<select name="tail_lines" onChange="applyViewLogFilterChange(document.form_logfile)">
							<?php
							foreach($log_tail_lines AS $tail_lines => $display_text) {
								print "<option value='" . $tail_lines . "'"; if ($_REQUEST["tail_lines"] == $tail_lines) { print " selected"; } print ">" . $display_text . "</option>\n";
							}
							?>
						</select>
					</td>
					<td width="5"></td>
    				<td width="100">
						Message Type:&nbsp;
					</td>
					<td width="1">
						<select name="message_type" onChange="applyViewLogFilterChange(document.form_logfile)">
							<option value="-1"<?php if ($_REQUEST['message_type'] == '-1') {?> selected<?php }?>>All</option>
							<option value="1"<?php if ($_REQUEST['message_type'] == '1') {?> selected<?php }?>>Stats</option>
							<option value="2"<?php if ($_REQUEST['message_type'] == '2') {?> selected<?php }?>>Warnings</option>
							<option value="3"<?php if ($_REQUEST['message_type'] == '3') {?> selected<?php }?>>Errors</option>
							<option value="4"<?php if ($_REQUEST['message_type'] == '4') {?> selected<?php }?>>Debug</option>
							<option value="5"<?php if ($_REQUEST['message_type'] == '5') {?> selected<?php }?>>SQL Calls</option>
						</select>
					</td>
					<td>
						&nbsp;<input type="image" src="images/button_go.gif" name="go" alt="Go" border="0" align="absmiddle">
						<input type="image" src="images/button_clear.gif" name="clear" alt="Clear" border="0" align="absmiddle">
						<input type="image" src="images/button_purge.gif" name="purge" alt="Purge" border="0" align="absmiddle">
					</td>
				</tr>
				<tr>
					<td width="80">
						Refresh:&nbsp;
					</td>
					<td width="1">
						<select name="refresh" onChange="applyViewLogFilterChange(document.form_logfile)">
							<?php
							foreach($page_refresh_interval AS $seconds => $display_text) {
								print "<option value='" . $seconds . "'"; if ($_REQUEST["refresh"] == $seconds) { print " selected"; } print ">" . $display_text . "</option>\n";
							}
							?>
						</select>
					</td>
					<td width="5"></td>
					<td width="100">
						Display Order:&nbsp;
					</td>
					<td width="1">
						<select name="reverse" onChange="applyViewLogFilterChange(document.form_logfile)">
							<option value="1"<?php if ($_REQUEST['reverse'] == '1') {?> selected<?php }?>>Newest First</option>
							<option value="2"<?php if ($_REQUEST['reverse'] == '2') {?> selected<?php }?>>Oldest First</option>
						</select>
					</td>
				</tr>
			</table>
			<table cellpadding="0" cellspacing="0">
				<tr>
					<td width="80">
						Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="40" value="<?php print $_REQUEST["filter"];?>">
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		<input type='hidden' name='action' value='view_logfile'>
		</form>
	</tr>