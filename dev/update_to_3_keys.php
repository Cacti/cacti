<?php
$no_http_headers = true;
include("../include/config.php");
include("../lib/tree.php");

$trees = db_fetch_assoc("select id from graph_tree");

if (sizeof($trees) > 0) {
	foreach ($trees as $tree) {
		$tree_items = db_fetch_assoc("select 
			graph_tree_items.id,
			graph_tree_items.order_key
			from graph_tree_items
			where graph_tree_items.graph_tree_id='" . $tree["id"] . "'
			order by graph_tree_items.order_key");
		
		if (sizeof($tree_items) > 0) {
			$_tier = 0;
			
			foreach ($tree_items as $tree_item) {
				$tier = tree_tier($tree_item["order_key"], 2);
				
				/* back off */
				if ($tier < $_tier) {
					for ($i=$_tier; $i>$tier; $i--) {
						print "reset ctr = $i\n";
						$tier_counter[$i] = 0;
					}
				}
				
				/* we key tier==0 off of '1' and tier>0 off of '0' */
				if (!isset($tier_counter[$tier])) {
					$tier_counter[$tier] = 1;
				}else{
					$tier_counter[$tier]++;
				}
				
				$search_key = preg_replace("/0+$/", "", $tree_item["order_key"]);
				if (strlen($search_key) % 2 != 0) { $search_key .= "0"; }
				
				$new_search_key = "";
				for ($i=1; $i<$tier; $i++) {
					$new_search_key .= str_pad(strval($tier_counter[$i]),3,'0',STR_PAD_LEFT);
				}
				
				/* build the new order key string */
				$key = str_pad($new_search_key . str_pad(strval($tier_counter[$tier]),3,'0',STR_PAD_LEFT), 90, '0', STR_PAD_RIGHT);
				
				print "[tier=$tier] update graph_tree_items set order_key='$key' where id=" . $tree_item["id"] . "\n";
				db_execute("update graph_tree_items set order_key='$key' where id=" . $tree_item["id"]);
				
				$_tier = $tier;
			}
		}
	}
}

?>
