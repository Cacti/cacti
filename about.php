<?/*
+-------------------------------------------------------------------------+
| Copyright (C) 2002 Ian Berry                                            |
|									  |								      |
| This program is free software; you can redistribute it and/or	          |
| modify it under the terms of the GNU General Public License             |
| as published by the Free Software Foundation; either version 2          |
| of the License, or (at your option) any later version.                  |
|									  |									  |
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
+-------------------------------------------------------------------------+ */?>
<?	$section = "Console Access"; include ('include/auth.php');
	include_once ("include/form.php");
	include_once ("include/top_header.php");
	
	start_box("<strong>About cacti</strong>", "", "");
	?> 


	<tr>
		<td bgcolor="#<?print $colors[header_panel];?>" colspan="2">
			<strong><font color="#<?print $colors[header_text];?>">Version <?print $config[cacti_version];?></font></strong>
		</td>
	</tr>
	<tr>
		<td valign="top" bgcolor="#<?print $colors[light];?>" class="textArea">
			<a href="http://www.raxnet.net/"><img align="right" src="images/raxnet_logo.gif" border="0" alt="raXnet"></a>
			
			raXnet's cacti is designed to be the complete frontend to rrdtool. Cacti's goal is to make the
			network administrator's job easier by taking care of all the nessesary details to create 
			meaningful network graphs.
			
			<p>The design of cacti took many hours of SQL and PHP coding, so I hope you find it very useful.</p>
			
			<p><strong>Developer Thanks</strong><br>
			<ul type="disc">
				<li>A <em>very</em> special thanks to <a href="mailto:rob@ldg.net">Rob Sweet</a> for contributing large amounts 
					of code to the 0.8 branch. Also thanks to <a href="http://www.cox.com/">Cox Communications</a> for paying Rob to work on
					cacti.</li>
			</ul>
			</p>
			
			<p><strong>Thanks</a></strong><br>
			<ul type="disc">
				<li>A very special thanks to <a href="http://ee-staff.ethz.ch/~oetiker/">Tobi Oetiker</a>, 
					the creator of <a href="http://www.mrtg.org/">rrdtool</a> and the very popular
					<a href="http://www.mrtg.org">MRTG</a>.</li>
				<li>Brady Alleman, creator of NetMRG and 
					<a href="http://www.treehousetechnologies.net">Treehouse Technolgies</a> for questions and ideas. Just
					as a note, NetMRG is a complete Network Monitering solution also written in PHP/MySQL. His
					product also makes use of rrdtool's graphing capabilites, I encourage you to check it out.</li>
				<li><a href="http://www.globalunderground.co.uk/">Cluboxed</a>, the creator of the popular Global 
					Underground compilations for hours of listening enjoyment.</li>
			</ul>
			</p>
			
			<p><strong>Ways to Contact Ian</a></strong><br>
			<a href="mailto:iberry@raxnet.net">iberry@raxnet.net</a><br>
			<a href="mailto:rax@kuhncom.net">rax@kuhncom.net</a><br>
			</p>
		</td>
	</tr>


<?	end_box();
	include_once ("include/bottom_footer.php");?>
