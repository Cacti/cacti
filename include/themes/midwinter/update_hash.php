<?php
/*
  +-------------------------------------------------------------------------+
  | Copyright (C) 2004-2022 The Cacti Group                                 |
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

function update_hash($file) {
}

function file_search($folder, $pattern_array) {
	$return = array();
	$iti    = new RecursiveDirectoryIterator($folder);

	foreach (new RecursiveIteratorIterator($iti) as $file) {
		$fileParts = explode('.', $file);

		if (in_array(strtolower(array_pop($fileParts)), $pattern_array, true)) {
			$return[] = $file;
		}
	}

	return $return;
}

function file_hash($match) {
	global $cssFile;
	$md5File = dirname($cssFile) . '/' . $match[2];
	$md5Real = realpath($md5File);
	$md5Hash = md5_file($md5File);
	$result  = $match[1] . $match[2] . '?' . $md5Hash . $match[4];

	return $result;
}

function file_update($cssFile) {
	$fileContents = file($cssFile);
	$fileUpdated  = preg_replace_callback_array(
		array(
			'/(@import url\(\')([^?]*)(\?.*)*(\'\))/' => 'file_hash',
			'/(@import url\(")([^?]*)(\?.*)*("\))/'   => 'file_hash',
		), $fileContents
	);

	if ($fileContents != $fileUpdated) {
		print 'file_update(' . $cssFile . ")\n";
		file_put_contents($cssFile, $fileUpdated);
	}
}

global $cssFile;
$cssFiles   = file_search(__DIR__ . '/css', array('css'));
$cssFiles[] = __DIR__ . '/main.css';
$cssFiles[] = __DIR__ . '/billboard.midwinter.css';

foreach ($cssFiles as $cssFile) {
	file_update($cssFile);
}

print PHP_EOL;
