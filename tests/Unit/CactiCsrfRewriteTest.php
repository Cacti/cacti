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

require_once dirname(__DIR__, 2) . '/lib/csrf.php';

use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Csrf\TokenStorage\NativeSessionTokenStorage;

/*
 * The rewriter is the contract third-party plugins depend on: they never opt
 * in, they just get a hidden field and a patched XHR.  These assertions pin
 * the field name, the JS globals and the script tag, because changing any of
 * them silently breaks every plugin that posts.
 */
function csrf_test_rewrite_guard() : CactiCsrfGuard {
	$_SESSION = [];

	$storage = new NativeSessionTokenStorage();
	$manager = new CsrfTokenManager(new UriSafeTokenGenerator(), $storage);
	$guard   = new CactiCsrfGuard($manager, true);

	$guard->setScriptUrl('/cacti/include/js/csrf.js');

	return $guard;
}

test('a POST form gets the hidden token field', function () {
	$guard = csrf_test_rewrite_guard();
	$out   = $guard->rewriteBuffer("<html><head></head><body><form method='post'></form></body></html>");

	expect($out)->toContain("name='__csrf_magic'");
	expect($out)->toMatch('#<form[^>]*>\s*<input#');
});

test('a GET form is left alone', function () {
	$guard = csrf_test_rewrite_guard();
	$out   = $guard->rewriteBuffer("<html><head></head><body><form method='get'></form></body></html>");

	/*
	 * The head script is injected unconditionally (it also serves pages
	 * whose only POST comes from AJAX, with no <form> at all), so it
	 * legitimately carries the field name.  What must NOT happen is the
	 * hidden field landing inside the GET form itself.
	 */
	expect($out)->not->toContain("name='__csrf_magic'");
});

test('the JS globals and script tag are injected into head', function () {
	$guard = csrf_test_rewrite_guard();
	$out   = $guard->rewriteBuffer('<html><head></head><body></body></html>');

	expect($out)->toContain('var csrfMagicToken');
	expect($out)->toContain('var csrfMagicName');
	expect($out)->toContain('/cacti/include/js/csrf.js');
	expect($out)->toContain('CsrfMagic.end();');
});

/*
 * The JS globals sit inside a <script> string literal, a context
 * htmlspecialchars() does not protect.  json_encode() is what makes the
 * token correct by construction there.  token() re-mints a new random
 * encoding on every call (BREACH mitigation), so the embedded value is
 * pulled back out of the rendered output rather than compared against a
 * second, necessarily different call to token().
 */
test('the JS globals are valid JavaScript string literals carrying the right values', function () {
	$guard = csrf_test_rewrite_guard();
	$out   = $guard->rewriteBuffer('<html><head></head><body></body></html>');

	preg_match('#var csrfMagicToken = "([^"]+)";#', $out, $matches);

	expect($matches)->toHaveCount(2);
	expect($guard->validate($matches[1]))->toBeTrue();
	expect($out)->toContain('var csrfMagicName = "__csrf_magic";');
});

test('a non-HTML buffer is returned untouched', function () {
	$guard  = csrf_test_rewrite_guard();
	$buffer = '{"error":"csrf_timeout"}';

	expect($guard->rewriteBuffer($buffer))->toBe($buffer);
});

test('the frame breaker is not injected', function () {
	$guard = csrf_test_rewrite_guard();
	$out   = $guard->rewriteBuffer('<html><head></head><body></body></html>');

	expect($out)->not->toContain('top.location.href');
});

test('a nonce is emitted on injected scripts when one is set', function () {
	$guard = csrf_test_rewrite_guard();
	$guard->setNonce('abc123');

	$out = $guard->rewriteBuffer('<html><head></head><body></body></html>');

	expect($out)->toContain('nonce="abc123"');
});

test('no nonce attribute appears when none is set', function () {
	$guard = csrf_test_rewrite_guard();
	$out   = $guard->rewriteBuffer('<html><head></head><body></body></html>');

	expect($out)->not->toContain('nonce=');
});

test('a disabled guard does not rewrite', function () {
	$guard  = new CactiCsrfGuard(null, false);
	$buffer = "<html><head></head><body><form method='post'></form></body></html>";

	expect($guard->rewriteBuffer($buffer))->toBe($buffer);
});
