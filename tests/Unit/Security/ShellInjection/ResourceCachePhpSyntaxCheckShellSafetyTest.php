<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-4p7f-qcc2-vmx7: resource_cache_out() must run the admin-set
 * path_php_binary through the shell-free cacti_exec() with a discrete argv
 * (binary + '-l' + tmpfile). The old system()/cacti_escapeshellcmd() form
 * preserved argument boundaries, so a value like "php -r ..." or a path with
 * spaces still injected extra PHP options at this sink.
 */

$poller = file_get_contents(__DIR__ . '/../../../../lib/poller.php');

test('resource_cache_out runs the PHP syntax check shell-free via cacti_exec (GHSA-4p7f)', function () use ($poller) {
	expect($poller)->toContain("cacti_exec(\$php_path, array('-l', \$tmpfile)")
		->and($poller)->not->toContain('system(cacti_escapeshellcmd($php_path)')
		->and($poller)->not->toContain('system($php_path');
});
