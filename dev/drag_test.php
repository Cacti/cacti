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
<?	$section = "Console Access"; 
include ('include/auth.php');
include_once ('include/form.php');
include_once ("include/top_header.php");

DrawMatrixTableBegin("97%");
$drag_inits[] = array("handle","mytarget","true");
?>
<div ID=handle style="position:static"><img src='images/menu_header_graph_setup.gif' border='0' alt='Graph Setup'></DIV>
	<BR><BR><BR>
<div ID=mytarget style="position:static"><img src='images/menu_item_cdef.gif' border='0' alt='Graph Setup'></DIV>
  <?
  
DrawMatrixTableEnd();

include_once ("include/bottom_footer.php"); ?>
