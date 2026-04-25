<?php
/*
 * Probes the remote poller's HTTPS endpoint directly using get_default_contextoption().
 * Bypasses get_url_type() to exercise the TLS-self-signed code path unconditionally.
 * The harness intentionally leaves force_https off to keep the master plaintext for
 * tests 01/04 that require plain HTTP. This probe validates that HTTPS with
 * self-signed cert works via the SSL context helper. If any future change adds
 * 'verify_peer' => true to get_default_contextoption(), this probe will fail.
 *
 * Exit code: 0 on success, 2 on empty/false body.
 */

chdir(__DIR__ . '/../../../..');
$config = array();
$_SERVER['SCRIPT_NAME']     = '/probe_remote_fetch.php';
$_SERVER['SCRIPT_FILENAME'] = __FILE__;

require __DIR__ . '/../../../../include/global.php';

$url     = 'https://cacti-poller/index.php';
$context = stream_context_create(array('ssl' => get_default_contextoption()));
$body    = @file_get_contents($url, false, $context);

if (!is_string($body) || $body === '') {
    fwrite(STDERR, "FAIL: file_get_contents returned empty body\n");
    exit(2);
}

$len = strlen($body);
$preview = substr($body, 0, 200);
echo "OK len=$len\n";
echo "preview=" . str_replace(array("\r", "\n"), ' ', $preview) . "\n";
exit(0);
