<?php
$no_http_headers = true;
include("../include/config.php");
include("../lib/data_query.php");

$host_snmp_query = db_fetch_assoc("select host_id,snmp_query_id from host_snmp_query");

if (sizeof($host_snmp_query) > 0) {
	foreach ($host_snmp_query as $item) {
		update_data_query_sort_cache($item["host_id"], $item["snmp_query_id"]);
	}
}

?>
