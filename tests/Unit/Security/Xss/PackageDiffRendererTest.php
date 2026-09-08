<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 4) . '/include/vendor/phpdiff/Diff.php';
require_once dirname(__DIR__, 4) . '/include/vendor/phpdiff/Renderer/Html/Inline.php';

test('package inline diffs escape file content before adding markup', function () {
	$payload  = "</td></tr></table><script>alert('xss')</script>";
	$diff     = new Diff(['safe'], [$payload]);
	$renderer = new Diff_Renderer_Html_Inline();
	$html     = $diff->render($renderer);

	expect($html)->toContain('&lt;script&gt;')
		->and($html)->toContain('&lt;/script&gt;')
		->and($html)->not->toContain('<script>')
		->and($html)->not->toContain('</table><script>');
});
