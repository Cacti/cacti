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

switch ($action) {
 case 'save':
    db_execute ("delete from auth_graph where userid=$id");
    db_execute ("delete from auth_graph_hierarchy where userid=$id");
    
    if(isset($HTTP_POST_VARS)) {
	while(list($var, $val) = each($HTTP_POST_VARS)) {
	    if (($var != "id") && ($var != "action")) {
		if (substr($var, 0, 5) == "graph") {
		    db_execute ("replace into auth_graph (userid,graphid) values($id," . substr($var, 5) . ")");
		}elseif (substr($var, 0, 4) == "tree") {
		    db_execute ("replace into auth_graph_hierarchy (userid,hierarchyid) values($id," . substr($var, 4) . ")");
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
    
    /* ------------------- GRAPH PERMISSIONS ------------------- */
    
    /* find out what default policy this user has */
    $graph_policy = db_fetch_cell("select GraphPolicy from auth_users where id=$id","GraphPolicy");
    
    if ($graph_policy == "1") {
	$policy_display = "Select the graphs you want to <strong>DENY</strong> this user from.";
    } elseif ($graph_policy == "2") {
	$policy_display = "Select the graphs you want <strong>ALLOW</strong> this user to view.";
    }
    
    $graphs = db_fetch_assoc("select 
			       ag.UserID,
			       g.ID, g.Title 
			       from rrd_graph g
			       left join auth_graph ag on (g.id=ag.graphid and ag.userid=$id) 
				 order by g.title");
    $rows = sizeof($graphs);
    
    DrawFormHeader("Individual Graph Permissions","",false);
    DrawFormItem("", $policy_display);
    
    DrawMatrixCustom("<td width=\"100%\">");
    DrawMatrixTableBegin("100%");
    DrawMatrixRowBegin();
    DrawMatrixCustom("<td valign=\"top\" width=\"50%\">");
    
    if (sizeof($graphs) > 0) {
	foreach ($graphs as $graph) {
	    if ($graph[UserID] == "") {
		$old_value = "";
	    }else{
		$old_value = "on";
	    }
	    
	    $column1 = floor(($rows / 2) + ($rows % 2));
	    if ($i == $column1) {
		print "</td><td valign='top' width='50%'>";
	    }
			
	    DrawStrippedFormItemCheckBox("graph".$graph[ID], $old_value, $graph[Title],"");
	    
	    $i++;
	}
    }
		
    DrawMatrixCustom("</td>");
    DrawMatrixRowEnd();
    DrawMatrixTableEnd();
		
    /* ------------------- GRAPH HIERARCHIES ------------------- */
    
    if ($graph_policy == "1") {
	$policy_display = "Select the graph hierarchies that you want to <strong>HIDE</strong> from this user.";
    } elseif ($graph_policy == "2") {
	$policy_display = "Select the graph hierarchies that you want to <strong>ALLOW</strong> this user to view.";
    }
    
    $graphs2 = db_fetch_assoc("select 
			    agh.UserID, 
			    gh.ID, gh.Name
			    from graph_hierarchy gh
			    left join auth_graph_hierarchy agh on (gh.id=agh.hierarchyid and agh.userid=$id) 
			      order by gh.name");
    $rows = sizeof($graphs2);
    $i = 0;
    
    DrawFormItem("", $policy_display);
    
    DrawMatrixCustom("<td width=\"100%\">");
    DrawMatrixTableBegin("100%");
    DrawMatrixRowBegin();
    DrawMatrixCustom("<td valign=\"top\" width=\"50%\">");
    
    if (sizeof($graphs) > 0) {
	foreach ($graphs2 as $graph) {
	    if ($graph[UserID] == "") {
		$old_value = "";
	    } else {
		$old_value = "on";
	    }
	    
	    $column1 = floor(($rows / 2) + ($rows % 2));
	    if ($i == $column1) {
		print "</td><td valign='top' width='50%'>\n";
	    }
	
	    DrawStrippedFormItemCheckBox("tree" . $graph[ID], $old_value, $graph[Name],"");
	    $i++;
	}
    }
    DrawMatrixCustom("</td>");
    DrawMatrixRowEnd();
    DrawMatrixTableEnd();
    
    
    DrawFormSaveButton();
    DrawFormItemHiddenIDField("ID",$id);
    DrawFormFooter();
    
    break;
} ?>
