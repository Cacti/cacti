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
function DrawMenu($userid, $menuid) {
    global $config,$paths,$colors;
	
    include_once ("include/database.php");
    include_once ("include/config.php");
    
    /* get the current use logged in (if there is one) */
    if ($userid=="COOKIE") {
		$userid = $HTTP_COOKIE_VARS[$conf_cookiename];
    }
    
    /* set up the available menu headers */
    $menu_headers = array(0 => "Graph Setup", 1 => "Data Gathering", 2 => "Configuration", 3 => "Utilities");
    
    /* link each menu item to a header */
    $menu_items[mIndex] = array(0, 0, 0, 0, 1, 1, 1, 1, 1, 2, 2, 3, 3);
    
    /* setup the actual menu item definitions */
    $menu_items[mTitle] = array(
    	"Graph Management", 
	"Graph Templates", 
	"Graph Hierarchy",
	"Colors",
	"Data Sources",
	"Round Robin Archives",
	"SNMP Interfaces",
	"Data Input",
	"CDEF's",
	"Cron Printout",
	"cacti Settings",
	"User Management",
	"Logout User"
	);
    
    $menu_items[mURL] = array(
    	"graphs.php", 
	"graph_templates.php",
	"tree.php",
	"color.php",
	"ds.php",
	"rra.php",
	"snmp.php",
	"data.php",
	"cdef.php",
	"cron.php",
	"settings.php",
	"user_admin.php",
	"logout.php"
	);
    
    /* NOTICE: we will have to come back and re-impliment "custom auth menus" at some point */
    $user_perms = db_fetch_assoc("select
    	auth_sections.Section
	from auth_sections left join auth_acl on auth_acl.SectionID=auth_sections.ID
	where auth_acl.UserID=$userid");
    
    print '<tr><td width="100%"><table cellpadding=3 cellspacing=0 border=0 width="100%">';
    
    $_m_index = -1;
   
    if (sizeof($menu_headers) > 0) {
	    	for ($i=0; ($i < sizeof($menu_items[mIndex])); $i++) {
		    	if ($menu_items[mIndex][$i] != $_m_index) {
			    	$_m_index = $menu_items[mIndex][$i];
				
				print '<tr><td class="textMenuHeader">' . $menu_headers[$_m_index] . '</td></tr>';
		    	}
		    	
			print '<tr><td class="textMenuItem" background="images/menu_line.gif"><a href="' . $menu_items[mURL][$i] . '">' . $menu_items[mTitle][$i] . '</a></td></tr>';
		}
    }
	
    print '</table></td></tr>';
}

?>