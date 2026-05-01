<?php
require_once "lib/functions.php";
$cases = [
    ["hostname", "hostname"],
    ["host.id", "host.id"],
    ["ua.full_name", "ua.full_name"],
    ["hostname; DROP TABLE users", "hostnameDROPTABLEusers"],
    ["id` OR 1=1 --", "idOR11"],
    ["user_auth.locked", "user_auth.locked"],
];
foreach ($cases as $case) {
    $input = $case[0];
    $expected = $case[1];
    $actual = sanitize_sql_column($input);
    if ($actual !== $expected) {
        echo "FAIL: input=$input, expected=$expected, actual=$actual\n";
        exit(1);
    }
}
echo "PASS\n";
