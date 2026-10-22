<?php

error_reporting(0);

include(dirname(__FILE__) . '/../include/cli_check.php');

global $local_db_cnn_id;

/* the sixth field of 'mysqladmin status', read over Cacti's own database
 * connection so the database password is never placed on a command line */
$questions = db_fetch_row("SHOW GLOBAL STATUS LIKE 'Questions'", false, (is_object($local_db_cnn_id) ? $local_db_cnn_id : false));

if (!isset($questions['Value']) || $questions['Value'] === '') {
	print 'U';
} else {
	print $questions['Value'];
}
