<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2003 Ian Berry                                            |
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
 | cacti: a php-based graphing solution                                    |
 +-------------------------------------------------------------------------+
 | Most of this code has been designed, written and is maintained by       |
 | Ian Berry. See about.php for specific developer credit. Any questions   |
 | or comments regarding this code should be directed to:                  |
 | - iberry@raxnet.net                                                     |
 +-------------------------------------------------------------------------+
 | - raXnet - http://www.raxnet.net/                                       |
 +-------------------------------------------------------------------------+
*/

$section = "Add/Edit Graphs"; include ('include/auth.php');

include_once ('include/form.php');
include_once ('include/functions.php');

switch ($_REQUEST["action"]) {
	case 'save':
		$redirect_location = form_save();
		
		header ("Location: $redirect_location"); exit;
		break;
	case 'item_movedown':
		item_movedown();
		
		header ("Location: " . getenv("HTTP_REFERER"));
		break;
	case 'item_moveup':
		item_moveup();
		
		header ("Location: " . getenv("HTTP_REFERER"));
		break;
	case 'item_edit':
		include_once ("include/top_header.php");
		
		item_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	case 'item_remove':
		item_remove();

		header ("Location: " . getenv("HTTP_REFERER"));
                break;
    	case 'remove':
		tree_remove();	
		
		header ("Location: tree.php");
		break;
	case 'edit':
		include_once ("include/top_header.php");
		
		tree_edit();
		
		include_once ("include/bottom_footer.php");
		break;
	default:
		include_once ("include/top_header.php");
		
		tree();
		
		include_once ("include/bottom_footer.php");
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset($_POST["save_component_tree"])) {
		tree_save();
		return "tree.php";
	}elseif (isset($_POST["save_component_tree_item"])) {
		item_save();
		return "tree.php?action=edit&id=" . $_POST["graph_tree_id"];
	}
}

/* -----------------------
    Tree Item Functions
   ----------------------- */

function item_edit() {
	include_once('include/tree_functions.php');
	include_once('include/tree_view_functions.php');
	
	global $colors;
	
	if (isset($_GET["tree_item_id"])) {
		$tree_item = db_fetch_row("select * from graph_tree_items where id=" . $_GET["tree_item_id"]);
	}
	
	/* bold the active "type" */
	if ($tree_item["local_graph_id"] > 0) { $title = "<strong>Tree Item [graph]</strong>"; }else{ $title = "Tree Item [graph]"; }
	
	print "<form method='post' action='tree.php'>\n";
	
	start_box("<strong>Tree Item</strong>", "98%", $colors["header"], "3", "center", "");
	
	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Parent Item</font><br>
			Choose the parent for this header/graph.
		</td>
		<td>
			<?php grow_dropdown_tree($_GET["tree_id"], "parent_item_id", ($_GET["parent_id"] ? $_GET["parent_id"] : get_parent_id($tree_item["id"], "graph_tree_items")));?>
		</td>
	</tr>
	<?php
	
	end_box();
	
	start_box($title, "98%", $colors["header"], "3", "center", "");
	
	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Graph</font><br>
			Choose a graph from this list to add it to the tree.
		</td>
		<?php form_dropdown("local_graph_id",db_fetch_assoc("select local_graph_id,title from graph_templates_graph where local_graph_id != 0 order by title"),"title","local_graph_id",$tree_item["local_graph_id"],"None","");?>
	</tr>
	
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],1); ?>
		<td width="50%">
			<font class="textEditTitle">Round Robin Archive</font><br>
			Choose a round robin archive to control how this graph is displayed.
		</td>
		<?php form_dropdown("rra_id",db_fetch_assoc("select id,name from rra"),"name","id",$tree_item["rra_id"],"None","1");?>
	</tr>
	
	<?php
	
	end_box();
	
	/* bold the active "type" */
	if ($tree_item["title"] != "") { $title = "<strong>Tree Item [header]</strong>"; }else{ $title = "Tree Item [header]"; }
	
	start_box($title, "98%", $colors["header"], "3", "center", "");
	
	form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Header Title</font><br>
			If this item is a header, enter a title here.
		</td>
		<?php form_text_box("title",$tree_item["title"],"","255","40");?>
	</tr>
	<?php
	
	end_box();
	
	form_hidden_id("id",$_GET["tree_item_id"]);
	form_hidden_id("graph_tree_id",$_GET["tree_id"]);
	form_hidden_box("save_component_tree_item","1","");
	
	start_box("", "98%", $colors["header"], "3", "center", "");
	?>
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right">
			<?php form_save_button("save", "tree.php?action=edit&id=" . $_GET["tree_id"]);?>
		</td>
	</tr>
	</form>
	<?php
	end_box();
}

function item_moveup() {
	include_once('include/tree_functions.php');
	
	$order_key = db_fetch_cell("SELECT order_key FROM graph_tree_items WHERE id=" . $_GET["tree_item_id"]);
	if ($order_key > 0) { branch_up($order_key, 'graph_tree_items', 'order_key', ''); }
}

function item_movedown() {
	include_once('include/tree_functions.php');
	
	$order_key = db_fetch_cell("SELECT order_key FROM graph_tree_items WHERE id=" . $_GET["tree_item_id"]);
	if ($order_key > 0) { branch_down($order_key, 'graph_tree_items', 'order_key', ''); }
}

