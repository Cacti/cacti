<?

/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
   die("<br><strong>This script is only meant to run at the command line.</strong>");
}

$no_http_headers = true;

include(dirname(__FILE__) . "/../include/global.php");

if ($database_password == "") {
	$sql = `mysqladmin -h $database_hostname -u $database_username status | awk '{print $6 }'`;
}else{
	$sql = `mysqladmin -h $database_hostname -u $database_username -p$database_password status | awk '{print $6 }'`;
}

print trim($sql);

?>
