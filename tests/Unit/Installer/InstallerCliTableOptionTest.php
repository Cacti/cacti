<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 */


require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global_constants.php';
require_once dirname(__DIR__, 3) . '/lib/functions.php';

if (!function_exists('log_install_high')) {
	function log_install_high($section, $text, $background = false) {
	}
}

/*
 * cli/install_cacti.php runs the installer when it is included, so load only
 * its two option helpers.
 */
if (!function_exists('set_install_multioption')) {
	$cli    = file_get_contents(dirname(__DIR__, 3) . '/cli/install_cacti.php');
	$start  = strpos($cli, 'function set_install_option(');
	$end    = strpos($cli, "\n}\n", strpos($cli, 'function set_install_multioption(')) + 3;

	$helpers = tempnam(sys_get_temp_dir(), 'cacti-cli-helpers');
	file_put_contents($helpers, "<?php\n" . substr($cli, $start, $end - $start));
	require_once $helpers;
	unlink($helpers);
}

test('--table stores the selection under the key setTables() reads', function () {
	/*
	 * setTables() looks up chk_table_<name>. With an empty prefix the CLI stored
	 * the bare table name, so --table=plugin_config:1 was accepted and then
	 * ignored: the table was listed as found but never converted.
	 */
	$cli     = file_get_contents(dirname(__DIR__, 3) . '/cli/install_cacti.php');
	$options = array();

	preg_match('/case \'--table\':\s*set_install_multioption\(\$options, \'Tables\', \'Table\', \$value, \'([^\']*)\'\);/', $cli, $call);
	expect($call)->toHaveCount(2);

	set_install_multioption($options, 'Tables', 'Table', 'plugin_config:1', $call[1]);

	expect($options['Tables'])->toBe(array('chk_table_plugin_config' => '1'));
});
