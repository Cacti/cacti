<?/*
+-------------------------------------------------------------------------+
| Copyright (C) 2002 Ian Berry                                            |
|																	      |
| This program is free software; you can redistribute it and/or			  |
| modify it under the terms of the GNU General Public License             |
| as published by the Free Software Foundation; either version 2          |
| of the License, or (at your option) any later version.                  |
|																		  |
| This program is distributed in the hope that it will be useful,         |
| but WITHOUT ANY WARRANTY; without even the implied warranty of          |
| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
| GNU General Public License for more details.                            |
+-------------------------------------------------------------------------+
| cacti: the rrdtool frontend [php-auth, php-tree, php-form]              |
+-------------------------------------------------------------------------+
| This code is currently maintained and debugged by Ian Berry, any        |
| questions or comments regarding this code should be directed to:        |
| - iberry@raxnet.net                                                     |
+-------------------------------------------------------------------------+
| - raXnet - http://www.raxnet.net/                                       |
+-------------------------------------------------------------------------+
*/?>
<?	$section = "Console Access"; include ('include/auth.php');
#	include ('include/config.php');
#	include ("include/database.php");
	include_once ("include/top_header.php"); ?> 

<table width="97%">
	<tr>
		<td bgcolor="#<?print $colors[dark_bar];?>" colspan="2">
			<strong><font size="+0">About raXnet's cacti (Version <?print $config[cacti_version];?>)</font></strong>
		</td>
	</tr>
	<tr>
		<td valign="top">
			<p>raXnet's cacti, the complete rrdtool frontend was written by Ian Berry. This
			product took many long hours of PHP and SQL design, so I hope you find it very
			useful.</p>
			
			<p><strong>Thanks</strong><br>
			- A very special thanks to <a href="http://ee-staff.ethz.ch/~oetiker/">Tobi Oetiker</a>, 
			the creator of <a href="http://www.mrtg.org/">rrdtool</a> and the very popular
			<a href="http://www.mrtg.org">MRTG</a>.<br>
			- Brady Alleman, creator of NetMRG and 
			<a href="http://www.treehousetechnologies.net">Treehouse Technolgies</a> for questions and ideas. Just
			as a note, NetMRG is a complete Network Monitering solution also written in PHP/MySQL. His
			product also makes use of rrdtool's graphing capabilites, I encourage you to check it out.<br>
			- <a href="http://www.globalunderground.co.uk/">Cluboxed</a>, the creator of the popular Global 
			Underground compilations for hours of listening enjoyment.</p>
			
			<p><strong>Ways to Contact Ian</strong><br>
			<a href="mailto:iberry@raxnet.net">iberry@raxnet.net</a><br>
			<a href="mailto:rax@kuhncom.net">rax@kuhncom.net</a><br></p>
			
			<strong><a href="http://www.raxnet.net/">raXnet home</a></strong><br>
		</td>
		<td>
			<img src="images/cacti.png" width="52" height="385" border="0" alt="">
		</td>
	</tr>
</table>

<?include_once ("include/bottom_footer.php");?>
