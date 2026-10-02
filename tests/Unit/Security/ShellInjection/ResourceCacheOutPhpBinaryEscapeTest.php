<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-4p7f-qcc2-vmx7: resource_cache_out() built a system() command by
 * concatenating the admin-set path_php_binary value with no escaping. It must
 * now be escaped like every other path_php_binary consumer in the codebase.
 */

$root   = dirname(__DIR__, 4);
$source = file_get_contents($root . '/lib/poller.php');

test('resource_cache_out escapes path_php_binary before system()', function () use ($source) {
	expect($source)->toContain('system(cacti_escapeshellcmd($php_path)')
		->and($source)->not->toContain('system($php_path');
});
