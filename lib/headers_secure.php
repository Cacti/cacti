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
	 * Per-request cryptographic nonce. 16 bytes base64-encoded.
	 */
	public static function getNonce() {
		static $nonce = null;
		if ($nonce === null) {
			if (function_exists('random_bytes')) {
				$nonce = base64_encode(random_bytes(16));
			} else {
				$nonce = base64_encode(openssl_random_pseudo_bytes(16));
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
	 * Emit the full security-header set. Safe to call multiple times;
	 * headers_sent() short-circuits re-emission after output begins.
	 */
	public static function emitHeaders() {
		if (headers_sent()) {
			return;
		}

		$script_policy = '';
		$alternates    = '';
		if (function_exists('read_config_option')) {
			$cfg_script = read_config_option('content_security_policy_script');
			if ($cfg_script === 'unsafe-eval') {
				$script_policy = "'unsafe-eval'";
			}
			$cfg_alternates = read_config_option('content_security_alternate_sources');
			if ($cfg_alternates !== null && $cfg_alternates !== false) {
				$alternates = function_exists('html_escape')
					? html_escape($cfg_alternates)
					: htmlspecialchars((string)$cfg_alternates, ENT_QUOTES, 'UTF-8');
			}
		}

		/* 'unsafe-inline' stays until the 183 inline <script>/<style>
		 * tags get nonces or are migrated to external files. Operators
		 * who need a strict CSP today can set
		 * content_security_policy_script='nonce' once that work lands. */
		$csp = "default-src 'self'; "
			. "script-src 'self' {$script_policy} 'unsafe-inline' {$alternates}; "
			. "style-src 'self' 'unsafe-inline' {$alternates}; "
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

		header('X-Frame-Options: SAMEORIGIN');
		header('Content-Security-Policy: ' . $csp);

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
