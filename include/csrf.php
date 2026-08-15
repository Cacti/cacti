<?php
/*
  +-------------------------------------------------------------------------+
  | Copyright (C) 2004-2026 The Cacti Group                                 |
  |                                                                         |
  | This program is free software; you can redistribute it and/or           |
  | modify it under the terms of the GNU General Public License             |
  | as published by the Free Software Foundation; either version 2          |
  | of the License, or (at your option) any later version.                  |
  |                                                                         |
  | This program is distributed in the hope that it will be useful,         |
  | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
  | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
  | GNU General Public License for more details.                            |
  +-------------------------------------------------------------------------+
  | Cacti: The Complete RRDTool-based Graphing Solution                     |
  +-------------------------------------------------------------------------+
  | This code is designed, written, and maintained by the Cacti Group. See  |
  | about.php and/or the AUTHORS file for specific developer information.   |
  +-------------------------------------------------------------------------+
  | http://www.cacti.net/                                                   |
  +-------------------------------------------------------------------------+
*/

require_once(CACTI_PATH_LIBRARY . '/csrf.php');

/**
 * The process-wide CSRF guard.
 *
 * Built on first use so the CLI, and an install running before composer has
 * populated include/vendor, never touch Symfony at all.
 *
 * @return CactiCsrfGuard The shared guard instance.
 */
function csrf_guard() : CactiCsrfGuard {
	static $guard = null;

	if ($guard !== null) {
		return $guard;
	}

	if (!CACTI_WEB || !class_exists('Symfony\Component\Security\Csrf\CsrfTokenManager')) {
		/* Degrading is intended: an install running before composer has
		   populated include/vendor must not fatal.  But in a web context that
		   leaves the pre-auth installer serving requests with no CSRF check at
		   all, which csrf-magic could not do, so say so rather than fail open
		   in silence. */
		if (CACTI_WEB) {
			cacti_log('WARNING: symfony/security-csrf is missing, so CSRF validation is disabled for this request', false, 'CSRF');
		}

		$guard = new CactiCsrfGuard(null, false);

		return $guard;
	}

	$storage = new Symfony\Component\Security\Csrf\TokenStorage\NativeSessionTokenStorage();
	$manager = new Symfony\Component\Security\Csrf\CsrfTokenManager(
		new Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator(),
		$storage
	);

	/* Read-only on purpose.  csrf_get_secret() mints and persists a secret when
	   none exists, and the grace window must not create the very file phase 2
	   deletes.  A missing secret simply disables the legacy fallback.

	   Do not trim.  csrf-magic keyed its HMAC on the raw bytes of this file,
	   and cli/refresh_csrf.php writes PHP source with a trailing newline, so
	   stripping whitespace yields a different key and silently rejects every
	   pre-upgrade token on any site that ever rotated its secret. */
	$secret = (defined('CACTI_CSRF_SECRET') && CACTI_CSRF_SECRET != '' && is_readable(CACTI_CSRF_SECRET) ? (string) file_get_contents(CACTI_CSRF_SECRET) : '');

	/* csrf-magic's own csrf_get_secret() tried $path_csrf_secret and then
	   fell back to its own directory regardless of that setting.  A site
	   that pointed $path_csrf_secret elsewhere but never actually created
	   the file there still had a working secret at the old default, so
	   check it too before giving up on the grace window. */
	if ($secret === '') {
		$fallback = CACTI_PATH_INCLUDE . '/vendor/csrf/csrf-secret.php';

		if (is_readable($fallback)) {
			$secret = (string) file_get_contents($fallback);
		}
	}

	$guard = new CactiCsrfGuard($manager, true, $secret, 7200);
	$guard->setScriptUrl(CACTI_PATH_URL . 'include/js/csrf.js');

	return $guard;
}

/**
 * Register the output rewriter and validate the current request.
 *
 * Replaces csrf-magic's self-executing bootstrap, which ran from the bottom of
 * the library file as a side effect of including it.
 */
function csrf_startup() : void {
	$guard = csrf_guard();

	if (!$guard->isEnabled()) {
		return;
	}

	/* include/session.php registers session_write_close() as a shutdown
	   function, and shutdown functions run before output buffers flush.  If
	   the token were minted lazily from inside the output buffer callback
	   below, the first mint on a POST that reaches csrf_check() via the
	   legacy fallback would write $_SESSION after the session already
	   closed, and the token rendered into the page would never validate.
	   Mint it now, while the session is still open. */
	$guard->token();

	ob_start(function ($buffer) use ($guard) {
		return $guard->rewriteBuffer($buffer);
	});

	csrf_check();
}

/**
 * @return string A token for the current session, or an empty string when the
 *                guard is disabled.
 */
function csrf_get_tokens() : string {
	csrf_deprecated(__FUNCTION__);

	return csrf_guard()->token();
}

/**
 * Validate a POST request, invoking the failure callback when it fails.
 *
 * @param bool $fatal Retained for compatibility with csrf-magic's signature.
 */
function csrf_check(bool $fatal = true) : void {
	if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
		return;
	}

	$submitted = (isset($_POST[CactiCsrfGuard::INPUT_NAME]) ? (string) $_POST[CactiCsrfGuard::INPUT_NAME] : '');

	if (csrf_guard()->validate($submitted)) {
		return;
	}

	csrf_error_callback();
}

/**
 * @param mixed $tokens One token, or a ';' separated list as csrf-magic sent.
 *
 * @return bool True when any supplied token validates.
 */
