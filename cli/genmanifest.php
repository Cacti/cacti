#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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

require(__DIR__ . '/../include/cli_check.php');
require_once(CACTI_PATH_LIBRARY . '/xml.php');
require_once(CACTI_PATH_LIBRARY . '/import.php');

global $debug;

$parms = $_SERVER['argv'];

if (cacti_sizeof($parms)) {
	// Defaults
	$generate     = false;
	$list         = false;
	$debug        = false;
	$directory    = false;

	$shortopts = 'VvHh';

	$longopts = array(
		'directory::',
		'list',
		'generate',
		'debug',
		'version',
		'help'
	);

	$options = getopt($shortopts, $longopts);

	foreach($options as $arg => $value) {
		switch($arg) {
		case 'list':
			$list = true;

			break;
		case 'generate':
			$generate = true;

			break;
		case 'directory':
			$directory = $value;

			break;
		case 'debug':
			$debug = true;

			break;
		case 'version':
		case 'V':
		case 'v':
			display_version();
			exit;
		case 'help':
		case 'H':
		case 'h':
			display_help();
			exit(0);
		default:
			print "ERROR: Invalid Argument: ($arg)" . PHP_EOL . PHP_EOL;
			display_help();
			exit(1);
		}
	}
}

if (!$list && !$generate) {
	display_help();
	exit(0);
}

if ($directory === false) {
	print 'FATAL: The Package Directory is a mandatory option.' . PHP_EOL;
	display_help();
	exit(1);
}

if (!cacti_sizeof($directory)) {
	$directory = array($directory);
}

$manifest = array();
$keys     = array();
$keycnt   = 0;
$pkgcnt   = 0;

if (cacti_sizeof($directory)) {
	foreach($directory as $dir) {
		if (!is_dir($dir)) {
			print "FATAL: The Package Directory '$dir' must be exist and must contain packages. Skipping!" . PHP_EOL;
			continue;
		}

		if (!is_writable($dir)) {
			print "FATAL: The Package Directory '$dir' must be writable. Skipping!" . PHP_EOL;
			continue;
		}

		$files    = glob("$dir/*.xml.gz");
		$keyfound = false;

		pkg_debug('Processing files');
		if (cacti_sizeof($files)) {
			foreach($files as $file) {
				pkg_debug("Processing file: $file");

				if (is_readable($file)) {
					$filename = "compress.zlib://$file";
					$data = file_get_contents($filename);

					if ($data != '') {
						$xmldata = simplexml_load_string($data);
						$pkgdata = xml_to_array($xmldata);

						// Set the package name
						$name = $pkgdata['info']['name'];
						unset($pkgdata['info']['name']);

						$manifest[$pkgcnt] = array(
							'kind'       => 'Package',
							'name'       => $name,
							'filename'   => basename($file),
							'metadata'   => $pkgdata['info']
						);

						$pkgcnt++;

						if (isset($pkgdata['publickey'])) {
							$keys[$keycnt]['publickey'] = base64_decode($pkgdata['publickey']);
							$keyfound = true;
						}

						if (isset($pkgdata['publickeyname'])) {
							$keys[$keycnt]['publickeyname'] = $pkgdata['publickeyname'];
							$keyfound = true;
						}

						if ($keyfound) {
							$keycnt++;
						}
					}
				}
			}
		}

		if ($list) {
			print '-----------------------------------------------------------' . PHP_EOL;
			print 'Outputting Directory Manifest' . PHP_EOL;
			print '-----------------------------------------------------------' . PHP_EOL;
			print json_encode($manifest, JSON_PRETTY_PRINT) . PHP_EOL;

			print '-----------------------------------------------------------' . PHP_EOL;
			print 'Outputting Public Key Information' . PHP_EOL;
			print '-----------------------------------------------------------' . PHP_EOL;

			if (cacti_sizeof($keys)) {
				print @json_encode(array_unique($keys), JSON_PRETTY_PRINT) . PHP_EOL;
			} else {
				print "WARNING: Your packages contained no public keys.  Consider repackaging" . PHP_EOL;
			}
			print '-----------------------------------------------------------' . PHP_EOL;
		} else {
			$package_manifest = array();
			$package_manifest['manifest'] = $manifest;
			$package_manifest['keys']     = @array_unique($keys);
			file_put_contents("$dir/package.manifest", json_encode($package_manifest, JSON_PRETTY_PRINT));
			print "Manifest package.manifest written to $dir/package.manifest" . PHP_EOL;
		}
	}
}

function pkg_debug($string) {
	global $debug;

	if ($debug) {
		print date('H:i:s') . ' ' . trim($string) . PHP_EOL;
	}
}

/**
 * display_version - displays version information
 *
 * @return (void)
 */
function display_version() {
	if (defined('CACTI_VERSION')) {
		$version = CACTI_VERSION;
	} else {
		$version = get_cacti_version();
	}

	print "Cacti Package Genkey Tool, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - displays help information
 *
 * @return (void)
 */
function display_help() {
	display_version();

	print PHP_EOL;
	print 'usage: genmanifest.php [options]' . PHP_EOL . PHP_EOL;
	print 'This script generates a Package Authors manifest.properties file based upon a directory' . PHP_EOL;
	print 'that contains a list of Package files.' . PHP_EOL . PHP_EOL;

	print 'Options:' . PHP_EOL;
	print '  --list            List the values for the manifest file instead of generating.' . PHP_EOL;
	print '  --generate        Create the Author manifest.properties file.' . PHP_EOL . PHP_EOL;

	print 'Required:' . PHP_EOL;
	print '  --directory=path  Path to a writable directory containing packages.' . PHP_EOL;
}

