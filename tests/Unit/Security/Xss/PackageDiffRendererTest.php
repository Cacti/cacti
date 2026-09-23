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

use Jfcherng\Diff\Differ;
use Jfcherng\Diff\Factory\RendererFactory;

test('package inline diffs escape file content before adding markup', function () {
	$payload  = "</td></tr></table><script>alert('xss')</script>";
	$differ   = new Differ(['safe'], [$payload], ['ignoreWhitespace' => true, 'ignoreCase' => false]);
	$renderer = RendererFactory::make('Inline');
	$html     = $renderer->render($differ);

	expect($html)->toContain('&lt;script&gt;')
		->and($html)->toContain('&lt;/script&gt;')
		->and($html)->not->toContain('<script>')
		->and($html)->not->toContain('</table><script>');
});
