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
	expect($remoteAgentSource)->toContain('if ($poller_host === $client_addr)');
});

test('remote agent authorization requires hostname allowlist membership', function () use ($remoteAgentSource) {
	expect($remoteAgentSource)->toContain('if (!in_array($normalized_client_name, $allowed_hostnames, true))');
});

test('remote agent authorization no longer suppresses dns_get_record errors', function () use ($remoteAgentSource) {
	expect($remoteAgentSource)->not->toContain('@dns_get_record(');
	expect($remoteAgentSource)->toContain('dns_get_record($client_name, DNS_A | DNS_AAAA)');
});

test('remote agent authorization does not trust HTTP Host header', function () use ($remoteAgentSource) {
	$start = strpos($remoteAgentSource, 'function remote_client_authorized()');
	expect($start)->not->toBeFalse();

	$body = substr($remoteAgentSource, $start, 2200);
	expect($body)->not->toContain('HTTP_HOST');
	expect($body)->not->toContain('SERVER_NAME');
});

test('remote agent authorization caches dns authorization decisions', function () use ($remoteAgentSource) {
	expect($remoteAgentSource)->toContain('remote_agent_auth_cache_get($cache_key)');
	expect($remoteAgentSource)->toContain('remote_agent_auth_cache_set($cache_key, true)');
	expect($remoteAgentSource)->toContain('remote_agent_auth_cache_set($cache_key, false)');
});
