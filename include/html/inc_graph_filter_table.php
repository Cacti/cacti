	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_graph_id">
		<td>
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td width="100">
						Filter by host:&nbsp;
					</td>
					<td width="1">
						<select name="cbo_graph_id" onChange="window.location=document.form_graph_id.cbo_graph_id.options[document.form_graph_id.cbo_graph_id.selectedIndex].value">
							<option value="graphs.php?host_id=-1&filter=<?php print $_REQUEST["filter"];?>"<?php if ($_REQUEST["host_id"] == "-1") {?> selected<?php }?>>Any</option>
							<option value="graphs.php?host_id=0&filter=<?php print $_REQUEST["filter"];?>"<?php if ($_REQUEST["host_id"] == "0") {?> selected<?php }?>>None</option>
							<?php
							$hosts = db_fetch_assoc("select id,CONCAT_WS('',description,' (',hostname,')') as name from host order by description,hostname");

							if (sizeof($hosts) > 0) {
							foreach ($hosts as $host) {
								print "<option value='graphs.php?host_id=" . $host["id"] . "&filter=" . $_REQUEST["filter"] . "&page=1'"; if ($_REQUEST["host_id"] == $host["id"]) { print " selected"; } print ">" . $host["name"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td width="30"></td>
					<td width="60">
						Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print $_REQUEST["filter"];?>">
					</td>
					<td>
						&nbsp;<input type="image" src="images/button_go.gif" alt="Go" border="0" align="absmiddle">
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		</form>
	</tr>
