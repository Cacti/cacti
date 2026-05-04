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
*/

/**
 * Centralised HTTP security header emission.
 *
 * Before this class, header lines were hand-rolled in include/global.php.
 * That layout made it easy for duplicate/weaker CSP strings to leak in
 * (e.g. the meta tag in lib/html.php) and hard to add a missing
 * directive without touching every caller. Keep the emission here so
 * one site is the authoritative source for the policy.
 *
 * Nonce support is available via getNonce()/getNonceAttribute() for
 * inline <script>/<style> tags that haven't been migrated to external
 * files yet. 'unsafe-inline' stays in script-src/style-src until every
 * inline tag carries a nonce; removing it today would blank the UI.
 */
class CactiSecureHeaders {
	/**
	 * Per-request cryptographic nonce. 18 bytes base64url-encoded (RFC 4648 §5)
	 * yields 24 chars with no padding. Base64url avoids '+' and '/' which are
	 * not safe unquoted in CSP values.
	 */
	public static function getNonce() {
		static $nonce = null;
		if ($nonce !== null) {
			return $nonce;
		}

		$bytes = false;

		/* Preferred: CSPRNG via random_bytes(). Throws on entropy failure. */
		if (function_exists('random_bytes')) {
			try {
				$bytes = random_bytes(18);
			} catch (\Exception $e) {
				if (function_exists('cacti_log')) {
					cacti_log('CSP nonce generation via random_bytes() failed: ' . $e->getMessage(), false, 'SYSTEM');
				}
			}
		}

		/* Second choice: OpenSSL CSPRNG. */
		if ($bytes === false && function_exists('openssl_random_pseudo_bytes')) {
			$bytes = openssl_random_pseudo_bytes(18);
			if ($bytes === false) {
				if (function_exists('cacti_log')) {
					cacti_log('CSP nonce generation via openssl_random_pseudo_bytes() failed', false, 'SYSTEM');
				}
			}
		}

		/* Last resort: non-CSPRNG. Acceptable only when both CSPRNGs are
		 * unavailable; warns operators so they can investigate the environment. */
		if ($bytes === false) {
			if (function_exists('cacti_log')) {
				cacti_log('CSP nonce falling back to non-CSPRNG source; check PHP entropy configuration', false, 'SYSTEM');
			}
			$bytes = substr(hash('sha256', uniqid(mt_rand(), true), true), 0, 18);
		}

		$nonce = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
		return $nonce;
	}

	/**
	 * `nonce="..."` attribute string for inline <script>/<style> tags.
	 */
	public static function getNonceAttribute() {
		return 'nonce="' . self::getNonce() . '"';
	}

	/**
	 * Reads the configured CSP script mode and normalises it to a known token.
	 * Returns '' when read_config_option is unavailable (early CLI bootstrap).
	 */
	public static function getCspMode() {
		if (!function_exists('read_config_option')) {
			return '';
		}
		$value = read_config_option('content_security_policy_script');
		if ($value === 'unsafe-eval' || $value === 'nonce' || $value === 'nonce-report') {
			return $value;
		}
		return '';
	}

	/**
	 * True when the active mode requires per-request nonces in the CSP.
	 */
	public static function isNonceMode() {
		$mode = self::getCspMode();
		return ($mode === 'nonce' || $mode === 'nonce-report');
	}

	/**
	 * Pure function: build the CSP policy body string from its inputs.
	 * Keeping construction separate from emission makes it unit-testable
	 * without relying on header() side-effects.
	 *
	 * @param string $mode       One of '', 'unsafe-eval', 'nonce', 'nonce-report'.
	 * @param string $nonce      Base64url nonce; ignored when mode is not nonce-based.
	 * @param string $alternates Space-separated alternate source hosts (already sanitized).
	 * @param string $report_uri Pre-validated report URI; appended only for nonce modes.
	 * @return string            Full CSP value, suitable for use after the header name.
	 */
	public static function buildCspPolicy($mode, $nonce, $alternates, $report_uri = '') {
		if ($mode === 'nonce' || $mode === 'nonce-report') {
			/* 'strict-dynamic' lets a nonced page script transitively trust
			 * scripts it inserts via DOM (jQuery .html(), .append(), etc).
			 * Without it most jQuery-driven UIs break under nonce mode.
			 * 'unsafe-eval' covers jQuery's globalEval + new Function() paths.
			 * Browsers that honour strict-dynamic (Chrome 52+, Firefox 60+,
			 * Safari 15.4+) ignore the 'self' source list once strict-dynamic
			 * is present; the nonce becomes the sole script trust anchor. */
			$script_src = "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic' 'unsafe-eval' {$alternates}";
			/* Style-src keeps 'unsafe-inline' on purpose: jQuery .css(),
			 * setAttribute('style', ...), and the dozens of legacy inline
			 * style="" attributes scattered across Cacti pages all rely on
			 * inline-style execution. Style XSS is a much narrower attack
			 * surface than script XSS; the trade-off is operator-friendly. */
			$style_src  = "style-src 'self' 'unsafe-inline' {$alternates}";
		} else {
			$eval_token = ($mode === 'unsafe-eval') ? " 'unsafe-eval'" : '';
			$script_src = "script-src 'self'{$eval_token} 'unsafe-inline' {$alternates}";
			$style_src  = "style-src 'self' 'unsafe-inline' {$alternates}";
		}

		$policy = "default-src 'self'; "
			. "{$script_src}; "
			. "{$style_src}; "
			. "img-src 'self' data: blob: {$alternates}; "
			. "font-src 'self' {$alternates}; "
			. "connect-src 'self' {$alternates}; "
			. "frame-src 'self' {$alternates}; "
			. "frame-ancestors 'self'; "
			. "worker-src 'self' {$alternates}; "
			. "object-src 'none'; "
			. "base-uri 'self'; "
			. "form-action 'self'; "
			. "manifest-src 'self';";

		/* Browsers honor report-uri on both enforcing and report-only policies.
		 * Only attach when a URI is configured and we're in a nonce mode; the
		 * unsafe-inline modes do not warrant violation tracking here. */
		if ($report_uri !== '' && ($mode === 'nonce' || $mode === 'nonce-report')) {
			$policy .= " report-uri {$report_uri};";
		}

		return $policy;
	}

