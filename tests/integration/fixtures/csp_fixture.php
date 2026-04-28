<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Stand-alone fixture served by the built-in PHP web server
 * (`php -S 127.0.0.1:<port> -t tests/integration/fixtures csp_fixture.php`).
 *
 * The active CSP mode comes from the CSP_TEST_MODE environment variable so
 * the harness can drive every branch of CactiSecureHeaders without a DB.
 *
 * Keep this file dependency-free on purpose: only lib/headers_secure.php is
 * loaded so a failure here points at the header helper, not at the Cacti
 * bootstrap.
 */

require_once __DIR__ . '/../../../lib/headers_secure.php';

/* Stub read_config_option(): the real implementation reads from the DB.
 * Tests drive it via CSP_TEST_MODE and (optional) CSP_TEST_ALTERNATES. */
if (!function_exists('read_config_option')) {
	function read_config_option($key) {
		if ($key === 'content_security_policy_script') {
			$v = getenv('CSP_TEST_MODE');
			return ($v === false) ? '' : $v;
		}
		if ($key === 'content_security_alternate_sources') {
			$v = getenv('CSP_TEST_ALTERNATES');
			return ($v === false) ? '' : $v;
		}
		return '';
	}
}

CactiSecureHeaders::emitHeaders();

/* Exercise the "calling emitHeaders twice in the same request is safe"
 * invariant: second call is a no-op once headers_sent() is true, but before
 * body output it still returns without duplicating headers (PHP's own header
 * list dedupes by name for non-array headers, but we document the behaviour
 * here so the integration test can assert it). */
CactiSecureHeaders::emitHeaders();

$nonce_attr = CactiSecureHeaders::getNonceAttribute();

echo "<!doctype html>\n";
echo "<html><head><title>csp fixture</title></head><body>\n";
echo "<script {$nonce_attr}>'test';</script>\n";
echo "</body></html>\n";
