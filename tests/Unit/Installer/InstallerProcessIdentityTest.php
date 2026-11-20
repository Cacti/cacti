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

namespace Cacti\Tests\InstallerProcessIdentity;

require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';

function file_get_contents($filename, $use_include_path = false, $context = null, $offset = 0, $length = null) {
	return $GLOBALS['installer_process_cmdlines'][$filename] ?? false;
}

\cacti_fixture_source_function(dirname(__DIR__, 3) . '/lib/poller.php', 'cacti_process_identity_matches', __NAMESPACE__);

function identities_match(string $mine, string $other, array $flags = []) : ?bool {
	$pid = getmypid() + 100000;
	$GLOBALS['installer_process_cmdlines'] = [
		'/proc/' . getmypid() . '/cmdline' => implode("\0", array_merge(['php'], $flags, [$mine, ''])),
		'/proc/' . $pid . '/cmdline'       => implode("\0", array_merge(['php'], $flags, [$other, ''])),
	];

	$previous = $GLOBALS['config'] ?? null;
	$GLOBALS['config']['base_path'] = dirname(__DIR__, 3);

	try {
		return cacti_process_identity_matches($pid);
	} finally {
		$GLOBALS['config'] = $previous;
	}
}

test('all installer entry points recognize each other as the lock owner', function () {
	$scripts = ['cli/install_cacti.php', 'cli/upgrade_database.php', 'install/background.php'];

	foreach ($scripts as $mine) {
		foreach ($scripts as $other) {
			expect(identities_match(dirname(__DIR__, 3) . '/' . $mine, dirname(__DIR__, 3) . '/' . $other))->toBeTrue();
		}
	}
});

test('unrelated scripts and matching filenames outside this installation remain different', function () {
	$installer = dirname(__DIR__, 3) . '/cli/install_cacti.php';

	foreach ([dirname(__DIR__, 3) . '/poller.php', '/another-cacti/cli/install_cacti.php', '/another-cacti/cli/upgrade_database.php'] as $other) {
		expect(identities_match($installer, $other))->toBeFalse()
			->and(identities_match($other, $installer))->toBeFalse();
	}

	expect(identities_match(dirname(__DIR__, 3) . '/poller.php', dirname(__DIR__, 3) . '/poller.php'))->toBeTrue();
});

test('PHP option values cannot impersonate the running script', function () {
	$root = dirname(__DIR__, 3);

	foreach ([['-d', 'auto_prepend_file=/tmp/shared.php'], ['-dauto_prepend_file=/tmp/shared.php'], ['--define', 'auto_prepend_file=/tmp/shared.php'], ['-c', '/tmp/php.ini.php']] as $flags) {
		expect(identities_match($root . '/cli/install_cacti.php', $root . '/install/background.php', $flags))->toBeTrue()
			->and(identities_match($root . '/cli/install_cacti.php', $root . '/poller.php', $flags))->toBeFalse();
	}
});
