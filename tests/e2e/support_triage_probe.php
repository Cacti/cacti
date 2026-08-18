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
*/

/*
 * Runs inside the Cacti web container. Drives the safe-triage redaction flow
 * (support.php, #7358) over real HTTP: with redact=1 the rendered Summary tab
 * must mask the RSA fingerprint and must not contain the live database host or
 * password (when non-default), and the Recent Log tab must render. Without
 * the flag, the report contains the full RSA fingerprint.
 *
 * Usage (inside the container):
 *   BASE_URL=http://localhost/cacti php support_triage_probe.php
 */

chdir(dirname(__DIR__, 2));

require_once CACTI_PATH_INCLUDE . '/global.php';

$base = rtrim(getenv('BASE_URL') ?: 'http://localhost/cacti', '/');
$user = getenv('CACTI_USER') ?: 'admin';
$pass = getenv('CACTI_PASS') ?: 'admin';
$jar  = tempnam(sys_get_temp_dir(), 'cacticookie');

function triage_probe_fail(string $message): void {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function triage_probe_request(string $url, string $jar, ?array $post = null): string {
	$ch = curl_init($url);

	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_COOKIEJAR      => $jar,
		CURLOPT_COOKIEFILE     => $jar,
		CURLOPT_TIMEOUT        => 20,
	]);

	if ($post !== null) {
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
	}

	$body = curl_exec($ch);
	curl_close($ch);

	return (string) $body;
}

function triage_probe_csrf(string $html): string {
	if (preg_match('/name="__csrf_magic"\s+value="([^"]+)"/', $html, $m)) {
		return $m[1];
	}

	if (preg_match("/csrfMagicToken\s*=\s*\"([^\"]+)\"/", $html, $m)) {
		return $m[1];
	}

	return '';
}

function triage_probe_report(string $html): string {
	// The shareable report lives in a hidden textarea.
	if (preg_match('/<textarea id=.diag_report.[^>]*>(.*?)<\/textarea>/s', $html, $m)) {
		return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
	}

	return '';
}

// Authenticate.
$loginHtml = triage_probe_request("$base/index.php", $jar);
$token     = triage_probe_csrf($loginHtml);
triage_probe_request("$base/index.php", $jar, [
	'__csrf_magic'   => $token,
	'action'         => 'login',
	'login_username' => $user,
	'login_password' => $pass,
]);

// Seed an RSA fingerprint so there is an identifying value to redact.
$fingerprint = 'abcdef0123456789cafebabefeedface';
set_config_option('rsa_fingerprint', $fingerprint);

global $database_hostname, $database_password;

// Redacted render: the report must mask the fingerprint and hide infra values.
$redacted = triage_probe_report(triage_probe_request("$base/support.php?tab=summary&redact=1", $jar));

if ($redacted === '') {
	triage_probe_fail('redacted Summary tab returned no diagnostics report');
}

if (strpos($redacted, $fingerprint) !== false) {
	triage_probe_fail('redacted report leaked the full RSA fingerprint');
}

if (strpos($redacted, substr($fingerprint, 0, 8)) === false) {
	triage_probe_fail('redacted report dropped the masked fingerprint prefix');
}

if (!empty($database_password) && strpos($redacted, $database_password) !== false) {
	triage_probe_fail('redacted report leaked the database password');
}

if (!empty($database_hostname) && !in_array($database_hostname, ['localhost', '127.0.0.1'], true)
	&& strpos($redacted, $database_hostname) !== false) {
	triage_probe_fail('redacted report leaked the database hostname');
}

// Unredacted render: the fingerprint appears in full.
$plain = triage_probe_report(triage_probe_request("$base/support.php?tab=summary", $jar));

if (strpos($plain, $fingerprint) === false) {
	triage_probe_fail('unredacted report did not contain the RSA fingerprint');
}

// The Recent Log tab must render.
$log = triage_probe_request("$base/support.php?tab=log&redact=1", $jar);

if (strpos($log, 'Recent Log') === false) {
	triage_probe_fail('Recent Log tab did not render');
}

@unlink($jar);

print "PASS support triage docker probe\n";
