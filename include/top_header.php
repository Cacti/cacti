<?/*
+-------------------------------------------------------------------------+
| Copyright (C) 2002 Ian Berry                                            |
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
| cacti: the rrdtool frontend [php-auth, php-tree, php-form]              |
+-------------------------------------------------------------------------+
| This code is currently maintained and debugged by Ian Berry, any        |
| questions or comments regarding this code should be directed to:        |
| - iberry@raxnet.net                                                     |
+-------------------------------------------------------------------------+
| - raXnet - http://www.raxnet.net/                                       |
+-------------------------------------------------------------------------+
*/?>
<?	include_once ("auth_functions.php");
	global $colors;
?>
<html>
<head>
	<title>cacti</title>
	<STYLE TYPE="text/css">	<!--

	BODY, TABLE, TR, TD {font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 10px;}
	BODY {background-color: #FFFFFF;}
	A {text-decoration: none;}
	A:active { text-decoration: none;}
	A:hover {text-decoration: underline; color: #333333;}
	A:visited {color: Blue;}
	.textHeader {font-size: 12px; font-weight: bold;}
	.textHeaderDark {font-size: 12px; color: #ffffff;}
	.textSubHeaderDark {font-size: 12px; color: #ffffff;}
	.textArea {font-size: 12px;}
	.textTab {font-size: 10px; font-weight: bold;}
	.textEditTitle {font-size: 10px; font-weight: bold;}
	.textMenuBackground {background-color: #888888;}
	.textMenuHeader {background-color: #AEB4B7; font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 10px; font-weight: bold;}
	.textMenuItem {background-color: #E3E3E3; font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 10px;}
	.cboSmall {font-size: 10px;}
	.linkEditMain {text-decoration: none; <?if ($config["vis_main_column_bold"]["value"]==true){?>font-weight: bold;<?}?>}
	.linkEditMain:visited {text-decoration: none; <?if ($config["vis_main_column_bold"]["value"]==true){?>font-weight: bold;<?}?>}
	.linkEditMain:active {text-decoration: underline; color: #333333; <?if ($config["vis_main_column_bold"]["value"]==true){?>font-weight: bold;<?}?>}
	.linkEditMain:hover {color: Blue; text-decoration: underline; <?if ($config["vis_main_column_bold"]["value"]==true){?> font-weight: bold;<?}?>}
	.linkOverDark {color: #ffffff; text-decoration: none;}
	.linkOverDark:visited {color: #ffffff; text-decoration: none;}
	.linkOverDark:active {color: #ffffff; text-decoration: none;}
	.linkOverDark:hover {color: #ffffff; text-decoration: underline;}
	.linkTabs {color: #000080; text-decoration: none;}
	.linkTabs:visited {color: #000080; text-decoration: none;}
	.linkTabs:active {color: #3a5fcd; text-decoration: none;}
	.linkTabs:hover {color: #3a5fcd; text-decoration: underline;}
	-->
</style>
	<?include_once("include/js_popup.inc");?>
	<script language="javascript" src="include/dom-drag.js"></script>
</head>

<body leftmargin="0" topmargin="0" marginwidth="0" marginheight="0" onLoad="startup();">

<map name="tabs">
	<area alt="Console" coords="7,5,87,35" href="index.php">
	<area alt="Graphs" coords="88,5,165,32" href="graph_view.php?action=tree" shape="RECT">
</map>

<table width="100%" cellspacing="0" cellpadding="0">
	<tr>
		<td colspan="3" bgcolor="#454E53">
			<table border=0 cellpadding=0 cellspacing=0 width='100%'>
				<tr>
					<td valign=bottom width=36>
						<img src="images/top_tabs_main.gif" border="0" width=250 height=32 usemap="#tabs"></td><td align=right>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td colspan="3" bgcolor="#<?print $colors[panel];?>">
			<img src="images/transparent_line.gif" width="170" height="5" border="0"><br>
		</td>
	</tr>
	<tr>
		<td height="27" colspan="3" bgcolor="#<?print $colors[panel];?>" background="images/top_banner.gif">
			&nbsp;
		</td>
	</tr>
	<tr>
		<td colspan="3" bgcolor="#<?print $colors[panel];?>">
			<img src="images/transparent_line.gif" width="170" height="5" border="0"><br>
		</td>
	</tr>
	<tr height="5" bgcolor="#<?print $colors[dark_outline];?>"><td colspan="3"><img src="images/transparent_line.gif" width="20" height="5" border="0"></td></tr>
	<tr height="5">
		<td width="1%" rowspan="2" align="center" valign="top">
			<img src="images/transparent_line.gif" width="142" height="5" border="0"><br>
			<table width="133" cellpadding=1 cellspacing=0 border=0 class="textMenuBackground">
				<?DrawMenu ($HTTP_SESSION_VARS['user_id'], 1);?>
			</table>
			
			<p align="center"><a href='about.php'><img src="images/cacti_logo.gif" border="0"></a></p>
		</td>
		<td></td>
	</tr>
	<tr>
		<td height="500"></td>
		<td valign="top" width="100%">
