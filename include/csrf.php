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

require_once($config['include_path'] .'/vendor/csrf/csrf-conf.php');

/* cross site request forgery library */
function csrf_startup() {
	global $config;

	if ($config['is_web']) {
		/* If you need to debug CSRF, uncomment the following line */
		//csrf_conf('log_file', dirname(read_config_option('path_cactilog')) . '/csrf.log');
		$secret = '';

		$external_secret = !empty($config['path_csrf_secret']);
		if ($external_secret) {
			$secret = cacti_csrf_read_external_secret($config['path_csrf_secret']);
		} else {
			$secret = read_config_option('csrf_secret', true);
		}

		/* Bootstrap clean installs without writing beneath the document root. */
		if (!cacti_csrf_secret_is_valid($secret) && (!$external_secret || cacti_csrf_install_pending())) {
			if (empty($_SESSION['cacti_bootstrap_csrf_secret'])) {
				$_SESSION['cacti_bootstrap_csrf_secret'] = csrf_generate_secret();
			}

			$secret = $_SESSION['cacti_bootstrap_csrf_secret'];
		}

		if (!cacti_csrf_secret_is_valid($secret)) {
			http_response_code(500);
			die('ERROR: The configured external Cacti CSRF secret is unavailable or invalid.');
		}

		csrf_conf('secret', $secret);
		csrf_conf('hash', 'sha256');
		csrf_conf('url_path', $config['url_path']);
		csrf_conf('rewrite-js', $config['url_path'] . 'include/vendor/csrf/csrf-magic.js');
		csrf_conf('callback', 'csrf_error_callback');
		csrf_conf('expires', 7200);
	} else {
		csrf_conf('disable',true);
	}
}

function cacti_csrf_install_pending() {
	global $config;

	return defined('IN_CACTI_INSTALL') ||
		(defined('CACTI_VERSION') && isset($config['cacti_db_version']) && $config['cacti_db_version'] !== CACTI_VERSION);
}

/**
 * Read a packager-managed CSRF secret from outside the Cacti document root.
 */
function cacti_csrf_read_external_secret($path) {
	$path = cacti_csrf_external_secret_path($path);
	if (!cacti_csrf_external_path_is_safe($path) || !is_file($path)) {
		return '';
	}

	$secret = @file_get_contents($path, false, null, 0, 4097);
	if (!is_string($secret)) {
		return '';
	}

	$secret = cacti_csrf_parse_secret_contents($secret);

	return cacti_csrf_secret_is_valid($secret) ? $secret : '';
}

function cacti_csrf_secret_is_valid($secret) {
	return is_string($secret) && strlen($secret) >= 32 && strlen($secret) <= 4096;
}

/**
 * Decode the wrapper written by older refresh_csrf.php versions while also
 * accepting packager-managed raw secrets.
 */
function cacti_csrf_parse_secret_contents($secret) {
	if (!is_string($secret)) {
		return '';
	}

	$secret = trim($secret);
	if (preg_match('/^<\?php\s+\$secret\s*=\s*[\'\"]([a-f0-9]{32,})[\'\"]\s*;?\s*$/i', $secret, $matches)) {
		return $matches[1];
	}

	/* Never silently use an unparsable PHP wrapper as the secret value. */
	if (strpos($secret, '<?') === 0) {
		return '';
	}

	return $secret;
}

/**
 * Preserve the installer's historical support for a configured directory.
 */
function cacti_csrf_external_secret_path($path) {
	if (!is_string($path) || $path === '') {
		return $path;
	}

	if (is_dir($path)) {
		$directory = realpath($path);
		$filename = 'csrf-secret.php';
	} else {
		$directory = realpath(dirname($path));
		$filename = basename($path);
	}

	return $directory === false ? $path : $directory . DIRECTORY_SEPARATOR . $filename;
}

/**
 * Ensure an external secret resolves to a pre-existing directory outside the
 * Cacti document root.
 */
function cacti_csrf_external_path_is_safe($path) {
	global $config;
	$path = cacti_csrf_external_secret_path($path);

	if (!is_string($path) || $path === '') {
		return false;
	}

	$base_path = realpath($config['base_path']);
	$secret_dir = realpath(dirname($path));
	if ($base_path === false || $secret_dir === false) {
		return false;
	}

	$base_prefix = rtrim(str_replace('\\', '/', $base_path), '/') . '/';
	$secret_prefix = rtrim(str_replace('\\', '/', $secret_dir), '/') . '/';
	if (stripos($secret_prefix, $base_prefix) === 0) {
		return false;
	}

	if (file_exists($path)) {
		$secret_path = realpath($path);
		if ($secret_path === false) {
			return false;
		}

		$secret_path = str_replace('\\', '/', $secret_path);
		if (stripos($secret_path, $base_prefix) === 0) {
			return false;
		}
	}

	return true;
}

function csrf_error_callback() {
	//Resolve session fixation for PHP 5.4
	session_regenerate_id();
	raise_message('csrf_timeout');
	ob_end_clean();
	header('Location: ' . validate_redirect_url($_SERVER['REQUEST_URI']));
	csrf_log(__FUNCTION__, 'Timeout, redirecting to ' . validate_redirect_url($_SERVER['REQUEST_URI']));
	exit;
}

/**
 * Reject state changes transported through a URL or an unsupported method.
 * csrf-magic validates the token before page dispatch for every POST request.
 */
function csrf_require_post() {
	if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
		header('Allow: POST');
		http_response_code(405);
		exit;
	}
}

include_once($config['include_path'] . '/vendor/csrf/csrf-magic.php');
