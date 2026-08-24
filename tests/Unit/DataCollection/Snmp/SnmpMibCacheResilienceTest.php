<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$cacheSource = file_get_contents(dirname(__DIR__, 4) . '/lib/mib_cache.php');
$agentSource = file_get_contents(dirname(__DIR__, 4) . '/lib/snmpagent.php');

test('MIB parser resource changes are bounded and restored', function () use ($cacheSource) {
	expect($cacheSource)->toContain("filesize(\$path) > 16 * 1024 * 1024")
		->and($cacheSource)->toContain("ini_get('memory_limit')")
		->and($cacheSource)->toContain('set_time_limit(60)')
		->and($cacheSource)->toContain("ini_set('memory_limit', \$old_memory_limit)")
		->and($cacheSource)->toContain("set_time_limit((int) \$old_time_limit)")
		->and($cacheSource)->toContain('error_reporting($old_error_level)')
		->and($cacheSource)->toContain('catch (Throwable $e)');
});

test('core MIB cache rebuild is transactional and avoids implicit commits', function () use ($agentSource) {
	expect($agentSource)->toContain('db_begin_transaction()')
		->and($agentSource)->toContain('db_commit_transaction()')
		->and($agentSource)->toContain('db_rollback_transaction()')
		->and($agentSource)->not->toContain('db_execute("TRUNCATE $table")')
		->and($agentSource)->toContain('db_execute("DELETE FROM $table")');
});
