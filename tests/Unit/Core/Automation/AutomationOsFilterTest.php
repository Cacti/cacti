<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

declare(strict_types=1);

test('issue 7456 automation discovery binds the selected operating system', function (): void {
	$source = file_get_contents(dirname(__DIR__, 4) . '/automation_devices.php');
	$start  = strpos($source, "if (\$os != '-1'");

	expect($start)->not->toBeFalse();

	$body = substr($source, $start, strpos($source, "\n\t}", $start) - $start);

	expect($body)->toContain("'os = ?'")
		->and($body)->toContain('$sql_params[] = $os;')
		->and($body)->not->toContain('$sql_param[]');
});
