<?php
require_once($config['include_path'] .'/vendor/csrf/csrf-conf.php');

/* cross site request forgery library */
function csrf_startup() {
	global $config;

	if ($config['is_web']) {
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
	raise_message('csrf_timeout');
	ob_end_clean();
	header('Location: ' . sanitize_uri($_SERVER['REQUEST_URI']));
	csrf_log(__FUNCTION__, 'Timeout, redirecting to ' . sanitize_uri($_SERVER['REQUEST_URI']));
	exit;
}

include_once($config['include_path'] . '/vendor/csrf/csrf-magic.php');
