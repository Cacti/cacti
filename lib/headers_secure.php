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
 *   'nonce'      Emits a per-request nonce plus 'strict-dynamic' in a
 *                report-only policy. Cacti-owned inline <script> tags can
 *                adopt the nonce incrementally without breaking legacy pages.
 *   'nonce-enforce' Enforces the nonce policy. This mode is additionally
 *                gated by CACTI_CSP_NONCE_ENFORCE so selecting it before the
 *                migration is complete cannot unexpectedly blank the UI.
 *   'unsafe-eval' None - most permissive, also allows eval(). This is the
 *                legacy value, kept so upgrades that already selected it are
 *                not silently rewritten.
 *
 * Nonce migration keeps the enforced policy byte-compatible and emits the
 * stricter script-src in a separate report-only header. The rest of each policy
 * is built at the call sites. The buildScriptSrc()/buildReportUriDirective()
 * methods are pure so mode branching can be tested without request state.
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

		try {
			$bytes = random_bytes(18);
		} catch (\Throwable $e) {
			if (function_exists('cacti_log')) {
				cacti_log('CSP nonce generation failed: ' . $e->getMessage(), false, 'SYSTEM');
			}

			throw new \RuntimeException('Unable to generate a cryptographically secure CSP nonce.', 0, $e);
		}

		$nonce = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

		return $nonce;
	}

	/**
	 * `nonce="..."` attribute for inline <script> tags. Returns an empty string
	 * outside nonce mode so the attribute is only emitted when it matters.
	 */
	public static function getNonceAttribute(): string {
		$mode  = self::getCspMode();
		$nonce = self::isNonceModeValue($mode) ? self::getNonce() : '';

		return self::buildNonceAttribute($mode, $nonce);
	}

	/**
	 * Build a nonce attribute for a normalized policy mode.
	 */
	public static function buildNonceAttribute(string $mode, string $nonce): string {
		if (!self::isNonceModeValue($mode)) {
			return '';
		}

		if (!self::isValidNonce($nonce)) {
			throw new \InvalidArgumentException('Invalid CSP nonce.');
		}

		return 'nonce="' . $nonce . '"';
	}

	/**
	 * Normalise the stored setting to one of the four known tokens. Unknown
	 * or legacy values fall back to the default (unsafe-inline) rather than a
	 * stricter policy, so a bad option value never blanks the UI.
	 */
	public static function getCspMode(): string {
		if (!function_exists('read_config_option')) {
			return '';
		}

		$mode = self::normalizeCspMode(read_config_option('content_security_policy_script'));

		if ($mode === 'nonce-enforce' &&
			(!defined('CACTI_CSP_NONCE_ENFORCE') || CACTI_CSP_NONCE_ENFORCE !== true)) {
			return 'nonce';
		}

		return $mode;
	}

	/**
	 * Normalize the stored setting without requiring a configuration context.
	 */
	public static function normalizeCspMode(mixed $value): string {
		if ($value === 'nonce' || $value === 'nonce-enforce' || $value === 'unsafe-eval') {
			return $value;
		}

		return '';
	}

	/**
	 * Normalize the configured space-delimited CSP host sources. Reject the
	 * complete value when any token is malformed so an attempted directive or
	 * header injection cannot leave behind a partially valid, broader policy.
	 *
	 * @psalm-taint-escape header
	 * @psalm-taint-escape html
	 */
	public static function normalizeAlternateSources(mixed $value): string {
		if (!is_string($value) && !is_numeric($value)) {
			return '';
		}

		$value = trim((string) $value);

		if ($value === '' || strlen($value) > 2048) {
			return '';
		}

		$sources = preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY);

		if ($sources === false) {
			return '';
		}

		$normalized = [];

		foreach ($sources as $source) {
			if (!self::isValidAlternateSource($source)) {
				return '';
			}

			$normalized[$source] = true;
		}

		return implode(' ', array_keys($normalized));
	}

	public static function isNonceMode(): bool {
		return self::isNonceModeValue(self::getCspMode());
	}

	/**
	 * script-src directive for the enforced policy. Nonce migration deliberately
	 * retains the compatible default here; reportOnlyScriptSrc() builds the
	 * strict policy used to inventory remaining violations.
	 */
	public static function scriptSrc(string $alternates): string {
		$mode  = self::getCspMode();
		$nonce = $mode === 'nonce-enforce' ? self::getNonce() : '';

		return self::buildEnforcedScriptSrc($mode, $alternates, $nonce);
	}

	/**
	 * Build the enforced script-src for a normalized mode.
	 */
	public static function buildEnforcedScriptSrc(string $mode, string $alternates, string $nonce = ''): string {
		// Nonce mode remains report-only until every core and plugin script is
		// nonce-aware. Keep the enforced policy compatible in the meantime.
		if ($mode === 'nonce') {
			$mode = '';
		}

		if ($mode === 'nonce-enforce') {
			$mode = 'nonce';
		}

		return self::buildScriptSrc($mode, $nonce, $alternates);
	}

	/**
	 * Strict script-src used by the report-only migration policy.
	 */
	public static function reportOnlyScriptSrc(string $alternates): string {
		$mode  = self::getCspMode();
		$nonce = $mode === 'nonce' ? self::getNonce() : '';

		return self::buildReportOnlyScriptSrc($mode, $nonce, $alternates);
	}

	/**
	 * Build the strict migration script-src for a normalized mode.
	 */
	public static function buildReportOnlyScriptSrc(string $mode, string $nonce, string $alternates): string {
		if ($mode !== 'nonce') {
			return '';
		}

		return self::buildScriptSrc('nonce', $nonce, $alternates);
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
		$alternates = self::normalizeAlternateSources($alternates);

		if ($mode === 'nonce') {
			if (!self::isValidNonce($nonce)) {
				throw new \InvalidArgumentException('Invalid CSP nonce.');
			}

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
		if (!self::isNonceModeValue($mode) || $uri === '') {
			return '';
		}

		if (!self::isValidReportUri($uri)) {
			throw new \InvalidArgumentException('Invalid CSP report URI.');
		}

		return " report-uri $uri;";
	}

	/**
	 * Validate a CSP nonce source value before it reaches a policy.
	 */
	private static function isValidNonce(string $nonce): bool {
		return preg_match('/^[A-Za-z0-9_-]{24}$/D', $nonce) === 1;
	}

	/**
	 * Both migration and enforcement modes issue nonces to trusted scripts.
	 */
	private static function isNonceModeValue(string $mode): bool {
		return $mode === 'nonce' || $mode === 'nonce-enforce';
	}

	/**
	 * Accept an absolute HTTP(S) URL or a root-relative endpoint without a
	 * query, fragment, authority credentials, whitespace, or control bytes.
	 */
	private static function isValidReportUri(string $uri): bool {
		if ($uri === '' || preg_match('/[\x00-\x20\x7f;"\'\\\\]/', $uri)) {
			return false;
		}

		if (str_starts_with($uri, '/')) {
			return !str_starts_with($uri, '//') &&
				!str_contains($uri, '?') &&
				!str_contains($uri, '#');
		}

		$parts = parse_url($uri);

		return is_array($parts) &&
			filter_var($uri, FILTER_VALIDATE_URL) !== false &&
			isset($parts['scheme'], $parts['host']) &&
			in_array(strtolower($parts['scheme']), ['http', 'https'], true) &&
			!isset($parts['user']) &&
			!isset($parts['pass']) &&
			!isset($parts['query']) &&
			!isset($parts['fragment']);
	}

	/**
	 * Accept CSP host-source expressions supported by the alternate-source
	 * setting: optional HTTP/WebSocket scheme, hostname/IP with optional
	 * wildcard, optional port, and an optional path without query or fragment.
	 */
	private static function isValidAlternateSource(string $source): bool {
		if ($source === '' || strlen($source) > 255) {
			return false;
		}

		$pattern = '~\A(?:(?:https?|wss?)://)?(?<host>\*|\*\.[A-Za-z0-9.-]+|\[[0-9A-Fa-f:.]+\]|[A-Za-z0-9.-]+)(?::(?<port>\*|[0-9]{1,5}))?(?:/[A-Za-z0-9._\~!$&()*+,:=@%/-]*)?\z~Di';

		if (preg_match($pattern, $source, $matches) !== 1) {
			return false;
		}

		$host = $matches['host'];

		if ($host !== '*') {
			if (str_starts_with($host, '[')) {
				$address = substr($host, 1, -1);

				if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
					return false;
				}
			} else {
				$hostname = str_starts_with($host, '*.') ? substr($host, 2) : $host;

				if (filter_var($hostname, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)      === false &&
					filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
					return false;
				}
			}
		}

		$port_value = $matches['port'] ?? '';

		if ($port_value !== '' && $port_value !== '*') {
			$port = (int) $port_value;

			if ($port < 1 || $port > 65535) {
				return false;
			}
		}

		return preg_match('/%(?![A-Fa-f0-9]{2})/', $source) !== 1;
	}

	/**
	 * Resolve the violation report endpoint. Prefers the configured value,
	 * rejecting anything that could break the header line, and otherwise
	 * derives the bundled csp_report.php path from the install's URL path.
	 */
	private static function reportUri(): string {
		$configured = '';

		if (function_exists('read_config_option')) {
			$configured = read_config_option('content_security_report_uri');
		}

		$base = defined('CACTI_PATH_URL') ? rtrim(CACTI_PATH_URL, '/') : '';

		return self::resolveReportUri($configured, $base);
	}

	/**
	 * Resolve a configured report endpoint against the Cacti URL base.
	 *
	 * @psalm-taint-escape header
	 */
	public static function resolveReportUri(mixed $configured, string $base): string {
		if ($configured !== null && $configured !== false && $configured !== '' &&
			self::isValidReportUri((string) $configured)) {
			return (string) $configured;
		}

		$fallback = rtrim($base, '/') . '/csp_report.php';

		return self::isValidReportUri($fallback) ? $fallback : '/csp_report.php';
	}
}
