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

include ("config.php");

?>
<html>
<head>
	<title>cacti</title>
	<link href="include/main.css" rel="stylesheet">
</style>
</head>

<body leftmargin="0" topmargin="0" marginwidth="0" marginheight="0">

<table width="100%" cellspacing="0" cellpadding="0">
	<tr>
		<td colspan="3" bgcolor="#454E53">
			<table border="0" cellpadding="0" cellspacing="0" width='100%'>
				<tr>
					<td valign="bottom">
						&nbsp;
						<?php
						$no_console = false;
						
						if (read_config_option("global_auth") == "on") {
							if ((sizeof(db_fetch_assoc("select realm_id from user_auth_realm where user_id=" . $_SESSION["sess_user_id"])) == 1) && (db_fetch_cell("select realm_id from user_auth_realm where user_id=" . $_SESSION["sess_user_id"]) == "7")) {
								$no_console = true;
							}
						}
						
						if ($no_console == false) {
							print "<a href='index.php'><img src='images/top_tabs_console.gif' border='0' width='79' height='32' align='absmiddle'></a>";
						}
						
						print "<a href='graph_view.php'><img src='images/top_tabs_graphs.gif' border='0' width='79' height='32' align='absmiddle'></a>";
						?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td colspan="3" bgcolor="#<?php print $colors["panel"];?>">
			<img src="images/transparent_line.gif" width="170" height="5" border="0"><br>
		</td>
	</tr>
	<tr>
		<td height="27" colspan="3" bgcolor="#<?php print $colors["panel"];?>" background="images/top_banner.gif">
			&nbsp;
		</td>
	</tr>
	<tr>
		<td colspan="3" bgcolor="#<?php print $colors["panel"];?>">
			<img src="images/transparent_line.gif" width="170" height="5" border="0"><br>
		</td>
	</tr>
	<tr height="5" bgcolor="#<?php print $colors["dark_outline"];?>"><td colspan="3"><img src="images/transparent_line.gif" width="20" height="5" border="0"></td></tr>
	<tr height="5">
		<td width="1%" rowspan="2" align="center" valign="top">
			<img src="images/transparent_line.gif" width="142" height="5" border="0"><br>
			<table bgcolor="#888888" width="133" cellpadding="1" cellspacing="0" border="0"
				<?php draw_menu();?>
			</table>
			
			<p align="center"><a href='about.php'><img src="images/cacti_logo.gif" border="0"></a></p>
		</td>
		<td></td>
	</tr>
	<tr>
		<td height="500"></td>
		<td valign="top" width="100%"><?php display_output_messages();?>
