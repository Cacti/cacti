<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
*/

declare(strict_types = 1);

$scenario = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
$queries  = [];
$debug    = false;
define('POLLER_VERBOSITY_MEDIUM', 3);
define('POLLER_VERBOSITY_NONE', 0);
define('CACTI_WEB', false);

function db_fetch_cell_prepared(string $sql, array $params = []) : string {
	return str_contains($sql, 'ignore_ips') ? $GLOBALS['scenario']['ignore'] : $GLOBALS['scenario']['range'];
}

function db_execute(string $sql) : bool {
	$GLOBALS['queries'][] = $sql;

	return true;
}

function cacti_sizeof(mixed $value) : int {
	return is_array($value) ? count($value) : 0;
}

function cacti_log(mixed ...$args) : void {
}

require dirname(__DIR__, 2) . '/lib/api_automation.php';

pcntl_async_signals(true);
pcntl_signal(SIGALRM, static function () : void {
	throw new RuntimeException('Discovery address enumeration did not terminate');
});
pcntl_alarm(2);

try {
	automation_primeIPAddressTable(7);
	$addresses = [];

	foreach ($queries as $query) {
		if (!preg_match('/VALUES\s+\(/', $query)) {
			throw new RuntimeException('Empty discovery INSERT');
		}
		preg_match_all("/\('([^']+)', '', 7, '0', '0', '0'\)/", $query, $matches);
		array_push($addresses, ...$matches[1]);
	}
	$result = ['completed' => true, 'addresses' => $addresses, 'writes' => count($queries)];
} catch (RuntimeException $error) {
	$result = ['completed' => false, 'error' => $error->getMessage()];
} finally {
	pcntl_alarm(0);
}

echo json_encode($result, JSON_THROW_ON_ERROR), PHP_EOL;
