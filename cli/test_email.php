<?php
$no_http_headers = true;
include('../include/global.php');

global $config;

$message =  'This is a test message generated from Cacti.  This message was sent to test the configuration of your Mail Settings<br><br>';
$message .= 'Your email settings are currently set as follows<br><br>';
$message .= '<b>Method</b>: ';

print 'Checking Configuration...';

$ping_results = true;
$how = read_config_option('settings_how');
if ($how < 0 || $how > 2)
	$how = 0;
if ($how == 0) {
	$mail = 'PHP\'s Mailer Class';
} else if ($how == 1) {
	$mail = 'Sendmail<br><b>Sendmail Path</b>: ';
	$sendmail = read_config_option('settings_sendmail_path');
	$mail .= $sendmail;
} else if ($how == 2) {
	print 'Method: SMTP';
	$mail = 'SMTP<br>';
	$smtp_host = read_config_option('settings_smtp_host');
	$smtp_port = read_config_option('settings_smtp_port');
	$smtp_username = read_config_option('settings_smtp_username');
	$smtp_password = read_config_option('settings_smtp_password');
	$smtp_secure   = read_config_option('settings_smtp_secure');
	$smtp_timeout  = read_config_option('settings_smtp_timeout');

	$mail .= "<b>Device</b>: $smtp_host<br>";
	$mail .= "<b>Port</b>: $smtp_port<br>";

	if ($smtp_username != '' && $smtp_password != '') {
		$mail .= '<b>Authentication</b>: true<br>';
		$mail .= "<b>Username</b>: $smtp_username<br>";
		$mail .= '<b>Password</b>: (Not Shown for Security Reasons)<br>';
		$mail .= "<b>Security</b>: $smtp_secure<br>";
	} else {
		$mail .= '<b>Authentication</b>: false<br>';
	}

	if (read_config_option('settings_ping_mail') == 0) {
		$ping_results = ping_mail_server($smtp_host, $smtp_port, $smtp_username, $smtp_password, $smtp_timeout, $smtp_secure);

		print "\nPing Results: " . ($ping_results == 1 ? 'Success':$ping_results);

		if ($ping_results != 1) {
			$mail .= '<b>Ping Results</b>: ' . $ping_results . '<br>';
		} else {
			$mail .= '<b>Ping Results</b>: Success<br>';
		}
	} else {
		$ping_results = 1;
		$mail .= '<b>Ping Results</b>: Bypassed<br>';
		print "\nPing Results: Bypassed";
	}
}
$message .= $mail;
$message .= '<br>';

$errors = '';
if ($ping_results == 1) {
	print "\nSending Message...";

	$global_alert_address = read_config_option('settings_test_email');

	$errors = send_test($global_alert_address, '', 'Cacti Test Message', $message, '', '', true);
	if ($errors == '') {
		$errors = 'Success!';
	}
} else {
	$errors = 'Message Not Sent due to ping failure.';
}
print "\nResult: $errors\n";

function send_test($to, $from, $subject, $body, $attachments = '', $headers = '', $html = false) {
	global $config;

	$cc = '';
	$bcc = '';
	$replyto = '';
	$body_text = '';

	return mailer($from, $to, '', '', '', $subject, $body, '', $attachments, $headers, $html, 3);
}
