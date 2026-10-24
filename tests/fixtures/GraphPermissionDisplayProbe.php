<?php
declare(strict_types=1);
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

if (PHP_SAPI !== 'cli') {
	exit(1);
}

// Use the actual helper and translation fallback without the web bootstrap
// or shared test-process stubs. These cached settings require no database.
$config = ['is_web' => false, 'config_options_array' => ['i18n_language_support' => 0]];
$root = dirname(__DIR__, 2);
require $root . '/include/global_constants.php';
require $root . '/lib/functions.php';
require $root . '/lib/html.php';
require $root . '/lib/auth.php';
require $root . '/include/global_languages.php';

$models = [
	1 => [1, 2, 3, 4, 5, 6, 7], // Graph OR device OR template.
	2 => [1, 3, 5, 6, 7],       // Graph OR (device AND template).
	3 => [1, 2, 3, 5, 6, 7],    // Graph OR device.
	4 => [1, 3, 4, 5, 6, 7],    // Graph OR template.
];
$cases = 0;
foreach (['user', 'group'] as $type) {
	foreach ([1, 2] as $default) {
		$policies = [['id' => 7, 'type' => $type, 'name' => 'Operators',
			'policy_graphs' => $default, 'policy_hosts' => $default, 'policy_graph_templates' => $default]];
		foreach ($models as $model => $allowed) {
			$config['config_options_array']['graph_auth_method'] = (string) $model;
			foreach (range(0, 7) as $grants) {
				$graph = ['disabled' => ''];
				foreach (['graph1' => 1, 'device1' => 2, 'template1' => 4] as $field => $bit) {
					$graph[$field] = (($grants & $bit) !== 0) === ($default === 2) ? 7 : null;
				}
				$html = get_permission_string($graph, $policies);
				$expected = in_array($grants, $allowed, true);
				if (str_contains($html, "class='accessGranted'") !== $expected ||
					str_contains($html, "class='accessRestricted'") === $expected) {
					throw new RuntimeException("Incorrect $type label: model $model, default $default, grants $grants");
				}
				$cases++;
			}
		}
	}
}
echo json_encode(['cases' => $cases, 'assertions' => $cases * 2], JSON_THROW_ON_ERROR) . "\n";
