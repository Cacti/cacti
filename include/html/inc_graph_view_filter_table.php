	<tr bgcolor="<?php print $colors["panel"];?>" class="noprint">
		<form name="form_graph_view" method="post">
		<td class="noprint">
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr class="noprint">
					<td nowrap style='white-space: nowrap;' width="40">
						&nbsp;<strong>Host:</strong>&nbsp;
					</td>
					<td width="1">
						<select name="host_id" onChange="applyGraphPreviewFilterChange(document.form_graph_view)">
							<option value="0"<?php if ($_REQUEST["host_id"] == "0") {?> selected<?php }?>>Any</option>

							<?php
							$hosts = db_fetch_assoc("SELECT DISTINCT host.id, host.description as name
								FROM host
								INNER JOIN graph_local
								ON host.id=graph_local.host_id" .
								(($request["graph_template_id"] > 0) ? " WHERE graph_template_id=" . $_REQUEST["graph_template_id"] :"") . "
								ORDER BY name");

							if (sizeof($hosts) > 0) {
							foreach ($hosts as $host) {
								print "<option value='" . $host["id"] . "'"; if ($_REQUEST["host_id"] == $host["id"]) { print " selected"; } print ">" . $host["name"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="100">
						&nbsp;<strong>Graph Template:</strong>&nbsp;
					</td>
					<td width="1">
						<select name="graph_template_id" onChange="applyGraphPreviewFilterChange(document.form_graph_view)">
							<option value="0"<?php if ($_REQUEST["graph_template_id"] == "0") {?> selected<?php }?>>Any</option>

							<?php
							$graph_templates = db_fetch_assoc("SELECT graph_templates.*
								FROM graph_templates
								INNER JOIN graph_local
								ON graph_templates.id=graph_local.graph_template_id" .
								(($request["host_id"] > 0) ? " WHERE host_id=" . $_REQUEST["host_id"] :"") . "
								ORDER BY name");

							if (sizeof($graph_templates) > 0) {
							foreach ($graph_templates as $template) {
								print "<option value='" . $template["id"] . "'"; if ($_REQUEST["graph_template_id"] == $template["id"]) { print " selected"; } print ">" . $template["name"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;<strong>Search:</strong>&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="40" value="<?php print $_REQUEST["filter"];?>">
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