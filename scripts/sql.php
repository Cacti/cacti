<?php

error_reporting(0);

include(__DIR__ . '/../include/cli_check.php');

print sql_questions();

/**
 * sql_questions - the sixth field of 'mysqladmin status'
 *
 * Read over Cacti's own database connection, so no client process is
 * started and the database password is never placed on a command line.
 *
 * @return string the Questions counter, or 'U' when it cannot be read
 */
function sql_questions() : string {
	global $local_db_cnn_id;

	$questions = db_fetch_row("SHOW GLOBAL STATUS LIKE 'Questions'", false, (is_object($local_db_cnn_id) ? $local_db_cnn_id : false));

	// Cacti expects 'U' on error, not empty string or 0.
	if (!isset($questions['Value']) || $questions['Value'] === '') {
		return 'U';
	}

	return (string) $questions['Value'];
}
