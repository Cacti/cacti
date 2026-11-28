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

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

$root = dirname(__DIR__, 4);

test('device, SNMP, database redaction, host template deletion, and installer hardening checks pass in isolated probes', function () {
	$devProbe  = dirname(__DIR__, 3) . '/fixtures/Core/BulkDevicesAndInstallerHardeningProbe.php';
	$instProbe = dirname(__DIR__, 3) . '/fixtures/Core/BulkInstallerHardeningProbe.php';

	$dev = cacti_test_isolated_probe($devProbe);
	expect($dev['ping']['ipv4'])->toBeTrue()
		->and($dev['ping']['ipv6'])->toBeTrue()
		->and($dev['ping']['ipv6_zone'])->toBeTrue()
		->and($dev['ping']['ipv6_dot'])->toBeTrue()
		->and($dev['ping']['ipv4_zone'])->toBeFalse()
		->and($dev['ping']['sqli_zone'])->toBeFalse()
		->and($dev['ping']['cmd_zone'])->toBeFalse()
		->and($dev['ping']['empty_zone'])->toBeFalse()
		->and($dev['ping']['empty'])->toBeFalse()
		->and($dev['plugins_list'])->toContain('sample')
		->and(count($dev['bg_calls']))->toBe(3)
		->and($dev['bg_calls'][0][1])->toContain('--')
		->and($dev['bg_calls'][1][1])->toContain('--')
		->and($dev['bg_calls'][2][1])->toContain('--')
		->and($dev['mgr_hostname_err'])->toBeTrue()
		->and($dev['templates_count'])->toBeGreaterThan(0);

	$del = cacti_test_isolated_probe($devProbe, array('host_templates_delete'));
	expect($del['messages'])->toContain('host_template_in_use')
		->and($del['sql'][0])->toContain('id IN (20)')
		->and($del['sql'][0])->not->toContain('10');

	$inst = cacti_test_isolated_probe($instProbe);
	expect($inst['err_non_php'])->toBeTrue()
		->and($inst['err_bad_cli'])->toBeTrue()
		->and($inst['err_good_php'])->toBeTrue()
		->and($inst['range_after_bad'])->toBe('')
		->and($inst['range_after_good'])->toBe('10.0.0.0/24')
		->and($inst['too_old_escaped'])->toBeTrue()
		->and($inst['exec_calls'])->toBeGreaterThanOrEqual(4)
		->and($inst['sk_dash_err'])->toBeTrue()
		->and($inst['sk_outside_err'])->toBeTrue()
		->and($inst['sk_create_dash'])->toBeFalse()
		->and($inst['sk_create_win'])->toBeFalse();
});

test('managers.php tooltip renders via safe DOM nodes and snmpagent cache uses atomic JSON', function () use ($root) {
	$managersSource = file_get_contents($root . '/managers.php');
	$persistSource  = file_get_contents($root . '/snmpagent_persist.php');
	$childSource    = file_get_contents($root . '/snmpagent_mibcachechild.php');
	$jsSource       = file_get_contents($root . '/install/install.js');

	expect($managersSource)->not->toContain('div.innerHTML =')
		->and($managersSource)->not->toContain('onMouseMove="showTooltip(')
		->and($managersSource)->toContain('titleEl.textContent = title;')
		->and($managersSource)->toContain('preEl.textContent = desc;')
		->and($managersSource)->toContain('data-tooltip-title=')
		->and($persistSource)->not->toContain('include( $path_mibcache )')
		->and($persistSource)->toContain('!is_link($path_mibcache)')
		->and($persistSource)->toContain('json_decode($raw_cache, true)')
		->and($childSource)->not->toContain('var_export($cache')
		->and($childSource)->toContain('json_encode($cache)')
		->and($childSource)->toContain('chmod($tmp_cache, 0600)')
		->and($childSource)->toContain('rename($tmp_cache, $path_mibcache)')
		->and($jsSource)->toContain('.text(JSON.stringify(debugData))');
});
