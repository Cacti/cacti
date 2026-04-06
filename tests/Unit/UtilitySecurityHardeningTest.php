<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$utilSource = file_get_contents(__DIR__ . '/../../lib/utility.php');

test('utility_php_extensions validates binary path before shell_exec', function () use ($utilSource) {
	expect($utilSource)->toContain("!preg_match('/^php");
	expect($utilSource)->toContain('is_executable($php_binary)');
});

test('utility_php_recommends validates binary path', function () use ($utilSource) {
	$count = substr_count($utilSource, 'is_executable($php_binary)');
	expect($count)->toBeGreaterThanOrEqual(3);
});

test('poller_update_poller_cache_from_buffer casts ids to intval', function () use ($utilSource) {
	expect($utilSource)->toContain("array_map('intval', \$local_data_ids)");
});

test('update_poller_cache_from_query casts ids to intval', function () use ($utilSource) {
	expect($utilSource)->toContain("array_map('intval', \$local_data_ids)");
});

test('update_replication_crc uses random_bytes', function () use ($utilSource) {
	expect($utilSource)->toContain('random_bytes(16)');
});

test('update_replication_crc uses parameterized query', function () use ($utilSource) {
	$start = strpos($utilSource, 'function update_replication_crc(');
	$body = substr($utilSource, $start, 500);
	expect($body)->toContain('REPLACE INTO settings (name, value) VALUES (?, ?)');
});

test('mysql recommendations output uses html_escape', function () use ($utilSource) {
	expect($utilSource)->toContain("html_escape(\$name)");
	expect($utilSource)->toContain("html_escape(\$value_display)");
	expect($utilSource)->toContain("html_escape(\$value_recommend)");
});
