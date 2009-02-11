<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2009 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

global $colors;
?>
<html>
<head>
	<title>Cacti</title>
	<link href="include/main.css" rel="stylesheet">
	<link href="images/favicon.ico" rel="shortcut icon"/>
	<script type="text/javascript" src="include/layout.js"></script>
	<?php if (isset($refresh)) {
	print "<meta http-equiv=refresh content=\"" . $refresh["seconds"] . "; url='" . $refresh["page"] . "'\">";
	}?>
</style>
</head>

<body leftmargin="0" topmargin="0" marginwidth="0" marginheight="0" background="images/left_border.gif">

<table width="100%" cellspacing="0" cellpadding="0">
	<tr height="1" bgcolor="#a9a9a9">
		<td valign="bottom" colspan="3" nowrap>
			<table width="100%" cellspacing="0" cellpadding="0">
				<tr style="background: transparent url('images/cacti_backdrop.gif') no-repeat center right;">
					<td id="tabs" valign="bottom">
						&nbsp;<a href="index.php"><img src="images/tab_console_down.gif" alt="Console" align="absmiddle" border="0"></a><a href="graph_view.php"><img src="images/tab_graphs.gif" alt="Graphs" align="absmiddle" border="0"></a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr height="2" bgcolor="#183c8f">
		<td colspan="3">
			<img src="images/transparent_line.gif" height="2" border="0"><br>
		</td>
	</tr>
	<tr height="5" bgcolor="#e9e9e9">
		<td colspan="3">
			<table width="100%">
				<tr>
					<td>
						<?php draw_navigation_text();?>
					</td>
					<td align="right">
						<?php if (read_config_option("auth_method") != 0) { ?>
						Logged in as <strong><?php print db_fetch_cell("select username from user_auth where id=" . $_SESSION["sess_user_id"]);?></strong> (<a href="logout.php">Logout</a>)&nbsp;
						<?php } ?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td bgcolor="#f5f5f5" colspan="1" height="8" width="135" style="background-image: url(images/shadow_gray.gif); background-repeat: repeat-x; border-right: #aaaaaa 1px solid;">
			<img src="images/transparent_line.gif" width="135" height="2" border="0"><br>
		</td>
		<td colspan="2" height="8" style="background-image: url(images/shadow.gif); background-repeat: repeat-x;" bgcolor="#ffffff">

		</td>
	</tr>
	<tr>
		<td valign="top" colspan="1" rowspan="2" width="135" style="padding: 5px; border-right: #aaaaaa 1px solid;" bgcolor='#f5f5f5'>
			<table bgcolor="#f5f5f5" width="100%" cellpadding="1" cellspacing="0" border="0">
				<?php draw_menu();?>
			</table>

			<img src="images/transparent_line.gif" width="135" height="5" border="0"><br>
			<p align="center"><a href='about.php'><img src="images/cacti_logo.gif" border="0"></a></p>
			<img src="images/transparent_line.gif" width="135" height="5" border="0"><br>
		</td>
		<td width="100%" colspan="2" valign="top" style="padding: 5px; border-right: #aaaaaa 1px solid;"><?php display_output_messages();?>

