<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

if (PHP_OS_FAMILY !== 'Windows') { throw new RuntimeException('Native Windows is required'); }
$config = ['cacti_server_os' => 'win32'];
define('CACTI_ESCAPE_CHARACTER', '"');
require dirname(__DIR__, 2) . '/lib/functions.php';
$cases = ['ordinary', 'two words', 'a"&b"c', 'a"|b', 'x"^y', 'a"(b)', 'C:' . chr(92) . 'data' . chr(92), 'a&b', 'a|b'];
foreach ($cases as $case) {
    $command = '"' . PHP_BINARY . '" ' . '"' . dirname(__DIR__) . '/fixtures/win32_argv.php" ' . cacti_escapeshellarg($case);
    $p = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($p)) { throw new RuntimeException('Cannot launch argument probe'); }
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $status = proc_close($p);
    $expected = [str_replace('"', '', $case)];
    if ($status !== 0 || json_decode($out, true) !== $expected || $err !== '') {
        throw new RuntimeException('Argument boundary failed for case ' . array_search($case, $cases, true));
    }
}
printf("PASS: Windows PHP %s, %d native argument cases\n", PHP_VERSION, count($cases));
