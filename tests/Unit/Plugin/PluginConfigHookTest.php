<?php

require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global.php';

test('plugin configuration hook preserves the legacy null diagnostic response (#7968)', function ($body, $expected) {
	$name      = 'config_fixture_' . bin2hex(random_bytes(6));
	$directory = CACTI_PATH_PLUGINS . '/' . $name;
	mkdir($directory);

	try {
		file_put_contents($directory . '/setup.php', '<?php ' . ($body === null ? '' : 'function plugin_' . $name . '_check_config() {' . $body . '}'));
		expect(api_plugin_check_config($name))->toBe($expected);
	} finally {
		unlink($directory . '/setup.php');
		rmdir($directory);
	}
})->with([
	['return true;', true],
	['return false;', false],
	['return null;', null],
	['', null],
	[null, true],
]);

test('missing plugin setup does not pass the configuration check', function () {
	expect(api_plugin_check_config('missing_config_' . bin2hex(random_bytes(6))))->toBeFalse();
});
