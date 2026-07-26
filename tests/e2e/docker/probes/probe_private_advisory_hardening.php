<?php
/*
 * Runs the private advisory hardening integration test from the e2e container.
 */

$test = '/var/www/html/tests/integration/PrivateAdvisoryHardeningIntegrationTest.php';

if (!is_file($test)) {
	fwrite(STDERR, "FAIL: integration test not found: $test\n");
	exit(2);
}

passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($test), $rc);
exit($rc);
