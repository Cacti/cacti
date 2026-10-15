<?php

require dirname(__DIR__, 2) . '/bootstrap-unit.php';

set_error_handler(function ($level, $message) {
	throw new RuntimeException($message);
});

// Call from actual file scope, where the third backtrace frame is absent.
if (api_plugin_register_realm('entrypoint_fixture', 'index.php', 'Fixture', false) !== false) {
	throw new RuntimeException('Registration outside install/upgrade/setup must be rejected.');
}

print "rejected without warnings\n";
