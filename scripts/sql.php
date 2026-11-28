<?php

error_reporting(0);

include(__DIR__ . '/../include/cli_check.php');

/* mysqladmin status reports the Queries counter in its Questions field, which
 * is the value this script has always printed. Reading it over the existing
 * connection keeps the database password off a command line. */
$queries = db_fetch_cell("SHOW GLOBAL STATUS LIKE 'Queries'", 'Value');

// Cacti expects 'U' on error, not empty string or 0.
print ctype_digit((string) $queries) ? $queries : 'U';
