<?php
// Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later.

// Use the actual enrollment expression and native QR encoder; no QR service is contacted.
test('2FA enrollment accepts account labels with ports and IPv6', function ($username, $host) {
	$source = file_get_contents(dirname(__DIR__, 4) . '/lib/auth.php');
	preg_match('/\$result\[\'link\'\]\s*=\s*(.*?);/s', $source, $expression);
	$current_user         = ['username' => $username, 'tfa_secret' => 'JBSWY3DPEHPK3PXP'];
	$previous             = $_SERVER['HTTP_HOST'] ?? null;
	$_SERVER['HTTP_HOST'] = $host;

	try {
		$link = eval('return ' . $expression[1] . ';');
	} finally {
		if ($previous === null) {
			unset($_SERVER['HTTP_HOST']);
		} else {
			$_SERVER['HTTP_HOST'] = $previous;
		}
	}
	parse_str(parse_url($link, PHP_URL_QUERY), $qr);
	$uri = $qr['data'];
	expect($uri)->toStartWith('otpauth://totp/Cacti:')
		->toContain('secret=JBSWY3DPEHPK3PXP')
		->toContain(str_replace(':', '_', $username . '@' . $host));
})->with([
	['alice', 'cacti.example'],
	['alice', 'cacti.example:8443'],
	['alice', '[2001:db8::1]'],
	['alice', '[2001:db8::1]:8443'],
	['realm:alice', 'cacti.example'],
]);
