<?php
declare(strict_types = 1);
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

require_once dirname(__DIR__, 3) . '/Helpers/CactiStubs.php';
require_once CACTI_PATH_INCLUDE . '/global.php';

test('filter actions preserve text in form attributes and JavaScript (#7465)', function (string $format, string $action) {
	$filter                 = (new ReflectionClass(CactiTableFilter::class))->newInstanceWithoutConstructor();
	$filter->form_action    = $action;
	$filter->form_id        = 'fixture_filter';
	$filter->filter_format  = $format;
	$filter->render_layouts = $format === 'modern';
	$filter->set_filter_array(['rows' => [[]]]);
	$html       = (new ReflectionMethod($filter, 'create_filter'))->invoke($filter);
	$javascript = (new ReflectionMethod($filter, 'create_javascript'))->invoke($filter);
	preg_match('/function applyFilter\(\) \{\s*strURL = (.*);/', $javascript, $matches);
	expect(json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR))->toBe($action . (str_contains($action, '?') ? '&' : '?'));
	$document = new DOMDocument();
	@$document->loadHTML('<?xml encoding="UTF-8">' . $html . $javascript);

	expect($document->getElementsByTagName('form')->item(0)->getAttribute('action'))->toBe($action)
		->and($document->getElementsByTagName('em')->length)->toBe(0)
		->and($document->getElementsByTagName('script')->length)->toBe($format === 'modern' ? 2 : 1);
})->with(['legacy', 'modern'])->with([
	'graphs.php',
	"graphs.php?filter=Bob's switch&rows=20",
	'graphs.php?filter=<em>Literal</em>&other=&quot;',
]);
