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

include_once ("include/functions.php");
include_once ('include/form.php');

switch ($action) {
 case 'save':
    $settings = db_fetch_assoc("select Name from settings");
    if (sizeof($settings) > 0) {
	foreach ($settings as $setting) {
	    db_execute("update settings set value=\"" . ${$setting[Name]} .
			"\" where name=\"$setting[Name]\"");
	}
    }
    
    header ("Location: settings.php");
    break;
 default:
    include_once ('include/top_header.php');
    
    $settings = db_fetch_assoc("select * from settings order by name");
    
    DrawFormHeader("cacti Settings","",false);
    if (sizeof($settings) > 0) {
	foreach ($settings as $setting) {
	    /* split appart the 'method' on the ':' */
	    $setting_method = split(":",$setting[Method]);
	    
	    /* make sure to skip group members here; only parents are allowed */
	    if ($setting_method[1] != "group") {
		/* draw the acual header and textbox on the form */
		DrawFormItem($setting[FriendlyName],$setting[Description]);
		
		/* choose what kind of item this is */
		switch ($setting_method[0]) {
		 case 'textbox':
		    DrawFormItemTextBox($setting[Name],$setting[Value],"","");
		    break;
		 case 'checkbox':
		    DrawFormItemCheckBox($setting[Name],$setting[Value],$setting[Description],"");
		    break;
		 case 'group':
		    /* use 'o' for the internal group counter...remeber we can have unlimited items
		     under each item (hence the group). */
		    $o = 1; 
		    $first = " WHERE"; 
		    $sql_where = "";
		    
		    /* loop through each item in the group to create the OR SQL where clause */
		    while ($o < count($setting_method)) {
			$sql_where .= "$first name=\"$setting_method[$o]\"";
			$first = " OR";
			$o++;
		    }
		    
		    /* pass the SQL where clause create above to MySQL */
		    $group_list = db_fetch_assoc("select * from settings $sql_where");
		    
		    /* loop through the resultset and draw each item in the group */
		    if (sizeof($group_list) > 0) {
			foreach ($group_list as $item) {
			    /* once again split apart the 'method' for this group item */
			    $setting_method_group = split(":",$item[Method]);
			    
			    switch ($setting_method_group[0]) {
			     case 'textbox':
				DrawFormItemTextBox($item[Name],$item[Value],"","");
				break;
			     case 'checkbox':
				DrawFormItemCheckBox($item[Name],$item[Value],$item[Description],"");
				break;
			    }
			    
			}
		    }
		    
		    break;
		}
	    }
	    
	}
    }
    DrawFormSaveButton();
    DrawFormFooter();
    
    include_once ("include/bottom_footer.php");
    break;
} ?>	
			
