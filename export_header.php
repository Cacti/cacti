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
<?	include ('include/config.php'); ?>
<html>
<head>
	<title>cacti</title>
	<link href="main.css" rel="stylesheet">
	<?
	$page_refresh = $array_settings["preview"]["pagerefresh"];
	echo "<meta http-equiv=refresh content=\"$page_refresh\"; url=\"$PHP_SELF?$QUERY_STRING\">\n";
	echo "<meta http-equiv=Pragma content=no-cache>\n";
	echo "<meta http-equiv=cache-control content=no-cache>\n";
	?>
</head>

<body leftmargin="0" topmargin="0" marginwidth="0" marginheight="0">

<table width="100%" cellspacing="0" cellpadding="0">
	<tr>
		<td bgcolor="#454E53" colspan="<?print $array_settings["preview"]["columnnumber"];?>" nowrap>
			<map name="tabs">
				<area alt="cacti" coords="7,5,87,35" href="http://www.raxnet.net/">
				<area alt="Graphs" coords="88,5,165,32" href="index.html" shape="RECT">
			</map>
			
			<img src="top_tabs_export.gif" border="0" usemap="#tabs"><br>
		</td>
		<td bgcolor="#454E53" align="right" nowrap>
			<a href="index.html"><img src="top_tabs_graph_preview<?if ($action == "preview") { print "_down"; }?>.gif" border="0" alt="Preview View"></a><br>
		</td>
	</tr>
	<tr>
		<td colspan="3" bgcolor="#<?print $colors[panel];?>">
			<img src="transparent_line.gif" width="170" height="5" border="0"><br>
		</td>
	</tr>
	<tr>
		<td height="5" colspan="3" bgcolor="#<?print $colors[panel];?>">
			
		</td>
	</tr>
</table>



<table width="100%" cellspacing="0" cellpadding="0"
	<tr height="5"><td colspan="3">&nbsp;</td></tr>
	<tr>
		<td bgcolor="#<?print $colors[light];?>" width="1%"></td>
		<td valign="top" colspan="2">
