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
require_once dirname(__DIR__, 3) . '/install/functions.php';
require_once dirname(__DIR__, 3) . '/lib/installer.php';

test('the requested step is honoured once the parameters have resolved the errors that held it back', function () {
	$source = file_get_contents(dirname(__DIR__, 3) . '/lib/installer.php');

	/*
	 * setDefaults() applies the step first, while the errors from the defaults
	 * still stand, so the step was held back to the first of them and the command
	 * line stopped with 'Unexpected step (5)' although every option was valid.
	 */
	expect($source)->toMatch('/\$this->stepError === false && isset\(\$install_params\[\'Step\'\]\)[^{]*\{\s*\$this->setStep\(intval\(\$install_params\[\'Step\'\]\)\);/');
});
