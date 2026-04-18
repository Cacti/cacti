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

/**
 * Context labels for cacti_html_context_escape().
 */
define('CACTI_ESC_ELEMENT',   'element');   // HTML element content (between tags)
define('CACTI_ESC_ATTR',      'attr');      // HTML attribute value (inside " or ')
define('CACTI_ESC_JS_STRING', 'js_string'); // JavaScript string literal content
define('CACTI_ESC_URL',       'url');       // URL component (query string key/value)
define('CACTI_ESC_CSS',       'css');       // CSS string value

/**
 * cacti_html_context_escape - escape a value for safe insertion into a
 * specific HTML / JS / URL / CSS context.
 *
 * Different contexts require different escape rules. Using the wrong escape
 * (e.g., htmlspecialchars for a JavaScript string) leaves exploitable holes.
 * This helper picks the right primitive per context and fails closed when
 * an unknown context is passed.
 *
 * Root-cause mitigation for context-confusion XSS:
 *   GHSA-7gx8-f5q4-86mv (tooltip HTML attr via SNMP description)
 *   GHSA-m544-32jr-54xw (JS string via session referer in auth_profile)
 *   GHSA-6233-v5hc-6gvf (HTML element via Report Tree titles)
 *   GHSA-977w-79m7-xjc4 (HTML element via SNMP data in graph export)
 *   GHSA-cfhh-pwvx-gp5g (reflected XSS via rfilter PCRE differential)
 *
 * Usage:
 *   print cacti_html_context_escape($value, CACTI_ESC_ELEMENT);
 *   print "<a title='" . cacti_html_context_escape($v, CACTI_ESC_ATTR) . "'>";
 *   print "var x = '" . cacti_html_context_escape($v, CACTI_ESC_JS_STRING) . "';";
 *   print "<a href='?q=" . cacti_html_context_escape($v, CACTI_ESC_URL) . "'>";
 *
 * @param mixed  $value    The value to escape
 * @param string $context  One of CACTI_ESC_* constants
 *
 * @return string  The escaped value safe for the given context
 */
function cacti_html_context_escape($value, $context) {
	$value = (string) $value;

	switch ($context) {
		case CACTI_ESC_ELEMENT:
		case CACTI_ESC_ATTR:
			// htmlspecialchars with ENT_QUOTES is safe for both element
			// content and both ' / " quoted attribute values.
			return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

		case CACTI_ESC_JS_STRING:
			// Emit a JSON-encoded string literal body (without the
			// surrounding quotes). Caller supplies the delimiting quotes.
			// JSON_HEX_TAG/APOS/QUOT/AMP keep the result safe inside <script>
			// tags and inside HTML attributes that contain JS.
			$flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE;
			$json  = json_encode($value, $flags);

			if ($json === false) {
				// Fail closed on encode failure.
				return '';
			}

			// Strip the surrounding double quotes; caller owns the delimiters.
			return substr($json, 1, -1);

		case CACTI_ESC_URL:
			// rawurlencode per RFC 3986 (spaces as %20, not +).
			return rawurlencode($value);

		case CACTI_ESC_CSS:
			// Only allow safe chars; escape everything else as \XX hex.
			// Keeps attacker out of style="expression(...)", url(...), etc.
			return preg_replace_callback('/[^a-zA-Z0-9\-\_]/', function ($m) {
				return '\\' . bin2hex($m[0]) . ' ';
			}, $value);

		default:
			// Unknown context — fail closed by escaping aggressively as
			// HTML element content. Preserves safety, alerts on misuse via
			// visibly escaped output.
			return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}
