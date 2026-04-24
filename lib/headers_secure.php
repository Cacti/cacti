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
	 * Per-request cryptographic nonce. 16 bytes base64url-encoded (RFC 4648 §5).
	 * Base64url avoids '+' and '/' which are not safe unquoted in CSP values.
	 */
	public static function getNonce() {
		static $nonce = null;
		if ($nonce === null) {
			if (function_exists('random_bytes')) {
				$nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
			} else {
				$nonce = rtrim(strtr(base64_encode(openssl_random_pseudo_bytes(16)), '+/', '-_'), '=');
			}
		}
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
	 * @param string $alternates Space-separated alternate source hosts (already escaped).
	 * @return string            Full CSP value, suitable for use after the header name.
	 */
	public static function buildCspPolicy($mode, $nonce, $alternates) {
		if ($mode === 'nonce' || $mode === 'nonce-report') {
			$script_src = "script-src 'self' 'nonce-{$nonce}' {$alternates}";
			$style_src  = "style-src 'self' 'nonce-{$nonce}' {$alternates}";
		} else {
			$eval_token = ($mode === 'unsafe-eval') ? " 'unsafe-eval'" : '';
			$script_src = "script-src 'self'{$eval_token} 'unsafe-inline' {$alternates}";
			$style_src  = "style-src 'self' 'unsafe-inline' {$alternates}";
		}

		return "default-src 'self'; "
			. "{$script_src}; "
			. "{$style_src}; "
			. "img-src 'self' {$alternates} data: blob:; "
			. "font-src 'self' {$alternates}; "
			. "connect-src 'self' {$alternates}; "
			. "frame-src 'self'; "
			. "frame-ancestors 'self'; "
			. "worker-src 'self' {$alternates}; "
			. "object-src 'none'; "
			. "base-uri 'self'; "
			. "form-action 'self'; "
			. "manifest-src 'self';";
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

		if (function_exists('read_config_option')) {
			$cfg_alternates = read_config_option('content_security_alternate_sources');
			if ($cfg_alternates !== null && $cfg_alternates !== false) {
				$alternates = function_exists('html_escape')
					? html_escape($cfg_alternates)
					: htmlspecialchars((string)$cfg_alternates, ENT_QUOTES, 'UTF-8');
			}
		}

		$csp = self::buildCspPolicy($mode, $nonce, $alternates);

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
