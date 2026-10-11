<?php

require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global.php';

test('error attribution respects the Cacti plugin root and owning directory (#7968)', function () {
	$name      = 'error_fixture_' . bin2hex(random_bytes(6));
	$directory = CACTI_PATH_PLUGINS . '/' . $name;
	$outside   = sys_get_temp_dir() . '/' . $name;
	mkdir($directory . '/vendor/plugins/thold', 0700, true);
	mkdir($outside . '/plugins/thold', 0700, true);

	try {
		file_put_contents($directory . '/setup.php', '<?php');
		file_put_contents($directory . '/vendor/plugins/thold/Foo.php', '<?php');
		file_put_contents($outside . '/plugins/thold/Foo.php', '<?php');
		expect(cacti_error_plugin($directory . '/setup.php'))->toBe($name);
		expect(cacti_error_plugin($directory . '/vendor/plugins/thold/Foo.php'))->toBe($name);
		expect(cacti_error_plugin($outside . '/plugins/thold/Foo.php'))->toBe('');
		expect(cacti_error_plugin(CACTI_PATH_PLUGINS . '/../lib/functions.php'))->toBe('');
		expect(cacti_error_plugin($directory . '/missing.php'))->toBe('');
		expect(cacti_error_plugin(CACTI_PATH_PLUGINS . '/index.php'))->toBe('');

		// A file reached through a plugin symlink still lies outside the resolved
		// plugin root and must not cause a same-named Cacti plugin to be disabled.
		symlink($outside . '/plugins/thold', $directory . '/external');
		expect(cacti_error_plugin($directory . '/external/Foo.php'))->toBe('');
		unlink($directory . '/external');
	} finally {
		unlink($directory . '/setup.php');
		unlink($directory . '/vendor/plugins/thold/Foo.php');
		unlink($outside . '/plugins/thold/Foo.php');
		rmdir($directory . '/vendor/plugins/thold');
		rmdir($directory . '/vendor/plugins');
		rmdir($directory . '/vendor');
		rmdir($directory);
		rmdir($outside . '/plugins/thold');
		rmdir($outside . '/plugins');
		rmdir($outside);
	}
});
