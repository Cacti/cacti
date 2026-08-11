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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * Content-Security-Policy script-src helper.
 *
 * The `content_security_policy_script` setting selects how the CSP restricts
 * inline JavaScript:
 *
 *   ''           HTMX (default) - keeps 'unsafe-inline', the policy every
 *                current theme and plugin relies on. Behaviour is identical
 *                to releases before this helper existed.
 *   'nonce'      Emits a per-request nonce plus 'strict-dynamic'. Cacti-owned
 *                inline <script> tags carry the nonce via getNonceAttribute();
 *                plugin scripts must adopt it or they are blocked.
 *   'unsafe-eval' None - most permissive, also allows eval(). This is the
 *                legacy value, kept so upgrades that already selected it are
 *                not silently rewritten.
 *
 * Only script-src changes across modes; the rest of the policy is built at the
 * call sites so the non-nonce output stays byte-identical to prior releases.
 * The buildScriptSrc()/buildReportUriDirective() methods are pure so the mode
 * branching can be unit tested without a config or request context.
 */
class CactiSecureHeaders {
	/**
	 * Per-request nonce. 18 random bytes base64url-encoded (RFC 4648 sec 5)
	 * give 24 chars with no padding and none of the '+' '/' '=' characters
	 * that are unsafe unquoted in a CSP source token.
	 */
	public static function getNonce(): string {
		static $nonce = null;

		if ($nonce !== null) {
			return $nonce;
		}

		$bytes = false;

		if (function_exists('random_bytes')) {
			try {
				$bytes = random_bytes(18);
			} catch (\Exception $e) {
				if (function_exists('cacti_log')) {
					cacti_log('CSP nonce generation via random_bytes() failed: ' . $e->getMessage(), false, 'SYSTEM');
				}
			}
		}

		if ($bytes === false && function_exists('openssl_random_pseudo_bytes')) {
			$strong  = false;
			$openssl = openssl_random_pseudo_bytes(18, $strong);

			// Only accept OpenSSL output flagged as cryptographically strong;
			// otherwise fall through to the warned last-resort source.
			// openssl_random_pseudo_bytes() returns a string (it throws rather
			// than returning false under PHP 8), so $strong is the real gate.
			if ($strong === true) {
				$bytes = $openssl;
			}
		}

		// Both CSPRNGs unavailable. Warn and fall back so the page still renders.
		if ($bytes === false) {
			if (function_exists('cacti_log')) {
				cacti_log('CSP nonce falling back to non-CSPRNG source; check PHP entropy configuration', false, 'SYSTEM');
			}
			$bytes = substr(hash('sha256', uniqid((string) mt_rand(), true), true), 0, 18);
		}

		$nonce = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

		return $nonce;
	}

	/**
	 * `nonce="..."` attribute for inline <script> tags. Returns an empty string
	 * outside nonce mode so the attribute is only emitted when it matters.
	 */
	public static function getNonceAttribute(): string {
		if (!self::isNonceMode()) {
			return '';
		}

		return 'nonce="' . self::getNonce() . '"';
	}

	/**
	 * Normalise the stored setting to one of the three known tokens. Unknown
	 * or legacy values fall back to the default (unsafe-inline) rather than a
	 * stricter policy, so a bad option value never blanks the UI.
	 */
	public static function getCspMode(): string {
		if (!function_exists('read_config_option')) {
			return '';
		}

		$value = read_config_option('content_security_policy_script');

		if ($value === 'nonce' || $value === 'unsafe-eval') {
			return $value;
		}

		return '';
	}

	public static function isNonceMode(): bool {
		return self::getCspMode() === 'nonce';
	}

	/**
	 * script-src directive for the active mode. $alternates is the already
	 * computed alternate-source string used by the surrounding policy.
	 */
	public static function scriptSrc(string $alternates): string {
		$mode = self::getCspMode();

		// Only spend entropy on a nonce when the policy will actually carry one.
		$nonce = ($mode === 'nonce') ? self::getNonce() : '';

		return self::buildScriptSrc($mode, $nonce, $alternates);
	}

	/**
	 * Pure builder for the script-src directive.
	 *
	 * 'strict-dynamic' lets a nonced script transitively trust scripts it
	 * injects (jQuery .html()/.append()), which supporting browsers require;
	 * those browsers ignore 'self'/'unsafe-inline' once it is present. Older
	 * browsers ignore 'strict-dynamic'/nonce and fall back to 'unsafe-inline'.
	 * 'unsafe-eval' covers jQuery globalEval() and new Function().
	 *
	 * @param string $mode       '' | 'nonce' | 'unsafe-eval'
	 * @param string $nonce      base64url nonce; used only in nonce mode
	 * @param string $alternates sanitized alternate-source token string
	 */
	public static function buildScriptSrc(string $mode, string $nonce, string $alternates): string {
		if ($mode === 'nonce') {
			return "script-src 'self' 'nonce-$nonce' 'strict-dynamic' 'unsafe-eval' 'unsafe-inline' $alternates";
		}

		if ($mode === 'unsafe-eval') {
			return "script-src 'self' 'unsafe-eval' 'unsafe-inline' $alternates";
		}

		return "script-src 'self' 'unsafe-inline' $alternates";
	}

	/**
	 * ` report-uri <uri>;` suffix for the CSP header, or '' when not applicable.
	 * report-uri only affects nonce mode and is invalid inside a <meta> policy,
	 * so only the HTTP-header call site appends it.
	 */
	public static function reportUriDirective(): string {
		return self::buildReportUriDirective(self::getCspMode(), self::reportUri());
	}

	/**
	 * Pure builder for the report-uri suffix.
	 *
	 * @param string $mode '' | 'nonce' | 'unsafe-eval'
	 * @param string $uri  already-validated report URI
	 */
	public static function buildReportUriDirective(string $mode, string $uri): string {
		if ($mode !== 'nonce' || $uri === '') {
			return '';
		}

		return " report-uri $uri;";
	}

	/**
	 * Resolve the violation report endpoint. Prefers the configured value,
	 * rejecting anything that could break the header line, and otherwise
	 * derives the bundled csp_report.php path from the install's URL path.
	 */
	private static function reportUri(): string {
		if (function_exists('read_config_option')) {
			$configured = read_config_option('content_security_report_uri');

			if ($configured !== null && $configured !== false && $configured !== '') {
				if (!preg_match('/[;\r\n"\s]/', (string) $configured)) {
					return (string) $configured;
				}
			}
		}

		$base = defined('CACTI_PATH_URL') ? rtrim(CACTI_PATH_URL, '/') : '';

		return $base . '/csp_report.php';
	}
}
