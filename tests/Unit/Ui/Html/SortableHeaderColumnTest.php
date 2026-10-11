<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

require_once dirname(__DIR__, 4) . '/lib/functions.php';
require_once dirname(__DIR__, 4) . '/lib/html_utility.php';
require_once dirname(__DIR__, 4) . '/lib/html.php';

test('sortable header columns preserve named and legacy metadata', function (array $metadata, string $column,
	string $sort_column, string $sort_direction, array $orders, string $primary, string $fallback, array $expected) {
	expect(html_header_sort_column($metadata, $column, $sort_column, $sort_direction, $orders, $primary, $fallback))
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
	global $_CACTI_REQUEST, $config;
	$saved = [$_SERVER, $_GET, $_POST, $_REQUEST, $_CACTI_REQUEST ?? [], $_SESSION ?? [], $config ?? []];

	try {
		$_SERVER['SCRIPT_NAME'] = '/headers.php';
		$config                 = ['is_web' => false, 'config_options_array' => ['log_validation' => '']];
		set_request_var('action', 'edit');
		set_request_var('tab', 'details');
		set_request_var('sort_column', 'name');
		set_request_var('sort_direction', 'ASC');
		$_SESSION['sort_data']['2_headers_edit_details'] = ['other' => 'DESC', 'name' => 'ASC'];
		expect(html_header_sort_order(2))->toBe(['other' => 'DESC', 'name' => 'ASC']);
		expect(html_header_sort_order(3))->toBe(['name' => 'ASC']);
	} finally {
		[$_SERVER, $_GET, $_POST, $_REQUEST, $_CACTI_REQUEST, $_SESSION, $config] = $saved;
	}
});

test('both sortable renderers retain legacy icons, escaping and independent counters', function () {
	global $_CACTI_REQUEST, $config;
	$saved        = [$_SERVER, $_GET, $_POST, $_REQUEST, $_CACTI_REQUEST ?? [], $_SESSION ?? [], $config ?? []];
	$buffer_level = ob_get_level();

	try {
		$config = ['is_web' => false, 'config_options_array' => ['log_validation' => '', 'i18n_language_support' => '0']];
		require_once dirname(__DIR__, 4) . '/include/global_constants.php';
		require_once dirname(__DIR__, 4) . '/include/global_languages.php';
		$_SERVER['SCRIPT_NAME'] = '/headers.php';
		set_request_var('action', 'edit');
		set_request_var('tab', 'details');
		set_request_var('sort_column', 'name');
		set_request_var('sort_direction', 'ASC');
		$_SESSION = [];
		$headers  = [
			'name'        => ['display' => 'Name', 'tip' => 'Quotes " &', 'nohide' => false],
			'count'       => ['Count', 'DESC'],
			'nosort_note' => ['display' => 'Note']
		];

		ob_start();
		html_header_sort($headers, 'name', 'ASC', 2, '/headers.php');
		$plain = ob_get_clean();
		ob_start();
		html_header_sort_checkbox($headers, 'name', 'ASC', true, '/headers.php', '', 'rows');
		$checkbox = ob_get_clean();

		foreach ([$plain, $checkbox] as $html) {
			expect($html)->toContain('fa fa-sort-up', 'fa fa-sort', "sort-direction='DESC'", 'primarySort', 'nohide', 'Quotes &quot; &amp;');
		}
		expect($checkbox)->toContain("id='selectall'", "data-prefix='rows'", "name='rows' method='post'");
		expect($_SESSION['valid_sort_columns']['0_headers_edit_details'])->toBe(['name', 'count']);

		$_SESSION['sort_data']['1_headers_edit_details'] = ['count' => 'DESC', 'name' => 'ASC'];
		ob_start();
		html_header_sort($headers, 'count', 'DESC', 2, '/headers.php');
		$second = ob_get_clean();
		expect($second)->toContain('fa fa-sort-down', 'primarySort', 'secondarySort', "sort-column='count' sort-direction='ASC'");
		expect($_SESSION['valid_sort_columns']['1_headers_edit_details'])->toBe(['name', 'count']);
	} finally {
		while (ob_get_level() > $buffer_level) {
			ob_end_clean();
		}
		[$_SERVER, $_GET, $_POST, $_REQUEST, $_CACTI_REQUEST, $_SESSION, $config] = $saved;
	}
});