function item_save() {
	include_once("include/tree_functions.php");
	
	if (empty($_POST["id"])) {
		/* new/save - generate new order key */
		$order_key = get_next_tree_id(db_fetch_cell("select order_key from graph_tree_items where id=" . $_POST["parent_item_id"]),"graph_tree_items","order_key");
	}else{
		/* edit/save - use old order_key */
		$order_key = db_fetch_cell("select order_key from graph_tree_items where id=" . $_POST["id"]);
	}
	
	$save["id"] = $_POST["id"];
	$save["graph_tree_id"] = $_POST["graph_tree_id"];
	$save["title"] = $_POST["title"];
	$save["order_key"] = $order_key;
	$save["local_graph_id"] = $_POST["local_graph_id"];
	$save["rra_id"]	= $_POST["rra_id"];

	if (sql_save($save, "graph_tree_items")) {
		raise_message(1);
		
		reparent_branch($_POST["parent_item_id"], $_POST["id"]);
	}else{
		raise_message(2);
                header("Location: " . $_SERVER["HTTP_REFERER"]);
                exit;
	}
}

function item_remove() {
	include_once("include/tree_functions.php");
	
        if ((read_config_option("remove_verification") == "on") && ($_GET["confirm"] != "yes")) {
                include ('include/top_header.php');
                form_confirm("Are You Sure?", "Are you sure you want to delete the item <strong>'" . db_fetch_cell("select title from graph_tree_items where id=" . $_GET["id"]) . "'</strong>?", getenv("HTTP_REFERER"), "tree.php?action=item_remove&id=" . $_GET["id"] . "&tree_id=" . $_GET["tree_id"]);
                include ('include/bottom_footer.php');
                exit;
        }

        if ((read_config_option("remove_verification") == "") || ($_GET["confirm"] == "yes")) {
               delete_branch($_GET["id"]);
        }
	
	header("Location: tree.php?action=edit&id=" . $_GET["tree_id"]); exit;
}


/* ---------------------
    Tree Functions
   --------------------- */

function tree_save() {
        $save["id"] = $_POST["id"];
        $save["name"] = $_POST["name"];

        if (sql_save($save, "graph_tree")) {
                raise_message(1);
        }else{
                raise_message(2);
                header("Location: " . $_SERVER["HTTP_REFERER"]);
                exit;
        }
}

function tree_remove() {
	global $config;

        if ((read_config_option("remove_verification") == "on") && ($_GET["confirm"] != "yes")) {
                include ('include/top_header.php');
                form_confirm("Are You Sure?", "Are you sure you want to delete the tree <strong>'" . db_fetch_cell("select name from graph_tree where id=" . $_GET["id"]) . "'</strong>?", getenv("HTTP_REFERER"), "tree.php?action=remove&id=" . $_GET["id"]);
                include ('include/bottom_footer.php');
                exit;
        }
                
        if ((read_config_option("remove_verification") == "") || ($_GET["confirm"] == "yes")) {
                db_execute("delete from graph_tree where id=" . $_GET["id"]);
        }
}

function tree_edit() {
	include_once("include/tree_view_functions.php");
	
	global $colors, $cdef_item_types;
	
	start_box("<strong>Trees [edit]</strong>", "98%", $colors["header"], "3", "center", "");
	
	if (isset($_GET["id"])) {
		$tree = db_fetch_row("select * from graph_tree where id=" . $_GET["id"]);
	}else{
		unset($tree);
	}
	
	?>
	<form method="post" action="tree.php">
	
	<?php form_alternate_row_color($colors["form_alternate1"],$colors["form_alternate2"],0); ?>
		<td width="50%">
			<font class="textEditTitle">Name</font><br>
			A useful name for this graph tree.
		</td>
		<?php form_text_box("name",$tree["name"],"","255", "40");?>
	</tr>
	
	<?php
	form_hidden_id("id",$_GET["id"]);
	end_box();
	
	start_box("Tree Items", "98%", $colors["header"], "3", "center", "tree.php?action=item_edit&tree_id=" . $tree["id"] . "&parent_id=0");
	grow_edit_graph_tree($_GET["id"], "", "");
	end_box();
	
	form_hidden_box("save_component_tree","1","");
	
	start_box("", "98%", $colors["header"], "3", "center", "");
	?>
	<tr bgcolor="#FFFFFF">
		 <td colspan="2" align="right">
			<?php form_save_button("save", "tree.php");?>
		</td>
	</tr>
	</form>
	<?php
	end_box();
}

function tree() {
	global $colors;
	
	start_box("<strong>Graph Trees</strong>", "98%", $colors["header"], "3", "center", "tree.php?action=edit");
	                         
	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
		DrawMatrixHeaderItem("Name",$colors["header_text"],1);
		DrawMatrixHeaderItem("&nbsp;",$colors["header_text"],1);
	print "</tr>";
    
	$trees = db_fetch_assoc("select * from graph_tree order by name");
	
	if (sizeof($trees) > 0) {
	foreach ($trees as $tree) {
		form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
			?>
			<td>
				<a class="linkEditMain" href="tree.php?action=edit&id=<?php print $tree["id"];?>"><?php print $tree["name"];?></a>
			</td>
			<td width="1%" align="right">
				<a href="tree.php?action=remove&id=<?php print $tree["id"];?>"><img src="images/delete_icon.gif" width="10" height="10" border="0" alt="Delete"></a>&nbsp;
			</td>
		</tr>
	<?php
	}
	}
	end_box();	
}
 ?>
