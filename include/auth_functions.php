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
    
    $item = db_fetch_row("select ItemOrder from menu where id=$menuid");
    
    /* decide whether this menu is sorted alphabetically or seqentially */
    if ($item[ItemOrder] == 1) {
		$sql_sort_string = "cname, name";
    }else{
		$sql_sort_string = "c.sequence,i.sequence";
    }
    
    if ($config["global_auth"]["value"] == "on") {
		/* auth is on: show items based on user logged in */
		$data = db_fetch_assoc("select a.SectionID, a.UserID,
			s.ID, s.Section, 
			c.Name as CName,c.ID, c.ImagePath as CImagePath, 
			i.*
			from auth_acl a 
			left join auth_sections s 
			on a.sectionid=s.id 
			left join menu_items i
			on a.sectionid=i.sectionid 
			left join menu_category c 
			on c.id=i.categoryid 
			where a.userid=$userid 
			and i.menuid=$menuid 
			order by $sql_sort_string");
    }else{
		/* auth is off: show all items */
		$data = db_fetch_assoc("select
			c.name as CName,c.ID, c.ImagePath as CImagePath, 
			i.*
			from menu_items i
			left join menu_category c 
			on c.id=i.categoryid 
			where i.menuid=$menuid 
			order by $sql_sort_string");
    }
    
	print '<tr><td width="100%"><table cellpadding=3 cellspacing=0 border=0 width="100%">';
    
    if (sizeof($data) > 0) {
		foreach ($data as $row) {
		    if ($row[CName] != $old_cat_name) {
				print '<tr><td class="textMenuHeader">' . $row[CName] . '</td></tr>';
				
				$old_cat_name = $row[CName];
		    }
		    
			if ($row[Parent] == ""){
				$parent = "_self";
			}else{
				$parent = $row[Parent];
			}
			
			print '<tr><td class="textMenuItem" background="images/menu_line.gif"><a parent="' . $parent . '" href="' . $row[URL] . '">' . $row[Name] . '</a></td></tr>';
		}
    }
	
	print '</table></td></tr>';
}

?>