<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

namespace InstallerVendorReadinessTest;

$source = file_get_contents(dirname(__DIR__, 3) . '/install/functions.php');

if (preg_match('/function install_validate_vendor_dependencies\(.*?^}\R/ms', $source, $matches) !== 1) {
	throw new \RuntimeException('Missing dependency validator');
}
eval('namespace InstallerVendorReadinessTest;' . $matches[0]);

test('dependency readiness accepts production-only installs and rejects missing or stale packages', function () {
	$root = sys_get_temp_dir() . '/cacti-vendor-test-' . bin2hex(random_bytes(8));
	mkdir($root . '/include/vendor/composer', 0700, true);
	mkdir($root . '/include/vendor/example/runtime', 0700, true);
	file_put_contents($root . '/include/vendor/autoload.php', '<?php');
	$required = ['name' => 'example/runtime', 'version' => '1.0.0'];
	file_put_contents($root . '/composer.lock', json_encode(['packages' => [$required], 'packages-dev' => [['name' => 'example/test', 'version' => '2.0.0']]]));
	$installed = $root . '/include/vendor/composer/installed.json';

	try {
		file_put_contents($installed, json_encode(['packages' => [$required + ['install-path' => '../example/runtime']]]));
		expect(install_validate_vendor_dependencies($root))->toBeTrue();
		rmdir($root . '/include/vendor/example/runtime');
		expect(install_validate_vendor_dependencies($root))->toBeFalse();
		mkdir($root . '/include/vendor/example/runtime');
		file_put_contents($installed, json_encode(['packages' => [array_merge($required, ['version' => '0.9.0', 'install-path' => '../example/runtime'])]]));
		expect(install_validate_vendor_dependencies($root))->toBeFalse();
		file_put_contents($installed, '{broken');
		expect(install_validate_vendor_dependencies($root))->toBeFalse();
		unlink($installed);
		expect(install_validate_vendor_dependencies($root))->toBeFalse();
	} finally {
		if (is_file($installed)) {
			unlink($installed);
		}
		unlink($root . '/composer.lock');
		unlink($root . '/include/vendor/autoload.php');
		rmdir($root . '/include/vendor/composer');
		rmdir($root . '/include/vendor/example/runtime');
		rmdir($root . '/include/vendor/example');
		rmdir($root . '/include/vendor');
		rmdir($root . '/include');
		rmdir($root);
	}
});
