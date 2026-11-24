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
  | Cacti: The Complete RRDTool-based Graphing Solution                     |
  +-------------------------------------------------------------------------+
  | This code is designed, written, and maintained by the Cacti Group. See  |
  | about.php and/or the AUTHORS file for specific developer information.   |
  +-------------------------------------------------------------------------+
  | http://www.cacti.net/                                                   |
  +-------------------------------------------------------------------------+
*/

/* maintenance script for theme developers, command line only */
if (PHP_SAPI !== 'cli') {
	http_response_code(404);

	exit;
}

/**
 * Handles the file search. Used as part of Cacti's include functionality.
 *
 * @param string $folder The folder.
 * @param array $pattern_array The pattern array.
 *
 * @return array An array of results.
 */
function file_search($folder, $pattern_array) {
	$return = array();
	$iti = new RecursiveDirectoryIterator($folder);
	foreach(new RecursiveIteratorIterator($iti) as $file){
		$fileParts = explode('.', $file);
		if (in_array(strtolower(array_pop($fileParts)), $pattern_array)){
			$return[] = $file;
		}
	}
	return $return;
}

/**
 * Handles the file hash. Used as part of Cacti's include functionality.
 *
 * @param array $match The match.
 *
 * @return string The resulting string.
 */
function file_hash($match) {
	global $cssFile;
	$md5File = dirname($cssFile) . '/' . $match[2];
	$md5Hash = is_file($md5File) ? md5_file($md5File) : false;

	if ($md5Hash === false) {
		fwrite(STDERR, 'ERROR: unable to hash ' . $md5File . ' imported by ' . $cssFile . PHP_EOL);

		exit(1);
	}

	$result  = $match[1] . $match[2] . '?' . $md5Hash . $match[4];

	return $result;
}

/**
 * Handles the file update. Used as part of Cacti's include functionality.
 *
 * @param string $cssFile The cssfile.
 *
 * @return void No value is returned.
 */
function file_update($cssFile) {
	$fileContents = file($cssFile);
	$fileUpdated  = preg_replace_callback_array(
		[
			'/(@import url\(\')([^?]*)(\?.*)*(\'\))/' => 'file_hash',
			'/(@import url\(")([^?]*)(\?.*)*("\))/' => 'file_hash',
		], $fileContents
	);

	if ($fileContents != $fileUpdated) {
		echo "file_update(" . $cssFile . ")\n";

		/* write a complete copy next to the stylesheet and swap it in, so it is never served half written */
		$tempFile = $cssFile . '.' . getmypid() . '.tmp';

		if (file_put_contents($tempFile, $fileUpdated) === false ||
			!chmod($tempFile, fileperms($cssFile) & 0777) ||
			!rename($tempFile, $cssFile)) {
			if (file_exists($tempFile)) {
				unlink($tempFile);
			}

			fwrite(STDERR, 'ERROR: unable to update ' . $cssFile . PHP_EOL);

			exit(1);
		}
	}
}

global $cssFile;
$cssFiles = file_search(__DIR__ . '/css', ['css']);
$cssFiles[] = __DIR__ . '/main.css';
$cssFiles[] = __DIR__ . '/billboard.midwinter.css';

foreach ($cssFiles as $cssFile) {
	file_update($cssFile);
}

echo PHP_EOL;