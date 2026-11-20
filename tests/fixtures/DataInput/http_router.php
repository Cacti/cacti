<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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
} elseif ($path === '/large') {
	// one byte over the 1 MiB body cap
	header('Content-Type: text/plain');
	print str_repeat('x', 1048577);
} elseif ($path === '/drip') {
	// a device that keeps the connection busy without ever finishing
	header('Content-Type: text/plain');

	// without this the built-in server holds the body until the script ends
	while (ob_get_level() > 0) {
		ob_end_flush();
	}

	for ($i = 0; $i < 30; $i++) {
		print 'x';
		flush();
		usleep(200000);
	}
} elseif ($path === '/redirect') {
	header('Location: /plain', true, 302);
} else {
	http_response_code(404);
	print 'not found';
}
