<?/* 
+-------------------------------------------------------------------------+
| raXnet cacti: the rrdtool frontend                                      |
+-------------------------------------------------------------------------+
| This code was crafted by Ian Berry, make sure any questions             |
| about the structure or integrity of this code be directed to:           |
| - rax@kuhncom.net                                                       |
| - iberry@onion.dyndns.org                                               |
+-------------------------------------------------------------------------+
| raXnet home: http://raxnet.sourceforge.net                              |
+-------------------------------------------------------------------------+
| Function Library Documentation:                                         |
|                                                                         |
+   ["options"]["sql_type_column"] - the name of the SQL column that      |
| 	  contains the 'Type' field.                                          |
|	["options"]["sql_string"]* - the SQL string to use when getting data. |
|	["options"]["sql_connection_id"] - the SQL connection id to the       |
|	  database server.                                                    |
|	["options"]["indent"] - the character to use for indentation.         |
|	["options"]["remove_action"]* - the URL to use when removing an item. |
|    ["options"]["sql_delete_table_name"] - the table name to use when    |
|	  deleting items from a tree.                                         |
|	["remove_branch"] - set to 'true' when you want to delete items       |
|	  instead of drawing them on the screen.                              |
|	["branch_to_remove"] - the id of the starting branch to delete.       |
|	                                                                      |
|	Items:                                                                |
|	["item"][<NUM>] - the name of the 'Type', must match values exactly   |
|	  to the SQL 'Type' column.                                           |
|	["item_action"][<NUM>]* - the URL to use when an item is clicked.     |
|	["item_td_code"][<NUM>]* - extra code to be included in the TD        |
|	                                                                      |
|	* - these items are passed to PHP's eval function before being passed |
|	  back to the browser, put and runtime code here.                     |
|	                                                                      |
|	Once the array is created, make sure it is passed to the 'GrowTree'   |
|	function to draw the tree.                                            |
+-------------------------------------------------------------------------+
*/?>
<?

