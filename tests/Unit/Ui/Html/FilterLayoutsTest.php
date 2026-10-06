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

/*
 * Coverage for the saved filter-layout feature added to the CactiTableFilter
 * class (lib/html_filter.php) and the layouts.php management page. The pure
 * helpers are executed directly; the request/permission wiring is asserted by
 * scanning source, matching the database-free unit-test convention.
 */

$root = dirname(__DIR__, 4);

test('filter_layouts_page_key reduces a url to its page basename', function () {
	expect(filter_layouts_page_key('host.php?filter=x&rows=30'))->toBe('host.php');
	expect(filter_layouts_page_key('cdef.php'))->toBe('cdef.php');
	expect(filter_layouts_page_key('/var/www/host.php?a=b'))->toBe('host.php');
});

test('filter_layouts_valid_page accepts only bare same-site page basenames', function () {
	expect(filter_layouts_valid_page('host.php'))->toBeTrue();
	expect(filter_layouts_valid_page('graph_view.php'))->toBeTrue();

	// No query, scheme, host, traversal, or markup may name a page.
	expect(filter_layouts_valid_page('host.php?filter=a'))->toBeFalse();
	expect(filter_layouts_valid_page('http://evil.example/host.php'))->toBeFalse();
	expect(filter_layouts_valid_page('../host.php'))->toBeFalse();
	expect(filter_layouts_valid_page(''))->toBeFalse();
});

test('filter_layouts_build_document normalizes a filter url into a stored document', function () {
	$document = filter_layouts_build_document('host.php?rfilter=down&rows=30&filter_layout=5');

	expect($document)->toBeArray();
	expect($document['version'])->toBe(1);
	expect($document['page'])->toBe('host.php');
	// The re-selection marker is never persisted.
	expect($document['filter'])->toBe(['rfilter' => 'down', 'rows' => '30']);

	// Scheme, host, traversal, or markup payloads cannot name a page.
	expect(filter_layouts_build_document('http://evil.example/host.php'))->toBeFalse();
	expect(filter_layouts_build_document('javascript:alert(1)'))->toBeFalse();
	expect(filter_layouts_build_document('../host.php?x=1'))->toBeFalse();
});

test('filter_layouts_document_url round-trips a document back to a safe url', function () {
	$document = filter_layouts_build_document('host.php?rfilter=down&rows=30');

	expect(filter_layouts_document_url($document))->toBe('host.php?rfilter=down&rows=30');
	expect(filter_layouts_document_url(['version' => 1, 'page' => 'host.php', 'filter' => []]))->toBe('host.php');

	// A document that does not name a safe page yields no navigable url.
	expect(filter_layouts_document_url(['version' => 1, 'page' => '../evil.php', 'filter' => []]))->toBe('');
});

test('filter_layouts_decode rejects anything but a bounded version 1 document', function () {
	expect(filter_layouts_decode('{"version":1,"page":"host.php","filter":{"rows":"30"}}'))->toBeArray();

	expect(filter_layouts_decode(''))->toBeFalse();
	expect(filter_layouts_decode('not json'))->toBeFalse();
	expect(filter_layouts_decode('{"version":2,"page":"host.php"}'))->toBeFalse();
	expect(filter_layouts_decode('{"version":1,"page":"../evil.php"}'))->toBeFalse();
	expect(filter_layouts_decode('{"version":1,"page":"host.php","filter":"x"}'))->toBeFalse();
	expect(filter_layouts_decode('{"version":1,"page":"host.php","filter":{}}' . str_repeat(' ', 8192)))->toBeFalse();
});

test('preset pages disable layouts by default', function () {
	$presets = filter_layouts_preset_pages();

	expect($presets)->toBeArray();
	expect($presets)->toContain('cdef.php');
	expect($presets)->toContain('vdef.php');
	expect($presets)->toContain('color.php');
	expect($presets)->toContain('gprint_presets.php');
});

test('the filter class exposes a render_layouts toggle that preset pages clear', function () use ($root) {
	$src = file_get_contents($root . '/lib/html_filter.php');

	expect($src)->toContain('public bool   $render_layouts  = true;');
	expect($src)->toContain('in_array($page, filter_layouts_preset_pages(), true)');
	expect($src)->toContain('$this->render_layouts = false;');
	expect($src)->toContain('if ($this->render_layouts) {');
});

