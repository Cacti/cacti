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
include_once ("include/form.php");
include_once ("include/top_header.php");

start_box("<strong>About cacti</strong>", "98%", $colors["header"], "3", "center", "");
?>

<tr>
	<td bgcolor="#<?php print $colors["header_panel"];?>" colspan="2">
		<strong><font color="#<?php print $colors["header_text"];?>">Version <?php print $config["cacti_version"];?></font></strong>
	</td>
</tr>
<tr>
	<td valign="top" bgcolor="#<?php print $colors["light"];?>" class="textArea">
		<a href="http://www.raxnet.net/"><img align="right" src="images/raxnet_logo.gif" border="0" alt="raXnet"></a>
		
		raXnet's cacti is designed to be a complete graphing solution for your network. Its goal is to make the
		network administrator's job easier by taking care of all the necessary details necessary to create 
		meaningful network graphs.
		
		<p>The design of cacti took many hours of SQL and PHP coding, so I hope you find it very useful.</p>
		
		<p><strong>Developer Thanks</strong><br>
		<ul type="disc">
			<li><a href="http://blyler.cc">Andy Blyler</a>, for ideas, code, and that much needed overall support 
			during really lengthy coding sessions.</li>
			<li>Rivo Nurges, for that c-based poller that was talked so long about. This <em>really</em> fast poller
			is what will enable cacti to make its way into larger and larger networks.</li>
		</ul>
		</p>
		
		<p><strong>Thanks</a></strong><br>
		<ul type="disc">
			<li>A very special thanks to <a href="http://ee-staff.ethz.ch/~oetiker/">Tobi Oetiker</a>, 
				the creator of <a href="http://www.mrtg.org/">rrdtool</a> and the very popular
				<a href="http://www.mrtg.org">MRTG</a>.</li>
			<li>Brady Alleman, creator of NetMRG and 
				<a href="http://www.thtech.net">Treehouse Technolgies</a> for questions and ideas. Just
				as a note, NetMRG is a complete Network Monitoring solution also written in PHP/MySQL. His
				product also makes use of rrdtool's graphing capabilities, I encourage you to check it out.</li>
		</ul>
		</p>
		
		<p><strong>Ways to Contact Ian</a></strong><br>
		<a href="mailto:iberry@raxnet.net">iberry@raxnet.net</a><br>
		<a href="mailto:rax@kuhncom.net">rax@kuhncom.net</a><br>
		</p>
	</td>
</tr>

<?php
end_box();
include_once ("include/bottom_footer.php");
?>
