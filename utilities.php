<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2003 Ian Berry                                            |
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

include ('include/auth.php');
include_once ('include/form.php');
include_once ('include/utility_functions.php');

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	case 'clear_poller_cache':
		include_once ("include/top_header.php");
		
		repopulate_poller_cache();
		
		utilities();
		utilities_view_poller_cache();
		
		include_once ("include/bottom_footer.php");
		break;
	case 'view_snmp_cache':
		include_once ("include/top_header.php");
		
		utilities();
		utilities_view_snmp_cache();
		
		include_once ("include/bottom_footer.php");
		break;
	case 'view_poller_cache':
		include_once ("include/top_header.php");
		
		utilities();
		utilities_view_poller_cache();
		
		include_once ("include/bottom_footer.php");
		break;
	default:
		include_once ("include/top_header.php");
		
		utilities();
		
		include_once ("include/bottom_footer.php");
		break;
}

/* -----------------------
    Utilities Functions
   ----------------------- */

function utilities_view_snmp_cache() {
	global $colors;
	
	$snmp_cache = db_fetch_assoc("select host_snmp_cache.*,
		host.description,
		snmp_query.name
		from host_snmp_cache,snmp_query,host 
		where host_snmp_cache.host_id=host.id 
		and host_snmp_cache.snmp_query_id=snmp_query.id 
		order by host_snmp_cache.host_id,host_snmp_cache.snmp_query_id,host_snmp_cache.snmp_index");
	
	start_box("<strong>View SNMP Cache</strong> [" . sizeof($snmp_cache) . " Item" . ((sizeof($snmp_cache) > 0) ? "s" : "") . "]", "98%", $colors["header"], "3", "center", "");
	
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
	
	end_box();
}

function utilities_view_poller_cache() {
	global $colors;
	
	$poller_cache = db_fetch_assoc("select 
		data_input_data_cache.*,
		data_template_data.name 
		from data_input_data_cache,data_template_data 
		where data_input_data_cache.local_data_id=data_template_data.local_data_id");
	
	start_box("<strong>View Poller Cache</strong> [" . sizeof($poller_cache) . " Item" . ((sizeof($poller_cache) > 0) ? "s" : "") . "]", "98%", $colors["header"], "3", "center", "");
	
	$i = 0;
	if (sizeof($poller_cache) > 0) {
	foreach ($poller_cache as $item) {
		form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],$i); 
		?>
			<td>
				Data Source: <?php print $item["name"];?>
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
				Action: <?php print $item["action"];?>, <?php print (($item["action"] == "1") ? "Script: " . $item["command"] : "OID: " . $item["arg1"] . " (Host: " . $item["management_ip"] . ", Community: " . $item["snmp_community"] . ")");?>
			</td>
		</tr>
		<?php
	}
	}
	
	end_box();
}

function utilities() {
	global $colors;
	
	start_box("<strong>Utilities</strong>", "98%", $colors["header"], "3", "center", "");
	
	?>
	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td class="textArea">
			<p><strong><a href='utilities.php?action=view_poller_cache'>View Poller Cache</a></strong></p>
			
			<p>This is the data that is being passed to the poller each time it runs. This data is then
			in turn executed/interpreted and the results are fed into the rrd files for graphing or the
			database for display.</p>
		</td>
	</tr>
	<tr bgcolor="#<?php print $colors["form_alternate2"];?>">
		<td class="textArea">
			<p><strong><a href='utilities.php?action=view_snmp_cache'>View SNMP Cache</a></strong></p>
			
			<p>The SNMP cache stores information gathered from SNMP queries. It is used by cacti to determine
			the OID to use when gathering information from an SNMP-enabled host.</p>
		</td>
	</tr>
	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td class="textArea">
			<p><strong><a href='utilities.php?action=clear_poller_cache'>Clear Poller Cache</a></strong></p>
			
			<p>The poller cache will be cleared and re-generated if you select this option. Sometimes
			host/data source data can get out of sync with the cache in which case it makes sense to clear
			the cache and start over.</p>
		</td>
	</tr>
	<?php
		
	end_box();
}
   
?>
