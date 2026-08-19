<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Materialize binary MIME fixtures into a per-process temp directory.
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

	$content      = 'hi';
	$crc          = crc32($content);
	$filename     = 'a.txt';
	$filename_len = strlen($filename);
	$content_len  = strlen($content);

	$local_header = "PK\x03\x04"
		. pack('v', 20)
		. pack('v', 0)
		. pack('v', 0)
		. pack('v', 0)
		. pack('v', 0)
		. pack('V', $crc)
		. pack('V', $content_len)
		. pack('V', $content_len)
		. pack('v', $filename_len)
		. pack('v', 0)
		. $filename
		. $content;

	$central_dir = "PK\x01\x02"
		. pack('v', 20)
		. pack('v', 20)
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
		. pack('V', 0)
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

	$gz_path = $dir . '/valid.xml.gz';
	file_put_contents($gz_path, gzencode('<?xml version="1.0"?><root/>'));

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
