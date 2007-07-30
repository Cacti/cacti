	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_snmpcache">
		<td>
			<table cellpadding="0" cellspacing="0">
				<tr>
					<td nowrap style='white-space: nowrap;' width="50">
						Host:&nbsp;
					</td>
					<td width="1">
						<select name="host_id" onChange="applyViewSNMPFilterChange(document.form_snmpcache)">
							<option value="-1"<?php if ($_REQUEST["host_id"] == "-1") {?> selected<?php }?>>Any</option>
							<option value="0"<?php if ($_REQUEST["host_id"] == "0") {?> selected<?php }?>>None</option>
							<?php
							if ($_REQUEST["snmp_query_id"] == -1) {
								$hosts = db_fetch_assoc("SELECT DISTINCT
											host.id,
											host.description,
											host.hostname
											FROM (host_snmp_cache,snmp_query,host)
											WHERE host_snmp_cache.host_id=host.id
											AND host_snmp_cache.snmp_query_id=snmp_query.id
											ORDER by host.description");
							}else{
								$hosts = db_fetch_assoc("SELECT DISTINCT
											host.id,
											host.description,
											host.hostname
											FROM (host_snmp_cache,snmp_query,host)
											WHERE host_snmp_cache.host_id=host.id
											AND host_snmp_cache.snmp_query_id=snmp_query.id
											AND host_snmp_cache.snmp_query_id='" . $_REQUEST["snmp_query_id"] . "'
											ORDER by host.description");
							}
							if (sizeof($hosts) > 0) {
							foreach ($hosts as $host) {
								print "<option value='" . $host["id"] . "'"; if ($_REQUEST["host_id"] == $host["id"]) { print " selected"; } print ">" . $host["description"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="90">
						&nbsp;Query Name:&nbsp;
					</td>
					<td width="1">
						<select name="snmp_query_id" onChange="applyViewSNMPFilterChange(document.form_snmpcache)">
							<option value="-1"<?php if ($_REQUEST["host_id"] == "-1") {?> selected<?php }?>>Any</option>
							<?php
							if ($_REQUEST["host_id"] == -1) {
								$snmp_queries = db_fetch_assoc("SELECT DISTINCT
											snmp_query.id,
											snmp_query.name
											FROM (host_snmp_cache,snmp_query,host)
											WHERE host_snmp_cache.host_id=host.id
											AND host_snmp_cache.snmp_query_id=snmp_query.id
											ORDER by snmp_query.name");
							}else{
								$snmp_queries = db_fetch_assoc("SELECT DISTINCT
											snmp_query.id,
											snmp_query.name
											FROM (host_snmp_cache,snmp_query,host)
											WHERE host_snmp_cache.host_id=host.id
											AND host_snmp_cache.host_id='" . $_REQUEST["host_id"] . "'
											AND host_snmp_cache.snmp_query_id=snmp_query.id
											ORDER by snmp_query.name");
							}
							if (sizeof($snmp_queries) > 0) {
							foreach ($snmp_queries as $snmp_query) {
								print "<option value='" . $snmp_query["id"] . "'"; if ($_REQUEST["snmp_query_id"] == $snmp_query["id"]) { print " selected"; } print ">" . $snmp_query["name"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print $_REQUEST["filter"];?>">
					</td>
					<td nowrap style='white-space: nowrap;'>
						&nbsp;<input type="image" src="images/button_go.gif" name="go" alt="Go" border="0" align="absmiddle">
						<input type="image" src="images/button_clear.gif" name="clear" alt="Clear" border="0" align="absmiddle">
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		<input type='hidden' name='action' value='view_snmp_cache'>
		</form>
	</tr>