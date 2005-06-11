	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_graph_items">
		<td>
			<table cellpadding="0" cellspacing="0">
				<tr>
					<td>
						Select a host:&nbsp;
					</td>
					<td>
						<select name="cbo_graph_items" onChange="window.location=document.form_graph_items.cbo_graph_items.options[document.form_graph_items.cbo_graph_items.selectedIndex].value">
							<option value="graphs_items.php?action=item_edit&local_graph_id=<?php print $_REQUEST["local_graph_id"];?>&host_id=0&filter=<?php print $_REQUEST["filter"];?>"<?php if ($_REQUEST["host_id"] == "0") {?> selected<?php }?>>Any</option>
							<option value="graphs_items.php?action=item_edit&local_graph_id=<?php print $_REQUEST["local_graph_id"];?>&host_id=-1&filter=<?php print $_REQUEST["filter"];?>"<?php if ($_REQUEST["host_id"] == "-1") {?> selected<?php }?>>None</option>
							<?php
							$hosts = db_fetch_assoc("select id,CONCAT_WS('',description,' (',hostname,')') as name from host order by description,hostname");

							if (sizeof($hosts) > 0) {
							foreach ($hosts as $host) {
								print "<option value='graphs_items.php?action=item_edit&local_graph_id=" . $_REQUEST["local_graph_id"] . "&host_id=" . $host["id"] . "&filter=" . $_REQUEST["filter"] . "'"; if ($_REQUEST["host_id"] == $host["id"]) { print " selected"; } print ">" . title_trim($host["name"], 40) . "</option>\n";
							}
							}
							?>

						</select>
					</td>
				</tr>
			</table>
		</td>
		</form>
	</tr>