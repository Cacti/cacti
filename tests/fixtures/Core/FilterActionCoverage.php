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

require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 3) . '/include/global.php';

/**
 * Rebuild the URL a generated filter function requests, reading each field
 * control the way the browser does, so the test checks the decoded result
 * rather than the escaping used to embed it.
 */
function filter_action_url(string $script, string $function, array $values) : string {
	if (!preg_match('/function ' . $function . '\(\) \{\s*strURL = (.*?);?\n/', $script, $match)) {
		throw new RuntimeException("The $function URL is missing.");
	}
	$url = '';

	foreach (explode(' + ', $match[1]) as $term) {
		if (preg_match('/^\$\(\'#(\w+)\'\)\.is\(\':checked\'\)$/', $term, $field)) {
			$url .= $values[$field[1]] === 'on' ? 'true' : 'false';
		} elseif (preg_match('/^\$\(\'#(\w+)\'\)\.val\(\)$/', $term, $field)) {
			$url .= $values[$field[1]];
		} elseif (preg_match('/^base64_encode\(\$\(\'#(\w+)\'\)\.val\(\)\)$/', $term, $field)) {
			$url .= base64_encode($values[$field[1]]);
		} else {
			$literal = json_decode($term, false, 512, JSON_THROW_ON_ERROR);

			if (!is_string($literal)) {
				throw new RuntimeException("The $function URL has an unexpected term.");
			}
			$url .= $literal;
		}
	}

	return $url;
}

$cases = 0;

foreach (['legacy', 'modern'] as $format) {
	foreach (["graphs.php?filter=Bob's switch", 'graphs.php?filter=</script><em>Literal</em>&other=&quot;', 'graphs.php'] as $action) {
		foreach ([[], ['rows' => [[]]], ['rows' => [[
			'enabled' => ['method' => 'filter_checkbox', 'friendly_name' => 'Enabled', 'value' => 'on'],
			'filter'  => ['method' => 'textbox', 'friendly_name' => 'Search', 'value' => 'hello', 'max_length' => '120'],
			'rfilter' => ['method' => 'textbox', 'friendly_name' => 'Regular expression', 'value' => 'hello', 'max_length' => '120']
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
			$prefix = $action . (str_contains($action, '?') ? '&' : '?');
			$values = array_map(static fn (array $field) : string => $field['value'], $fields['rows'][0] ?? []);
			$script = implode("\n", $scripts);

			if (filter_action_url($script, 'applyFilter', $values) !== $prefix . ($values === [] ? '' : 'enabled=true&filter=hello&rfilter=aGVsbG8=')
				|| filter_action_url($script, 'clearFilter', $values) !== $prefix . 'clear=true') {
				throw new RuntimeException('Filter action URLs changed.');
			}
			$cases++;
		}
	}
}
print json_encode(['passed' => $cases], JSON_THROW_ON_ERROR) . "\n";
