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

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

switch ($_REQUEST["action"]) {
	default:
		include_once ("include/top_header.php");
		
		utilities();
		
		include_once ("include/bottom_footer.php");
		break;
}

/* -----------------------
    Utilities Functions
   ----------------------- */

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
			<p><strong><a href='utilities.php?action=clear_poller_cache'>Clear Poller Cache</a></strong></p>
			
			<p>The poller cache will be cleared and re-generated if you select this option. Sometimes
			host/data source data can get out of sync with the cache in which case it makes sense to clear
			the cache and start over.</p>
		</td>
	</tr>
	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td class="textArea">
			<p><strong><a href='utilities.php?action=view_snmp_cache'>View SNMP Cache</a></strong></p>
			
			<p>The SNMP cache stores information gathered from SNMP queries. It is used by cacti to determine
			the OID to use when gathering information from an SNMP-enabled host.</p>
		</td>
	</tr>
	<?php
			
	end_box();	
}
   
?>
