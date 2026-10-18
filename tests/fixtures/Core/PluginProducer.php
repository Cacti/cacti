<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

if (getenv('CACTI_E2E_DISPOSABLE') !== '1') {
	throw new RuntimeException('Fixture plugin requires a disposable installation');
}

function plugin_coveragefixture_version(): array {
	return ['longname' => 'Disposable lifecycle fixture', 'author' => 'Cacti tests', 'version' => '1.0.0', 'homepage' => 'https://example.invalid'];
}

function plugin_coveragefixture_install(): ?bool {
	api_plugin_register_hook('coveragefixture', 'coveragefixture_notify', 'coveragefixture_notify', 'setup.php', true);

	if (($GLOBALS['coveragefixture_install'] ?? '') === 'throw') {
		throw new RuntimeException('Injected plugin installation failure');
	}

	return $GLOBALS['coveragefixture_install'] ?? null;
}

function plugin_coveragefixture_check_config(): ?bool {
	return $GLOBALS['coveragefixture_ready'] ?? true;
}

function plugin_coveragefixture_uninstall(): void {
	$GLOBALS['coveragefixture_uninstalled'] = true;
}

function coveragefixture_notify(array $arguments): void {
	$GLOBALS['coveragefixture_calls'][] = $arguments;
}

function coveragefixture_transform(array $value): array {
	$value['plugin'] = 'coveragefixture';

	return $value;
}
