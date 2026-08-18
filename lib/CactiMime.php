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

use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Mime\MimeTypesInterface;

/** Content-derived MIME inspection with an injectable detector. */
final class CactiMimeDetector {
	public function __construct(private MimeTypesInterface $mimeTypes) {
	}

	public function detect(string $path) : ?string {
		if (!is_file($path) || !is_readable($path) || !$this->mimeTypes->isGuesserSupported()) {
			return null;
		}

		return $this->mimeTypes->guessMimeType($path);
	}

	/** @param string[] $allowedMimes */
	public function validate(string $path, array $allowedMimes) : bool {
		$detected = $this->detect($path);

		return $detected !== null && in_array($detected, $allowedMimes, true);
	}
}

/** Narrow compatibility facade for procedural upload handlers. */
final class CactiMime {
	private static ?CactiMimeDetector $detector = null;

	/** @return string[] */
	public static function packageImportMimes() : array {
		return [
			'application/gzip',
			'application/x-gzip',
			'application/xml',
			'application/x-xml',
			'text/xml',
		];
	}

	/** @param string[] $allowedMimes */
	public static function validate(string $path, array $allowedMimes) : bool {
		if (!function_exists('finfo_open')) {
			cacti_log('Package upload MIME validation is unavailable because the PHP fileinfo extension is not loaded.', false, 'SECURITY');

			return false;
		}

		self::$detector ??= new CactiMimeDetector(MimeTypes::getDefault());

		return self::$detector->validate($path, $allowedMimes);
	}
}