	/**
	 * Strip characters that are not valid inside a CSP source list token.
	 * html_escape() is wrong for CSP context because it leaves ';' intact,
	 * which would terminate a directive early and allow header injection.
	 */
	private static function sanitizeCspSources($raw) {
		if (!is_string($raw) || $raw === '') {
			return '';
		}
		/* Drop everything that isn't a CSP source-list safe char. */
		return preg_replace('/[^A-Za-z0-9.:\-*\/ ]/', '', $raw);
	}

	/**
	 * Default CSP violation report URI. Derived from $url_path so installs
	 * at /, /cacti2, or behind a rewriting reverse proxy still point at the
	 * bundled csp_report.php shim. Falls back to /cacti/csp_report.php only
	 * when $url_path is unset or unreadable.
	 *
	 * The result is always normalised to start with a single '/' and to
	 * collapse any duplicate path separators introduced by trailing slashes
	 * in the configured base.
	 */
	private static function defaultReportUri() {
		$base = '';
		if (isset($GLOBALS['url_path']) && is_string($GLOBALS['url_path']) && $GLOBALS['url_path'] !== '') {
			$base = $GLOBALS['url_path'];
		}
		if ($base === '') {
			return '/cacti/csp_report.php';
		}
		/* Drop trailing slashes; ensure single leading slash. */
		$base = '/' . ltrim(rtrim($base, '/'), '/');
		if ($base === '/') {
			return '/csp_report.php';
		}
		return $base . '/csp_report.php';
	}

	/*
	 * Mode branching for emitHeaders():
	 *
	 *   ''            -> Content-Security-Policy with 'unsafe-inline'
	 *   'unsafe-eval' -> Content-Security-Policy with 'unsafe-inline' + 'unsafe-eval'
	 *   'nonce'       -> Content-Security-Policy with 'nonce-<token>' (enforce)
	 *   'nonce-report'-> Content-Security-Policy-Report-Only with 'nonce-<token>'
	 *
	 * Plugins that emit inline <script> or <style> tags must call
	 * CactiSecureHeaders::getNonceAttribute() and include the attribute;
	 * otherwise their scripts will be blocked in nonce modes.
	 */

	/**
	 * Emit the full security-header set. Safe to call multiple times;
	 * headers_sent() short-circuits re-emission after output begins.
	 */
	public static function emitHeaders() {
		if (headers_sent()) {
			return;
		}

		$mode       = self::getCspMode();
		$nonce      = self::isNonceMode() ? self::getNonce() : '';
		$alternates = '';
		$report_uri = '';

		if (function_exists('read_config_option')) {
			$cfg_alternates = read_config_option('content_security_alternate_sources');
			if ($cfg_alternates !== null && $cfg_alternates !== false) {
				/* html_escape() leaves ';' intact; wrong for CSP directive values.
				 * The CSP scrubber drops any char that could terminate a directive
				 * or inject a header line. */
				$alternates = self::sanitizeCspSources((string)$cfg_alternates);
			}

			/* Allow operators to configure the violation report endpoint; fall back
			 * to the Cacti-bundled handler if the option is missing or invalid.
			 * The fallback is derived from $url_path so installs at /, /cacti2,
			 * or behind a rewrite still point at the right shim. */
			$cfg_report_uri = read_config_option('content_security_report_uri');
			if ($cfg_report_uri !== null && $cfg_report_uri !== false && $cfg_report_uri !== '') {
				/* Reject URIs containing chars that would break the CSP header line. */
				if (preg_match('/[;\r\n "\s]/', (string)$cfg_report_uri)) {
					$report_uri = self::defaultReportUri();
				} else {
					$report_uri = (string)$cfg_report_uri;
				}
			} else {
				$report_uri = self::defaultReportUri();
			}
		}

		$csp = self::buildCspPolicy($mode, $nonce, $alternates, $report_uri);

		header('X-Frame-Options: SAMEORIGIN');

		if ($mode === 'nonce-report') {
			header('Content-Security-Policy-Report-Only: ' . $csp);
		} else {
			header('Content-Security-Policy: ' . $csp);
		}

		if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
			header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
		}

		header('Cross-Origin-Opener-Policy: same-origin');
		header('Cross-Origin-Resource-Policy: same-origin');
		/* IE-era P3P header. Modern browsers ignore it; kept so legacy
		 * IE cookie handling still works on intranet installs. */
		header('P3P: CP="CAO PSA OUR"');
		header('X-Content-Type-Options: nosniff');
		header('Referrer-Policy: strict-origin-when-cross-origin');
		header('Permissions-Policy: camera=(), geolocation=(), interest-cohort=(), microphone=(), payment=(), usb=()');
		header('Cache-Control: no-store, no-cache, must-revalidate');
	}
}
