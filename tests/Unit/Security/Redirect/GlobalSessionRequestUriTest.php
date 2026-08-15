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

test('global session handles a missing request URI consistently', function () {
	$source = file_get_contents(dirname(__DIR__, 4) . '/include/global_session.php');

	expect($source)->not->toBeFalse()
		->and($source)->toContain("\$request_uri = \$_SERVER['REQUEST_URI'] ?? '';")
		->and(substr_count($source, "\$_SERVER['REQUEST_URI']"))->toBe(1)
		->and(substr_count($source, 'sanitize_uri($request_uri)'))->toBe(4)
		->and(substr_count($source, 'sanitize_uri(appendHeaderSuppression($request_uri))'))->toBe(1)
		->and($source)->not->toContain("\$_SERVER['REQUEST_URL']")
		->and($source)->toContain("strpos(\$request_uri, 'index.php') !== false");
});