function GrowTree($array_tree) {
	include_once ('include/form.php');
	global $colors;
	
	/* get the "starting leaf" if the user clicked on a specific branch */
	if (($array_tree["options"]["start_branch"] != "") && ($array_tree["options"]["start_branch"] != "0")) {
		$search_key = preg_replace("/0+$/","",db_fetch_cell("select OrderKey from graph_hierarchy_items where id=" . $array_tree["options"]["start_branch"]));
	}
	
	$item_to_eval = $array_tree["options"]["sql_string"];
	eval ("\$sql_string = \"$item_to_eval\";");
	
	$heirarchy = db_fetch_assoc($sql_string);
	$rows = sizeof($heirarchy); $i = 0;
	
	/* loop through all items */
	if (sizeof($heirarchy) > 0) {
		foreach ($heirarchy as $leaf) {
			$tier = tree_tier($leaf[OrderKey], 2);
			
			if ($array_values["remove"]["start"] == true) {
				if ($array_values["remove"]["start_branch"] >= $tier) {
					$array_values["remove"]["start"] = false;
				}
			}
			
			if (isset($array_values["hide"]["start_branch"]) == true) {
				if ($array_values["hide"]["start_branch"] >= $tier) {
					unset($array_values["hide"]["start_branch"]);
				}
			}
			
			/* action type: draw or delete? */
			if ($array_tree["remove"]["remove_branch"] == true) {
				/* once we get to the item we want to delete, keep deleting until we arrive
				at an adjecent spot in the tree */
				if ($array_tree["remove"]["branch_to_remove"] == $leaf[ID]) {
					$array_values["remove"]["start"] = true;
					$array_values["remove"]["start_branch"] = $tier;
				}
				
				if ($array_values["remove"]["start"] == true) {
					mysql_query("delete from " . $array_tree["options"]["sql_delete_table_name"]
						 . " where id=" . $leaf[ID]
						 , $array_tree["options"]["sql_connection_id"]);
				}
			}else{
				if (isset($array_values["hide"]["start_branch"]) == false) {
					/* create the &nbsp's for html (3 for each indent, times 6 characters) */
					//$indent = str_pad("", $tier*3*(strlen($array_tree["options"]["indent"])), $array_tree["options"]["indent"]);
					$pix = $tier * 20;
					$indent = "<img src=\"images/gray_line.gif\" width=\"$pix\" height=\"1\" align=\"middle\">&nbsp;";
					
					
					/* set up variables used in this section */
					$start_tr = ""; $end_tr = ""; $start_nested_table = ""; $end_nested_table = ""; $td_indent = ""; /* array counter */
					
					/* put the current column type in a variable for easy access (c) :) */
					$column_type = $array_tree["options"]["sql_type_column"];
					$current_column_type = $leaf[$column_type];
					
					/* get the next type (if we're not the last item) to see when to end a row on an
					'off' column */
					if (($i+2) <= $rows) {
						// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
						next($leaf);
						$next_column_type = $leaf[$column_type];
						prev($leaf);
						
						// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
					}
					
					/* figure out if the user wants a margin; if this item can have children,
					and if it can draw a '+' */
					if ($array_tree["options"]["create_margin"] == true) {
						if ($array_tree["item_can_have_children"][$current_column_type] == true) {
							if ($array_tree["options"]["use_expand_contract"] == true) {
								if ($leaf[Status] == "1") {
									/* PHP: EVAL */
									$item_to_eval = $array_tree["options"]["show_item"];
									eval ("\$show_hide_item = \"$item_to_eval\";");
								}else{
									/* PHP: EVAL */
									$item_to_eval = $array_tree["options"]["hide_item"];
									eval ("\$show_hide_item = \"$item_to_eval\";");
								}
								
								$html_margin = "<td bgcolor=\"$colors[panel]\" width=\"1%\">$show_hide_item</td>";
							}
						}else{
							$html_margin = "<td bgcolor=\"$colors[panel]\" align=\"center\" width=\"1%\"></td>";
						}
					}else{
						$html_margin = "";
					}
					
					/* do some basic preparation depending on if the user is using columns or not */
					if ($array_tree["item_columns"][$current_column_type] == "") {
						/* always start a new row when columns aren't in use */
						/* start a new row: 1) using alternating colors or 2) default */
						if ($array_tree["options"]["alternating_row_colors"] == true) {
							$start_tr = ReturnMatrixRowAlternateColorBegin($colors[alternate],$colors[light],$array_values["row_counter"]);
						}else{
							$start_tr =  "<tr>";
						}
						
						/* for a one column deal ALWAYS use the ident */
						$td_indent = $indent;
						
						/* nothing special; end the row */
						$end_tr = "</tr>";
					}else{
						/* if the 'current_column_type' has changed 1) set the column #1 to 1
						2) start a new row */
						if ($array_values["last_tree_type"] != $current_column_type) {
							/* reset counter when type changes */
							$array_values["column"][$current_column_type] = 0;
						}
						
						$array_values["column"][$current_column_type]++;
						
						/* only display margin on column #1 */
						if ($array_values["column"][$current_column_type] == 1) {
							$td_indent = $indent;
							
							$start_tr =  "<tr>";
							
							if ($array_tree["item_columns"][$current_column_type] > 1) {
								$start_nested_table = "<td><table><tr>";
							}
						}else{
							$html_margin = "";
							$td_indent = "&nbsp;";
						}
						
						if ($array_values["column"][$current_column_type] == $array_tree["item_columns"][$current_column_type]) {
							/* this row is done; clean up and move on */
							$array_values["column"][$current_column_type] = 0;
							
							$end_tr = "</tr>";
							
							if ($array_tree["item_columns"][$current_column_type] > 1) {
								$end_nested_table = "</tr></table></td>";
							}
						}elseif (($i+1) >= $rows) {
							/* if we are "out of graphs"; go on to the next row */
							$end_tr = "</tr>";
							$end_nested_table = "</tr></table></td>";
						}elseif ($next_column_type != $current_column_type) {
							/* if it is not the end of the row; but our forcasting powers tell us that
							it is the end of the row; end it */
							$end_tr = "</tr>";
							$end_nested_table = "</tr></table></td>";
						}
					}
					
					/* PHP: EVAL */
					$item_to_eval = $array_tree["item_action"][$current_column_type];
					eval ("\$html_item = \"$item_to_eval\";");
					
					/* PHP: EVAL */
					$item_to_eval = $array_tree["item_td_code"][$current_column_type];
					eval ("\$html_td_item = \"$item_to_eval\";");
					
					/* draw the main item */
					DrawMatrixCustom("$start_tr$html_margin$start_nested_table<td $html_td_item>$td_indent$html_item</td>"); $array_values["row_counter"]++;
					
					/* PHP: EVAL */
					if ($array_tree["options"]["moveup_action"] != "") {
						$item_to_eval = $array_tree["options"]["moveup_action"];
						$item_to_eval2 = $array_tree["options"]["movedown_action"];
						eval ("\$html_item = \"$item_to_eval\";");
						eval ("\$html_item2 = \"$item_to_eval2\";");
						
						DrawMatrixLoopItem("[<a href=\"$html_item2\">Down</a>], [<a href=\"$html_item\">Up</a>]","","",false,"");
					}
					
					/* PHP: EVAL */
					if ($array_tree["options"]["remove_action"] != "") {
						$item_to_eval = $array_tree["options"]["remove_action"];
						eval ("\$html_item = \"$item_to_eval\";");
						
						DrawMatrixLoopItemAction("Remove",$colors[panel],"",false,$html_item);
					}
					
					DrawMatrixCustom("$end_nested_table$end_tr");
					
					$array_values["last_tree_type"] = $current_column_type;
					
					/* if do the hide thing if config says we can */
					if ($array_tree["options"]["use_expand_contract"] == true) {
						if ($leaf[Status] == "1") { /* hide all chilren */
							/* initiate hide until we're done with this parent */
							$array_values["hide"]["start_branch"] = $tier;
						}
					}
				}
			}
			
			$i++;
		}
	}
	
	return $array_values["row_counter"];
}

##  This function decides what 'tier' a given id is on based on the characters per tier.
##  For example:  Called with ('1000','1'), it would return '1'.
##                Called with ('1010','1'), it would return '3'.
##                Called with ('1010','2'), it would return '2'.
##
##  Note:  'tier' is determined from left to right.
function tree_tier($id,$chars_per_tier) {
    $root_test = str_pad('',$chars_per_tier,'0');
	
    if (preg_match("/^$root_test/",$id)) {
		$tier = 0;
	}else{
		$tier = (strlen($id)/$chars_per_tier);
		
		for($ct = -$chars_per_tier; abs($ct) < strlen($id); $ct -= $chars_per_tier) {
    		if (substr($id,$ct,$chars_per_tier) == "00") {
				$tier = (strlen($id)/$chars_per_tier)-(abs($ct)/$chars_per_tier) ;
    		}else{
				break;
    		}
		}
    }
	
    return($tier);
}

function ReturnMatrixRowAlternateColorBegin($row_color1, $row_color2, $row_value) {
	if (($row_value % 2) == 1) {
		$current_color = $row_color1;
	}else{
		$current_color = $row_color2;
	}
	
	return "<tr bgcolor=\"#$current_color\">";
}

?>