test('publishing and global management require the Settings/Utilities realm', function () use ($root) {
	$src = file_get_contents($root . '/lib/html_filter.php');

	// Realm 15 is Settings/Utilities.
	expect($src)->toContain('return is_realm_allowed(15);');
	expect($src)->toContain('function filter_layouts_publish(int $id) : bool {');
	expect($src)->toContain('if (!filter_layouts_can_manage_global()) {');
});

test('the layout request handler only acts on csrf-protected POST requests', function () use ($root) {
	$src = file_get_contents($root . '/lib/html_filter.php');

	expect($src)->toContain('function filter_layouts_handle_request() : void {');
	expect($src)->toContain("strtoupper(\$_SERVER['REQUEST_METHOD']) !== 'POST'");
	expect($src)->toContain("case 'layout_save':");
	expect($src)->toContain("case 'layout_publish':");
});

test('a per-user page filter format setting is registered and defaults to modern', function () use ($root) {
	$src = file_get_contents($root . '/include/global_settings.php');

	expect($src)->toContain("'page_filter_format' => [");
	expect($src)->toContain("'modern' => __('Modern')");
	expect($src)->toContain("'legacy' => __('Legacy')");
	expect($src)->toContain("'default'       => 'modern',");
});

test('filter_layouts_user_format defaults to modern without an authenticated user', function () {
	unset($_SESSION['sess_user_id']);

	expect(filter_layouts_user_format())->toBe('modern');
});

test('the filter class renders a legacy or modern layout from the user preference', function () use ($root) {
	$src = file_get_contents($root . '/lib/html_filter.php');

	expect($src)->toContain('public string $filter_format   = \'modern\';');
	expect($src)->toContain('$this->filter_format = filter_layouts_user_format();');
	expect($src)->toContain('private function use_modern_filter() : bool {');
	expect($src)->toContain('return $this->render_layouts && $this->filter_format === \'modern\';');

	// Modern short-circuits create_filter() before the inline rendering.
	expect($src)->toContain('if ($this->use_modern_filter()) {');
	expect($src)->toContain('print $this->create_modern_filter();');
});

test('field rendering is shared so legacy and the modern dialog stay in sync', function () use ($root) {
	$src = file_get_contents($root . '/lib/html_filter.php');

	expect($src)->toContain('private function emit_field(string $field_name, array $field_array) : string {');
	// Both the legacy loop and the modern dialog emit fields through the helper.
	expect(substr_count($src, 'print $this->emit_field($field_name, $field_array);'))->toBeGreaterThan(1);
});

test('the modern filter keeps time controls on the bar and fields in the dialog', function () use ($root) {
	$src = file_get_contents($root . '/lib/html_filter.php');

	expect($src)->toContain('private function field_is_bar(string $field_name, array $field_array) : bool {');
	expect($src)->toContain("\$field_name === 'refresh'");
	expect($src)->toContain("\$field_name === 'rows'");
	// Search is routed onto the bar (right of Rows) and applies on Enter.
	expect($src)->toContain("\$field_name === 'filter' || \$field_name === 'rfilter'");
	expect($src)->toContain('keydown.cactiSearch');
	// Bar-routed Search carries a persistent label even when a page omits friendly_name.
	expect($src)->toContain("\$field_array['friendly_name'] = __('Search');");
	// The row-count selector is always labeled Rows.
	expect($src)->toContain("\$field_array['friendly_name'] = __('Rows');");
	// Bar selectors are ordered Rows, Search, then Refresh and separated from the buttons.
	expect($src)->toContain("foreach (['rows', 'filter', 'rfilter', 'refresh'] as \$pref) {");
	expect($src)->toContain('$bar_fields = $ordered + $bar_fields;');
	expect($src)->toContain('// Separate the always-present Layouts selector (plus any Rows/Refresh) from the layout buttons.');
	// Two separators: selectors|buttons and buttons|page-actions.
	expect(substr_count($src, "<span class='barSep'></span>"))->toBeGreaterThan(1);
	expect($src)->toContain('cactiFilterEditDialog');
	expect($src)->toContain("\$this->layout_button('layout_edit',");
	expect($src)->toContain("\$this->layout_button('layout_saveas',");

	// The dialog renders one filter variable per row rather than mirroring the
	// inline filter's multi-field row grouping.
	expect($src)->toContain('$dialog_fields[$field_name] = $field_array;');
	expect($src)->toContain("<div class='filterRow cactiFilterEditRow'>");
	expect($src)->not->toContain('$dialog_rows');
});

