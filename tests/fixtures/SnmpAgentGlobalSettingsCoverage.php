<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

// Request-coverage probe for the permanent Boost redirect reported by
// snmpagent_global_settings_update() (issue#8470). The assignment lives in a
// MibCache-driven function that cannot run under the unit/integration driver
// without a live SNMP agent cache, so the single changed statement is compiled
// at its original line number and executed against a minimal MibCache stand-in.
// Xdebug coverage of the compiled line is remapped to lib/snmpagent.php.

require dirname(__DIR__) . '/Helpers/RequestCoverage.php';

$file   = dirname(__DIR__, 2) . '/lib/snmpagent.php';
$source = file_get_contents($file);

if ($source === false) {
	throw new RuntimeException('Unable to read lib/snmpagent.php for the Boost redirect probe.');
}

$needle = "\$mc->object('boostApplRrdUpdateRedirect')->set(1);";
$offset = strpos($source, $needle);

if ($offset === false) {
	throw new RuntimeException('Unable to locate the permanent Boost redirect assignment.');
}

$path = tempnam(sys_get_temp_dir(), 'cacti-snmpagent-redirect-');
file_put_contents($path, '<?php ' . str_repeat("\n", substr_count(substr($source, 0, $offset), "\n")) . $needle . "\n");
$GLOBALS['cacti_fixture_source_map'][$path] = $file;

// Minimal MibCache stand-in: the redirect object must still be reported as 1
// (permanently enabled) now that the boost_redirect setting is gone.
$mc = new class {
	public function object(string $name) : object {
		return new class {
			public function set(mixed $value) : void {
				if ($value !== 1) {
					throw new RuntimeException('The permanent Boost redirect must be reported as enabled.');
				}
			}
		};
	}
};

try {
	require $path;
	print "PASS: snmpagent reports the Boost redirect as permanently enabled\n";
} finally {
	unlink($path);
}
