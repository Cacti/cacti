	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_userlog">
		<td>
			<table cellpadding="0" cellspacing="0">
				<tr>
					<td width="20">
						Username:&nbsp;
					</td>
					<td width="1">
						<select name="cbo_username" onChange="window.location=document.form_userlog.cbo_username.options[document.form_userlog.cbo_username.selectedIndex].value">
							<option value="utilities.php?action=view_user_log&username=-1"<?php if ($_REQUEST["username"] == "-1") {?> selected<?php }?>>All</option>
							<?php
							$users = db_fetch_assoc("SELECT DISTINCT username FROM user_log ORDER BY username");

							if (sizeof($users) > 0) {
							foreach ($users as $user) {
								print "<option value='utilities.php?action=view_user_log&username=" . $user["username"] . "&page=1'"; if ($_REQUEST["username"] == $user["username"]) { print " selected"; } print ">" . $user["username"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td width="5"></td>
    				<td width="20">
						Result:&nbsp;
					</td>
					<td width="1">
						<select name="cbo_result" onChange="window.location=document.form_userlog.cbo_result.options[document.form_userlog.cbo_result.selectedIndex].value">
							<option value="utilities.php?action=view_user_log&result=-1"<?php if ($_REQUEST['result'] == '-1') {?> selected<?php }?>>Any</option>
							<option value="utilities.php?action=view_user_log&result=1"<?php if ($_REQUEST['result'] == '1') {?> selected<?php }?>>Success</option>
							<option value="utilities.php?action=view_user_log&result=2"<?php if ($_REQUEST['result'] == '2') {?> selected<?php }?>>Failed</option>
						</select>
					</td>
					<td width="5"></td>
					<td width="20">
						Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print $_REQUEST["filter"];?>">
					</td>
					<td>
						&nbsp;<input type="image" src="images/button_go.gif" name="go" alt="Go" border="0" align="absmiddle">
						<input type="image" src="images/button_clear.gif" name="clear" alt="Clear" border="0" align="absmiddle">
						<input type="image" src="images/button_purge.gif" name="purge" alt="Purge" border="0" align="absmiddle">
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		</form>
	</tr>