<?php

$no_http_headers = true;

include(dirname(__FILE__) . '/../include/global.php');
$contents = file($config['base_path'] . '/install/colors.csv');

if (sizeof($contents)) {
	foreach($contents as $line) {
		$line    = trim($line);
		$parts   = explode(',',$line);
		$natural = $parts[0];
		$hex     = $parts[1];
		$name    = $parts[2];

		$id = db_fetch_cell("SELECT hex FROM colors WHERE hex='$hex'");

		if (!empty($id)) {
			db_execute("UPDATE colors SET name='$name', read_only='on' WHERE hex='$hex'");
		}else{
			db_execute("INSERT INTO colors (name, hex, read_only) VALUES ('$name', '$hex', 'on')");
		}
	}
}
