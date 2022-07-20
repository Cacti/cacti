<?php

/**
 * @file
 *
 * csrf-magic is a PHP library that makes adding CSRF-protection to your
 * web applications a snap. No need to modify every form or create a database
 * of valid nonces; just include this file at the top of every
 * web-accessible page (or even better, your common include file included
 * in every page), and forget about it! (There are, of course, configuration
 * options for advanced users).
 *
 * This library is PHP4 and PHP5 compatible and is maintained from
 * https://github.com/ezyang/csrf-magic/
 */

/**
 * Rewrites <form> on the fly to add CSRF tokens to them. This can also
 * inject our JavaScript library.
 */
function csrf_ob_handler($buffer, $flags) {
	// Even though the user told us to rewrite, we should do a quick heuristic
	// to check if the page is *actually* HTML. We don't begin rewriting until
	// we hit the first <html tag.
	static $is_html = false;
	if (!$is_html) {
		// not HTML until proven otherwise
		$is_html = (stripos($buffer, '<html') !== false);
	}

	if ($is_html) {
		$tokens = csrf_get_tokens();
		$name = $GLOBALS['csrf']['input-name'];
		$endslash = $GLOBALS['csrf']['xhtml'] ? ' /' : '';
		$input = "<input type='hidden' name='$name' value=\"$tokens\"$endslash>";
		$buffer = preg_replace('#(<form[^>]*method\s*=\s*["\']post["\'][^>]*>)#i', '$1' . $input, $buffer);

		if ($GLOBALS['csrf']['frame-breaker']) {
			$buffer = str_ireplace('</head>', '<script type="text/javascript">if (top != self) {top.location.href = self.location.href;}</script></head>', $buffer);
		}

		$js = $GLOBALS['csrf']['rewrite-js'];

		if (!empty($js)) {
			$buffer = str_ireplace(
				'</head>',
				'<script type="text/javascript">'.
					'var csrfMagicToken = "'.$tokens.'";'.
					'var csrfMagicName = "'.$name.'";</script>'.
				'<script src="'.$js.'" type="text/javascript"></script></head>',
				$buffer
			);

			$script = '<script type="text/javascript">CsrfMagic.end();</script>';
			$buffer = str_ireplace('</body>', $script . '</body>', $buffer, $count);

			if (!$count) {
				$buffer .= $script;
			}
		}
	}

	csrf_log(__FUNCTION__, 'returns: ' . var_export($buffer, true));

	return $buffer;
}

/**
 * Checks if this is a post request, and if it is, checks if the nonce is valid.
 * @param bool $fatal Whether or not to fatally error out if there is a problem.
 * @return True if check passes or is not necessary, false if failure.
 */
function csrf_check($fatal = true) {
	$result = true;
	if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
		$result = false;
		csrf_start();

		$name = $GLOBALS['csrf']['input-name'];
		$result = isset($_POST[$name]);
		$tokens = '';

		csrf_log(__FUNCTION__, "csrf magic $name was $result");

		if ($result) {
			// we don't regenerate a token and check it because some token creation
			// schemes are volatile.
			$tokens = $_POST[$name];
			$result = csrf_check_tokens($tokens);
			if (is_array($tokens)) {
				$tokens = implode(';', $tokens);
			}

			csrf_log(__FUNCTION__, "check_tokens($name, $tokens) returned $result");
		}

		if ($fatal && !$result) {
			$callback = $GLOBALS['csrf']['callback'];

			// filter tokens to ensure only valid tokens passed
			if (trim($tokens, 'A..Za..z0..9:;,') !== '') {
				$tokens = 'hidden';
			}

			$callback($tokens);
			exit;
		}
	}

	csrf_log(__FUNCTION__, 'returns: ' . var_export($result, true));

	return $result;
}

/**
 * Retrieves a valid token(s) for a particular context. Tokens are separated
 * by semicolons.
 */
