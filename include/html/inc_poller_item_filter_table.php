	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_pollercache">
		<td>
			<table cellpadding="0" cellspacing="0">
				<tr>
					<td width="20">
						Host:&nbsp;
					</td>
					<td width="1">
						<select name="cbo_host_id" onChange="window.location=document.form_pollercache.cbo_host_id.options[document.form_pollercache.cbo_host_id.selectedIndex].value">
							<option value="utilities.php?action=view_poller_cache&host_id=-1&poller_action=<?php print $_REQUEST['poller_action'];?>"<?php if ($_REQUEST["host_id"] == "-1") {?> selected<?php }?>>Any</option>
							<option value="utilities.php?action=view_poller_cache&host_id=0&poller_action=<?php print $_REQUEST['poller_action'];?>"<?php if ($_REQUEST["host_id"] == "0") {?> selected<?php }?>>None</option>
							<?php
							$hosts = db_fetch_assoc("select id,description,hostname from host order by description");

							if (sizeof($hosts) > 0) {
							foreach ($hosts as $host) {
								print "<option value='utilities.php?action=view_poller_cache&host_id=" . $host["id"] . "&poller_action=" . $_REQUEST["poller_action"] . "&page=1'"; if ($_REQUEST["host_id"] == $host["id"]) { print " selected"; } print ">" . $host["description"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td width="5"></td>
    				<td width="20">
						Action:&nbsp;
					</td>
					<td width="1">
						<select name="cbo_poller_action" onChange="window.location=document.form_pollercache.cbo_poller_action.options[document.form_pollercache.cbo_poller_action.selectedIndex].value">
							<option value="utilities.php?action=view_poller_cache&poller_action=-1&host_id=<?php print $_REQUEST['host_id'];?>"<?php if ($_REQUEST['poller_action'] == '-1') {?> selected<?php }?>>Any</option>
							<option value="utilities.php?action=view_poller_cache&poller_action=0&host_id=<?php print $_REQUEST['host_id'];?>"<?php if ($_REQUEST['poller_action'] == '0') {?> selected<?php }?>>SNMP</option>
							<option value="utilities.php?action=view_poller_cache&poller_action=1&host_id=<?php print $_REQUEST['host_id'];?>"<?php if ($_REQUEST['poller_action'] == '1') {?> selected<?php }?>>Script</option>
							<option value="utilities.php?action=view_poller_cache&poller_action=2&host_id=<?php print $_REQUEST['host_id'];?>"<?php if ($_REQUEST['poller_action'] == '2') {?> selected<?php }?>>Script Server</option>
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
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		</form>
	</tr>