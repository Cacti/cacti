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
header("Cache-control: no-cache");
$section = "User Administration"; include ('include/auth.php');

if (isset($form[action])) { $action = $form[action]; } else { $action = $args[action]; }
if (isset($form[ID])) { $id = $form[ID]; } else { $id = $args[id]; }

switch ($action) {
 case 'save':
    db_execute("delete from auth_acl where userid=$id");
    
    if(isset($HTTP_POST_VARS)) {
	while(list($var, $val) = each($HTTP_POST_VARS)) {
	    if ($var != "ID") {
		if ($var != "action") {
		    db_execute("replace into auth_acl (userid,sectionid) values($id,$var)");
		}
	    }
	    $i++;
	}
    }
    
    header('Location: user_admin.php'); 
    exit;
    break;
 default:
    include_once ('include/form.php');
    include_once ('include/top_header.php');
    
    $perms = db_fetch_assoc("select ac.UserID, s.ID, s.Section, a.Name from auth_sections s left join 
			    auth_areas a on s.areaid=a.id left join auth_acl ac on (s.id=ac.sectionid and 
								    ac.UserID=$id) order by a.Name,s.section");
    
    DrawFormHeader("Edit User Permissions","",false);
    DrawFormItem("","Select or deselect the permissions that you want this user to have.");
    
    if (sizeof($perms) > 0) {
	foreach ($perms as $perm) {
	    if ($perm[Name] != $old_area_name){
		/* new area */
		DrawFormItem($perm[Name],"");
		$old_area_name = $perm[Name];
	    }
	
	    if ($perm[UserID] == "") {
		$old_value = "";
	    } else {
		$old_value = "on";
	    }
	
	    DrawFormItemCheckBox($perm[ID], $old_value, $perm[Section],"");
	}
    }
    
    DrawFormSaveButton();
    DrawFormItemHiddenIDField("ID",$id);
    DrawFormFooter();
    
    break;
} 
?>	
		
