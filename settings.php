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
$section = "User Administration"; 
include ('include/auth.php');
header("Cache-control: no-cache");

include_once ("include/config_settings.php");
include_once ("include/functions.php");
include_once ('include/form.php');

switch ($action) {
 case 'save':
	if (sizeof($settings) > 0) {
	foreach (array_keys($settings) as $setting) {
		if ($settings[$setting][tab] == $form[tab]) {
			if ($settings[$setting][method] == "group") {
				if (sizeof($settings[$setting][items]) > 0) {
				foreach (array_keys($settings[$setting][items]) as $item) {
					db_execute("replace into settings (name,value) values ('$item', '$form[$item]')");
				}
				}
			}else{
				db_execute("replace into settings (name,value) values ('$setting', '$form[$setting]')");
			}
		}
	}
	}

	header ("Location: settings.php?tab=$form[tab]");
	break;
 default:
    	include_once ('include/top_header.php');
	
	if (!(isset($args[tab]))) {
		/* there is no selected tab; select the first one */
		$current_tab = array_keys($tabs);
		$current_tab = $current_tab[0];
	}else{
		$current_tab = $args[tab];
	}
	
	$title_text = "cacti Settings (" . $tabs[$current_tab] . ")"; include_once ("include/top_table_header.php");
	
	?>
	
			<tr height="33">
				<td valign="bottom" colspan="3" background="images/tab_back.gif">
					<table border="0" cellspacing="0" cellpadding="0">
						<tr>
						<?
						if (sizeof($tabs) > 0) {
						foreach (array_keys($tabs) as $tab_short_name) {
						?>
							<td nowrap class="textTab" align="center" background="images/tab_middle.gif">
								<img src="images/tab_left.gif" border="0" align="absmiddle"><a href="settings.php?tab=<?print $tab_short_name;?>"><?print $tabs[$tab_short_name];?></a><img src="images/tab_right.gif" border="0" align="absmiddle">
							</td>
						<?
						}
						}
						?>
					</table>
				</td>
			</tr>
	
	<?
	
    	print "<form method='post' action='settings.php?action=save'>\n";
	
	if (sizeof($settings) > 0) {
	foreach (array_keys($settings) as $setting) {
	    	/* make sure to skip group members here; only parents are allowed */
		if (($settings[$setting][method] != "internal") && ($settings[$setting][tab] == $current_tab)) {
			++$i;
			DrawMatrixRowAlternateColorBegin($colors[form_alternate1],$colors[form_alternate2],$i);
			
			/* draw the acual header and textbox on the form */
			DrawFormItem($settings[$setting][friendly_name],$settings[$setting][description]);
			
			$current_value = db_fetch_cell("select value from settings where name='$setting'");
			
			/* choose what kind of item this is */
			switch ($settings[$setting][method]) {
				case 'textbox':
					DrawFormItemTextBox($setting,$current_value,"","");
					print "</tr>\n";
					break;
				case 'checkbox':
					DrawFormItemCheckBox($setting,$current_value,$settings[$setting][friendly_name],"");
					print "</tr>\n";
					break;
				case 'group':
					print "<td>\n";
					
		    			/* loop through the resultset and draw each item in the group */
					if (sizeof($settings[$setting][items]) > 0) {
					foreach (array_keys($settings[$setting][items]) as $item) {
						$current_value = db_fetch_cell("select value from settings where name='$item'");
						
			    			switch ($settings[$setting][items][$item][method]) {
							case 'textbox':
								DrawtrippedFormItemTextBox($item,$current_value,"","");
								break;
							case 'checkbox':
								DrawStrippedFormItemCheckBox($item,$current_value,$settings[$setting][items][$item][description],"",true);
								break;
						}
			    
					}
					}
					
					print "</td></tr>\n";
					break;
			}
		}
	
	}
	}
	
	?>
		<tr bgcolor="#FFFFFF">
			 <td colspan="2" align="right" background="images/blue_line.gif">
				<?DrawFormSaveButton("save");?>
			</td>
		</tr>
	<?
	DrawFormItemHiddenIDField("tab",$current_tab);
	
	include_once ("include/bottom_table_footer.php");
	include_once ("include/bottom_footer.php");
	
	break;
} ?>	
			
