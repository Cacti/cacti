<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004 Ian Berry                                            |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | cacti: a php-based graphing solution                                    |
 +-------------------------------------------------------------------------+
 | Most of this code has been designed, written and is maintained by       |
 | Ian Berry. See about.php for specific developer credit. Any questions   |
 | or comments regarding this code should be directed to:                  |
 | - iberry@raxnet.net                                                     |
 +-------------------------------------------------------------------------+
 | - raXnet - http://www.raxnet.net/                                       |
 +-------------------------------------------------------------------------+
*/

include("./include/auth.php");
include_once("./lib/utility.php");

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'clear_poller_cache':
		include_once("./include/top_header.php");

		/* obtain timeout settings */
		$max_execution = ini_get("max_execution_time");
		$max_memory = ini_get("memory_limit");

		ini_set("max_execution_time", "0");
		ini_set("memory_limit", "32M");

		repopulate_poller_cache();

		ini_set("max_execution_time", $max_execution);
		ini_set("memory_limit", $max_memory);

		utilities();
		utilities_view_poller_cache();

		include_once("./include/bottom_footer.php");
		break;
	case 'view_snmp_cache':
		include_once("./include/top_header.php");

		utilities();
		utilities_view_snmp_cache();

		include_once("./include/bottom_footer.php");
		break;
	case 'view_poller_cache':
		include_once("./include/top_header.php");

		utilities();
		utilities_view_poller_cache();

		include_once("./include/bottom_footer.php");
		break;
	case 'view_logfile':
		include_once("./include/top_header.php");

		utilities();
		utilities_view_logfile();

		include_once("./include/bottom_footer.php");
		break;
	case 'clear_logfile':
		include_once("./include/top_header.php");

		utilities_clear_logfile();

		utilities();
		utilities_view_logfile();

		include_once("./include/bottom_footer.php");
		break;
	default:
		include_once("./include/top_header.php");

		utilities();

		include_once("./include/bottom_footer.php");
		break;
}

/* -----------------------
    Utilities Functions
   ----------------------- */

function utilities_view_logfile() {
	global $colors;

	$logfile = read_config_option("path_cactilog");

	if ($logfile == "") {
		$logfile = "./log/rrd.log";
	}

	/* helps determine output color */
	$linecolor = True;

	/* read logfile into an array and display */
	if (file_exists($logfile)) {
		$logcontents = file($logfile);
	}else{
		touch($logfile);
		$logcontents = file($logfile);
	}

	$logcontents = array_reverse($logcontents);

	html_start_box("<strong>View Cacti Log File</strong> [" . sizeof($logcontents) . " Item" . ((sizeof($logcontents) > 0) ? "s" : "") . "]", "98%", $colors["header"], "3", "center", "");

	$i = 0;
	foreach ($logcontents as $item) {
		if (strpos($item,":") <> 0) {
			if ($linecolor = True) {
				$linecolor = False;
			}else{
				$linecolor = True;
			}
		}

		switch ($linecolor) {
			case True:
				form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);
				?>
				<td>
				<?php print $item;?>
				</td>
				</tr>
				<?php
				break;
			case False:
				form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++;
				?>
				<td>
				<?php print $item;?>
				</td>
				</tr>
				<?php
				break;
		}
	}

	html_end_box();
}

function utilities_clear_logfile() {
	$logfile = read_config_option("path_cactilog");

	if ($logfile == "") {
		$logfile = "./log/rrd.log";
	}

	if (file_exists($logfile)) {
		unlink($logfile);
		touch($logfile);
	}
}

function utilities_view_snmp_cache() {
	global $colors;

	$snmp_cache = db_fetch_assoc("select host_snmp_cache.*,
		host.description,
		snmp_query.name
		from host_snmp_cache,snmp_query,host
		where host_snmp_cache.host_id=host.id
		and host_snmp_cache.snmp_query_id=snmp_query.id
		order by host_snmp_cache.host_id,host_snmp_cache.snmp_query_id,host_snmp_cache.snmp_index");

	html_start_box("<strong>View SNMP Cache</strong> [" . sizeof($snmp_cache) . " Item" . ((sizeof($snmp_cache) > 0) ? "s" : "") . "]", "98%", $colors["header"], "3", "center", "");

	$i = 0;
	if (sizeof($snmp_cache) > 0) {
	foreach ($snmp_cache as $item) {
			form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);
			?>
				<td>
					Host: <?php print $item["description"];?>, SNMP Query: <?php print $item["name"];?>
				</td>
			</tr>
			<?php
			form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);
			?>
				<td>
					Index: <?php print $item["snmp_index"];?>, Field Name: <?php print $item["field_name"];?>, Field Value: <?php print $item["field_value"];?>
				</td>
			</tr>
			<?php
			form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++;
			?>
				<td>
					OID: <?php print $item["oid"];?>
				</td>
			</tr>
			<?php
	}
	}

	html_end_box();
}

