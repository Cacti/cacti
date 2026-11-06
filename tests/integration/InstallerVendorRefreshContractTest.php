<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

$root = dirname(__DIR__, 2);

test('installer verifies dependencies before side effects and never builds them', function () use ($root) {
	$source = file_get_contents($root . '/lib/installer.php');
	$start  = strpos($source, 'private function install()');
	$body   = substr($source, $start);

	expect(strpos($body, 'DependencyPreflight'))->toBeLessThan(strpos($body, '$this->setCSRFSecret()'))
		->and($source)->not->toContain('refreshVendorDependencies', '--dry-run', 'Refreshing Composer dependencies');
});

test('all installer transports check dependencies before application bootstrap', function () use ($root) {
	foreach (['cli/install_cacti.php', 'cli/upgrade_database.php', 'install/background.php', 'install/install.php', 'install/step_json.php'] as $file) {
		$source    = file_get_contents($root . '/' . $file);
		$preflight = strpos($source, 'preflight.php');
		$bootstrap = strpos($source, $file === 'install/install.php' || $file === 'install/step_json.php' ? 'include/auth.php' : 'include/cli_check.php');
		expect($preflight)->not->toBeFalse()->and($preflight)->toBeLessThan($bootstrap);
	}
});
