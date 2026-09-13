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
 * Built on first use so the CLI never touches Symfony.  An explicit installer
 * request may also run without it while Composer populates include/vendor;
 * every other web request fails closed if the dependency cannot be loaded.
 *
 * @return CactiCsrfGuard The shared guard instance.
 */
function csrf_guard() : CactiCsrfGuard {
	static $guard = null;

	if ($guard !== null) {
		return $guard;
	}

	if (!CACTI_WEB) {
		$guard = new CactiCsrfGuard(null, false);

		return $guard;
	}

	if (!class_exists('Symfony\Component\Security\Csrf\CsrfTokenManager')) {
		/* The installer is the only web context allowed to run before Composer
		   has populated include/vendor.  A normal request reaching this branch
		   has a missing, corrupt, or partially synchronized dependency tree and
		   must not continue without CSRF validation. */
		if (!defined('IN_CACTI_INSTALL')) {
			$message = 'FATAL: CSRF protection is unavailable because symfony/security-csrf could not be loaded. Restore Cacti Composer dependencies and reload this page.';

			cacti_log($message, false, 'CSRF');
			http_response_code(500);
			print $message;

			exit(1);
		}

		cacti_log('WARNING: symfony/security-csrf is not yet available during installation, so CSRF validation is disabled for this request', false, 'CSRF');

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
	$secret_file = csrf_secret_file_path(defined('CACTI_CSRF_SECRET') ? CACTI_CSRF_SECRET : '');
	$secret      = ($secret_file != '' && is_readable($secret_file) ? (string) file_get_contents($secret_file) : '');

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
 * Validate a POST request.
 *
 * csrf-magic returned a bool, and plugins such as the bundled audit plugin
 * branch on it directly (`!csrf_check(false)`).  A void return silently
 * breaks that caller: `!null` is always true, so the check would appear to
 * fail on every request.  $fatal has the same meaning it always did: true
 * routes a failed check to csrf_error_callback(), which exits; false hands
 * the result back to the caller instead.
 *
 * @param bool $fatal Exit via csrf_error_callback() on failure when true;
 *                    otherwise return false and let the caller decide.
 *
 * @return bool True for a non-POST request or a validated POST; false for a
 *              POST that failed validation and $fatal is false.
 */
function csrf_check(bool $fatal = true) : bool {
	if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
		return true;
	}

	$submitted = filter_input(INPUT_POST, CactiCsrfGuard::INPUT_NAME, FILTER_UNSAFE_RAW);
	$submitted = (is_string($submitted) ? $submitted : '');

	if (csrf_guard()->validate($submitted)) {
		return true;
	}

	if ($fatal) {
		csrf_error_callback();
	}

	return false;
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
 * Resolve the legacy secret configuration to a file.
 *
 * Older installer code accepted a directory and appended csrf-secret.php,
 * even though config.php.dist documents a full file path.  Preserve that
 * behavior during the legacy-token grace window.
 *
 * @param string $path A configured file or directory path.
 *
 * @return string The resolved secret file path.
 */
function csrf_secret_file_path(string $path) : string {
	if ($path !== '' && is_dir($path)) {
		return rtrim($path, '/\\') . '/csrf-secret.php';
	}

	return $path;
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

	$file = csrf_secret_file_path(defined('CACTI_CSRF_SECRET') ? CACTI_CSRF_SECRET : '');

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
	// Invalidate the failed session id instead of leaving it usable until GC.
	session_regenerate_id(true);

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
