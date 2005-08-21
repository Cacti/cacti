	<? if (empty($_REQUEST["host_id"])) { $_REQUEST["host_id"] = 0; } 
	if (empty($_REQUEST["filter"])) { $_REQUEST["filter"] = ""; } ?>
	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_graph_id" method="post">
		<td>
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td width="120" class="textHeader">
						Filter by host:&nbsp;
					</td>
					<td width="1">
						<select name="cbo_graph_id" onChange="window.location=document.form_graph_id.cbo_graph_id.options[document.form_graph_id.cbo_graph_id.selectedIndex].value">
							<option value="graph_view.php?action=preview&host_id=0&filter=<?php print $_REQUEST["filter"];?>"<?php if ($_REQUEST["host_id"] == "0") {?> selected<?php }?>>None</option>

							<?php
							$hosts = get_host_array();

							if (sizeof($hosts) > 0) {
							foreach ($hosts as $host) {
								print "<option value='graph_view.php?action=preview&host_id=" . $host["id"] . "&filter=" . $_REQUEST["filter"] . "'"; if ($_REQUEST["host_id"] == $host["id"]) { print " selected"; } print ">" . $host["name"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td width="5"></td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print $_REQUEST["filter"];?>">
					</td>
					<td>
						&nbsp;<input type="image" src="images/button_go.gif" alt="Go" border="0" align="absmiddle">
						<input type="image" src="images/button_clear.gif" name="clear" alt="Clear" border="0" align="absmiddle">
					</td>
				</tr>
			</table>
		</td>
		</form>
	</tr>
