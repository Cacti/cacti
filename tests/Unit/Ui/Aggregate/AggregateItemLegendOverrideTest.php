<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiTests\AggregateItemLegend;
require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';
\cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/api_aggregate.php', 'aggregate_apply_item_legend', __NAMESPACE__);

test('aggregate item legend overrides accept empty text without changing bindings', function () {
	$item = ['text_format' => 'Traffic', 'legend' => 'Bytes', 'task_item_id' => 42, 'sequence' => 4];
	expect(aggregate_apply_item_legend($item, ['t_text_format' => 'on', 'text_format' => '', 'task_item_id' => 99]))
		->toBe(['text_format' => '', 'legend' => 'Bytes', 'task_item_id' => 42, 'sequence' => 4]);
});

test('disabled aggregate legend overrides inherit source text', function () {
	$item = ['text_format' => 'Traffic', 'legend' => 'Bytes'];
	expect(aggregate_apply_item_legend($item, ['t_text_format' => '', 'text_format' => 'Ignored', 't_legend' => '', 'legend' => 'Ignored']))->toBe($item);
});

test('aggregate export legends and text overrides are independent', function () {
	expect(aggregate_apply_item_legend(['text_format' => 'Traffic', 'legend' => 'Bytes'], ['t_legend' => 'on', 'legend' => 'Custom']))
		->toBe(['text_format' => 'Traffic', 'legend' => 'Custom']);
});

test('aggregate legend data stays literal until the output boundary', function () {
	$value = '<img src=x onerror=alert(1)> & "quotes"';
	expect(aggregate_apply_item_legend(['text_format' => 'Traffic'], ['t_text_format' => 'on', 'text_format' => $value]))->toBe(['text_format' => $value]);
});
