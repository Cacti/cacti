<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiLibraryErrorFixture;

/** The notification transport is the only substituted boundary. */
function admin_email(string $subject, string $message): bool {
	$GLOBALS['library_error_notifications'][] = [$subject, $message];

	return true;
}
function mailer(...$arguments): string {
	$GLOBALS['library_error_mail'][] = $arguments;

	return $GLOBALS['library_error_mail_result'];
}
function fixture_setup(string $plugin): bool {
	return \api_plugin_register_hook($plugin, 'coverage_error_event', 'coverage_error_event', 'setup.php', true);
}

/** Deterministic DNS failure for this fixture only; sockets use the real transport. */
function gethostbyname(string $host): string {
	return $host === 'collector.coverage.invalid' ? $host : \gethostbyname($host);
}

function remote(): void {
	require_once __DIR__ . '/../../Helpers/SourceSlice.php';
	class_alias(\ErrorException::class, __NAMESPACE__ . '\\ErrorException');
	class_alias(\Throwable::class, __NAMESPACE__ . '\\Throwable');
	\cacti_fixture_source_function(CACTI_PATH_LIBRARY . '/functions.php', 'call_remote_data_collector', __NAMESPACE__);
	$poller = (int) \sql_save(['id' => 0, 'name' => 'Collector failure fixture', 'hostname' => 'collector.coverage.invalid', 'disabled' => 'on'], 'poller');
	\library_expect($poller > 1, true, 'Notification fixture owns its collector');
	$previous = [];

	foreach (['force_https', 'remote_agent_port', 'remote_agent_timeout'] as $key) {
		$previous[$key] = \read_config_option($key);
	}
	$GLOBALS['library_error_notifications'] = [];
	$sentinel                               = static fn (): bool => false;
	set_error_handler($sentinel);

	try {
		\set_config_option('force_https', '');
		\set_config_option('remote_agent_port', '9');
		\set_config_option('remote_agent_timeout', '1');

		foreach (['collector.coverage.invalid' => '', \gethostbyname(gethostname()) => false] as $host => $expected) {
			\db_execute_prepared('UPDATE poller SET hostname = ? WHERE id = ?', [$host, $poller]);
			$count = count($GLOBALS['library_error_notifications']);
			\library_expect(call_remote_data_collector($poller, '/coverage'), $expected, 'DNS and refused-connection failures retain their established outcome');
			\library_expect(count($GLOBALS['library_error_notifications']), $count + 1, 'First collector failure sends an administrator notification');
			\library_expect(str_contains($GLOBALS['library_error_notifications'][$count][1], $host), true, 'Collector notification identifies the failed destination');
			\library_expect(call_remote_data_collector($poller, '/coverage'), $expected, 'Repeated failure retains its outcome');
			\library_expect(count($GLOBALS['library_error_notifications']), $count + 1, 'Repeated collector failure is debounced');
			$current = set_error_handler($sentinel);
			restore_error_handler();
			\library_expect($current, $sentinel, 'Failed collector transport restores the caller error handler');
		}
	} finally {
		restore_error_handler();

		foreach ($previous as $key => $value) {
			\set_config_option($key, $value);
		}

		foreach (['poller_down:', 'poller_connect_down:'] as $prefix) {
			$key = 'debounce_' . md5($prefix . $poller);
			\db_execute_prepared('DELETE FROM settings WHERE name = ?', [$key]);
			unset($GLOBALS['config'][OPTIONS_CLI][$key]);
		}
		\db_execute_prepared('DELETE FROM poller WHERE id = ?', [$poller]);
		unset($GLOBALS['library_error_notifications']);
	}
}
function run(): void {
	require_once __DIR__ . '/../../Helpers/SourceSlice.php';

	foreach (['CactiErrorHandler', 'get_theme_paths', 'send_mail'] as $function) {
		\cacti_fixture_source_function(CACTI_PATH_LIBRARY . '/functions.php', $function, __NAMESPACE__);
	}
	$name      = 'coverage_error_' . bin2hex(random_bytes(6));
	$directory = CACTI_PATH_PLUGINS . '/' . $name;
	\library_expect(mkdir($directory, 0700), true, 'Error fixture owns its plugin directory');
	\library_expect(file_put_contents($directory . '/setup.php', '<?php'), 5, 'Error attribution uses an actual plugin entry point');
	$level                                  = error_reporting(E_ALL);
	$GLOBALS['library_error_notifications'] = [];

	try {
		\library_expect(\db_execute_prepared('INSERT INTO plugin_config (directory, name, status) VALUES (?, ?, ?)', [$name, 'Error handling fixture', 1]), true, 'Error fixture owns its enabled plugin');
		\library_expect(fixture_setup($name), true, 'Error fixture owns an active hook');
		\library_expect(\api_plugin_hook('coverage_error_event', ['id' => 17]), ['coverage_error_event', ['id' => 17]], 'Missing hook function preserves caller arguments and does not fabricate a result');

		foreach ([E_WARNING, E_USER_NOTICE, E_USER_ERROR, E_DEPRECATED] as $severity) {
			\library_expect(CactiErrorHandler($severity, 'Fixture diagnostic', $directory . '/setup.php', 1), false, 'Logged diagnostic retains PHP default reporting');
			\library_expect((int) \db_fetch_cell_prepared('SELECT status FROM plugin_config WHERE directory = ?', [$name]), 1, 'Nonfatal diagnostic preserves plugin availability');
		}
		error_reporting(0);
		\library_expect(CactiErrorHandler(E_ERROR, 'Suppressed fixture error', $directory . '/setup.php', 1), true, 'Suppressed error is handled without disabling the plugin');
		\library_expect($GLOBALS['library_error_notifications'], [], 'Suppressed and nonfatal errors do not notify the administrator');
		error_reporting(E_ALL);
		\library_expect(CactiErrorHandler(E_ERROR, 'Fixture fatal error', $directory . '/setup.php', 1), false, 'Fatal plugin error retains PHP default reporting');
		\library_expect((int) \db_fetch_cell_prepared('SELECT status FROM plugin_config WHERE directory = ?', [$name]), 7, 'Fatal plugin error persists the disabled error state');
		\library_expect((int) \db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_hooks WHERE name = ? AND status = 1', [$name]), 0, 'Fatal plugin error disables its active hooks');
		\library_expect(count($GLOBALS['library_error_notifications']), 1, 'Fatal plugin error sends exactly one administrator notification');
		\library_expect(str_contains($GLOBALS['library_error_notifications'][0][1], $name), true, 'Administrator notification identifies the affected plugin');
		$missing = 'coverage-missing-' . bin2hex(random_bytes(8)) . '.css';
		\library_expect(get_theme_paths('%s', 'include/themes/modern/', 'modern', $missing), '', 'Missing theme asset never produces a broken asset URL');
		\library_expect(count($GLOBALS['library_error_notifications']), 2, 'Missing required theme asset notifies the administrator');
		get_theme_paths('%s', 'include/themes/modern/', 'modern', $missing);
		\library_expect(count($GLOBALS['library_error_notifications']), 2, 'Repeated missing asset warning is debounced');

		foreach (['Fixture <sender@example.invalid>', ['email' => 'sender@example.invalid', 'name' => 'Fixture']] as $sender) {
			foreach (['', 'SMTP unavailable'] as $result) {
				$GLOBALS['library_error_mail_result'] = $result;
				$GLOBALS['library_error_mail']        = [];
				$attachments                          = [['filename' => 'sample.csv', 'attachment' => 'x,y']];
				$headers                              = ['X-Fixture' => 'handoff'];
				\library_expect(send_mail('recipient@example.invalid', $sender, 'Subject', '<b>Body</b>', $attachments, $headers, true, true), $result, 'Mail wrapper preserves the transport outcome');
				\library_expect($GLOBALS['library_error_mail'], [[$sender, 'recipient@example.invalid', 'subject' => 'Subject', 'body' => '<b>Body</b>', 'attachments' => $attachments, 'headers' => $headers, 'html' => true, 'expandIds' => true]], 'Mail wrapper preserves structured sender, body, attachments, and flags');
			}
		}
	} finally {
		error_reporting($level);
		\db_execute_prepared('DELETE FROM plugin_hooks WHERE name = ?', [$name]);
		\db_execute_prepared('DELETE FROM plugin_config WHERE directory = ?', [$name]);
		unlink($directory . '/setup.php');
		rmdir($directory);
		unset($GLOBALS['library_error_notifications'], $GLOBALS['library_error_mail'], $GLOBALS['library_error_mail_result']);
	}
}
