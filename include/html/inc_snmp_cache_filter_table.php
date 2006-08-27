	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_snmpcache">
		<td>
			<table cellpadding="0" cellspacing="0">
				<tr>
					<td width="20">
						Host:&nbsp;
					</td>
					<td width="1">
						<select name="cbo_host_id" onChange="window.location=document.form_snmpcache.cbo_host_id.options[document.form_snmpcache.cbo_host_id.selectedIndex].value">
							<option value="utilities.php?action=view_snmp_cache&host_id=-1&snmp_query_id=<?php print $_REQUEST['snmp_query_id'];?>&filter=<?php print $_REQUEST['filter'];?>"<?php if ($_REQUEST["host_id"] == "-1") {?> selected<?php }?>>Any</option>
							<option value="utilities.php?action=view_snmp_cache&host_id=0&snmp_query_id=<?php print $_REQUEST['snmp_query_id'];?>&filter=<?php print $_REQUEST['filter'];?>"<?php if ($_REQUEST["host_id"] == "0") {?> selected<?php }?>>None</option>
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
								print "<option value='utilities.php?action=view_snmp_cache&host_id=" . $host["id"] . "&filter=" . $_REQUEST["filter"] . "&page=1'"; if ($_REQUEST["host_id"] == $host["id"]) { print " selected"; } print ">" . $host["description"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td width="5"></td>
					<td width="80">
						Query Name:&nbsp;
					</td>
					<td width="1">
						<select name="cbo_snmp_query_id" onChange="window.location=document.form_snmpcache.cbo_snmp_query_id.options[document.form_snmpcache.cbo_snmp_query_id.selectedIndex].value">
							<option value="utilities.php?action=view_snmp_cache&snmp_query_id=-1&filter=<?php print $_REQUEST['filter'];?>&host_id=<?php print $_REQUEST['host_id'];?>"<?php if ($_REQUEST["host_id"] == "-1") {?> selected<?php }?>>Any</option>
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
								print "<option value='utilities.php?action=view_snmp_cache&snmp_query_id=" . $snmp_query["id"] . "&filter=" . $_REQUEST["filter"] . "&host_id=" . $_REQUEST["host_id"] . "&page=1'"; if ($_REQUEST["snmp_query_id"] == $snmp_query["id"]) { print " selected"; } print ">" . $snmp_query["name"] . "</option>\n";
							}
							}
							?>
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