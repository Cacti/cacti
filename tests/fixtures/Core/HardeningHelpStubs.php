<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Global stubs used by help.php when exercised inside HardeningCoreBulkMatrix.php.
if (!function_exists('isrv')) {
	function isrv(string $name) : bool {
		return isset($_REQUEST[$name]);
	}

	function gnrv(string $name) : mixed {
		return $_REQUEST[$name] ?? '';
	}

	function gfrv(string $name) : int {
		return (int) ($_REQUEST[$name] ?? 0);
	}

	function get_username(int $id) : string {
		return 'user<' . $id . '>';
	}

	function cacti_log(string $message, bool $output = false, string $environment = '', int $level = 0) : void {
		$GLOBALS['help_logs'][] = $message;
	}

	function debounce_run_notification(string $key) : bool {
		return true;
	}

	function html_escape(string $value) : string {
		return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
	}

	function __(string $format, mixed ...$args) : string {
		return $args === [] ? $format : vsprintf($format, $args);
	}

	function admin_email(string $subject, string $body) : void {
		$GLOBALS['help_emails'][] = [$subject, $body];
	}
}
