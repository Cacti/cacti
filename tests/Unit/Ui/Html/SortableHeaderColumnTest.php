<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

require_once dirname(__DIR__, 4) . '/lib/html.php';

test('sortable header columns preserve named and legacy metadata', function (array $metadata, string $column,
	string $sort_column, string $sort_direction, array $orders, string $primary, string $fallback, array $expected) {
	expect(html_header_sort_column($metadata, $column, $sort_column, $sort_direction, $orders, $primary, $fallback))
		->toBe($expected);
})->with([
	'active ascending'                         => [['display' => 'Name'], 'name', 'name', 'ASC', [], 'name', 'neutral', ['Name', 'ti ti-caret-up-filled', 'DESC', 'left', '', '', 'primarySort']],
	'active descending secondary'              => [['display' => 'Name'], 'name', 'name', 'DESC', [], 'other', 'neutral', ['Name', 'ti ti-caret-down-filled', 'ASC', 'left', '', '', 'secondarySort']],
	'unsorted named defaults'                  => [['display' => 'Name'], 'name', 'other', 'ASC', [], 'other', 'neutral', ['Name', 'neutral', 'ASC', 'left', '', '', '']],
	'unsorted explicit metadata'               => [['display' => 'Name', 'sort' => 'DESC', 'align' => 'right', 'tip' => "Quoted ' tip", 'nohide' => true], 'name', 'other', 'ASC', [], 'other', 'neutral', ['Name', 'neutral', 'DESC', 'right', "Quoted ' tip", 'nohide', '']],
	'nohide presence is preserved'             => [['display' => 'Name', 'nohide' => false], 'name', 'other', 'ASC', [], '', 'neutral', ['Name', 'neutral', 'ASC', 'left', '', 'nohide', '']],
	'nohide null is absent'                    => [['display' => 'Name', 'nohide' => null], 'name', 'other', 'ASC', [], '', 'neutral', ['Name', 'neutral', 'ASC', 'left', '', '', '']],
	'stored ascending primary'                 => [['display' => 'Name'], 'name', 'other', 'ASC', ['name' => 'ASC'], 'name', 'neutral', ['Name', 'ti ti-caret-up-filled', 'DESC', 'left', '', '', 'primarySort']],
	'stored descending secondary'              => [['display' => 'Name'], 'name', 'other', 'ASC', ['name' => 'DESC'], 'other', 'neutral', ['Name', 'ti ti-caret-down-filled', 'ASC', 'left', '', '', 'secondarySort']],
	'unknown stored direction'                 => [['display' => 'Name'], 'name', 'other', 'ASC', ['name' => 'unknown'], 'name', 'neutral', ['Name', 'neutral', 'DESC', 'left', '', '', 'primarySort']],
	'legacy ascending primary'                 => [['Name', 'DESC'], 'name', 'name', 'ASC', [], 'name', 'neutral', ['Name', 'ti ti-caret-up-filled', 'DESC', 'left', '', '', 'primarySort']],
	'legacy descending secondary'              => [['Name', 'ASC'], 'name', 'name', 'DESC', [], 'other', 'neutral', ['Name', 'ti ti-caret-down-filled', 'ASC', 'left', '', '', 'secondarySort']],
	'legacy unsorted'                          => [['Name', 'DESC'], 'name', 'other', 'ASC', [], '', 'neutral', ['Name', 'neutral', 'DESC', 'left', '', '', '']],
	'legacy stored ascending'                  => [['Name', 'DESC'], 'name', 'other', 'ASC', ['name' => 'ASC'], 'name', 'neutral', ['Name', 'ti ti-caret-up-filled', 'DESC', 'left', '', '', 'primarySort']],
	'legacy stored descending'                 => [['Name', 'ASC'], 'name', 'other', 'ASC', ['name' => 'DESC'], 'other', 'neutral', ['Name', 'ti ti-caret-down-filled', 'ASC', 'left', '', '', 'secondarySort']],
	'legacy unknown stored direction'          => [['Name', 'ASC'], 'name', 'other', 'ASC', ['name' => 'unknown'], '', 'neutral', ['Name', 'neutral', 'DESC', 'left', '', '', 'secondarySort']],
	'checkbox fallback'                        => [['display' => 'Name'], 'name', 'other', 'ASC', [], '', 'ti ti-caret-down-filled', ['Name', 'ti ti-caret-down-filled', 'ASC', 'left', '', '', '']],
	'plain fallback'                           => [['display' => 'Name'], 'name', 'other', 'ASC', [], '', 'ti ti-caret-up-down-filled', ['Name', 'ti ti-caret-up-down-filled', 'ASC', 'left', '', '', '']],
	'lowercase icon retains historic reversal' => [['display' => 'Name'], 'name', 'name', 'asc', [], 'name', 'neutral', ['Name', 'ti ti-caret-up-filled', 'ASC', 'left', '', '', 'primarySort']],
	'null display stays null'                  => [['display' => null], 'name', 'other', 'ASC', [], '', 'neutral', [null, 'neutral', 'ASC', 'left', '', '', '']],
]);

test('sortable header history stays scoped to renderer count, action and tab', function () {
	global $_CACTI_REQUEST;
	$saved = [$_SERVER, $_GET, $_POST, $_REQUEST, $_CACTI_REQUEST ?? [], $_SESSION ?? []];

	try {
		$_SERVER['SCRIPT_NAME'] = '/headers.php';
		srv('action', 'edit');
		srv('tab', 'details');
		srv('sort_column', 'name');
		srv('sort_direction', 'ASC');
		$_SESSION['sort_data']['2_headers_edit_details'] = ['other' => 'DESC', 'name' => 'ASC'];
		expect(html_header_sort_order(2))->toBe(['other' => 'DESC', 'name' => 'ASC']);
		expect(html_header_sort_order(3))->toBe(['name' => 'ASC']);
	} finally {
		[$_SERVER, $_GET, $_POST, $_REQUEST, $_CACTI_REQUEST, $_SESSION] = $saved;
	}
});
