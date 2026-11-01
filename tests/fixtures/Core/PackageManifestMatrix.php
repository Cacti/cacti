<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\Manifest;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 3) . '/lib/functions.php';
define('MESSAGE_LEVEL_ERROR', 1);
$GLOBALS['manifest_calls']    = [];
$GLOBALS['manifest_messages'] = [];

function db_fetch_row_prepared($sql, $params) {
	if ($params !== ['7']) {
		throw new \RuntimeException('Repository ID was not bound');
	}

	return $GLOBALS['manifest_repo'];
}

function cacti_http($method, $url, $options = []) {
	$GLOBALS['manifest_calls'][] = [$method, $url, $options];

	if ($GLOBALS['manifest_real_http'] ?? false) {
		return \cacti_http($method, $url, $options);
	}

	return $GLOBALS['manifest_result'];
}

function raise_message($id, $text, $level) {
	$GLOBALS['manifest_messages'][] = [$id, $level];
}

function __esc($text, ...$args) {
	return vsprintf($text, $args);
}

\cacti_fixture_source_function(dirname(__DIR__, 3) . '/package_import.php', 'get_repo_file', __NAMESPACE__);
$token = bin2hex(random_bytes(16));
$count = 0;

foreach ([0, 2] as $type) {
	foreach (['', $token] as $key) {
		foreach ([['success' => true, 'body' => 'manifest'], ['success' => true, 'body' => ''], ['success' => false, 'body' => 'must not leak']] as $result) {
			foreach ([false, true] as $javascript) {
				$GLOBALS['manifest_repo']     = ['repo_type' => $type, 'repo_location' => 'https://github.com/Author/Repo', 'repo_branch' => 'main', 'repo_api_key' => $key, 'name' => 'Fixture'];
				$GLOBALS['manifest_result']   = $result;
				$GLOBALS['manifest_calls']    = [];
				$GLOBALS['manifest_messages'] = [];
				$actual                       = get_repo_file('7', 'package.manifest', $javascript);
				$expected                     = $result['success'] && $result['body'] !== '' ? $result['body'] : false;
				$url                          = $type === 0 ? 'https://raw.githubusercontent.com/Author/Repo/main/package.manifest' : 'https://github.com/Author/Repo/package.manifest';
				$options                      = $type === 0 && $key !== '' ? ['headers' => ['Authorization: Bearer ' . $key]] : [];

				if ($actual !== $expected || $GLOBALS['manifest_calls'] !== [['GET', $url, $options]] || count($GLOBALS['manifest_messages']) !== ($expected === false && !$javascript ? 1 : 0)) {
					throw new \RuntimeException('Manifest transport contract failed at case ' . $count);
				}
				$count++;
			}
		}
	}
}

// Exercise the real HTTP policy through the changed import function. No network
// service is contacted: these inputs must be rejected by the preflight policy.
$GLOBALS['manifest_real_http'] = true;

foreach (['file:///etc', 'ftp://example.com', 'http://127.0.0.1', 'http://[::1]', 'http://169.254.169.254', 'http://10.0.0.1', 'http://192.168.1.1'] as $url) {
	$GLOBALS['manifest_repo']['repo_type']     = 2;
	$GLOBALS['manifest_repo']['repo_location'] = $url;

	if (get_repo_file('7', 'package.manifest', true) !== false) {
		throw new \RuntimeException('Unsafe manifest target accepted');
	}
	$count++;
}
$GLOBALS['manifest_repo'] = [];

if (get_repo_file('7') !== false) {
	throw new \RuntimeException('Unknown repository accepted');
}
$count++;
print json_encode(['passed' => $count], JSON_THROW_ON_ERROR) . "\n";