test('the modern dialog Apply action is labelled Apply and gated on a saved layout', function () use ($root) {
	$src = file_get_contents($root . '/lib/html_filter.php');

	// The apply action is labelled Apply and only fires once the filter is saved.
	expect($src)->toContain("\$applyLabel = __('Apply');");
	expect($src)->toContain('json_encode($applyLabel)');
	expect($src)->toContain('if (!layoutDirty) {');

	// Saving no longer reloads the page and the dialog is non-modal.
	expect($src)->toContain('layoutSetDirty(false)');
	expect($src)->toContain('modal: false');

	// The stale hardcoded Search label and the Clear action are gone.
	expect($src)->not->toContain('json_encode(__(\'Search\'))');
	expect($src)->not->toContain('json_encode($clearLabel)');
});

test('page action buttons (import, export, sort) render on the bar, not the dialog', function () use ($root) {
	$src = file_get_contents($root . '/lib/html_filter.php');

	// Non go/clear buttons are collected as bar actions and emitted on the bar.
	expect($src)->toContain('$bar_actions[$field_name] = $field_array;');
	expect($src)->toContain('foreach ($bar_actions as $field_name => $field_array) {');

	// The dialog no longer carries a page-action button row.
	expect($src)->not->toContain('$dialog_buttons');
});

test('the modern javascript wires the edit dialog save and publish actions', function () use ($root) {
	$src = file_get_contents($root . '/lib/html_filter.php');

	expect($src)->toContain('private function create_modern_javascript(string $applyFilter, string $changeFunction, string $clearFunction) : string {');
	expect($src)->toContain('function layoutDialogSave(forceNew) {');
	expect($src)->toContain('function layoutUpdateButtons() {');
	expect($src)->toContain("layoutPost('layout_publish'");
	// The bar Clear button (right of Refresh) resets the filter via the page clear action.
	expect($src)->toContain("id='layout_clear'");
	expect($src)->toContain("\$('#layout_clear').click(function() { \" . \$clearFunction");
});

test('the edit dialog is tagged for theming and the modern theme skins it', function () use ($root) {
	$src = file_get_contents($root . '/lib/html_filter.php');
	$css = file_get_contents($root . '/include/themes/modern/main.css');

	// The widget wrapper is tagged so themes can skin the dialog chrome.
	expect($src)->toContain("dw.addClass('cactiFilterDialog');");

	expect($css)->toContain('.ui-dialog.cactiFilterDialog .ui-dialog-titlebar');
	expect($css)->toContain('.ui-dialog.cactiFilterDialog .ui-dialog-buttonpane');
});

test('removing a user deletes their own layouts but not published ones', function () use ($root) {
	$src = file_get_contents($root . '/lib/auth.php');

	// Scoped to user_id so published (user_id = 0) layouts are preserved.
	expect($src)->toContain("DELETE FROM user_layouts WHERE user_id = ?', [\$user_id]");
});

test('auth invokes the layout handler after authorization', function () use ($root) {
	$src = file_get_contents($root . '/include/auth.php');

	expect($src)->toContain('filter_layouts_handle_request();');
});

test('layouts.php is admin-only and registered in the Presets menu', function () use ($root) {
	$arrays = file_get_contents($root . '/include/global_arrays.php');

	expect($arrays)->toContain("'layouts.php'                => 15,");
	expect($arrays)->toContain("'layouts.php'              => __('Layouts'),");
});

test('user_layouts is defined consistently across the schema files', function () use ($root) {
	$sql   = file_get_contents($root . '/cacti.sql');
	$audit = file_get_contents($root . '/docs/audit_schema.sql');
	$upg   = file_get_contents($root . '/install/upgrades/1_3_0.php');

	expect($sql)->toContain('CREATE TABLE `user_layouts`');
	expect($upg)->toContain('CREATE TABLE IF NOT EXISTS user_layouts');
	expect($audit)->toContain("('user_layouts',1,'id'");
	expect($audit)->toContain("('user_layouts',5,'data','text'");

	foreach (['user_id', 'page', 'name', 'data'] as $column) {
		expect($sql)->toContain("`$column`");
	}
});
