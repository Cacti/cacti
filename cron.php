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
$section = "Data Input"; 
include ('include/auth.php');
include_once ('include/form.php');
include_once ("include/functions.php");
include_once ("include/top_header.php");

DrawMatrixTableBegin("97%");
DrawMatrixRowBegin();
DrawMatrixHeaderTop("Processes to Start for Data Gathering",$colors[dark_bar],"","1");
DrawMatrixHeaderTop("<div align=\"center\"><a href=\"cron.php?action=output\">Show Output</a></div>",$colors[dark_bar],"","1");
DrawMatrixRowEnd();

$ds_list = db_fetch_assoc("select ID from rrd_ds where active=\"on\" and subdsid=0");
if (sizeof($ds_list) > 0) {	
    foreach ($ds_list as $ds) {
	$str = GetCronPath($ds[ID]);
    
	DrawMatrixRowBegin();
	DrawMatrixLoopItem($str,false,"");
	
	if ($action == "output") {
	    $matrix_name = `$str`;
	} else {
	    $matrix_name = "";
	}
	
	DrawMatrixLoopItemAction($matrix_name,$colors[panel],$colors[panel_text],false,"");
	DrawMatrixRowEnd();
    }
}

DrawMatrixTableEnd();

include_once ("include/bottom_footer.php");

?>
