<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiLibraryReportDeliveryFixture;

/** Capture only the outbound delivery boundary; queue and history use the real schema. */
function mailer(...$arguments): string {
	$GLOBALS['library_mail_delivery'][] = $arguments;

	return $GLOBALS['library_mail_failure'] ?? '';
}
function run(): void {
	require_once __DIR__ . '/../../Helpers/SourceSlice.php';
	\cacti_fixture_source_function(CACTI_PATH_LIBRARY . '/reports.php', 'reports_log_and_notify', __NAMESPACE__);
	$name = 'Coverage delivery ' . bin2hex(random_bytes(6));
	$id   = (int) \reports_queue($name, REPORTS_SEND_NOW, 'reports', 0, '', []);
	\library_expect($id > 0, true, 'Delivery fixture owns a real queue record');
	$path = tempnam(sys_get_temp_dir(), 'cacti-report-delivery-');
	\library_expect(is_string($path), true, 'Delivery fixture owns its attachment');
	$bytes = "name,value\nfixture,12.5\n";
	\library_expect(file_put_contents($path, $bytes), strlen($bytes), 'Attachment bytes are complete');
	$attachments = [['inline' => 'attachment', 'attachment' => $path, 'filename' => 'samples.csv', 'mime_type' => 'text/csv']];
	$defaults    = ['email' => \read_config_option('settings_from_email') ?: 'cacti@cacti.net', 'name' => \read_config_option('settings_from_name') ?: 'Cacti Reports'];

	try {
		foreach ([
			['to_email' => 'fixture@example.invalid'],
			['to_email' => 'fixture@example.invalid', 'cc_email' => 'cc@example.invalid', 'bcc_email' => 'bcc@example.invalid', 'reply_to' => 'reply@example.invalid', 'from' => ['email' => 'sender@example.invalid', 'name' => 'Fixture sender']],
			['to_email' => 'fixture@example.invalid', 'from' => 'sender@example.invalid'],
		] as $delivery) {
			$GLOBALS['library_mail_delivery'] = [];
			\db_execute_prepared('UPDATE reports_queued SET notification = ? WHERE id = ?', [json_encode(['email' => $delivery], JSON_THROW_ON_ERROR), $id]);
			$raw     = ['samples' => [0, 12.5, null]];
			$output  = $bytes;
			$html    = '<p>Fixture report</p>';
			$text    = 'Fixture report';
			$headers = ['X-Cacti-Fixture' => 'delivery'];
			reports_log_and_notify($id, time(), 'html', 'reports', 0, 'Fixture subject', $raw, $output, $html, $text, $attachments, $headers);
			\library_expect($GLOBALS['library_mail_delivery'], [[$delivery['from'] ?? $defaults, $delivery['to_email'], $delivery['cc_email'] ?? '', $delivery['bcc_email'] ?? '', $delivery['reply_to'] ?? '', 'Fixture subject', $html, $text, $attachments, $headers]], 'Delivery preserves every destination, body, header and attachment');
			$history = \db_fetch_row_prepared('SELECT * FROM reports_log WHERE name = ? ORDER BY id DESC LIMIT 1', [$name]);
			\library_expect(json_decode($history['report_raw_data'], true), $raw, 'History preserves raw samples');
			$saved = json_decode(base64_decode($history['report_attachments'], true), true, 512, JSON_THROW_ON_ERROR);
			\library_expect($saved, [array_replace($attachments[0], ['attachment' => $bytes, 'inline' => 'inline'])], 'History retains attachment bytes independently of the temporary path');
			\library_expect(file_get_contents($path), $bytes, 'Delivery preserves the caller-owned attachment');
		}
	} finally {
		\db_execute_prepared('DELETE FROM reports_queued WHERE id = ?', [$id]);
		\db_execute_prepared('DELETE FROM reports_log WHERE name = ?', [$name]);
		unlink($path);
		unset($GLOBALS['library_mail_delivery']);
	}
}

function failures(): void {
	require_once __DIR__ . '/../../Helpers/SourceSlice.php';
	\cacti_fixture_source_function(CACTI_PATH_LIBRARY . '/reports.php', 'generate_report', __NAMESPACE__);
	$id = (int) \sql_save(['id' => 0, 'name' => 'Delivery failure fixture', 'user_id' => 1, 'enabled' => 'on', 'from_email' => 'sender@example.invalid', 'email' => 'recipient@example.invalid'], 'reports');
	\library_expect($id > 0, true, 'Delivery failure fixture owns its report');
	$request = [$_REQUEST, $_GET, $_POST, $GLOBALS['_CACTI_REQUEST'] ?? []];
	$session = $_SESSION;

	try {
		$report = \db_fetch_row_prepared('SELECT * FROM reports WHERE id = ?', [$id]);

		foreach ([false, true] as $interactive) {
			$_REQUEST = $_GET = $_POST = $GLOBALS['_CACTI_REQUEST'] = [];

			if ($interactive) {
				\srv('id', $id);
			}
			$GLOBALS['library_mail_failure']  = 'Fixture SMTP unavailable';
			$GLOBALS['library_mail_delivery'] = [];
			unset($_SESSION[SESS_MESSAGES]['report_message']);
			\library_expect(generate_report(0, $report, true), false, 'Report generation propagates the mail transport failure');
			\library_expect(count($GLOBALS['library_mail_delivery']), 1, 'Failed delivery makes exactly one transport attempt');

			if ($interactive) {
				\library_expect($_SESSION[SESS_MESSAGES]['report_message']['level'] ?? null, MESSAGE_LEVEL_ERROR, 'Interactive delivery failure is shown as an error');
			} else {
				\library_expect(isset($_SESSION[SESS_MESSAGES]['report_message']), false, 'Scheduled delivery does not fabricate an interactive success message');
			}
			$GLOBALS['library_mail_failure'] = '';
			\library_expect(generate_report(0, $report, true), true, 'The same report can retry after the mail transport recovers');
			\library_expect(count($GLOBALS['library_mail_delivery']), 2, 'Successful retry makes one additional transport attempt');
		}
	} finally {
		\db_execute_prepared('DELETE FROM reports WHERE id = ?', [$id]);
		[$_REQUEST, $_GET, $_POST, $GLOBALS['_CACTI_REQUEST']] = $request;
		$_SESSION                                              = $session;
		unset($GLOBALS['library_mail_failure'], $GLOBALS['library_mail_delivery']);
	}
}