function csrf_get_tokens() {
	$has_cookies = !empty($_COOKIE);

	// $ip implements a composite key, which is sent if the user hasn't sent
	// any cookies. It may or may not be used, depending on whether or not
	// the cookies "stick"
	$secret = csrf_get_secret();
	$token  = '';
	$ip     = '';

	if (!$has_cookies && $secret) {
		$ip = csrf_get_client_addr();
		if (!empty($ip)) {
			$ip = ';ip:' . csrf_hash($_SERVER['REMOTE_ADDR']);
		}
	}

	csrf_start();

	// These are "strong" algorithms that don't require per se a secret
	if ($GLOBALS['csrf']['session'] && session_id()) {
		$token = 'sid:' . csrf_hash(session_id()) . $ip;
	} elseif ($GLOBALS['csrf']['cookie']) {
		$val = csrf_generate_secret();
		setcookie($GLOBALS['csrf']['cookie'], $val, time() + 3600, $GLOBALS['csrf']['url_path']);
		$token = 'cookie:' . csrf_hash($val) . $ip;
	} elseif ($GLOBALS['csrf']['key']) {
		$token = 'key:' . csrf_hash($GLOBALS['csrf']['key']) . $ip;
	} elseif (!$secret) {
		$token = 'invalid';
	} elseif ($GLOBALS['csrf']['user'] !== false) {
		$token = 'user:' . csrf_hash($GLOBALS['csrf']['user']);
	} elseif ($GLOBALS['csrf']['allow-ip']) {
		$token = ltrim($ip, ';');
	} else {
		$token = 'invalid';
	}

	csrf_log(__FUNCTION__, 'returns: ' . var_export($token, true));

	return $token;
}

function csrf_flattenpost($data) {
	$ret = array();
	foreach($data as $n => $v) {
		$ret = array_merge($ret, csrf_flattenpost2(1, $n, $v));
	}

	csrf_log(__FUNCTION__, 'returns: ' . var_export($ret, true));

	return $ret;
}

function csrf_flattenpost2($level, $key, $data) {
	if(!is_array($data)) {
		$ret = array($key => $data);
	} else {
		$ret = array();
		foreach($data as $n => $v) {
			$nk = $level >= 1 ? $key."[$n]" : "[$n]";
			$ret = array_merge($ret, csrf_flattenpost2($level+1, $nk, $v));
		}
	}

	csrf_log(__FUNCTION__, 'returns: ' . var_export($ret, true));

	return $ret;
}

/**
 * @param $tokens is safe for HTML consumption
 */
function csrf_callback($tokens) {
	// (yes, $tokens is safe to echo without escaping)
	$data = '';
	foreach (csrf_flattenpost($_POST) as $key => $value) {
		if ($key != $GLOBALS['csrf']['input-name']) {
			$data .= '<input type="hidden" name="'.htmlspecialchars($key).'" value="'.htmlspecialchars($value).'" />';
		}
	}

	echo "<html>
	<head>
		<title>CSRF check failed</title>
	</head>
	<body>
		<p>

			CSRF check failed. Your form session may have expired, or you may not have cookies
			enabled.

		</p>
		<form method='post' action=''>$data<input type='submit' value='Try again' /></form>
		<p>Debug: $tokens</p></body></html>
	</body>
</html>";
}

/**
 * Checks if a composite token is valid. Outward facing code should use this
 * instead of csrf_check_token()
 */
function csrf_check_tokens($tokens) {
	if (is_string($tokens)) {
		$tokens = explode(';', $tokens);
	}

	$valid_token = false;
	foreach ($tokens as $token) {
		if (csrf_check_token($token)) {
			$valid_token = true;
			break;
		}
	}

	csrf_log(__FUNCTION__, 'returns: ' . var_export($valid_token, true));

	return $valid_token;
}

/**
 * Checks if a token is valid.
 */
function csrf_check_token($token) {
	$valid_token = false;
	if (strpos($token, ':') !== false) {
		list($type, $value) = explode(':', $token, 2);

		if (strpos($value, ',') !== false) {
			list($x, $time) = explode(',', $token, 2);

			$check_token = true;
			if ($GLOBALS['csrf']['expires']) {
				$expiry_time = time();
				$expiry_csrf = $time + $GLOBALS['csrf']['expires'];
				$check_token = ($expiry_time < $expiry_csrf);

				csrf_log(__FUNCTION__, "expiry $check_token = $expiry_time < $expiry_csrf");
			}

			if ($check_token) {
				switch ($type) {
					case 'sid':
						$valid_token = ($value === csrf_hash(session_id(), $time));
						break;
					case 'cookie':
						$n = $GLOBALS['csrf']['cookie'];
						if ($n && isset($_COOKIE[$n])) {
							$valid_token = ($value === csrf_hash($_COOKIE[$n], $time));
						}
						break;
					case 'key':
						if ($GLOBALS['csrf']['key']) {
							$valid_token = ($value === csrf_hash($GLOBALS['csrf']['key'], $time));
						}
						break;

						// We could disable these 'weaker' checks if 'key' was set, but
						// that doesn't make me feel good then about the cookie-based
						// implementation.
					case 'user':
						if (csrf_get_secret() && $GLOBALS['csrf']['user'] !== false) {
							$valid_token = ($value === csrf_hash($GLOBALS['csrf']['user'], $time));
						}
						break;
					case 'ip':
						// do not allow IP-based checks if the username is set, or if
						// the browser sent cookies
						if (csrf_get_secret() &&
							$GLOBALS['csrf']['user'] === false &&
							empty($_COOKIE) &&
							$GLOBALS['csrf']['allow-ip']) {

							$client_ip = csrf_get_client_addr();
							if (!empty($client_ip)) {
								$valid_token = ($value === csrf_hash($client_ip, $time));
							}
						}
						break;
				}

				csrf_log(__FUNCTION__, 'Checking ' . $type . ' resulted ' . $valid_token);
			}
		}
	}

	csrf_log(__FUNCTION__, 'returns: ' . var_export($valid_token, true));

	return $valid_token;
}

