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

$current_script_name = basename($HTTP_SERVER_VARS["SCRIPT_NAME"]);

switch ($action) { 
 case 'save':
    	/* only change password when user types on */
	if ($form[Password] != $form[Confirm]) {
		$passwords_do_not_match = true;
	}elseif (($form[Password] == "") && ($form[Confirm] == "")) {
		$password = $form[_password];
	}else{
		$password = "PASSWORD('$form[Password]')";
	}
	
	if ($passwords_do_not_match != true) {
		$save["ID"] = $form["ID"];
		$save["Username"] = $form["Username"];
		$save["FullName"] = $form["FullName"];
		$save["Password"] = $password;
		$save["MustChangePassword"] = $form["MustChangePassword"];
		$save["ShowTree"] = $form["ShowTree"];
		$save["ShowList"] = $form["ShowList"];
		$save["ShowPreview"] = $form["ShowPreview"];
		$save["GraphSettings"] = $form["GraphSettings"];
		$save["LoginOpts"] = $form["LoginOpts"];
		$save["GraphPolicy"] = $form["GraphPolicy"];
	
		sql_save($save, "auth_users");
	}
    
    	header ("Location: $current_script_name");
	break;
 case 'remove':
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
	    db_execute("delete from settings_viewing_tree where userid=$id");
	    db_execute("delete from settings_graph_tree where userid=$id");
	    db_execute("delete from settings_ds_tree where userid=$id");
	}
    
    header ("Location: $current_script_name");
    break;
 case 'edit':
	include_once ("include/top_header.php");
	$title_text = "User Management [edit]";
	include_once ("include/top_table_header.php");
	
	if (isset($args[id])) {
		$user = db_fetch_row("select * from auth_users where id=$id");
	}else{
		unset($user);
	}
	
	DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">User Name</font><br>
			
		</td>
		<?DrawFormItemTextBox('Username',$user[Username],"","");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Full Name</font><br>
			
		</td>
		<?DrawFormItemTextBox('FullName',$user[FullName],"","");?>
	</tr>
    
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Password</font><br>
			
		</td>
		<td>
			<?DrawStrippedFormItemPasswordTextBox("Password","","","","40");?><br>
			<?DrawStrippedFormItemPasswordTextBox("Confirm","","","","40");?>
		</td>
	</tr>
	<?
   // if ($badpass == "true") {
//	DrawFormItem("Password","<font color=\"red\">Passwords do not match! Please retype.</font>");
    //} else{
//	DrawFormItem("Password","");
   // }
   ?>
    
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Account Options</font><br>
			
		</td>
		<td>
		<?
			DrawStrippedFormItemCheckBox("MustChangePassword",$user[MustChangePassword],"User Must Change Password at Next Login","",true);
			DrawStrippedFormItemCheckBox("GraphSettings",$user[GraphSettings],"Allow this User to Keep Custom Graph Settings","on",true);
		?>
		</td>
	</tr>
    
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Graph Options</font><br>
			
		</td>
		<td>
		<?
			DrawStrippedFormItemCheckBox("ShowTree",$user[ShowTree],"User Has Rights to View Tree Mode","on",true);
			DrawStrippedFormItemCheckBox("ShowList",$user[ShowList],"User Has Rights to View List Mode","on",true);
			DrawStrippedFormItemCheckBox("ShowPreview",$user[ShowPreview],"User Has Rights to View Preview Mode","on",true);
		?>
		</td>
	</tr>
    
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Default Policy</font><br>
			The default allow/deny graph policy for this user.
		</td>
		<?
		DrawFormItemDropDownCustomHeader("GraphPolicy");
		DrawFormItemDropDownCustomItem("GraphPolicy","1","Allow",$user[GraphPolicy]);
		DrawFormItemDropDownCustomItem("GraphPolicy","2","Deny",$user[GraphPolicy]);
		DrawFormItemDropDownCustomFooter();
		?>
	</tr>
    
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Login Options</font><br>
			What to do when this user logs in.
		</td>
		<td>
		<?
			DrawStrippedFormItemRadioButton("LoginOpts", $user[LoginOpts], "1", "Show the page that user pointed their browser to.","1",true);
			DrawStrippedFormItemRadioButton("LoginOpts", $user[LoginOpts], "2", "Show the default console screen.","1",true);
			DrawStrippedFormItemRadioButton("LoginOpts", $user[LoginOpts], "3", "Show the default graph screen.","1",true);
		?>
		</td>
	</tr>
	
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right" background="images/blue_line.gif">
			<?DrawFormSaveButton("save");?>
		</td>
	</tr>
	
	<?
	DrawFormItemHiddenIDField("ID",$args[id]);
	DrawFormItemHiddenTextBox("_password",$user[Password],"");
	DrawFormFooter();
	
	include_once ("include/bottom_footer.php");
	break;
 default:
	include_once ("include/top_header.php");
	$title_text = "User Management"; $add_text = "$current_script_name?action=edit";
	include_once ("include/top_table_header.php");
    
	DrawMatrixRowBegin();
		DrawMatrixHeaderItem("User Name",$colors[panel],$colors[panel_text]);
		DrawMatrixHeaderItem("Full Name",$colors[panel],$colors[panel_text]);
		DrawMatrixHeaderItem("Default Graph Policy",$colors[panel],$colors[panel_text]);
	DrawMatrixRowEnd();
    
	$user_list = db_fetch_assoc("select ID,Username,FullName,GraphPolicy from auth_users order by Username");
	
	foreach ($user_list as $user) {
		DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i);
			?>
			<td>
				<a class="linkEditMain" href="<?print $current_script_name;?>?action=edit&id=<?print $user[ID];?>"><?print $user[Username];?></a>
			</td>
			<td>
				<?print $user[FullName];?>
			</td>
			<td>
				<?if ($user[GraphPolicy] == "1") { print "ALLOW"; }else{ print "DENY"; }?>
			</td>
			<td width="1%" align="right">
				<a href="<?print $current_script_name;?>?action=remove&id=<?print $user[ID];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
	<?
	$i++;
	}
    
	include_once ("include/bottom_footer.php");
	include_once ("include/bottom_table_footer.php");
	
	break;
} 
?>
