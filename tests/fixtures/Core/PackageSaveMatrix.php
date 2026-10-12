<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\ManifestSave\Assert {
	class NotBlank {
	}
	class Regex {
		public function __construct($pattern) {
		}
	}
	class Length {
		public function __construct($max = null) {
		}
	}
}
namespace Cacti\Test\ManifestSave {
	if (PHP_SAPI !== 'cli') {
		http_response_code(404);
		exit;
	}
	require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
	require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
	define('MESSAGE_LEVEL_INFO', 1);
	define('MESSAGE_LEVEL_WARN', 2);
	class CactiValidator {
		public static function validateInput($value, $name, $constraints, $level) {
			return $value;
		}
	}
	function isrv($key) {
		return isset($GLOBALS['save_request'][$key]);
	}
	function gnrv($key) {
		return $GLOBALS['save_request'][$key] ?? '';
	}
	function is_error_message() {
		return false;
	}
	function sql_save($save, $table, $key) {
		$GLOBALS['save_record'] = $save;

		return 7;
	}
	function raise_message($id, ...$args) {
		$GLOBALS['save_messages'][] = $id;
	}
	function __esc($text, ...$args) {
		return vsprintf($text, $args);
	}
	function header($value) {
		$GLOBALS['save_redirect'] = $value;
	}
	function cacti_http($method, $url, $options = []) {
		$GLOBALS['save_calls'][] = [$method, $url, $options];

		return $GLOBALS['save_result'];
	}
	\cacti_fixture_source_function(dirname(__DIR__, 3) . '/package_repos.php', 'form_save', __NAMESPACE__);
	$count = 0;
	$token = bin2hex(random_bytes(16));

	foreach ([0, 2] as $type) {
		foreach (['', $token] as $key) {
			foreach ([['success' => true, 'body' => 'manifest'], ['success' => true, 'body' => ''], ['success' => false, 'body' => 'do not accept']] as $result) {
				$GLOBALS['save_request']  = ['save_component_repo' => true, 'id' => '7', 'repo_type' => $type, 'name' => 'Fixture', 'repo_location' => 'https://github.com/Author/Repo', 'repo_branch' => 'main', 'repo_api_key' => $key];
				$GLOBALS['save_result']   = $result;
				$GLOBALS['save_calls']    = [];
				$GLOBALS['save_messages'] = [];
				form_save();
				$url     = $type === 0 ? 'https://raw.githubusercontent.com/Author/Repo/main/package.manifest' : 'https://github.com/Author/Repo/package.manifest';
				$options = $type === 0 && $key !== '' ? ['headers' => ['Authorization: Bearer ' . $key]] : [];
				$message = $result['success'] && $result['body'] !== '' ? 'repo_exists' : 'repo_missing';

				if ($GLOBALS['save_calls'] !== [['GET', $url, $options]] || $GLOBALS['save_messages'] !== [$message, 1] || $GLOBALS['save_redirect'] !== 'Location: package_repos.php?header=false&action=edit&id=7') {
					throw new \RuntimeException('Save transport mismatch at case ' . $count);
				}
				$count++;
			}
		}
	}
	print json_encode(['passed' => $count], JSON_THROW_ON_ERROR) . "\n";
}