function csrf_check_tokens(mixed $tokens) : bool {
	csrf_deprecated(__FUNCTION__);

	if (is_string($tokens)) {
		$tokens = explode(';', $tokens);
	}

	foreach ((array) $tokens as $token) {
		if (csrf_guard()->validate((string) $token)) {
			return true;
		}
	}

	return false;
}

/**
 * Accept and discard a csrf-magic configuration call.
 *
 * The guard is configured through its constructor.  This exists so plugins
 * that call csrf_conf() do not fatal.
 *
 * @param string $key   The csrf-magic option name.
 * @param mixed  $value The value, ignored.
 */
function csrf_conf(string $key, mixed $value) : void {
	csrf_deprecated(__FUNCTION__);
}

/**
 * Log one deprecation notice per function per request.
 *
 * Silent at default verbosity.  A plugin author opts in by raising the log
 * level; logging unconditionally would flood cacti.log.
 *
 * @param string $function The deprecated function that was called.
 */
function csrf_deprecated(string $function) : void {
	static $seen = [];

	if (isset($seen[$function])) {
		return;
	}

	$seen[$function] = true;

	cacti_log($function . '() is deprecated and will be removed; use csrf_guard() instead', false, 'CSRF', POLLER_VERBOSITY_DEBUG);
}

/**
 * Read the csrf-magic secret, or mint and persist one if none exists.
 *
 * Retained because install/functions.php writes this value during a fresh
 * install and the legacy token fallback verifies against it.  Removed in
 * phase 2 along with the rest of the secret plumbing.
 *
 * @return string The secret, or an empty string when none can be established.
 */
function csrf_get_secret() : string {
	static $secret = null;

	if ($secret !== null) {
		return $secret;
	}

	$file = (defined('CACTI_CSRF_SECRET') ? CACTI_CSRF_SECRET : '');

	if ($file != '' && file_exists($file)) {
		// the raw bytes are the key; see the note in csrf_guard()
		$secret = (string) @file_get_contents($file);

		if ($secret !== '') {
			return $secret;
		}
	}

	$secret = csrf_generate_secret();

	if ($file != '' && csrf_writable($file)) {
		$old_umask = umask(0027);
		$fh        = fopen($file, 'w');

		if ($fh !== false) {
			fwrite($fh, $secret);
			fclose($fh);
		}

		umask($old_umask);
	}

	return $secret;
}

/**
 * Generate a new secret.
 *
 * csrf-magic built this from mt_rand(), which is not a cryptographic source.
 * This uses random_bytes().
 *
 * @return string A 64 character hex secret.
 */
function csrf_generate_secret() : string {
	return hash('sha256', random_bytes(32));
}

/**
 * @param string $path The path to test.
 *
 * @return bool True when $path can be written without leaving a file behind.
 */
function csrf_writable(string $path) : bool {
	if ($path === '') {
		return false;
	}

	if (substr($path, -1) === '/') {
		return csrf_writable($path . uniqid((string) random_int(0, PHP_INT_MAX)) . '.tmp');
	}

	if (file_exists($path)) {
		$fh = @fopen($path, 'a');

		if ($fh === false) {
			return false;
		}

		fclose($fh);

		return true;
	}

	$fh = @fopen($path, 'w');

	if ($fh === false) {
		return false;
	}

	fclose($fh);
	unlink($path);

	return true;
}

/**
 * Record a CSRF diagnostic.
 *
 * csrf-magic wrote these to its own log file.  They go to cacti.log now, at a
 * verbosity that is silent by default.
 *
 * @param string $function The reporting function.
 * @param string $message  What happened.
 */
function csrf_log(string $function, string $message) : void {
	cacti_log($function . '(): ' . $message, false, 'CSRF', POLLER_VERBOSITY_DEBUG);
}

/**
 * Handle a request that failed CSRF validation.
 *
 * The installer runs its steps over JSON, so a plain redirect leaves it stuck
 * on a dead XHR.  That branch answers with a scoped payload carrying a fresh
 * token instead, which install.js retries against once.  See issue #7343.
 */
function csrf_error_callback() : void {
	// Resolve session fixation for PHP 5.4
	session_regenerate_id();

	if (defined('IN_CACTI_INSTALL') &&
		isset($GLOBALS['auth_json']) &&
		$GLOBALS['auth_json'] === true &&
		!empty($GLOBALS['is_request_ajax'])) {
		$response = json_encode(
			[
				'error'          => 'csrf_timeout',
				'title'          => __('Session Expired'),
				'message'        => __('Your installer session expired due to inactivity. Reload the installer and try again.'),
				'csrfMagicToken' => csrf_get_tokens(),
			],
			JSON_INVALID_UTF8_SUBSTITUTE
		);

		if ($response === false) {
			$response = '{"error":"csrf_timeout","title":"Session Expired","message":"Reload the installer and try again."}';
		}

		ob_end_clean();
		http_response_code(403);
		header('Content-Type: application/json');
		header('Cache-Control: no-store');
		header('X-Content-Type-Options: nosniff');
		header('Content-Length: ' . strlen($response));
		print $response;
		csrf_log(__FUNCTION__, 'Timeout, returning JSON response for the installer');

		exit;
	}

	raise_message('csrf_timeout');
	ob_end_clean();
	header('Location: ' . sanitize_uri($_SERVER['REQUEST_URI']));
	csrf_log(__FUNCTION__, 'Timeout, redirecting to ' . sanitize_uri($_SERVER['REQUEST_URI']));

	exit;
}
