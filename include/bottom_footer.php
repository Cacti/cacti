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
		</td>
	</tr>
</table>
<SCRIPT LANGUAGE=JAVASCRIPT>
<!--
function drag_prep() {
<?
    if (sizeof($drag_inits) > 0) {
	foreach ($drag_inits as $div_id) {
	    if ($browser->BROWSER == 'Netscape') {
		print "  obj1 = document.all ? document.all[\"$div_id[0]\"] : document.getElementById(\"$div_id[0]\");\n";
		if ($div_id[1]) {
		    print "  obj2 = document.all ? document.all[\"$div_id[1]\"] : document.getElementById(\"$div_id[1]\");\n";
		    print "  Drag.init(obj1, obj2);\n";
		} else {
		    print "  Drag.init(obj1);\n";
		}
		if ($div_id[2]) {
		    print "  obj2.onDragStart = function(x, y) { pop_dragger(\"$div_id[0]\", \"$div_id[1]\"); }\n";
		    print "  obj2.onDragEnd   = function(x, y) { alert(get_abs_x(obj2)+\" , \"+get_abs_y(obj2)); }\n";
		}
	    } else {
		print "  Drag.init(document.all[\"$div_id[0]\"],document.all[\"$div_id[1]\"]);\n";
	    }
	}
    }
    ?>
}	
//-->
</SCRIPT>
	
</body>
</html>

<?
/* we use this session var to store field values for when a save fails,
this way we can restore the field's previous values. we reset it here, because
they only need to be stored for a single page */
session_unregister("sess_field_values");
?>