/**
 * Sets a configuration value.
 */
function csrf_conf($key, $val) {
	if (!isset($GLOBALS['csrf'][$key])) {
		trigger_error('No such configuration ' . $key, E_USER_WARNING);
	} else {
		$old_val = $GLOBALS['csrf'][$key];
		$GLOBALS['csrf'][$key] = $val;

		//csrf_log(__FUNCTION__,'Configuration option [' . $key . '] set to [' . $val . '] (was [' . $old_val . '])');
	}
}

/**
 * Starts a session if we're allowed to.
 */
function csrf_start() {
	global $config;

	if ($GLOBALS['csrf']['auto-session'] && !session_id()) {
		session_start();
	}
}

/**
 * Retrieves the secret, and generates one if necessary.
 */
function csrf_get_secret() {
	$secret = '';
	$files  = array();
	if ($GLOBALS['csrf']['secret']) {
		$secret = $GLOBALS['csrf']['secret'];
	}

	if (empty($secret)) {
		if (isset($GLOBALS['csrf']['path_secret'])) {
			$files[] = $GLOBALS['csrf']['path_secret'];
		}
		$files[] = __DIR__ . '/csrf-secret.php';
	}

	foreach ($files as $file) {
		$dir = dirname($file);
		if (file_exists($file)) {
			$secret = @file_get_contents($file);
			if (!empty($secret)) {
				break;
			}
		}
	}

	if (empty($secret)) {
		$new_secret = csrf_generate_secret();
		foreach ($files as $file) {
			if (csrf_writable($file)) {
				$fh = fopen($file, 'w');
				fwrite($fh, $new_secret);
				fclose($fh);
				$secret = $new_secret;
				break;
			}
		}
	}

	$GLOBALS['csrf']['secret'] = $secret;

	csrf_log(__FUNCTION__, 'returns: ' . var_export($secret, true));

	return $secret;
}

/**
 * Generates a random string as the hash of time, microtime, and mt_rand.
 */
function csrf_generate_secret($len = 32) {
	$r = '';
	for ($i = 0; $i < $len; $i++) {
		$r .= chr(mt_rand(0, 255));
	}
	$r .= time() . microtime();

	$secret = csrf_internal_hash('',$r);

	csrf_log(__FUNCTION__, 'returns: ' . var_export($secret, true));

	return $secret;
}

function csrf_internal_hash($secret, $value) {
	$hash_func = 'sha1'; // fall back hash func
	if (!empty($GLOBALS['csrf']['hash'])) {
		$hash_func = $GLOBALS['csrf']['hash'];
	}

	if (function_exists("hash_hmac")) {
		$result = hash_hmac($hash_func, $value, $secret);
	} else {
		$result = $hash_func($secret . $value);
	}
	return $result;
}

/**
 * Generates a hash/expiry double. If time isn't set it will be calculated
 * from the current time.
 */
function csrf_hash($value, $time = null) {
	if (!$time) {
		$time = time();
	}

	$secret = csrf_get_secret();
	$result = csrf_internal_hash($secret, csrf_internal_hash($secret, $time . ':' . $value)) . ',' . $time;

	csrf_log(__FUNCTION__, 'returns: ' . var_export($result, true));

	return $result;
}

