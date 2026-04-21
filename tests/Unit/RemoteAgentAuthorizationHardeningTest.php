<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$remoteAgentSource = file_get_contents(__DIR__ . '/../../remote_agent.php');

test('remote agent authorization checks direct poller IP before DNS', function () use ($remoteAgentSource) {
	expect($remoteAgentSource)->toContain('if ($poller_host == $client_addr)');
});

test('remote agent authorization requires hostname allowlist membership', function () use ($remoteAgentSource) {
	expect($remoteAgentSource)->toContain('if (!in_array($client_name, $allowed_hostnames, true))');
});

test('remote agent authorization no longer suppresses dns_get_record errors', function () use ($remoteAgentSource) {
	expect($remoteAgentSource)->not->toContain('@dns_get_record(');
	expect($remoteAgentSource)->toContain('dns_get_record($client_name, DNS_A | DNS_AAAA)');
});

