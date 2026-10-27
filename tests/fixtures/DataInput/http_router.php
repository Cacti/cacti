<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Router for the PHP built-in web server, standing in for the devices the
 * HTTP data input scripts poll.
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/cmSignalData.htm') {
	header('Content-Type: text/html');
	// .txt keeps the modem's legacy markup out of web-markup analysis
	readfile(__DIR__ . '/sb6141_cmSignalData.html.txt');
} elseif ($path === '/plain') {
	header('Content-Type: text/plain');
	print "line one\nline two\n";
} elseif ($path === '/redirect') {
	header('Location: /plain', true, 302);
} else {
	http_response_code(404);
	print 'not found';
}