function csrf_get_client_addr() {
	$http_addr_headers = array(
		'X-Forwarded-For',
		'X-Client-IP',
		'X-Real-IP',
		'X-ProxyUser-Ip',
		'CF-Connecting-IP',
		'True-Client-IP',
		'HTTP_X_FORWARDED',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_CLUSTER_CLIENT_IP',
		'HTTP_FORWARDED_FOR',
		'HTTP_FORWARDED',
		'HTTP_CLIENT_IP',
		'REMOTE_ADDR',
	);

	$client_addr = false;
	foreach ($http_addr_headers as $header) {
		if (!empty($_SERVER[$header])) {
			$header_ips = explode(',', $_SERVER[$header]);
			foreach ($header_ips as $header_ip) {
				if (!empty($header_ip)) {
					if (!filter_var($header_ip, FILTER_VALIDATE_IP)) {
						csrf_log(__FUNCTION__, 'ERROR: Invalid remote client IP Address found in header (' . $header . ').');
					} else {
						$client_addr = $header_ip;
						csrf_log(__FUNCTION__, 'DEBUG: Using remote client IP Address found in header (' . $header . '): ' . $client_addr . ' (' . $_SERVER[$header] . ')');
						break;
					}
				}
			}
		}
	}

	csrf_log(__FUNCTION__, 'returns: ' . var_export($client_addr, true));

	return $client_addr;
}

function csrf_writable($path) {
	if (empty($path)) {
		return false;
	}

	if ($path[strlen($path)-1] == '/') {
		return csrf_writable($path . uniqid(mt_rand()) . '.tmp');
	}

	if (file_exists($path)) {
		if (($f = @fopen($path, 'a'))) {
			fclose($f);

			return true;
		}

		return false;
	}

	if (($f = @fopen($path, 'w'))) {
		fclose($f);
		unlink($path);

		return true;
	}

	return false;
}

function csrf_log($name, $text) {
	$log_file = '';
	if (!empty($GLOBALS['csrf']['log_file'])) {
		$log_file = $GLOBALS['csrf']['log_file'];
		if (!csrf_writable($log_file)) {
			die('ERROR: CSRF Log file unavailable: ' . $log_file . PHP_EOL);
		}

		$log_file = rtrim($log_file, '.log').'_'.sha1(session_id()).'.log';
		$d = new DateTime();
		$l = sprintf('[%20s] [%20s] [%s] %s%s%s%s%s%s%s', $d->Format('Y-m-d H:i:s.u'), $name, csrf_caller(), PHP_EOL, $text, PHP_EOL, PHP_EOL, csrf_backtrace('',0,2), PHP_EOL, PHP_EOL);

		file_put_contents($log_file, $l, FILE_APPEND);
		if (!empty($GLOBALS['csrf']['log_echo'])) {
			print $l;
		}
	}
}

function csrf_caller() {
	static $caller = '';

	if (empty($caller)) {
		if (!empty($_SERVER['REQUEST_URI'])) {
			$caller = $_SERVER['REQUEST_URI'];
		} else {
			$caller = $_SERVER['SCRIPT_NAME'];
		}
	}

	return $caller;
}

function csrf_backtrace($entry = '', $limit = 0, $skip = 0) {
	global $config;

	$skip = $skip >= 0 ? $skip : 1;
	$limit = $limit > 0 ? ($limit + $skip) : 0;

	$callers = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit);
	while ($skip > 0) {
		array_shift($callers);
		$skip--;
	}

	$s='';
	foreach ($callers as $c) {
		if (isset($c['line'])) {
			$line = '[' . $c['line'] . ']';
		} else {
			$line = '';
		}

		if (isset($c['file'])) {
			if (isset($config['base_path'])) {
				$file = str_replace($config['base_path'], '', $c['file']) . $line;
			} else {
				$file = $c['file'] . $line;
			}
		} else {
			$file = $line;
		}

		$func = $c['function'].'()';
		if (isset($c['class'])) {
			$func = $c['class'] . $c['type'] . $func;
		}

		$s = sprintf('%30s : %s' . PHP_EOL, $func, $file) . $s;
	}

	return $s;
}

/****** MAIN CODE ******/

require_once(__DIR__ . '/csrf-conf.php');

if (!empty($GLOBALS['csrf']['startup'])) {
	$csrf_startup_func = $GLOBALS['csrf']['startup'];
} elseif (function_exists('csrf_startup')) {
	$csrf_startup_func = 'csrf_startup';
}

if (function_exists($csrf_startup_func)) {
	call_user_func($csrf_startup_func);
}

if (!empty($_POST)) {
	csrf_log('<post>', var_export($_POST, true));
}

if (!empty($_GET)) {
	csrf_log('<get>', var_export($_GET, true));
}

if (!$GLOBALS['csrf']['disable']) {

	// Initialize our handler
	if ($GLOBALS['csrf']['rewrite']) {
		ob_start('csrf_ob_handler');
	}

	// Perform check
	if (!$GLOBALS['csrf']['defer'])	{
		csrf_check();
	}
}
