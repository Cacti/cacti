<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The RRD Cleaner rescan quotes each RRA file name with db_qstr() when it builds
 * the data_source_purge_temp insert. In the remote-storage branch the
 * "path,size,mtime" line is split from the right, so a comma in the path stays
 * with the path, and the numeric fields are cast.
 */

$src = file_get_contents(dirname(__DIR__, 4) . '/rrdcleaner.php');

test('the rescan quotes the RRA file name with db_qstr in both branches', function () use ($src) {
	expect($src)->toContain("db_qstr(str_replace(\$rra_path, '', \$pathname))")
		->and($src)->toContain("db_qstr(str_replace(\$rra_path, '', \$file->getPathname()))")
		->and($src)->not->toContain("\"('\" . str_replace(\$rra_path, '', \$pathname)");
});

test('the proxy listing rejects malformed records before building an insert', function () use ($src) {
	$guard = strpos($src, "if (!preg_match('/^(.+),([0-9]+),([0-9]+)$/D'");
	$insert = strpos($src, "\$sql[] =", $guard);
	expect($guard)->not->toBeFalse()->and($insert)->toBeGreaterThan($guard);
});
