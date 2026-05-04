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
$htmlUtilitySource = file_get_contents(__DIR__ . '/../../lib/html_utility.php');

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

test('validate_redirect_url normalizes backslashes before validation', function () use ($htmlUtilitySource) {
	$start = strpos($htmlUtilitySource, 'function validate_redirect_url(');
	expect($start)->not->toBeFalse();

	$body = substr($htmlUtilitySource, $start, 1800);
	expect($body)->toContain("\$url = str_replace('\\\\', '/', \$url);");
});

test('validate_redirect_url rejects userinfo and non-http schemes', function () use ($htmlUtilitySource) {
	$start = strpos($htmlUtilitySource, 'function validate_redirect_url(');
	$body = substr($htmlUtilitySource, $start, 2200);

	expect($body)->toContain('if ($ref_user !== null || $ref_pass !== null)');
	expect($body)->toContain("if (\$ref_scheme !== null && !in_array(\$ref_scheme, array('http', 'https'), true))");
});

test('cacti_normalize_windows_path is defined and exported', function () use ($functionsSource) {
	expect($functionsSource)->toContain('function cacti_normalize_windows_path(');
});

test('cacti_path_is_within delegates to normalizer on Windows', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function cacti_path_is_within(');
	expect($start)->not->toBeFalse();

	$body = substr($functionsSource, $start, 600);
	expect($body)->toContain("DIRECTORY_SEPARATOR === '\\\\'");
	expect($body)->toContain('cacti_normalize_windows_path($resolved)');
	expect($body)->toContain('cacti_normalize_windows_path($base_resolved)');
});

test('normalizer strips long-path prefixes and handles UNC shares', function () use ($functionsSource) {
	$start = strpos($functionsSource, 'function cacti_normalize_windows_path(');
	$body  = substr($functionsSource, $start, 900);

	// \\?\UNC\ is collapsed to \\ before the slash conversion runs
	expect($body)->toContain("strpos(\$lower, '\\\\\\\\?\\\\unc\\\\') === 0");
	expect($body)->toContain("'\\\\\\\\' . substr(\$lower, 8)");

	// bare \\?\ (long-path on a drive letter) is stripped entirely
	expect($body)->toContain("strpos(\$lower, '\\\\\\\\?\\\\') === 0");
	expect($body)->toContain('substr($lower, 4)');

	// slashes normalised, trailing slash trimmed, case-insensitive
	expect($body)->toContain("str_replace('\\\\', '/', \$lower)");
	expect($body)->toContain("rtrim(\$lower, '/')");
	expect($body)->toContain('strtolower((string) $path)');
});

test('normalizer behaviour covers drive, UNC and collision cases', function () use ($functionsSource) {
	// Extract and eval just the function so we can exercise it without
	// pulling in all of lib/functions.php (which would require a DB).
	if (!function_exists('cacti_normalize_windows_path')) {
		if (preg_match('/function cacti_normalize_windows_path.*?^\}/ms', $functionsSource, $m)) {
			eval($m[0]);
		}
	}

	expect(function_exists('cacti_normalize_windows_path'))->toBeTrue();

	$within = function ($cand, $base) {
		$cn = cacti_normalize_windows_path($cand);
		$bn = cacti_normalize_windows_path($base);

		return strpos($cn, $bn . '/') === 0 || $cn === $bn;
	};

	expect($within('C:\\cacti\\scripts\\foo.sh', 'c:\\cacti'))->toBeTrue();
	expect($within('\\\\server\\share\\sub\\file.txt', '\\\\server\\share'))->toBeTrue();
	expect($within('\\\\?\\C:\\cacti\\scripts\\x', 'c:\\cacti'))->toBeTrue();
	expect($within('\\\\?\\UNC\\server\\share\\sub', '\\\\server\\share'))->toBeTrue();
	expect($within('C:\\cacti', 'C:\\cacti'))->toBeTrue();
	expect($within('C:\\cacti\\', 'C:\\cacti'))->toBeTrue();

	expect($within('C:\\OTHER\\x', 'C:\\cacti'))->toBeFalse();
	expect($within('C:\\cactix\\x', 'C:\\cacti'))->toBeFalse();
	expect($within('\\\\server\\shareX\\x', '\\\\server\\share'))->toBeFalse();
});
