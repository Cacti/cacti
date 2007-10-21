	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_graph_id">
		<td>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="50">
						Host:&nbsp;
					</td>
					<td width="1">
						<select name="host_id" onChange="applyGraphsFilterChange(document.form_graph_id)">
							<option value="-1"<?php if ($_REQUEST["host_id"] == "-1") {?> selected<?php }?>>Any</option>
							<option value="0"<?php if ($_REQUEST["host_id"] == "0") {?> selected<?php }?>>None</option>
							<?php
							$hosts = db_fetch_assoc("select id,CONCAT_WS('',description,' (',hostname,')') as name from host order by description,hostname");

							if (sizeof($hosts) > 0) {
							foreach ($hosts as $host) {
								print "<option value=' " . $host["id"] . "'"; if ($_REQUEST["host_id"] == $host["id"]) { print " selected"; } print ">" . title_trim($host["name"], 40) . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td width="50">
						&nbsp;Template:&nbsp;
					</td>
					<td width="1">
						<select name="template_id" onChange="applyGraphsFilterChange(document.form_graph_id)">
							<option value="-1"<?php if ($_REQUEST["template_id"] == "-1") {?> selected<?php }?>>Any</option>
							<option value="0"<?php if ($_REQUEST["template_id"] == "0") {?> selected<?php }?>>None</option>
							<?php
							$templates = db_fetch_assoc("SELECT DISTINCT graph_templates.id, graph_templates.name
								FROM graph_templates
								INNER JOIN graph_templates_graph
								ON graph_templates.id=graph_templates_graph.graph_template_id
								WHERE graph_templates_graph.local_graph_id>0
								ORDER BY graph_templates.name");

							if (sizeof($templates) > 0) {
							foreach ($templates as $template) {
								print "<option value=' " . $template["id"] . "'"; if ($_REQUEST["template_id"] == $template["id"]) { print " selected"; } print ">" . title_trim($template["name"], 40) . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td width="1" nowrap style='white-space: nowrap;'>
						&nbsp;<input type="image" src="images/button_go.gif" alt="Go" border="0" align="absmiddle">
						<input type="image" src="images/button_clear.gif" name="clear" alt="Clear" border="0" align="absmiddle">
					</td>
				</tr>
			</table>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="50">
						Search:&nbsp;
					</td>
					<td>
						<input type="text" name="filter" size="40" value="<?php print $_REQUEST["filter"];?>">
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		</form>
	</tr>
