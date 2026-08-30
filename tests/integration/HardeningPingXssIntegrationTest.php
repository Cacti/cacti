<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 |
 | Integration test: hostname XSS hardening in lib/ping.php (GHSA-43gj-mcpx-24m9).
 |
 | Run inside the container where lib/ and include/ are available:
 |   docker exec cacti12_web php /var/www/html/cacti/tests/integration/HardeningPingXssIntegrationTest.php
 |
 | Exit 0 on all assertions passing; exit 1 on first failure.
 +-------------------------------------------------------------------------+
*/

define('CACTI_CLI_ONLY', true);
chdir('/var/www/html/cacti');
require_once 'include/global.php';
require_once 'lib/ping.php';

/* ---- harness ---- */

$failures = 0;

function ping_xss_pass($label) {
	echo "PASS: {$label}" . PHP_EOL;
}

function ping_xss_fail($label, $detail = '') {
	global $failures;
	$failures++;
	echo "FAIL: {$label}" . ($detail !== '' ? " -- {$detail}" : '') . PHP_EOL;
}

/* ---- test: UDP DNS-fail path escapes the XSS payload ---- */

// A hostname that is not an IP address will go through cacti_gethostbyname(),
// which returns the original string on DNS failure.  is_ipaddress() then
// returns false and the code takes the DNS-fail branch, which must call
// html_escape() before embedding the hostname in ping_response.

$xss = '<script>alert(1)</script>';

$ping          = new Net_Ping();
$ping->host    = ['hostname' => $xss];
$ping->timeout = 500;
$ping->retries = 1;

$ping->ping_udp();

$response = $ping->ping_response;

// The raw tag must not appear in the response string that callers display.
if (strpos($response, '<script>') !== false) {
	ping_xss_fail('ping_response must not contain raw <script> tag', "got: {$response}");
} else {
	ping_xss_pass('ping_response does not contain raw <script> tag');
}

// The HTML-escaped form must be present, confirming html_escape() was called.
if (strpos($response, '&lt;script&gt;') === false) {
	ping_xss_fail('ping_response must contain &lt;script&gt; (escaped form)', "got: {$response}");
} else {
	ping_xss_pass('ping_response contains &lt;script&gt; (escaped form)');
}

/* ---- test: ICMP DNS-fail path ---- */

$ping2          = new Net_Ping();
$ping2->host    = ['hostname' => $xss];
$ping2->timeout = 500;
$ping2->retries = 1;

$ping2->ping_icmp();

$resp2 = $ping2->ping_response;

if (strpos($resp2, '<script>') !== false) {
	ping_xss_fail('ICMP ping_response must not contain raw <script> tag', "got: {$resp2}");
} else {
	ping_xss_pass('ICMP ping_response does not contain raw <script> tag');
}

if (strpos($resp2, '&lt;script&gt;') === false) {
	ping_xss_fail('ICMP ping_response must contain &lt;script&gt; (escaped form)', "got: {$resp2}");
} else {
	ping_xss_pass('ICMP ping_response contains &lt;script&gt; (escaped form)');
}

/* ---- test: TCP DNS-fail path ---- */

$ping3          = new Net_Ping();
$ping3->host    = ['hostname' => $xss];
$ping3->timeout = 500;
$ping3->retries = 1;

$ping3->ping_tcp();

$resp3 = $ping3->ping_response;

if (strpos($resp3, '<script>') !== false) {
	ping_xss_fail('TCP ping_response must not contain raw <script> tag', "got: {$resp3}");
} else {
	ping_xss_pass('TCP ping_response does not contain raw <script> tag');
}

if (strpos($resp3, '&lt;script&gt;') === false) {
	ping_xss_fail('TCP ping_response must contain &lt;script&gt; (escaped form)', "got: {$resp3}");
} else {
	ping_xss_pass('TCP ping_response contains &lt;script&gt; (escaped form)');
}

/* ---- summary ---- */

$total = 6;
$passed = $total - $failures;
echo PHP_EOL . "Tests: {$total}, Passed: {$passed}, Failed: {$failures}" . PHP_EOL;

exit($failures > 0 ? 1 : 0);
