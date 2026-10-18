<?php
declare(strict_types = 1);
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

$permissionModels = [
	1 => [1, 2, 3, 4, 5, 6, 7], // Graph OR device OR template.
	2 => [1, 3, 5, 6, 7],       // Graph OR (device AND template).
	3 => [1, 2, 3, 5, 6, 7],    // Graph OR device.
	4 => [1, 3, 4, 5, 6, 7],    // Graph OR template.
];
$permissionCases = [];

foreach (['user', 'group'] as $type) {
	foreach ([1, 2] as $default) {
		foreach ($permissionModels as $model => $allowed) {
			foreach (range(0, 7) as $grants) {
				$permissionCases["$type model $model default $default grants $grants"] = [
					$type, $default, $model, $grants, in_array($grants, $allowed, true),
				];
			}
		}
	}
}

test('graph permission labels follow the configured access model', function (
	string $type, int $default, int $model, int $grants, bool $allowed
) {
	global $config;
	$original = $config;

	try {
		$config[OPTIONS_CLI]['graph_auth_method'] = (string) $model;
		$policies                                 = [['id' => 7, 'type' => $type, 'name' => 'Operators',
			'policy_graphs'                                   => $default, 'policy_hosts' => $default, 'policy_graph_templates' => $default]];
		$graph = ['disabled' => ''];

		// Permission rows are grants under default-deny and exceptions under
		// default-allow. Bits identify graph, device and template access.
		foreach (['graph1' => 1, 'device1' => 2, 'template1' => 4] as $field => $bit) {
			$graph[$field] = (($grants & $bit) !== 0) === ($default === 2) ? 7 : null;
		}
		$html = get_permission_string($graph, $policies);
		expect($html)->toContain($allowed ? "class='accessGranted'" : "class='accessRestricted'")
			->not->toContain($allowed ? "class='accessRestricted'" : "class='accessGranted'");
	} finally {
		$config = $original;
	}
})->with($permissionCases);
