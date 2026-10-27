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

require_once dirname(__DIR__, 2) . '/Helpers/RequestCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global.php';

$cases = [];

foreach (['legacy', 'modern'] as $format) {
	foreach (["graphs.php?filter=Bob's switch", 'graphs.php?filter=</script><em>Literal</em>&other=&quot;', 'graphs.php'] as $action) {
		foreach ([[], ['rows' => [[]]], ['rows' => [[
			'enabled' => ['method' => 'filter_checkbox', 'friendly_name' => 'Enabled', 'value' => 'on'],
			'filter'  => ['method' => 'textbox', 'friendly_name' => 'Search', 'value' => 'hello'],
			'rfilter' => ['method' => 'textbox', 'friendly_name' => 'Regular expression', 'value' => 'hello']
		]]]] as $fields) {
			$filter                 = (new ReflectionClass(CactiTableFilter::class))->newInstanceWithoutConstructor();
			$filter->form_action    = $action;
			$filter->form_id        = 'fixture_filter';
			$filter->filter_format  = $format;
			$filter->render_layouts = $format === 'modern';
			$filter->set_filter_array($fields);
			$html       = (new ReflectionMethod($filter, 'create_filter'))->invoke($filter);
			$javascript = (new ReflectionMethod($filter, 'create_javascript'))->invoke($filter);
			$document   = new DOMDocument();
			@$document->loadHTML('<?xml encoding="UTF-8">' . $html . $javascript);

			if (isset($fields['rows']) && $document->getElementsByTagName('form')->item(0)->getAttribute('action') !== $action) {
				throw new RuntimeException('Filter action changed in its HTML attribute.');
			}
			$scripts = [];

			foreach ($document->getElementsByTagName('script') as $script) {
				$scripts[] = $script->textContent;
			}

			if ($document->getElementsByTagName('em')->length !== 0 || count($scripts) !== ($format === 'modern' ? 2 : 1)) {
				throw new RuntimeException('Filter action escaped its output context.');
			}
			$prefix  = $action . (str_contains($action, '?') ? '&' : '?');
			$cases[] = ['scripts' => $scripts, 'apply' => $prefix . (empty($fields['rows'][0]) ? '' : 'enabled=true&filter=hello&rfilter=aGVsbG8='), 'clear' => $prefix . 'clear=true'];
		}
	}
}
print json_encode($cases, JSON_THROW_ON_ERROR);
