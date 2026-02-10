#!/usr/bin/env php
<?php

chdir(__DIR__ . '/../../');

$files = glob('./resource/*/*.xml');

$errors = 0;

foreach ($files as $file) {
	libxml_use_internal_errors(true);

	$xml = simplexml_load_file($file);

	if ($xml === false) {
		$errors++;

		print "The Resource XML File: $file has Errors" . PHP_EOL;

		foreach (libxml_get_errors() as $error) {
			print 'Error: ' . $error->message;
		}

		libxml_clear_errors(); // Clear the error buffer
	} else {
		print "The Resource XML File: $file is well-formed." . PHP_EOL;
	}
}

exit($errors);
