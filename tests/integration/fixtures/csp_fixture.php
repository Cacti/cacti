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
 * the harness can drive every branch of the setting without a database. The
 * header and <meta> lines below mirror include/global.php and lib/html.php,
 * so the test proves the real wire format for each mode over HTTP.
 *
 * Dependency-free on purpose: only lib/headers_secure.php is loaded so a
 * failure points at the header helper, not the Cacti bootstrap.
 */

require_once __DIR__ . '/../../../lib/headers_secure.php';

if (!function_exists('read_config_option')) {
	function read_config_option($key) {
		switch ($key) {
			case 'content_security_policy_script':
				$v = getenv('CSP_TEST_MODE');
				return $v === false ? '' : $v;
			case 'content_security_alternate_sources':
				$v = getenv('CSP_TEST_ALTERNATES');
				return $v === false ? '' : $v;
			case 'content_security_report_uri':
				$v = getenv('CSP_TEST_REPORT_URI');
				return $v === false ? '' : $v;
		}

		return '';
	}
}

$alternates = htmlspecialchars((string) read_config_option('content_security_alternate_sources'), ENT_QUOTES);
$script_src = CactiSecureHeaders::scriptSrc($alternates);
$report_uri = CactiSecureHeaders::reportUriDirective();

/* Mirrors the HTTP header emitted by include/global.php. */
header("Content-Security-Policy: default-src *; img-src 'self' https://api.qrserver.com $alternates data: blob:; style-src 'self' 'unsafe-inline' $alternates; $script_src; frame-ancestors 'self' $alternates; worker-src 'self' $alternates;$report_uri");

$nonce_attr = CactiSecureHeaders::getNonceAttribute();

echo "<!doctype html>\n<html><head>\n";
/* Mirrors the <meta> policy in lib/html.php: no frame-ancestors/report-uri,
 * both of which are invalid inside a meta CSP. */
echo "<meta http-equiv=\"Content-Security-Policy\" content=\"default-src *; img-src 'self' https://api.qrserver.com $alternates data: blob:; style-src 'self' 'unsafe-inline' $alternates; $script_src; worker-src 'self' $alternates;\">\n";
echo "</head><body>\n";
echo "<script type='text/javascript' $nonce_attr>var x = 1;</script>\n";
echo "</body></html>\n";
