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
$section = "Add/Edit Graphs"; 
include ('include/auth.php');
header("Cache-control: no-cache");

include_once ("include/functions.php");
include_once ("include/cdef_functions.php");
include_once ("include/config_arrays.php");
include_once ('include/form.php');

if (isset($form[action])) { $action = $form[action]; } else { $action = $args[action]; }
if (isset($form[ID])) { $id = $form[ID]; } else { $id = $args[id]; }

switch ($action) {
	case 'save':
		$redirect_location = form_save();
		
		header ("Location: $redirect_location"); exit;
		break;
	case 'remove':
		host_remove();
		
		header ("Location: host.php");
		break;
	case 'edit':
		include_once ("include/top_header.php");
		
		host_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	default:
		include_once ("include/top_header.php");
		
		host();
		
		include_once ("include/bottom_footer.php");
		break;
}


/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	global $form;
	
	if (isset($form[save_component_host])) {
		host_save();
		return "host.php";
	}
}

   
/* ---------------------
    CDEF Functions
   --------------------- */

function host_remove() {
	global $args, $config;
	
	if (($config["remove_verification"]["value"] == "on") && ($args[confirm] != "yes")) {
		include ('include/top_header.php');
		DrawConfirmForm("Are You Sure?", "Are you sure you want to delete the host <strong>'" . db_fetch_cell("select description from host where id=$args[id]") . "'</strong>?", getenv("HTTP_REFERER"), "host.php?action=remove&id=$args[id]");
		include ('include/bottom_footer.php');
		exit;
	}
	
	if (($config["remove_verification"]["value"] == "") || ($args[confirm] == "yes")) {
		db_execute("delete from host where id=$args[id]");
	}
}

function host_save() {
	global $form;
	
	$save["id"] = $form["id"];
	$save["host_template_id"] = $form["host_template_id"];
	$save["description"] = $form["description"];
	$save["hostname"] = $form["hostname"];
	$save["management_ip"] = $form["management_ip"];
	$save["snmp_community"] = $form["snmp_community"];
	$save["snmp_version"] = $form["snmp_version"];
	$save["snmp_username"] = $form["snmp_username"];
	$save["snmp_password"] = $form["snmp_password"];
	
	if (sql_save($save, "host")) {
		raise_message(1);
	}else{
		raise_message(2);
		header("Location: " . $_SERVER["HTTP_REFERER"]);
		exit;
	}
}

function host_edit() {
	global $args, $colors, $snmp_versions;
	
	display_output_messages();
	
	start_box("<strong>Polling Hosts [edit]</strong>", "", "");
	
	if (isset($args[id])) {
		$host = db_fetch_row("select * from host where id=$args[id]");
	}else{
		unset($host);
	}
	
	?>
	<form method="post" action="host.php">
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Description</font><br>
			Give this host a meaningful description.
		</td>
		<?DrawFormItemTextBox("description",$host[description],"","250", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Host Template</font><br>
			Choose what type of host, host template this is. The host template will govern what kinds
			of data should be gathered from this type of host.
		</td>
		<?DrawFormItemDropdownFromSQL("host_template_id",db_fetch_assoc("select id,name from host_template"),"name","id",$host[host_template_id],"None","1");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">Hostname</font><br>
			Fill in the fully qualified hostname for this device.
		</td>
		<?DrawFormItemTextBox("hostname",$host[hostname],"","250", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">Management IP</font><br>
			Choose the IP address that will be used to gather data from this host. The hostname will be
			used a fallback in case this fails.
		</td>
		<?DrawFormItemTextBox("management_ip",$host[management_ip],"","15", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">SNMP Community</font><br>
			Fill in the SNMP read community for this device.
		</td>
		<?DrawFormItemTextBox("snmp_community",$host[snmp_community],"","15", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">SNMP Username</font><br>
			Fill in the SNMP username for this device (v3).
		</td>
		<?DrawFormItemTextBox("snmp_username",$host[snmp_username],"","50", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],0); ?>
		<td width="50%">
			<font class="textEditTitle">SNMP Community</font><br>
			Fill in the SNMP password for this device (v3).
		</td>
		<?DrawFormItemTextBox("snmp_password",$host[snmp_password],"","50", "40");?>
	</tr>
	
	<?DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],1); ?>
		<td width="50%">
			<font class="textEditTitle">SNMP Version</font><br>
			Choose the SNMP version for this host.
		</td>
		<?DrawFormItemDropdownFromSQL("snmp_version",$snmp_versions,"","",$host[snmp_version],"","1");?>
	</tr>
	
	<?
	DrawFormItemHiddenIDField("id",$args[id]);
	end_box();
	
	DrawFormItemHiddenTextBox("save_component_host","1","");
	
	start_box("", "", "");
	?>
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right">
			<?DrawFormSaveButton("save", "host.php");?>
		</td>
	</tr>
	</form>
	<?
	end_box();	
}

function host() {
	global $colors;
	
	display_output_messages();
	
	start_box("<strong>Polling Hosts</strong>", "", "host.php?action=edit");
	                         
	print "<tr bgcolor='#$colors[header_panel]'>";
		DrawMatrixHeaderItem("Description",$colors[header_text],1);
		DrawMatrixHeaderItem("Hostname",$colors[header_text],1);
		DrawMatrixHeaderItem("&nbsp;",$colors[header_text],1);
	print "</tr>";
    
	$hosts = db_fetch_assoc("select id,hostname,description from host order by description");
	
	if (sizeof($hosts) > 0) {
	foreach ($hosts as $host) {
		DrawMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$i); $i++;
			?>
			<td>
				<a class="linkEditMain" href="host.php?action=edit&id=<?print $host[id];?>"><?print $host[description];?></a>
			</td>
			<td>
				<?print $host[hostname];?>
			</td>
			<td width="1%" align="right">
				<a href="host.php?action=remove&id=<?print $host[id];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
	<?
	}
	}
	end_box();	
}
?>
