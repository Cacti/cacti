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
<?
$section = "Add/Edit Graphs"; 
include ('include/auth.php');
include_once ('include/form.php');
	
if (isset($form[action])) { $action = $form[action]; } else { $action = $args[action]; }
if (isset($form[ID])) { $id = $form[ID]; } else { $id = $args[id]; }

$current_script_name = basename($HTTP_SERVER_VARS["SCRIPT_NAME"]);

if ($action == 'remove') {
    ##  Code to remove profile goes here.
    header("$PHP_SELF");
}

include_once ("include/top_header.php");

start_box("Equipment Profiles", "", "equipment_profile.php?action=edit");

DrawMatrixRowBegin();
DrawMatrixHeaderItem("Profile Name",$colors[panel_text]);
DrawMatrixHeaderItem("&nbsp;",$colors[panel_text]);
DrawMatrixRowEnd();

$profile_list = db_fetch_assoc("select * from polling_hosts where is_profile > 0 order by descrip");

if (sizeof($profile_list) > 0) {
    foreach ($profile_list as $profile) {
	DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i);
	print "
					<td>
						<a class='linkEditMain' href='?action=graph_edit&id=$profile[ID]'>$profile[descrip]</a>
					</td>
					<td width='1%' align='right'>
						<a href='$PHP_SELF?action=remove&id=$profile[host_id]'><img src='images/delete_icon.gif' width='10' height='10' border='0' alt='Delete'></a>&nbsp;
					</td>
				</tr>\n";
			
	$i++;
    }
}

end_box();

include_once ("include/bottom_footer.php");
?>
