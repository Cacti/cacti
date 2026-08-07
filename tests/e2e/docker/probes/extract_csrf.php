<?php
/*
 +-------------------------------------------------------------------------+
 | Docker e2e helper: extract csrf-magic hidden token from an HTML file.    |
 +-------------------------------------------------------------------------+
*/

if ($argc !== 2) {
	fwrite(STDERR, "usage: php extract_csrf.php <html-file>\n");
	exit(2);
}

$html = file_get_contents($argv[1]);
if ($html === false) {
	fwrite(STDERR, "unable to read HTML file\n");
	exit(2);
}

if (!preg_match_all('/<input\b[^>]*>/i', $html, $inputs)) {
	exit(1);
}

foreach ($inputs[0] as $input) {
	if (!preg_match('/\bname\s*=\s*(["\']?)__csrf_magic\1/i', $input)) {
		continue;
	}

	if (preg_match('/\bvalue\s*=\s*(["\'])(.*?)\1/i', $input, $value)) {
		echo html_entity_decode($value[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
		exit(0);
	}

	if (preg_match('/\bvalue\s*=\s*([^\s>]+)/i', $input, $value)) {
		echo html_entity_decode($value[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
		exit(0);
	}
}

exit(1);
