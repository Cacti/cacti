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
$section = "User Administration"; 
include ('include/auth.php');
include_once ("include/form.php");

if ($form[action]) { $action = $form[action]; } else { $action = $args[action]; }
if ($form[ID]) { $id = $form[ID]; } else { $id = $args[id]; }

switch ($action) { 
 case 'save':
    /* only change password when user types on */
    if (($Password == "") && ($Confirm == "")) {
		$password_to_save = "\"$OldPass\"";
    }else{
		$password_to_save = "PASSWORD(\"$Password\")";
    }
    
    if ($form[GraphSettings] == '') { $form[GraphSettings] = 'off'; }
	
    if ($password == $confirm){
		db_execute("replace into auth_users (id,fullname,username,password,
			mustchangepassword,showtree,showlist,showpreview,graphsettings,loginopts,graphpolicy) 
	  		values($form[ID],\"$form[FullName]\",\"$form[Username]\",$password_to_save,\"$form[MustChangePassword]\",
			\"$form[ShowTree]\",\"$form[ShowList]\",\"$form[ShowPreview]\",\"$form[GraphSettings]\",$form[LoginOpts],$form[GraphPolicy])");
		header("Location: $PHP_SELF"); 
		exit;
    }else{
		$badpass = true;
		header("Location: $PHP_SELF?action=edit&id=$id&badpass=true"); exit;
    }
    break;
 case 'delete':
	if (($config["remove_verification"]["value"] == "on") && ($confirm != "yes")) {
		include_once ('include/top_header.php');
		DrawConfirmForm("Are You Sure?", "Are you sure you want to delete this user?", $current_script_name, "?action=remove&id=$id");
		exit;
	}
	
	if (($config["remove_verification"]["value"] == "") || ($confirm == "yes")) {
	    db_execute("delete from auth_users where id=$id");
	    db_execute("delete from auth_acl where userid=$id");
	    db_execute("delete from auth_hosts where userid=$id");
	    db_execute("delete from auth_graph where userid=$id");
	    db_execute("delete from auth_graph_hierarchy where userid=$id");
	    db_execute("delete from settings_graphs where userid=$id");
	    db_execute("delete from settings_tree where userid=$id");
	}
    
    header("Location: $PHP_SELF"); exit;
    break;
 case 'edit':
    include_once ("include/top_header.php");
    
    $user = db_fetch_row("select * from auth_users where id=$id");
    if ($id != "") { $pass = $user[Password]; }
    
    DrawFormHeader("Edit User Account Form","",false);
    
    DrawFormItem("Full Name","");
    DrawFormItemTextBox('FullName',$user[FullName],"","");
    
    DrawFormItem("User Name","");
    DrawFormItemTextBox('Username',$user[Username],"","");
    
    if ($badpass == "true") {
	DrawFormItem("Password","<font color=\"red\">Passwords do not match! Please retype.</font>");
    } else{
	DrawFormItem("Password","");
    }
    DrawFormItemPasswordTextBox("Password","","","");
    DrawFormItemPasswordTextBox("Confirm","","","");
    
    DrawFormItem("Account Options","");
    DrawFormItemCheckBox("MustChangePassword",$user[MustChangePassword],"User Must Change Password at Next Login","");
    DrawFormItemCheckBox("GraphSettings",$user[GraphSettings],"Allow this User to Keep Custom Graph Settings","on");
    
    DrawFormItem("Graph Options","");
    DrawFormItemCheckBox("ShowTree",$user[ShowTree],"User Has Rights to View Tree Mode","on");
    DrawFormItemCheckBox("ShowList",$user[ShowList],"User Has Rights to View List Mode","on");
    DrawFormItemCheckBox("ShowPreview",$user[ShowPreview],"User Has Rights to View Preview Mode","on");

    DrawFormItem("Default Policy","The default allow/deny graph policy for this user (changing this value will clear the current graph permissions for this user).");
    DrawFormItemDropDownCustomHeader("GraphPolicy");
    DrawFormItemDropDownCustomItem("GraphPolicy","1","Allow",$user[GraphPolicy]);
    DrawFormItemDropDownCustomItem("GraphPolicy","2","Deny",$user[GraphPolicy]);
    DrawFormItemDropDownCustomFooter();
    
    DrawFormItem("Login","What to do when this user logs in.");
    DrawFormItemRadioButton("LoginOpts", $user[LoginOpts], "1", "Show the page that user pointed their browser to.","1");
    DrawFormItemRadioButton("LoginOpts", $user[LoginOpts], "2", "Show the default console screen.","1");
    DrawFormItemRadioButton("LoginOpts", $user[LoginOpts], "3", "Show the default graph screen.","1");
    
    DrawFormSaveButton();
    DrawFormItemHiddenIDField("ID",$id);
    DrawFormItemHiddenTextBox("OldPass",$pass,"");
    DrawFormFooter();
    break;
 default:
    include_once ("include/top_header.php");
    
    DrawMatrixTableBegin(false);
    
    DrawMatrixRowBegin();
    DrawMatrixHeaderTop("Current Users",$colors[dark_bar],$colors[panel_text],"5");
    DrawMatrixHeaderAdd($colors[dark_bar],"","");
    DrawMatrixRowEnd();
    
    DrawMatrixRowBegin();
    DrawMatrixHeaderItem("User Name",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("Full Name",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("Realm Security",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("IP Security",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("Graph Permissions",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderItem("",$colors[panel],$colors[panel_text]);
    DrawMatrixRowEnd();
    
    $users = db_fetch_assoc("select * from auth_users order by username");
    $rows = sizeof($users);
    $i = 0;
    if (sizeof($users) > 0) {
	foreach ($users as $user) {
	    DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i);
	    DrawMatrixLoopItem($user[Username],html_boolean($config["vis_main_column_bold"]["value"]),"$PHP_SELF?action=edit&id=$user[ID]");
	    DrawMatrixLoopItem($user[FullName],false,"");
	    DrawMatrixLoopItem("Allowed Sections",false,"user_admin_permissions.php?id=$user[ID]");
	    DrawMatrixLoopItem("IP Security",false,"user_admin_ip.php?id=$user[ID]");
	    DrawMatrixLoopItem("Graph Permissions",false,"user_admin_graphs.php?id=$user[ID]");
	    DrawMatrixLoopItemAction("Remove",$colors[panel],"",false,"$PHP_SELF?action=delete&id=$user[ID]");
	    DrawMatrixRowEnd();
	    $i++;
	}
    }
    
    DrawMatrixTableEnd();
    DrawBodyFooter(true);
    
    break;
} 
?>
