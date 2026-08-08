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

$root = dirname(__DIR__, 2);

test('installer binds ajax table controls before restoring checkbox state', function () use ($root) {
	$javascript = file_get_contents($root . '/install/install.js');

	$content       = strpos($javascript, "$('#installContent').html(data.Html);");
	$bindControls  = strpos($javascript, 'handleTableNav();', $content);
	$restoreState  = strpos($javascript, 'processStepTemplateInstall(data.StepData);', $content);
	$restoreTables = strpos($javascript, 'processStepCheckTables(data.StepData);', $content);

	expect($content)->not->toBeFalse()
		->and($bindControls)->not->toBeFalse()
		->and($restoreState)->not->toBeFalse()
		->and($restoreTables)->not->toBeFalse()
		->and($content < $bindControls)->toBeTrue()
		->and($bindControls < $restoreState)->toBeTrue()
		->and($bindControls < $restoreTables)->toBeTrue();
});

test('installer restores select all without relying on a synthetic click', function () use ($root) {
	$javascript = file_get_contents($root . '/install/install.js');

	$start = strpos($javascript, 'function restoreInstallerSelection(');
	$end   = strpos($javascript, 'function processStepTemplateInstall(', $start);
	$body  = substr($javascript, $start, $end - $start);

	expect($start)->not->toBeFalse()
		->and($end)->not->toBeFalse()
		->and($body)->toContain("element.prop('checked', true);")
		->and($body)->toContain("selectAll(element.data('prefix'), true);")
		->and($body)->not->toContain('element.click()');
});
