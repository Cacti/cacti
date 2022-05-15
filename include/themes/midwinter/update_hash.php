<?php

function update_hash($file) {
}

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
		[
			'/(@import url\(\')([^?]*)(\?.*)*(\'\))/' => 'file_hash',
			'/(@import url\(")([^?]*)(\?.*)*("\))/' => 'file_hash',
		], $fileContents
	);

	if ($fileContents != $fileUpdated) {
		echo "file_update(" . $cssFile . ")\n";
		file_put_contents($cssFile, $fileUpdated);
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