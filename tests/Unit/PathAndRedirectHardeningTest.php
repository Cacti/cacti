<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$functionsSource = file_get_contents(__DIR__ . '/../../lib/functions.php');
$importSource    = file_get_contents(__DIR__ . '/../../lib/import.php');

test('cacti_header redirects using validated save_url', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function cacti_header(');
	expect($start)->not->toBeFalse();

	$body = substr($functionsSource, $start, 220);
	expect($body)->toContain('$save_url = validate_redirect_url(');
	expect($body)->toContain("header('Location: ' . \$save_url);");
	expect($body)->not->toContain('$safe_url');
});

test('cacti_header exits after redirect', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function cacti_header(');
	$body = substr($functionsSource, $start, 260);
	expect($body)->toContain('exit;');
});

test('validate_relative_path_within rejects absolute and drive-prefixed paths', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function validate_relative_path_within(');
	expect($start)->not->toBeFalse();

	$body = substr($functionsSource, $start, 1600);
	expect($body)->toContain("preg_match('/^[a-zA-Z]:\\//', \$normalized)");
	expect($body)->toContain("\$normalized[0] === '/'");
});

test('validate_relative_path_within rejects symlink path segments', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function validate_relative_path_within(');
	$body = substr($functionsSource, $start, 1800);
	expect($body)->toContain('is_link($walk)');
});

test('validate_relative_path_within enforces canonical containment checks', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function validate_relative_path_within(');
	$body = substr($functionsSource, $start, 2200);
	expect($body)->toContain('cacti_path_is_within($resolved, $base_real)');
	expect($body)->toContain('cacti_path_is_within($parent, $base_real)');
});

test('import path policy is anchored to scripts/resource prefixes', function () use ($importSource) {
	expect($importSource)->toContain("preg_match('/^(scripts|resource)\\/[A-Za-z0-9._\\/-]+$/', \$normalized_name)");
});

