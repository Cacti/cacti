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

header ("Cache-Control: no-cache, must-revalidate");
header ("Pragma: no-cache");

/* install form constants */
define ("TOTAL_STEPS", 3);
define ("TOTAL_VARS", 7);

include_once ('include/form.php');
include_once ("include/version_functions.php");

$do_not_read_config = true; 
include ("include/config.php");

/* Make sure cacti is not already up-to-date */
if (GetCurrentVersion() == $config[cacti_version]) {
    print "You can only run this for new installs and upgrades, this installation is already
	    up-to-date. Click <a href=\"index.php\">here</a> to use cacti.";
    exit;
}

/* Here, we define each name, default value, type, and path check for each value
 we want the user to input. The "name" field must exist in the 'settings' table for
 this to work. Cacti also uses different default values depending on what OS it is
 running on. */

/* cacti Web Root */
$input[0]["name"] = "path_webcacti";
$input[0]["default"] = dirname($HTTP_SERVER_VARS["SCRIPT_NAME"]);
$input[0]["type"] = "textbox";

/* Web Server Document Root */
$input[1]["name"] = "path_webroot";
$input[1]["default"] = str_replace("\\\\", "/", $HTTP_SERVER_VARS["DOCUMENT_ROOT"]);
$input[1]["check"] = "";
$input[1]["type"] = "textbox";

/* rrdtool Binary Path */
$input[2]["name"] = "path_rrdtool";
if ($cacti_server_os == "unix") {
    $which_rrdtool = `which rrdtool`;
    if ($which_rrdtool != '') {
	$input[2]["default"] = $which_rrdtool;
    } else {
	$input[2]["default"] = "/usr/local/rrdtool/bin/rrdtool";
    }
}elseif ($cacti_server_os == "win32") {
    $input[2]["default"] = "c:/rrdtool/rrdtool.exe";
}
$input[2]["check"] = "";
$input[2]["type"] = "textbox";

if ($cacti_server_os == "unix") {
    /* snmpwalk Binary Path */
    $input[3]["name"] = "path_snmpwalk";
    $which_snmpwalk = `which snmpwalk`;
    if ($which_snmpwalk != '') {
	$input[3]["default"] = $which_snmpwalk;
    } else {
	$input[3]["default"] = "/usr/local/bin/snmpwalk";
    }
    $input[3]["check"] = "";
    $input[3]["type"] = "textbox";
    
    /* snmpget Binary Path */
    $input[4]["name"] = "path_snmpget";
    $which_snmpget = `which snmpget`;
    if ($which_snmpget != '') {
	$input[4]["default"] = $which_snmpget;
    } else {
	$input[4]["default"] = "/usr/local/bin/snmpget";
    }
    $input[4]["check"] = "";
    $input[4]["type"] = "textbox";
}

/* PHP Binary Path */
$input[5]["name"] = "path_php_binary";
if ($cacti_server_os == "unix") {
    $which_php = `which php`;
    if ($which_php != '') {
	$input[5]["default"] = $which_php;
    } else {
	$input[5]["default"] = "/usr/bin/php";
    }
}elseif ($cacti_server_os == "win32") {
    $input[5]["default"] = "c:/php/php.exe";
}
$input[5]["check"] = "";
$input[5]["type"] = "textbox";

if (GetCurrentVersion() == "new_install") {
    /* Built-in Authentication */
    $input[6]["name"] = "global_auth";
    $input[6]["default"] = "on";
    $input[6]["type"] = "checkbox";
}

/* pre-processing that needs to be done for each step */
switch ($step) {
 case '4': /* last step--save all settings and continue */
    /* get all items on the form and write values for them */
    $i = 0;
    
    while ($i < TOTAL_VARS) {
	if (isset($input[$i]["name"])) {
	    db_execute("update settings set value=\"" . ${$input[$i]["name"]} . "\"
										   where name=\"" . $input[$i]["name"] . "\"", $cnn_id);
	}
	
	$i++;
    }
    
    /* update database */
    $status = UpdateCacti(GetCurrentVersion(), $config[cacti_version]);
    
    db_execute("delete from version",$cnn_id);
    db_execute("insert into version (cacti) values (\"$config[cacti_version]\")");
    
    header ("Location: index.php"); 
    exit;
    break;
}

