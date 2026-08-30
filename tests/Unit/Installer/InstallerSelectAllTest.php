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

$root = dirname(__DIR__, 3);

function installer_select_all_javascript(string $root) : string {
	$javascript = file_get_contents($root . '/install/install.js');

	if ($javascript === false) {
		throw new RuntimeException('Unable to read install/install.js');
	}

	return $javascript;
}

test('installer binds ajax table controls before restoring checkbox state', function () use ($root) {
	$javascript    = installer_select_all_javascript($root);
	$contentMarker = "$('#installContent').html(data.Html);";
	$content       = strpos($javascript, $contentMarker);

	expect($content)->not->toBeFalse();

	if ($content === false) {
		throw new RuntimeException('Unable to find the installer content marker');
	}

	$contentOffset = $content + strlen($contentMarker);
	$bindControls  = strpos($javascript, 'handleTableNav();', $contentOffset);
	$restoreState  = strpos($javascript, 'processStepTemplateInstall(data.StepData);', $contentOffset);
	$restoreTables = strpos($javascript, 'processStepCheckTables(data.StepData);', $contentOffset);

	expect($bindControls)->not->toBeFalse()
		->and($restoreState)->not->toBeFalse()
		->and($restoreTables)->not->toBeFalse()
		->and($content < $bindControls)->toBeTrue()
		->and($bindControls < $restoreState)->toBeTrue()
		->and($bindControls < $restoreTables)->toBeTrue();
});

test('installer restores select all without relying on a synthetic click', function () use ($root) {
	$javascript = installer_select_all_javascript($root);

	$start = strpos($javascript, 'function restoreInstallerSelection(');

	expect($start)->not->toBeFalse();

	if ($start === false) {
		throw new RuntimeException('Unable to find restoreInstallerSelection()');
	}

	$end = strpos($javascript, 'function processStepTemplateInstall(', $start);

	expect($end)->not->toBeFalse();

	if ($end === false) {
		throw new RuntimeException('Unable to find processStepTemplateInstall()');
	}

	$body = substr($javascript, $start, $end - $start);

	expect($body)->toContain("element.prop('checked', true);")
		->and($body)->toContain("selectAll(element.data('prefix'), true);")
		->and($body)->not->toContain('element.click()');
});
