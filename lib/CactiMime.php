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

use Symfony\Component\Mime\MimeTypes;

/**
 * Content-based MIME inspection for upload sites.
 *
 * Extension-only checks accept any file the user names .zip or .xml. A
 * PHP payload renamed import.zip passes those checks but is rejected
 * here because finfo reads the magic bytes. The Symfony MimeTypes guesser
 * wraps finfo (via libmagic) and falls back to extension only when finfo
 * is unavailable, which is why strict mode fails closed when libmagic
 * is missing.
 *
 * This is necessary, not sufficient. Callers MUST keep their existing
 * defenses: path/traversal checks on $_FILES['name'], ZIP-content scans
 * before extraction, and XXE hardening for any XML parsed downstream.
 * MIME validation rejects the obvious mismatch; it does not replace
 * structural validation of the payload itself.
 */
class CactiMime {
	/** @var bool tracks whether the libmagic-missing log has fired this request */
	private static bool $logged_finfo_missing = false;

	/**
	 * Detect the canonical MIME type for a file by its content.
	 *
	 * @param  string $path absolute path to a readable file
	 * @return string detected MIME, or 'application/octet-stream' if unknown
	 */
	public static function detect(string $path) : string {
		self::warn_if_finfo_missing();

		$guess = MimeTypes::getDefault()->guessMimeType($path);

		if ($guess === null) {
			return 'application/octet-stream';
		}

		return $guess;
	}

	/**
	 * Allowlist for the package-import upload form. Centralised so the
	 * production caller and the test suite stay in lockstep. Cacti package
	 * files are gzipped XML opened via compress.zlib:// in import.php; raw
	 * XML and ZIP are also accepted by the legacy importer.
	 *
	 * @return string[]
	 */
	public static function packageImportMimes() : array {
		return [
			'application/zip',
			'application/gzip',
			'application/x-gzip',
			'application/xml',
			'application/x-xml',
			'text/xml',
		];
	}

	/**
	 * Validate that $path's content-derived MIME is on the allowlist.
	 *
	 * Default is strict (fail closed when finfo/libmagic is unavailable). Callers
	 * that want extension-only fallback must pass false explicitly.
	 *
	 * @param  string   $path          absolute path to a readable file
	 * @param  string[] $allowed_mimes case-sensitive MIME allowlist
	 * @param  bool     $strict        when true (default), fail closed if finfo_open is unavailable
	 * @return bool     true iff detected MIME is in $allowed_mimes
	 */
	public static function validate(string $path, array $allowed_mimes, bool $strict = true) : bool {
		if ($strict && !function_exists('finfo_open')) {
			self::warn_if_finfo_missing();

			return false;
		}

		$detected = self::detect($path);

		return in_array($detected, $allowed_mimes, true);
	}

	/**
	 * Log once per request when libmagic/finfo is unavailable. Without it,
	 * the Symfony guesser falls back to extension-only matching and content
	 * validation is effectively disabled.
	 */
	private static function warn_if_finfo_missing() : void {
		if (self::$logged_finfo_missing) {
			return;
		}

		if (function_exists('finfo_open')) {
			return;
		}

		self::$logged_finfo_missing = true;

		if (function_exists('cacti_log')) {
			cacti_log('WARNING: finfo (libmagic) extension is unavailable; MIME detection falls back to file extension only.', false, 'SYSTEM');
		}
	}
}
