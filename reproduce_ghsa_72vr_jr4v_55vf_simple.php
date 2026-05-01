<?php
// Simplified demonstration of GHSA-72vr-jr4v-55vf

function demonstrate_vulnerability($sort_column, $sort_direction, $rows, $page) {
    $sql_join = 'LEFT JOIN user_auth ON user_auth.id=reports.user_id';
    $sql_where = 'WHERE 1=1';

    $sql_query = "SELECT user_auth.full_name, user_auth.username, reports.*
        FROM reports
        $sql_join
        $sql_where
        ORDER BY " .
        $sort_column . ' ' .
        $sort_direction .
        ' LIMIT ' . ($rows*($page-1)) . ',' . $rows;

    echo "GENERATED SQL: $sql_query\n";
}

$rows = 30;
$page = 1;

echo "Demonstrating SQL Injection via sort_column / sort_direction in Reports...\n";

// Case 1a - Leak locked account status
echo "\nCase 1a: Leak locked account status\n";
demonstrate_vulnerability("user_auth.locked", "ASC", $rows, $page);

// Case 1d - Remove LIMIT via sort_direction comment injection
echo "\nCase 1d: Remove LIMIT via sort_direction comment injection\n";
demonstrate_vulnerability("name", "ASC --", $rows, $page);

?>
