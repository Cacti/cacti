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
<? 	header("Cache-control: no-cache");
	$section = "User Administration"; include ('include/auth.php');
	include_once ('include/form.php');

if (isset($form[action])) { $action = $form[action]; } else { $action = $args[action]; }
if (isset($form[ID])) { $id = $form[ID]; } else { $id = $args[id]; }
if (isset($form[UID])) { $uid = $form[UID]; } else { $uid = $args[uid]; }

switch ($action) {
	case 'save':
		db_execute("replace into auth_hosts (id,hostname,userid,type) values($id,\"$form[Hostname]\",$form[UserID],$form[Type])");
		
		header("Location: $PHP_SELF?id=$uid"); exit;
		break;
	case 'delete':
		db_execute("delete from auth_hosts where id=$id");
		
		header("Location: $PHP_SELF?id=$uid"); exit;
		break;
	case 'edit':
		include_once ('include/top_header.php');
		
		if ($id != "") {
			$authhost = db_fetch_row("select * from auth_hosts where id=$id");
		}
		
		DrawFormHeader("Edit Hostname","",false);
		
		DrawFormItem("Hostname","");
		DrawFormItemTextBox("Hostname",$authhost[Hostname],"","");
		
		DrawFormSaveButton();
		DrawFormItemHiddenIDField("ID",$id);
		DrawFormItemHiddenIDField("Type",$type);
		DrawFormItemHiddenIDField("UserID",$uid);
		DrawFormFooter();
		
		break;
	default:
		include_once ("include/top_header.php");
		
		DrawMatrixTableBegin(false);
		
		DrawMatrixRowBegin();
			DrawMatrixHeaderTop("IP Security (Listed by Precedence)",$colors[dark_bar],$colors[panel_text],"1");
			DrawMatrixHeaderTop("&nbsp;",$colors[dark_bar],$colors[panel_text],"1");
		DrawMatrixRowEnd();
		
		DrawMatrixRowBegin();
			DrawMatrixHeaderItem("Deny Only These IP's",$colors[panel],$colors[panel_text]);
			DrawMatrixHeaderAdd($colors[panel],"","$PHP_SELF?action=edit&uid=$id&type=1");
		DrawMatrixRowEnd();
		
		$hosts = db_fetch_assoc("select * from auth_hosts where type=1 and userid=$id order by hostname");
    if (sizeof($hosts) > 0) {
	foreach ($hosts as $host) {
	    
	    DrawMatrixRowBegin();
	    DrawMatrixLoopItem($host[Hostname],false,"$PHP_SELF?action=edit&id=$host[ID]&uid=$id&type=1");
	    DrawMatrixLoopItemAction("Delete",$colors[panel],$colors[panel_text],false,"$PHP_SELF?action=delete&id=$host[ID]&uid=$id");
	    DrawMatrixRowEnd();
	}
    }
    DrawMatrixRowBegin();
    DrawMatrixHeaderItem("Only Allow These IP's",$colors[panel],$colors[panel_text]);
    DrawMatrixHeaderAdd($colors[panel],"","$PHP_SELF?action=edit&uid=$id&type=2");
    DrawMatrixRowEnd();
		
    $hosts2 = db_fetch_assoc("select * from auth_hosts where type=2 and userid=$id order by hostname");
    if (sizeof($hosts2) > 0) {
	foreach ($hosts2 as $host) {
	    DrawMatrixRowBegin();
	    DrawMatrixLoopItem($host[Hostname],false,"$PHP_SELF?action=edit&id=$host[ID]&uid=$id&type=2");
	    DrawMatrixLoopItemAction("Remove",$colors[panel],"",false,"$PHP_SELF?action=delete&id=$host[ID]&uid=$id");
	    DrawMatrixRowEnd();
	}
    }
    DrawMatrixTableEnd();
    DrawBodyFooter(true);
    
    break;
} ?>	
