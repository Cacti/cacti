	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_devices">
		<td>
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td nowrap style='white-space: nowrap;' width="50">
						Type:&nbsp;
					</td>
					<td width="1">
						<select name="host_template_id" onChange="applyViewDeviceFilterChange(document.form_devices)">
							<option value="-1"<?php if ($_REQUEST["host_template_id"] == "-1") {?> selected<?php }?>>Any</option>
							<option value="0"<?php if ($_REQUEST["host_template_id"] == "0") {?> selected<?php }?>>None</option>
							<?php
							$host_templates = db_fetch_assoc("select id,name from host_template order by name");

							if (sizeof($host_templates) > 0) {
							foreach ($host_templates as $host_template) {
								print "<option value='" . $host_template["id"] . "'"; if ($_REQUEST["host_template_id"] == $host_template["id"]) { print " selected"; } print ">" . $host_template["name"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Status:&nbsp;
					</td>
					<td width="1">
						<select name="host_status" onChange="applyViewDeviceFilterChange(document.form_devices)">
							<option value="-1"<?php if ($_REQUEST["host_status"] == "-1") {?> selected<?php }?>>Any</option>
							<option value="-3"<?php if ($_REQUEST["host_status"] == "-3") {?> selected<?php }?>>Enabled</option>
							<option value="-2"<?php if ($_REQUEST["host_status"] == "-2") {?> selected<?php }?>>Disabled</option>
							<option value="-4"<?php if ($_REQUEST["host_status"] == "-4") {?> selected<?php }?>>Not Up</option>
							<option value="3"<?php if ($_REQUEST["host_status"] == "3") {?> selected<?php }?>>Up</option>
							<option value="1"<?php if ($_REQUEST["host_status"] == "1") {?> selected<?php }?>>Down</option>
							<option value="2"<?php if ($_REQUEST["host_status"] == "2") {?> selected<?php }?>>Recovering</option>
							<option value="0"<?php if ($_REQUEST["host_status"] == "0") {?> selected<?php }?>>Unknown</option>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Rows:&nbsp;
					</td>
					<td width="1">
						<select name="host_rows" onChange="applyViewDeviceFilterChange(document.form_devices)">
							<option value="-1"<?php if ($_REQUEST["host_rows"] == "-1") {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows) > 0) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if ($_REQUEST["host_rows"] == $key) { print " selected"; } print ">" . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="20">
						&nbsp;Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print $_REQUEST["filter"];?>">
					</td>
					<td nowrap>
						&nbsp;<input type="image" src="images/button_go.gif" alt="Go" border="0" align="absmiddle">
						<input type="image" src="images/button_clear.gif" name="clear" alt="Clear" border="0" align="absmiddle">
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		</form>
	</tr>
