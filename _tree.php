<?

$section = "Add/Edit Graphs"; include ('include/auth.php');
include_once ("include/database.php");

$heirarchy = db_fetch_assoc("SELECT *,graph_viewing.Title HTitle,rrd_graph.Title GTitle, graph_viewing.ID VID
	FROM graph_viewing 
	LEFT JOIN rrd_graph ON graph_viewing.GraphID = rrd_graph.ID 
	WHERE OrderKey LIKE '$search_key%' ORDER BY OrderKey");

/*
for ($i = (sizeof($heirarchy) - 1); $i > 0; --$i) {
	$leaf = $heirarchy[$i];
	$tier = tree_tier($leaf[OrderKey], 2);
	
	if ($tier > 1) {
	    $parent_key = str_pad(substr($leaf[OrderKey],0,(($tier - 1) * 2) ),60,'0',STR_PAD_RIGHT);
	    
		if ($leaf[GraphID] && (! isset($counted_graphs[$parent_key]))) {
			++$num_children[$parent_key];
			++$counted_graphs[$parent_key];
	    }
		
	    if (!$leaf[GraphID]) {
			++$num_children[$parent_key];
			++$num_children[$parent_key];
	    }
	}
	
	if ($tier > $max_tier) { $max_tier = $tier; }
}
*/

if (sizeof($heirarchy) > 0) {
	foreach ($heirarchy as $leaf) {
		$no_children = 0;
	    
		if (sizeof($hide) > 0) {
			foreach (array_keys($hide) as $key_root) { 
		    	if (preg_match("/^$key_root/",$leaf[OrderKey])) {
					if (preg_match("/^".$key_root."00/",$leaf[OrderKey])) {
			    		$no_children = 1; 
					}else{
			    		$skip_entry = 1;
					}
		    	}
			}
	    }
		
	    if ($skip_entry) {
			$skip_entry = 0;
			continue;
	    }
		
	    $tier = tree_tier($leaf[OrderKey], 2);
	    //$current_leaf_type = $leaf[GraphID] ? "graph" : "heading";
	    
	    //if ($current_leaf_type == 'heading') {
		
		//if ($heading_ct > 0) {
		  //  if ($graph_ct % 2 != 0) { print "</tr>\n"; }
		 //   print "</table></td>\n";
		//}
		$pix = $tier * 20;
		$indent = "<img src=\"images/gray_line.gif\" width=\"$pix\" height=\"1\" align=\"middle\">&nbsp;";
		
		if ($leaf[GraphID] == "0") {
			print $indent . $leaf[HTitle] . "<br>";
		}else{
			print $indent . $leaf[Title] . "<br>";
		}
		//$colspan = (($max_tier - $tier) * 2);
		//$rowspan = $num_children[$leaf[OrderKey]];
		#		print "Children for $leaf[HTitle] = '".$num_children[$leaf[OrderKey]]."'<BR>\n";
		//print "<tr>\n";
		//if ($options[$current_leaf_type."s_show_children"]) {
		 //   if ($options[use_expand_contract]) {
			//if ($hide[preg_replace("/0+$/","",$leaf[OrderKey])] == 1) {
			//    $other_status = '0';
			//    $ec_icon = 'show';
			//} else {
			//    $other_status = '1';
			//    $ec_icon =  'hide';
			//    ++$heading_ct;
			//}
			//print "<td bgcolor=\"$colors[panel]\" align=\"center\" width=\"1\"><a 
			//	href='$PHP_SELF?action=tree&tree_id=$tree_id&start_branch=$start_branch&hide=$other_status&branch_id=$leaf[VID]'><img
			//	src='images/$ec_icon.gif' border='0'></a></td>\n";
			
		   // } else {
			//print "<td bgcolor=\"$colors[panel]\" width=\"1\">$indent</td>\n";
		  //  }
		///} else {
		//    print "<td bgcolor=\"$colors[panel]\" width=\"1\">$indent</td>\n";
		//}
		//print "<td bgcolor=\"$colors[panel]\" colspan=$colspan NOWRAP><strong><a
		//	href='?tree_id=$tree_id&start_branch=$leaf[OrderKey]'>$leaf[HTitle]</a></strong></td>\n";
		//print "</tr>";
		//if ($num_children[$leaf[OrderKey]] > 0 && ! $no_children) { 
		   // print "<tr>\n<td bgcolor=\"$colors[panel]\" width=\"1%\" rowspan=$rowspan>&nbsp;</td>
			//    <td colspan=$colspan><table border=0><tr>\n"; 
		//}
		//$graph_ct = 0;
	   // } else {
		//++$graph_ct;
		//switch ($array_settings["hierarchical"]["viewtype"]) {
		// case "1":
		//    print "<td><a href='graph.php?graphid=$leaf[GraphID]&rraid=all'><img align='middle' 
			//    src='graph_image.php?graphid=$leaf[GraphID]&rraid=$leaf[RRAID]&graph_start=-".$array_settings["preview"]["timespan"].'&graph_height='.
		  //    $array_settings["preview"]["height"].'&graph_width='.$array_settings["preview"]["width"] ."&graph_nolegend=true' border='0' alt='$leaf[Title]'></a><td>\n";
		 //   break;
		 //case "2":
		 //   print "<td><a href='graph.php?graphid=$leaf[GraphID]&rraid=all'>$leaf[GTitle]</a></td>";
		 //   break;
		//}
		//if ($graph_ct % 2 == 0) { print "</tr><tr>\n"; }
	   // }
		}
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

?>