<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$pingSource = file_get_contents(__DIR__ . '/../../lib/ping.php');

test('GHSA-43gj-mcpx-24m9: ICMP DNS failure ping_response wraps hostname in html_escape', function () use ($pingSource) {
	// When cacti_gethostbyname returns a non-IP (DNS failure), the caller-
	// visible ping_response must HTML-escape the hostname before embedding
	// it in the string, so a crafted hostname like <script>... cannot
	// reach the browser unescaped.
	expect($pingSource)->toContain(
		"'ICMP Ping Error: cacti_gethostbyname failed for ' . html_escape(\$this->host['hostname'])"
	);
});

test('GHSA-43gj-mcpx-24m9: UDP DNS failure ping_response wraps hostname in html_escape', function () use ($pingSource) {
	expect($pingSource)->toContain(
		"'UDP Ping Error: cacti_gethostbyname failed for ' . html_escape(\$this->host['hostname'])"
	);
});

test('GHSA-43gj-mcpx-24m9: TCP DNS failure ping_response wraps hostname in html_escape', function () use ($pingSource) {
	expect($pingSource)->toContain(
		"'TCP Ping Error: cacti_gethostbyname failed for ' . html_escape(\$this->host['hostname'])"
	);
});

test('GHSA-43gj-mcpx-24m9: cacti_log calls use raw hostname (not html_escape)', function () use ($pingSource) {
	// Log sinks consume plain text; HTML entities in log output would
	// corrupt syslog/file readers.  The log lines must pass the raw
	// hostname through, not the escaped variant.
	expect($pingSource)->toContain(
		"cacti_log('WARNING: ICMP Ping Error: cacti_gethostbyname failed for ' . \$this->host['hostname'])"
	);
	expect($pingSource)->toContain(
		"cacti_log('WARNING: UDP Ping Error: cacti_gethostbyname failed for ' . \$this->host['hostname'])"
	);
	expect($pingSource)->toContain(
		"cacti_log('WARNING: TCP Ping Error: cacti_gethostbyname failed for ' . \$this->host['hostname'])"
	);
});

test('GHSA-43gj-mcpx-24m9: shell command invocations do not use html_escape on hostname', function () use ($pingSource) {
	// Shell execution paths must use cacti_escapeshellarg (shell-safe),
	// never html_escape (HTML-safe only).  Mixing the two would either
	// double-encode or leave the shell boundary unprotected.
	$shellLines = preg_grep('/shell_exec\(/', explode("\n", $pingSource));
	foreach ($shellLines as $line) {
		expect($line)->not->toContain('html_escape');
		expect($line)->toContain('cacti_escapeshellarg');
	}
});
