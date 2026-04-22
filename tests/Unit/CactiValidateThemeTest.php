<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Behavior tests for cacti_validate_theme().
 *
 * Root-cause mitigation for GHSA-rm7p / GHSA-cx5r (LFI via graph_theme).
 * The helper must allowlist-validate the theme name against the actual
 * contents of include/themes/ and return a safe default for anything else.
 *
 * Tests use source-scan + isolated reimplementation to avoid the full
 * Cacti bootstrap. The isolated logic must match the production helper.
 */

beforeAll(function () {
	require_once dirname(__DIR__, 2) . '/include/global_constants.php';
	require_once dirname(__DIR__, 2) . '/lib/functions.php';
});

/**
 * Inline mirror of the production helper, parameterized with the allowlist.
 * Used to exercise the algorithm without depending on the filesystem.
 */
function resolve_theme_under(array $allowlist, $requested, $default) {
	$requested = basename((string) $requested);

	return isset($allowlist[$requested]) ? $requested : $default;
}

describe('cacti_validate_theme source contract', function () {
	$src = file_get_contents(__DIR__ . '/../../lib/functions.php');

	it('uses static cache so scandir runs once per request', function () use ($src) {
		expect($src)->toContain('static $valid_themes');
	});

	it('requires both is_dir and is_file(rrdtheme.php) for allowlist entry', function () use ($src) {
		expect($src)->toContain('is_dir($full)');
		expect($src)->toContain("is_file(\$full . '/rrdtheme.php')");
	});

	it('applies basename() to requested value before allowlist check', function () use ($src) {
		expect($src)->toContain('basename((string) $requested)');
	});

	it('falls back to a configured or modern default', function () use ($src) {
		expect($src)->toContain("read_config_option('selected_theme')");
		expect($src)->toContain("\$default = 'modern'");
	});
});

describe('theme allowlist algorithm', function () {
	$allow = array('modern' => true, 'classic' => true, 'midwinter' => true);

	it('accepts a valid theme', function () use ($allow) {
		expect(resolve_theme_under($allow, 'modern', 'modern'))->toBe('modern');
		expect(resolve_theme_under($allow, 'midwinter', 'modern'))->toBe('midwinter');
	});

	it('returns default for an invalid theme', function () use ($allow) {
		expect(resolve_theme_under($allow, 'evil', 'modern'))->toBe('modern');
	});

	it('strips path traversal via basename', function () use ($allow) {
		// basename('../../etc/passwd') => 'passwd'; not in allowlist; returns default
		expect(resolve_theme_under($allow, '../../etc/passwd', 'modern'))->toBe('modern');
		expect(resolve_theme_under($allow, '/etc/passwd', 'modern'))->toBe('modern');
		expect(resolve_theme_under($allow, 'modern/../../etc/passwd', 'modern'))->toBe('modern');
	});

	it('rejects empty, dot, and double-dot', function () use ($allow) {
		expect(resolve_theme_under($allow, '', 'modern'))->toBe('modern');
		expect(resolve_theme_under($allow, '.', 'modern'))->toBe('modern');
		expect(resolve_theme_under($allow, '..', 'modern'))->toBe('modern');
	});

	it('rejects theme names satisfying basename but not in allowlist', function () use ($allow) {
		// The exploit category the plain-basename fix missed:
		// attacker-placed directory with rrdtheme.php. Our allowlist is
		// built from the real include/themes/ so this is blocked unless
		// the attacker can write INTO include/themes/ (already game over).
		expect(resolve_theme_under($allow, 'attacker_uploaded_theme', 'modern'))->toBe('modern');
	});

	it('is case-sensitive (filesystem names are case-sensitive on POSIX)', function () use ($allow) {
		expect(resolve_theme_under($allow, 'MODERN', 'modern'))->toBe('modern');
		expect(resolve_theme_under($allow, 'Modern', 'modern'))->toBe('modern');
	});

	it('coerces non-string input safely', function () use ($allow) {
		expect(resolve_theme_under($allow, null, 'modern'))->toBe('modern');
		expect(resolve_theme_under($allow, 0, 'modern'))->toBe('modern');
		expect(resolve_theme_under($allow, false, 'modern'))->toBe('modern');
	});
});

describe('theme ingress enforcement', function () {
	$graphImageSource = file_get_contents(__DIR__ . '/../../graph_image.php');
	$graphJsonSource  = file_get_contents(__DIR__ . '/../../graph_json.php');
	$remoteSource     = file_get_contents(__DIR__ . '/../../remote_agent.php');

	it('uses cacti_validate_theme in graph_image request handling', function () use ($graphImageSource) {
		expect($graphImageSource)->toContain("cacti_validate_theme(get_request_var('graph_theme'))");
	});

	it('uses cacti_validate_theme in graph_json request handling', function () use ($graphJsonSource) {
		expect($graphJsonSource)->toContain("cacti_validate_theme(get_request_var('graph_theme'))");
	});

	it('uses cacti_validate_theme in remote_agent graph handler', function () use ($remoteSource) {
		expect($remoteSource)->toContain("cacti_validate_theme(get_request_var('graph_theme'))");
	});
});
