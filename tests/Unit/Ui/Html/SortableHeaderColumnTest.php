<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Load the actual libraries in a child: legacy suites declare core-function stubs during collection.
function sortable_header_probe(string $scenario, array $arguments = []) : array {
	$fixture = dirname(__DIR__, 3) . '/fixtures/SortableHeaderProbe.php';
	$process = proc_open([PHP_BINARY, $fixture, $scenario, json_encode($arguments, JSON_THROW_ON_ERROR)],
		[1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
	expect(is_resource($process))->toBeTrue();
	$output = stream_get_contents($pipes[1]);
	$errors = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	expect(proc_close($process))->toBe(0, $errors);

	return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
}

test('sortable header columns preserve named and legacy metadata', function (array $metadata, string $column,
	string $sort_column, string $sort_direction, array $orders, string $primary, string $fallback, array $expected) {
	expect(sortable_header_probe('column', [$metadata, $column, $sort_column, $sort_direction, $orders, $primary, $fallback]))
		->toBe($expected);
})->with([
	'active ascending'                         => [['display' => 'Name'], 'name', 'name', 'ASC', [], 'name', 'neutral', ['Name', 'fa fa-sort-up', 'DESC', 'left', '', '', 'primarySort']],
	'active descending secondary'              => [['display' => 'Name'], 'name', 'name', 'DESC', [], 'other', 'neutral', ['Name', 'fa fa-sort-down', 'ASC', 'left', '', '', 'secondarySort']],
	'unsorted named defaults'                  => [['display' => 'Name'], 'name', 'other', 'ASC', [], 'other', 'neutral', ['Name', 'neutral', 'ASC', 'left', '', '', '']],
	'unsorted explicit metadata'               => [['display' => 'Name', 'sort' => 'DESC', 'align' => 'right', 'tip' => "Quoted ' tip", 'nohide' => true], 'name', 'other', 'ASC', [], 'other', 'neutral', ['Name', 'neutral', 'DESC', 'right', "Quoted ' tip", 'nohide', '']],
	'nohide presence is preserved'             => [['display' => 'Name', 'nohide' => false], 'name', 'other', 'ASC', [], '', 'neutral', ['Name', 'neutral', 'ASC', 'left', '', 'nohide', '']],
	'nohide null is absent'                    => [['display' => 'Name', 'nohide' => null], 'name', 'other', 'ASC', [], '', 'neutral', ['Name', 'neutral', 'ASC', 'left', '', '', '']],
	'stored ascending primary'                 => [['display' => 'Name'], 'name', 'other', 'ASC', ['name' => 'ASC'], 'name', 'neutral', ['Name', 'fa fa-sort-up', 'DESC', 'left', '', '', 'primarySort']],
	'stored descending secondary'              => [['display' => 'Name'], 'name', 'other', 'ASC', ['name' => 'DESC'], 'other', 'neutral', ['Name', 'fa fa-sort-down', 'ASC', 'left', '', '', 'secondarySort']],
	'unknown stored direction'                 => [['display' => 'Name'], 'name', 'other', 'ASC', ['name' => 'unknown'], 'name', 'neutral', ['Name', 'neutral', 'DESC', 'left', '', '', 'primarySort']],
	'legacy ascending primary'                 => [['Name', 'DESC'], 'name', 'name', 'ASC', [], 'name', 'neutral', ['Name', 'fa fa-sort-up', 'DESC', 'left', '', '', 'primarySort']],
	'legacy descending secondary'              => [['Name', 'ASC'], 'name', 'name', 'DESC', [], 'other', 'neutral', ['Name', 'fa fa-sort-down', 'ASC', 'left', '', '', 'secondarySort']],
	'legacy unsorted'                          => [['Name', 'DESC'], 'name', 'other', 'ASC', [], '', 'neutral', ['Name', 'neutral', 'DESC', 'left', '', '', '']],
	'legacy stored ascending'                  => [['Name', 'DESC'], 'name', 'other', 'ASC', ['name' => 'ASC'], 'name', 'neutral', ['Name', 'fa fa-sort-up', 'DESC', 'left', '', '', 'primarySort']],
	'legacy stored descending'                 => [['Name', 'ASC'], 'name', 'other', 'ASC', ['name' => 'DESC'], 'other', 'neutral', ['Name', 'fa fa-sort-down', 'ASC', 'left', '', '', 'secondarySort']],
	'legacy unknown stored direction'          => [['Name', 'ASC'], 'name', 'other', 'ASC', ['name' => 'unknown'], '', 'neutral', ['Name', 'neutral', 'DESC', 'left', '', '', 'secondarySort']],
	'checkbox fallback'                        => [['display' => 'Name'], 'name', 'other', 'ASC', [], '', 'fa fa-sort-down', ['Name', 'fa fa-sort-down', 'ASC', 'left', '', '', '']],
	'plain fallback'                           => [['display' => 'Name'], 'name', 'other', 'ASC', [], '', 'fa fa-sort', ['Name', 'fa fa-sort', 'ASC', 'left', '', '', '']],
	'lowercase icon retains historic reversal' => [['display' => 'Name'], 'name', 'name', 'asc', [], 'name', 'neutral', ['Name', 'fa fa-sort-up', 'ASC', 'left', '', '', 'primarySort']],
	'null display stays null'                  => [['display' => null], 'name', 'other', 'ASC', [], '', 'neutral', [null, 'neutral', 'ASC', 'left', '', '', '']],
]);

test('sortable header history stays scoped to renderer count, action and tab', function () {
	$result = sortable_header_probe('history');
	expect($result[0])->toBe(['other' => 'DESC', 'name' => 'ASC']);
	expect($result[1])->toBe(['name' => 'ASC']);
});

test('both sortable renderers retain legacy icons, escaping and independent counters', function () {
	[$plain, $checkbox, $second, $columns] = sortable_header_probe('renderers');

	foreach ([$plain, $checkbox] as $html) {
		expect($html)->toContain('fa fa-sort-up', 'fa fa-sort', "sort-direction='DESC'", 'primarySort', 'nohide', 'Quotes &quot; &amp;');
	}
	expect($checkbox)->toContain("id='selectall'", "data-prefix='rows'", "name='rows' method='post'");
	expect($columns['0_headers_edit_details'])->toBe(['name', 'count']);
	expect($second)->toContain('fa fa-sort-down', 'primarySort', 'secondarySort', "sort-column='count' sort-direction='ASC'");
	expect($columns['1_headers_edit_details'])->toBe(['name', 'count']);
});
