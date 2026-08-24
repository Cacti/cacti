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

function cacti_csrf_generate_secret($length = 32) {
	if (!is_int($length) || $length < 32) {
		$length = 32;
	}

	return bin2hex(random_bytes($length));
}

function cacti_csrf_secret_file_contents() {
	return '<?php' . PHP_EOL .
		'/* Cacti CSRF secret: ' . cacti_csrf_generate_secret() . ' */' . PHP_EOL;
}

function cacti_csrf_read_or_create_secret($paths) {
	foreach ($paths as $path) {
		if (is_file($path)) {
			$secret = @file_get_contents($path);
			if (is_string($secret) && $secret !== '') {
				return $secret;
			}
		}
	}

	foreach ($paths as $path) {
		$old_umask = umask(0027);
		$handle = @fopen($path, 'x');
		umask($old_umask);

		if ($handle !== false) {
			$secret = cacti_csrf_secret_file_contents();
			$written = fwrite($handle, $secret);
			fclose($handle);

			if ($written === strlen($secret)) {
				return $secret;
			}

			@unlink($path);
		} elseif (is_file($path)) {
			/* Another request may have won the exclusive-create race. */
			$secret = @file_get_contents($path);
			if (is_string($secret) && $secret !== '') {
				return $secret;
			}
		}
	}

	/* Read-only web roots can still retain a strong per-session secret. */
	csrf_start();
	if (session_id()) {
		if (empty($_SESSION['cacti_csrf_secret'])) {
			$_SESSION['cacti_csrf_secret'] = cacti_csrf_generate_secret();
		}

		return $_SESSION['cacti_csrf_secret'];
	}

	return '';
}

/* cross site request forgery library */
function csrf_startup() {
	global $config;

	if ($config['is_web']) {
		$secret_paths = array();
		if (!empty($config['path_csrf_secret'])) {
			$secret_paths[] = $config['path_csrf_secret'];
		}
		$secret_paths[] = __DIR__ . '/vendor/csrf/csrf-secret.php';

		$secret = cacti_csrf_read_or_create_secret(array_unique($secret_paths));
		if ($secret !== '') {
			csrf_conf('secret', $secret);
		}

		/* If you need to debug CSRF, uncomment the following line */
		//csrf_conf('log_file', dirname(read_config_option('path_cactilog')) . '/csrf.log');
		if (!empty($config['path_csrf_secret'])) {
			csrf_conf('path_secret', $config['path_csrf_secret']);
		}

		csrf_conf('rewrite-js', $config['url_path'] . 'include/vendor/csrf/csrf-magic.js');
		csrf_conf('callback', 'csrf_error_callback');
		csrf_conf('expires', 7200);
	} else {
		csrf_conf('disable',true);
	}
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

include_once($config['include_path'] . '/vendor/csrf/csrf-magic.php');
