<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 | Licensed under the GNU General Public License, version 2 or later.     |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/* Exercise the production renderer with renamed I/O boundaries in a CLI process. */
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$source = file_get_contents(dirname(__DIR__, 3) . '/lib/html.php');
$start  = strpos($source, 'function html_host_filter(');
$end    = strpos($source, "\nfunction ", $start + 1);
$body   = substr($source, $start, $end - $start);
$calls  = array('html_host_filter', 'get_selected_theme', 'read_config_option',
	'isset_request_var', 'get_filter_request_var', 'get_allowed_devices',
	'cacti_sizeof', 'strip_domain', 'html_escape', '__');
$compiled = '';

foreach (token_get_all('<?php ' . $body) as $index => $token) {
	if (is_array($token)) {
		if ($token[0] === T_OPEN_TAG && $index === 0) {
			continue;
		}
		$compiled .= $token[0] === T_STRING && in_array($token[1], $calls, true) ? 'probe_' . $token[1] : $token[1];
	} else {
		$compiled .= $token;
	}
}
eval($compiled);

function probe_get_selected_theme() { return $GLOBALS['theme']; }
function probe_read_config_option($name) { return $GLOBALS['autocomplete']; }
function probe_isset_request_var($name) { return false; }
function probe_get_filter_request_var($name) { return -1; }
function probe_get_allowed_devices($where) { return array(array('id' => 1, 'description' => 'Device & one')); }
function probe_cacti_sizeof($value) { return count($value); }
function probe_strip_domain($value) { return $value; }
function probe_html_escape($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function probe___($value) { return $value; }

$rendered = array();
foreach (array('classic', 'modern') as $theme) {
	foreach (array(false, true) as $autocomplete) {
		ob_start();
		probe_html_host_filter();
		$rendered[$theme . '-' . (int) $autocomplete] = ob_get_clean();
	}
}
print json_encode($rendered, JSON_THROW_ON_ERROR) . "\n";
