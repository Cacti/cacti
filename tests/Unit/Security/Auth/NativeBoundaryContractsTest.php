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

use Symfony\Component\Process\Process;

test('native authentication and package boundaries handle hostile text and paths', function () {
	$fixture = dirname(__DIR__, 3) . '/fixtures/Core/Auth/BoundaryContracts.php';
	$process = new Process([PHP_BINARY, $fixture]);
	$process->mustRun();
	expect($process->getOutput())->toBe("Native authentication, repository, metadata, and theme contracts passed.\n");
	expect($process->getErrorOutput())->toBe('');
});
