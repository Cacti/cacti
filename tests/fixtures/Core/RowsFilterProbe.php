<?php
/* Render the real Cacti helper with untrusted plain-text caption/option data. */
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
chdir(dirname(__DIR__, 3));
$_SERVER['REQUEST_URI'] = '/sites.php';
$_SERVER['PHP_SELF'] = '/sites.php';
$_SERVER['SERVER_NAME'] = 'localhost';
require './include/global.php';
require_once './lib/html.php';
$item_rows = array(10 => '10', 30 => '<i>30</i> & more');
set_request_var('rows', '30');
set_request_var('filter', '\'"<img src=x onerror=alert(1)>&');
print "<!DOCTYPE html><html lang='en'><head><title>Rows filter probe</title></head><body><table role='presentation'><tr>";
html_rows_filter('<b>Rows & counts</b>', true);
html_search_filter(true, false);
print '</tr></table></body></html>';
