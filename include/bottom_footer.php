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
    foreach ($drag_inits as $div_id) {
	if ($div_id[2] == '') { $div_id[2] = 'undefined'; }
	if ($browser->BROWSER == 'Netscape') {
	    print "  Drag.init(document.getElementById(\"$div_id[0]\"),document.getElementById(\"$div_id[1]\"),\"$div_id[2]\");\n";
	} else {
	    print "  Drag.init(document.all[\"$div_id[0]\"],document.all[\"$div_id[1]\"],\"$div_id[2]\");\n";
	}
    }
    ?>
}	
//-->
</SCRIPT>
	
</body>
</html>
