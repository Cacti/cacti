<?

$do_not_read_config = true;
include ("../include/config.php");

if ($database_password == "") {
	$sql = `mysqladmin -h $database_hostname -u $database_username status | awk '{print $6 }'`;
}else{
	$sql = `mysqladmin -h $database_hostname -u $database_username -p$database_password status | awk '{print $6 }'`;
}

print trim($sql);

?>