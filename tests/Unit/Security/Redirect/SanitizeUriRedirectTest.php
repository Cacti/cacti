<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 3) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 4) . '/include/global.php';

// =====================================================================
// sanitize_uri off-site redirect hardening
//
// A leading "//host" or "/\host" is a network-path reference the browser
// resolves off-site. Browsers also strip leading C0 controls and whitespace
// (WHATWG: tab, LF, FF, CR, space) before resolving, so a prefix like
// "\x0C//evil.com" reaches the browser as "//evil.com". sanitize_uri must
// neutralise both forms because its output flows to header('Location: ...').
// =====================================================================

test('sanitize_uri collapses a bare protocol-relative path', function () {
	$out = sanitize_uri('//evil.com');

	expect(substr($out, 0, 2))->not->toBe('//')
		->and(substr($out, 0, 2))->not->toBe('/\\');
});

test('sanitize_uri collapses a backslash network-path reference', function () {
	$out = sanitize_uri('/\\evil.com');

	expect(substr($out, 0, 2))->not->toBe('//')
		->and(substr($out, 0, 2))->not->toBe('/\\');
});

test('sanitize_uri collapses repeated leading slashes', function () {
	expect(substr(sanitize_uri('///x'), 0, 2))->not->toBe('//');
});

test('sanitize_uri strips a leading control char before collapsing slashes', function (string $uri) {
	$out = sanitize_uri($uri);

	expect(substr($out, 0, 2))->not->toBe('//')
		->and(substr($out, 0, 2))->not->toBe('/\\');
})->with([
	'form feed' => ["\x0C//evil.com"],
	'tab'       => ["\x09//evil.com"],
	'soh'       => ["\x01//evil.com"],
	'null'      => ["\x00//evil.com"],
	'cr'        => ["\x0D//evil.com"],
	'space'     => [' //evil.com'],
]);

test('sanitize_uri neutralises a urlencoded control-char prefix', function () {
	// is_urlencoded() triggers urldecode() inside sanitize_uri(), so the raw
	// "%0C//evil.com" decodes to "\x0C//evil.com" before the slash collapse.
	$out = sanitize_uri('%0C//evil.com');

	expect(substr($out, 0, 2))->not->toBe('//')
		->and(substr($out, 0, 2))->not->toBe('/\\');
});

test('sanitize_uri leaves a legitimate local path untouched', function () {
	// A single leading slash is a local path, not a network-path reference, so
	// it must survive verbatim. (graph_view.php is avoided here because that
	// branch appends an action= parameter, which is unrelated behaviour.)
	expect(sanitize_uri('/cacti/settings.php'))->toBe('/cacti/settings.php')
		->and(sanitize_uri('graph_view.php?local_graph_id=3'))->toStartWith('graph_view.php');
});

// global_session.php prints the result inside a single-quoted inline script
// string, where a raw line break ends the script and a backslash escapes the
// closing quote.
test('sanitize_uri drops backslash, NUL, CR and LF', function (string $uri) {
	$out = sanitize_uri($uri);

	expect($out)->toBe('/cacti/index.php?a=b')
		->and(strpbrk($out, "\\\0\r\n"))->toBeFalse();
})->with([
	'backslash'         => ['/cacti/index.php?a=\\b'],
	'nul'               => ["/cacti/index.php?a=\0b"],
	'cr'                => ["/cacti/index.php?a=\rb"],
	'lf'                => ["/cacti/index.php?a=\nb"],
	'encoded backslash' => ['/cacti/index.php?a=%5Cb'],
	'encoded nul'       => ['/cacti/index.php?a=%00b'],
	'encoded cr'        => ['/cacti/index.php?a=%0db'],
	'encoded lf'        => ['/cacti/index.php?a=%0ab'],
]);
