	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_graph_items">
		<td>
			<table cellpadding="0" cellspacing="0">
				<tr width="100%">
					<td>
						Host:&nbsp;
					</td>
					<td>
						<select name="cbo_host_id" onChange="window.location=document.form_graph_items.cbo_host_id.options[document.form_graph_items.cbo_host_id.selectedIndex].value">
							<option value="graphs_items.php?action=item_edit&local_graph_id=<?php print $_REQUEST["local_graph_id"];?>&ds_host_id=-1&ds_data_template_id=<?php print $_REQUEST["ds_data_template_id"];?>"<?php if ($_REQUEST["ds_host_id"] == "-1") {?> selected<?php }?>>Any</option>
							<option value="graphs_items.php?action=item_edit&local_graph_id=<?php print $_REQUEST["local_graph_id"];?>&ds_host_id=0&ds_data_template_id=<?php print $_REQUEST["ds_data_template_id"];?>"<?php if ($_REQUEST["ds_host_id"] == "0") {?> selected<?php }?>>None</option>
							<?php
							$hosts = db_fetch_assoc("select id,CONCAT_WS('',description,' (',hostname,')') as name from host order by description,hostname");

							if (sizeof($hosts) > 0) {
								foreach ($hosts as $host) {
									print "<option value='graphs_items.php?action=item_edit&local_graph_id=" . $_REQUEST["local_graph_id"] . "&ds_host_id=" . $host["id"] . "&ds_data_template_id=" . $_REQUEST["ds_data_template_id"] . "'"; if ($_REQUEST["ds_host_id"] == $host["id"]) { print " selected"; } print ">" . $host["name"] . "</option>\n";
								}
							}
							?>

						</select>
					</td>
				</tr>
				<tr>
					<td>
						Data Template:&nbsp;
					</td>
					<td>
						<select name="cbo_data_template_id" onChange="window.location=document.form_graph_items.cbo_data_template_id.options[document.form_graph_items.cbo_data_template_id.selectedIndex].value">
							<option value="graphs_items.php?action=item_edit&local_graph_id=<?php print $_REQUEST["local_graph_id"];?>&ds_data_template_id=-1&ds_host_id=<?php print $_REQUEST["ds_host_id"];?>"<?php if ($_REQUEST["ds_data_template_id"] == "-1") {?> selected<?php }?>>Any</option>
							<option value="graphs_items.php?action=item_edit&local_graph_id=<?php print $_REQUEST["local_graph_id"];?>&ds_data_template_id=0&ds_host_id=<?php print $_REQUEST["ds_host_id"];?>"<?php if ($_REQUEST["ds_data_template_id"] == "0") {?> selected<?php }?>>None</option>
							<?php
							$data_templates = db_fetch_assoc("select id, name from data_template order by name");

							if (sizeof($data_templates) > 0) {
								foreach ($data_templates as $data_template) {
									print "<option value='graphs_items.php?action=item_edit&local_graph_id=" . $_REQUEST["local_graph_id"] . "&ds_data_template_id=" . $data_template["id"]. "&ds_host_id=" . $_REQUEST["ds_host_id"] . "'"; if ($_REQUEST["ds_data_template_id"] == $data_template["id"]) { print " selected"; } print ">" . $data_template["name"] . "</option>\n";
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