function utilities_view_poller_cache() {
	global $colors;

	$poller_cache = db_fetch_assoc("select
		poller_item.*,
		data_template_data.name_cache,
		data_local.host_id
		from poller_item,data_template_data,data_local
		where poller_item.local_data_id=data_template_data.local_data_id
		and data_template_data.local_data_id=data_local.id");

	html_start_box("<strong>View Poller Cache</strong> [" . sizeof($poller_cache) . " Item" . ((sizeof($poller_cache) > 0) ? "s" : "") . "]", "98%", $colors["header"], "3", "center", "");

	$i = 0;
	if (sizeof($poller_cache) > 0) {
	foreach ($poller_cache as $item) {
		form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);
		?>
			<td>
				Data Source: <?php print $item["name_cache"];?>
			</td>
		</tr>
		<?php
		form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i);
		?>
			<td>
				RRD: <?php print $item["rrd_path"];?>
			</td>
		</tr>
		<?php
		form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i); $i++;
		?>
			<td>
				Action: <?php print $item["action"];?>, <?php print ((($item["action"] == "1") || ($item["action"] == "2")) ? "Script: " . $item["arg1"] : "OID: " . $item["arg1"] . " (Host: " . $item["hostname"] . ", Community: " . $item["snmp_community"] . ")");?>
			</td>
		</tr>
		<?php
	}
	}

	html_end_box();
}

function utilities() {
	global $colors;

	html_start_box("<strong>Cacti System Utilities</strong>", "98%", $colors["header"], "3", "center", "");

	html_header(array("Poller Cache Administration"), 2);

	?>
	<colgroup span="3">
		<col valign="top" width="20"></col>
		<col valign="top" width="10"></col>
	</colgroup>

	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td class="textArea">
			<p><a href='utilities.php?action=view_poller_cache'>View Poller Cache</a></p>
		</td>
		<td class="textArea">
			<p>This is the data that is being passed to the poller each time it runs. This data is then in turn executed/interpreted and the results are fed into the rrd files for graphing or the database for display.</p>
		</td>
	</tr>
	<tr bgcolor="#<?php print $colors["form_alternate2"];?>">
		<td class="textArea">
			<p><a href='utilities.php?action=view_snmp_cache'>View SNMP Cache</a></p>
		</td>
		<td class="textArea">
			<p>The SNMP cache stores information gathered from SNMP queries. It is used by cacti to determine the OID to use when gathering information from an SNMP-enabled host.</p>
		</td>
	</tr>
	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td class="textArea">
			<p><a href='utilities.php?action=clear_poller_cache'>Clear Poller Cache</a></p>
		</td>
		<td class="textArea">
			<p>The poller cache will be cleared and re-generated if you select this option. Sometimes host/data source data can get out of sync with the cache in which case it makes sense to clear the cache and start over.</p>
		</td>
	</tr>

	<?php html_header(array("System Log Administration"), 2);?>

	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td class="textArea">
			<p><a href='utilities.php?action=view_logfile'>View Cacti Log File</a></p>
		</td>
		<td class="textArea">
			<p>The Cacti Log File stores statistic, error and other message depending on system settings.  This information can be used to identify problems with the poller and application.</p>
		</td>
	</tr>
	<tr bgcolor="#<?php print $colors["form_alternate2"];?>">
		<td class="textArea">
			<p><a href='utilities.php?action=clear_logfile'>Clear Cacti Log File</a></p>
		</td>
		<td class="textArea">
			<p>This action will reset the Cacti Log File.  Please note that if you are using the Syslog/Eventlog only, this action will have no effect.</p>
		</td>
	</tr>
	<?php

	html_end_box();
}

?>