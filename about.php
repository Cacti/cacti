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
include("./include/top_header.php");

html_start_box("<strong>About Cacti</strong>", "98%", $colors["header"], "3", "center", "");
?>

<tr>
	<td bgcolor="#<?php print $colors["header_panel"];?>" colspan="2">
		<strong><font color="#<?php print $colors["header_text"];?>">Version <?php print $config["cacti_version"];?></font></strong>
	</td>
</tr>
<tr>
	<td valign="top" bgcolor="#<?php print $colors["light"];?>" class="textArea">
		<a href="http://www.raxnet.net/"><img align="right" src="images/raxnet_logo.gif" border="0" alt="raXnet"></a>

		Cacti is designed to be a complete graphing solution for your network. Its goal is to make the
		network administrator's job easier by taking care of all the necessary details necessary to create
		meaningful network graphs.

		<p>The design of Cacti took many hours of SQL and PHP coding, so I hope you find it very useful.</p>

		<p><strong>Developer Thanks</strong><br>
		<ul type="disc">
			<li><a href="http://blyler.cc">Andy Blyler</a>, for ideas, code, and that much needed overall support
			during really lengthy coding sessions.</li>
			<li>Rivo Nurges, for that c-based poller that was talked so long about. This <em>really</em> fast poller
			is what will enable Cacti to make its way into larger and larger networks.</li>
		</ul>
		</p>

		<p><strong>Thanks</a></strong><br>
		<ul type="disc">
			<li>A very special thanks to <a href="http://ee-staff.ethz.ch/~oetiker/">Tobi Oetiker</a>,
				the creator of <a href="http://www.mrtg.org/">RRDTool</a> and the very popular
				<a href="http://www.mrtg.org">MRTG</a>.</li>
			<li>Brady Alleman, creator of NetMRG and
				<a href="http://www.thtech.net">Treehouse Technolgies</a> for questions and ideas. Just
				as a note, NetMRG is a complete Network Monitoring solution also written in PHP/MySQL. His
				product also makes use of RRDTool's graphing capabilities, I encourage you to check it out.</li>
			<li>The users of Cacti! Especially anyone who has taken the time to create a bug report, or otherwise
				help me fix a Cacti-related problem. Also to anyone who has purchased an item from my amazon.com
				wishlist or donated money via Paypal.</li>
		</ul>
		</p>

		<p><strong>License</strong><br>

		<p>Cacti is licensed under the GNU GPL:</p>

		<p><tt>This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.</tt></p>

<p><tt>This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.</tt></p>

		<p><strong>Cacti Variables</a></strong><span style="font-family: monospace; font-size: 10px;"><br>
		<strong>Operating System:</strong> <?php print $config["cacti_server_os"];?><br>
		<strong>PHP SNMP Support:</strong> <?php print $config["php_snmp_support"] ? "yes" : "no";?><br>
		</span></p>
	</td>
</tr>

<?php
html_end_box();
include("./include/bottom_footer.php");
?>
