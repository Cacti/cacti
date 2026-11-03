<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';

// Execute the exact checked-in guard expressions without dispatching the web
// pages. This checks decisions; hash_equals' constant-time implementation is a
// PHP contract and is not inferred from noisy statistical timing measurements.
$root  = dirname(__DIR__, 3);
$count = 0;

foreach (['auth_2fa.php', 'auth_resetpassword.php'] as $file) {
	$source = file_get_contents($root . '/' . $file);
	preg_match_all('/^\s*if \(([^\n]*hash_equals[^\n]*)\) \{/m', $source, $matches, PREG_OFFSET_CAPTURE);

	if (count($matches[1]) !== ($file === 'auth_2fa.php' ? 1 : 2)) {
		throw new RuntimeException('Expected constant-time guards are missing');
	}

	foreach ($matches[1] as [$expression, $offset]) {
		$line      = substr_count(substr($source, 0, $offset), "\n") + 1;
		$temporary = tempnam(sys_get_temp_dir(), 'cacti-auth-guard-');
		file_put_contents($temporary, '<?php return static function (array $variables) { extract($variables, EXTR_SKIP);' . str_repeat("\n", $line - 1) . 'return ' . $expression . '; };');
		$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($root . '/' . $file);

		try {
			$guard = require $temporary;
		} finally {
			unlink($temporary);
		}

		if ($file === 'auth_2fa.php') {
			$user                       = ['username' => 'fixture', 'tfa_secret' => bin2hex(random_bytes(16))];
			$tfaMins                    = 60;
			$tfaCookieTime              = (string) time();
			$_SERVER['HTTP_USER_AGENT'] = 'Fixture Browser';
			$expected                   = hash_hmac('sha1', $user['username'] . ':' . $tfaMins . ':' . $tfaCookieTime . ':' . $_SERVER['HTTP_USER_AGENT'], $user['tfa_secret']);

			foreach ([$expected, '', substr($expected, 0, -1), $expected . '0', '0e1234567890123456789012345678901234567890', str_repeat('a', 40)] as $tfaCookeHash) {
				if ($guard(compact('user', 'tfaMins', 'tfaCookieTime', 'tfaCookeHash')) !== ($tfaCookeHash === $expected)) {
					throw new RuntimeException('Cookie hash decision mismatch');
				}
				$count++;
			}
			$tfaCookeHash  = $expected;
			$tfaCookieTime = '';

			if ($guard(compact('user', 'tfaMins', 'tfaCookieTime', 'tfaCookeHash')) !== false) {
				throw new RuntimeException('Empty cookie timestamp accepted');
			}
			$count++;
		} else {
			foreach ([[false, 'valid', true], [['hash' => 'valid'], 'valid', false], [['hash' => 'valid'], 'vali', true], [['hash' => 'valid'], 'valid0', true], [['hash' => '0e123'], '0e456', true], [['hash' => 123], '123', false]] as [$hash, $user_hash, $expected]) {
				if ($guard(compact('hash', 'user_hash')) !== $expected) {
					throw new RuntimeException('Reset hash decision mismatch');
				}
				$count++;
			}
		}
	}
}
print json_encode(['passed' => $count], JSON_THROW_ON_ERROR) . "\n";
