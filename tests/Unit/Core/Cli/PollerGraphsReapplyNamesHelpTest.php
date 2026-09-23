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

$source = file_get_contents(dirname(__DIR__, 4) . '/cli/poller_graphs_reapply_names.php');

if ($source === false) {
	throw new RuntimeException('Unable to read poller_graphs_reapply_names.php.');
}

test('graph-name reapply help describes the host selector as one value', function () use ($source) {
	expect($source)->toContain('--host-id=<id|all|N1,N2,...>')
		->and($source)->toContain('--id and -id')
		->and($source)->not->toContain('[id|all][N1,N2,...]');
});
