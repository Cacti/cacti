<?php

$basePath = dirname(__DIR__, 4);
$GLOBALS['config'] = array(
	'base_path'    => $basePath,
	'include_path' => $basePath . '/include',
	'is_web'       => false,
);
$config = $GLOBALS['config'];

require_once($basePath . '/include/csrf.php');

beforeEach(function () {
	$GLOBALS['csrf']['secret'] = str_repeat('a', 64);
	$GLOBALS['csrf']['key'] = false;
	$GLOBALS['csrf']['user'] = false;
	$GLOBALS['csrf']['cookie'] = false;
	$GLOBALS['csrf']['session'] = false;
	$GLOBALS['csrf']['allow-ip'] = false;
	$GLOBALS['csrf']['expires'] = 7200;
	$GLOBALS['csrf']['hash'] = 'sha256';
	$GLOBALS['csrf']['log_file'] = '';
});

test('generated secrets use 32 cryptographically random bytes', function () {
	$first = csrf_generate_secret();
	$second = csrf_generate_secret();

	expect($first)->toMatch('/^[a-f0-9]{64}$/')
		->and($second)->toMatch('/^[a-f0-9]{64}$/')
		->and($second)->not->toBe($first);
});

test('tokens validate only for the configured secret key and lifetime', function () {
	$GLOBALS['csrf']['key'] = 'test-key';
	$token = 'key:' . csrf_hash('test-key');

	expect(csrf_check_tokens($token))->toBeTrue();

	$GLOBALS['csrf']['key'] = 'different-key';
	expect(csrf_check_tokens($token))->toBeFalse();

	$expired = 'key:' . csrf_hash('different-key', time() - 7201);
	expect(csrf_check_tokens($expired))->toBeFalse();
});

test('malformed nested and oversized token collections fail closed', function () {
	expect(csrf_check_tokens(array(array('nested'))))->toBeFalse()
		->and(csrf_check_tokens(array_fill(0, 9, 'invalid')))->toBeFalse()
		->and(csrf_check_tokens(str_repeat('a', 257)))->toBeFalse()
		->and(csrf_check_tokens('key:not-a-hash,not-a-time'))->toBeFalse();
});

test('tokens with timestamps too far in the future fail closed', function () {
	$GLOBALS['csrf']['key'] = 'test-key';
	$future = 'key:' . csrf_hash('test-key', time() + 301);

	expect(csrf_check_tokens($future))->toBeFalse();
});

test('atomic external secret writes replace the complete value', function () {
	$directory = sys_get_temp_dir() . '/cacti-csrf-' . bin2hex(random_bytes(8));
	mkdir($directory, 0700);
	$path = $directory . '/csrf-secret';
	$first = csrf_generate_secret();
	$second = csrf_generate_secret();

	try {
		expect(csrf_write_secret_atomic($path, $first))->toBeTrue()
			->and(trim(file_get_contents($path)))->toBe($first)
			->and(fileperms($path) & 0777)->toBe(0640)
			->and(csrf_write_secret_atomic($path, $second))->toBeTrue()
			->and(trim(file_get_contents($path)))->toBe($second);
	} finally {
		@unlink($path);
		@rmdir($directory);
	}
});

test('external secret paths must resolve outside the document root', function () use ($basePath) {
	global $config;

	$config['base_path'] = $basePath;
	expect(cacti_csrf_external_path_is_safe($basePath . '/include/config.php'))->toBeFalse()
		->and(cacti_csrf_external_path_is_safe(sys_get_temp_dir() . '/cacti-csrf-secret'))->toBeTrue();
});

test('configured external directories retain their historical secret filename', function () {
	expect(cacti_csrf_external_secret_path(sys_get_temp_dir()))
		->toBe(realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'csrf-secret.php');
});

test('secret handoffs reject undersized and oversized values', function () {
	expect(cacti_csrf_secret_is_valid(str_repeat('a', 31)))->toBeFalse()
		->and(cacti_csrf_secret_is_valid(str_repeat('a', 32)))->toBeTrue()
		->and(cacti_csrf_secret_is_valid(str_repeat('a', 4096)))->toBeTrue()
		->and(cacti_csrf_secret_is_valid(str_repeat('a', 4097)))->toBeFalse()
		->and(csrf_write_secret_atomic(sys_get_temp_dir() . '/unused', str_repeat('a', 4097)))->toBeFalse();
});

test('legacy PHP secret wrappers are decoded without executing them', function () {
	$secret = str_repeat('b', 64);
	expect(cacti_csrf_parse_secret_contents('<?php $secret = "' . $secret . '";'))
		->toBe($secret)
		->and(cacti_csrf_parse_secret_contents($secret . PHP_EOL))->toBe($secret);
});

test('unparsable PHP secret wrappers are rejected as data', function () {
	expect(cacti_csrf_parse_secret_contents('<?php $other = 1; // ' . str_repeat('x', 64)))->toBe('')
		->and(cacti_csrf_parse_secret_contents('<?php $secret = "short";'))->toBe('');
});

test('server-side form rewriting never hands a token to an absolute URL', function () {
	expect(csrf_form_action_is_local('<form method="post">'))->toBeTrue()
		->and(csrf_form_action_is_local('<form action="settings.php" method="post">'))->toBeTrue()
		->and(csrf_form_action_is_local('<form method="post" action="/cacti/settings.php">'))->toBeTrue()
		->and(csrf_form_action_is_local('<form method="post" action="https://example.net/collect">'))->toBeFalse()
		->and(csrf_form_action_is_local('<form action="//example.net/collect" method="post">'))->toBeFalse()
		->and(csrf_form_action_is_local('<form action=https://example.net/collect method="post">'))->toBeFalse()
		->and(csrf_form_action_is_local('<form action="\\\\example.net/collect" method="post">'))->toBeFalse()
		->and(csrf_form_action_is_local("<form action=\"ht\ttps://example.net/collect\" method=\"post\">"))->toBeFalse()
		->and(csrf_form_action_is_local('<form method="post" action="javascript:void(0)">'))->toBeFalse();
});
