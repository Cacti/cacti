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
 * Materialize binary MIME fixtures into a per-process temp directory.
 *
 * Binary fixtures (zip, gzip, opaque) are generated rather than checked
 * in so the repo stays text-only and so we do not depend on a specific
 * libmagic database. Returns an associative array of fixture paths.
 *
 * @return array{zip:string,xml:string,gz:string,unknown:string}
 */
function cacti_mime_build_fixtures() : array {
	static $cache = null;

	if ($cache !== null) {
		return $cache;
	}

	$dir = sys_get_temp_dir() . '/cacti-mime-fixtures-' . getmypid();

	if (!is_dir($dir)) {
		mkdir($dir, 0700, true);
	}

	// Minimal valid ZIP: a single stored entry "a.txt" containing "hi".
	// Hand-built so the file starts with the local-file-header magic
	// PK\x03\x04 that libmagic recognises as application/zip.
	$content      = 'hi';
	$crc          = crc32($content);
	$filename     = 'a.txt';
	$filename_len = strlen($filename);
	$content_len  = strlen($content);

	$local_header = "PK\x03\x04"
		. pack('v', 20)               // version needed
		. pack('v', 0)                // flags
		. pack('v', 0)                // method = stored
		. pack('v', 0)                // mod time
		. pack('v', 0)                // mod date
		. pack('V', $crc)
		. pack('V', $content_len)     // compressed size
		. pack('V', $content_len)     // uncompressed size
		. pack('v', $filename_len)
		. pack('v', 0)                // extra length
		. $filename
		. $content;

	$central_dir = "PK\x01\x02"
		. pack('v', 20)               // version made by
		. pack('v', 20)                // version needed
		. pack('v', 0)
		. pack('v', 0)
		. pack('v', 0)
		. pack('v', 0)
		. pack('V', $crc)
		. pack('V', $content_len)
		. pack('V', $content_len)
		. pack('v', $filename_len)
		. pack('v', 0)
		. pack('v', 0)
		. pack('v', 0)
		. pack('v', 0)
		. pack('V', 0)
		. pack('V', 0)                // local header offset
		. $filename;

	$eocd = "PK\x05\x06"
		. pack('v', 0)
		. pack('v', 0)
		. pack('v', 1)
		. pack('v', 1)
		. pack('V', strlen($central_dir))
		. pack('V', strlen($local_header))
		. pack('v', 0);

	$zip_path = $dir . '/valid.zip';
	file_put_contents($zip_path, $local_header . $central_dir . $eocd);

	// Gzip-compressed XML matches the Cacti package upload format
	// (the import form's accept attribute is .xml.gz).
	$gz_path = $dir . '/valid.xml.gz';
	file_put_contents($gz_path, gzencode('<?xml version="1.0"?><root/>'));

	// Opaque payload: 64 bytes from a deterministic seed so the test is
	// reproducible. Random bytes that do not start with any known magic
	// number cause libmagic to fail to identify a content type.
	$unknown_path = $dir . '/unknown.bin';
	$opaque       = '';

	for ($i = 0; $i < 64; $i++) {
		$opaque .= chr(($i * 17 + 11) & 0xFF);
	}

	file_put_contents($unknown_path, $opaque);

	$cache = [
		'zip'     => $zip_path,
		'xml'     => __DIR__ . '/valid.xml',
		'gz'      => $gz_path,
		'unknown' => $unknown_path,
	];

	return $cache;
}