if (!(isset($step))) {
    $step = 1;
}
?>
	<html>
	<head>
		<title>cacti</title>
		<style>
		<!--
			BODY,TABLE,TR,TD
			{
				font-size: 10pt;
				font-family: Verdana, Arial, sans-serif;
			}
			
			.code
			{
				font-family: Courier New, Courier;
			}
			
			.header-text
			{
				color: white;
				font-weight: bold;
			}
		-->
		</style>
	</head>
	
	<body>
	
	<form method="post" action="install.php">
	<table width="500" align="center" cellpadding=1 cellspacing=0 border=0 bgcolor="#104075">
		<tr bgcolor="#FFFFFF" height="10">
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td width="100%">
				<table cellpadding="3" cellspacing="0" border="0" bgcolor="#E6E6E6" width="100%">
					<tr>
						<td bgcolor="#104075" class="header-text">cacti Installation Guide (Step <?php print $step;?>)</td>
					</tr>
					<tr>
						<td width="100%" style="font-size: 12px;">
							<?php switch ($step) {
							 case "1": ?>
							<p>Thanks for taking the time to download and install cacti, the rrdtool frontend.
							Before you can start making cool graphs, there are a few pieces of data that cacti
							needs to know.</p>
							
							<p>Make sure you have read and followed the required steps needed to install cacti
							before continuing. Install information can be found for 
							<a href="docs/INSTALL.htm">Unix</a> and <a href="docs/INSTALL-WIN32.htm">Win32</a>-based operating systems.</p>
							
							<p>Also, if this is an upgrade, be sure to reading the <a href="docs/UPGRADE.htm">Upgrade</a> information file.</p>
							
							<p>Cacti is licensed under the GNU General Public License, you must agree
							to its provisions before continuing:</p>
							
							<p class="code">This program is free software; you can redistribute it and/or
							modify it under the terms of the GNU General Public License
							as published by the Free Software Foundation; either version 2
							of the License, or (at your option) any later version.</p>
							
							<p class="code">This program is distributed in the hope that it will be useful,
							but WITHOUT ANY WARRANTY; without even the implied warranty of
							MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
							GNU General Public License for more details.</p>
							<?php break;
							 case "2":
							    $current_version = db_fetch_cell("select cacti from version"); ?>
							<p>Cacti has determined the following information about your setup:</p>
							<p class="code"><?php if (!$current_version > 0) {
							    print "<strong>New Installation</strong>";
							}else{
							    if ($current_version == "new_install") {
								print "<strong>New Installation</strong>";
							    }else{
								print "<strong>Upgrade</strong> from $current_version to $config[cacti_version]";
							    }
							}?></p>
							
							<p>The following information has been determined from cacti's configuration file.
							If it is not correct, please edit 'include/config.php' before continuing.</p>
							
							<p class="code">
							<?php	print "Database User: $database_username<br>";
								print "Database Hostname: $database_hostname<br>";
								print "Database: $database_default<br>";
								print "Server Operating System Type: $config[cacti_server_os]<br>"; ?>
							</p>
							
							<?php break;
							case "3": ?>
							<p>Make sure make sure all of these values are correct before continuing.</p>
							<?php
								/* make sure to reread config at this point so we have a fresh view of things */
								$do_not_read_config = false; 
	include ('include/config.php');
				
							$i = 0;
							/* find the appropriate value for each 'config name' above by config.php, database,
							or a default for fall back */
							while ($i < TOTAL_VARS) {
								if (isset($input[$i]["name"])) {
									if (isset(${$input[$i]["name"]})) {
										/* 1. use any values already in config.php */
										$current_value = ${$input[$i]["name"]};
									}else{
										$name = $input[$i]["name"];
										
										if ($config[$name]["value"] == "") {
											/* 3. use the default values */
											$current_value = $input[$i]["default"];
										}else{
											/* 2. use the values in the 'settings' table */
											$current_value = $config[$name]["value"];
										}
									}
									
									/* run a check on the path specified only if specified above, then fill a string with
									the results ('FOUND' or 'NOT FOUND') so they can be displayed on the form */
									$form_check_string = "";
									if (isset($input[$i]["check"])) {
										if (file_exists($current_value . $input[$i]["check"])) {
											$form_check_string = "<font color=\"#008000\">[FOUND]</font> ";
										}else{
											$form_check_string = "<font color=\"#FF0000\">[NOT FOUND]</font> ";
										}
									}
									
									/* draw the acual header and textbox on the form */
									print "<p><strong>" . $form_check_string . $config[$name]["friendlyname"] . "</strong>";
									if ($config[$name]["friendlyname"] != "") { print ": " . $config[$name]["description"]; }else{ print "<strong>" . $config[$name]["description"] . "</strong>"; }
									print "<br>";
									
									switch ($input[$i]["type"]) {
										case 'textbox':
											DrawStrippedFormItemTextBox($input[$i]["name"],$current_value,"","");
											print "<br></p>";
											break;
										case 'checkbox':
											form_base_checkbox($input[$i]["name"],$current_value,$config[$name]["description"],"");
											break;
									}
								}
								
								$i++;
							}?>
							
							<p><strong><font color="#FF0000">NOTE:</font></strong> Once you click "Next",
							all of your settings will be saved and your database will be upgraded if this
							is an upgrade. You can change any of the settings on this screen at a later
							time by going to "cacti Settings" from within cacti.</p>
							<?php break;
}?>
							<p align="right"><input type="image" src="images/install_<?php if ($step==TOTAL_STEPS){?>finish<?php }else{?>next<?php }?>.gif" alt="<?php if ($step==TOTAL_STEPS){?>Finish<?php }else{?>Next<?php }?>"></p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
	<input type="hidden" name="step" value="<?php print ($step+1);?>">
	</form>
	
	</body>
</html>
