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

$root = dirname(__DIR__, 2);

test('filter_layouts_page_key reduces a url to its page basename', function () {
	expect(filter_layouts_page_key('host.php?filter=x&rows=30'))->toBe('host.php');
	expect(filter_layouts_page_key('cdef.php'))->toBe('cdef.php');
	expect(filter_layouts_page_key('/var/www/host.php?a=b'))->toBe('host.php');
});

test('filter_layouts_valid_url accepts only same-site page references', function () {
	expect(filter_layouts_valid_url('host.php'))->toBeTrue();
	expect(filter_layouts_valid_url('host.php?filter=a&rows=30'))->toBeTrue();
	expect(filter_layouts_valid_url('graph_view.php?action=tree'))->toBeTrue();

	// No scheme, host, traversal, or markup injection may be stored/navigated.
	expect(filter_layouts_valid_url('http://evil.example/host.php'))->toBeFalse();
	expect(filter_layouts_valid_url('javascript:alert(1)'))->toBeFalse();
	expect(filter_layouts_valid_url('../host.php'))->toBeFalse();
	expect(filter_layouts_valid_url('host.php?x="><script>alert(1)</script>'))->toBeFalse();
	expect(filter_layouts_valid_url(''))->toBeFalse();
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

test('auth invokes the layout handler after authorization', function () use ($root) {
	$src = file_get_contents($root . '/include/auth.php');

	expect($src)->toContain('filter_layouts_handle_request();');
});

test('layouts.php is admin-only and registered in the Presets menu', function () use ($root) {
	$arrays = file_get_contents($root . '/include/global_arrays.php');

	expect($arrays)->toContain("'layouts.php'                => 15,");
	expect($arrays)->toContain("'layouts.php'              => __('Filters'),");
});

test('user_layouts is defined consistently across the schema files', function () use ($root) {
	$sql   = file_get_contents($root . '/cacti.sql');
	$audit = file_get_contents($root . '/docs/audit_schema.sql');
	$upg   = file_get_contents($root . '/install/upgrades/1_3_0.php');

	expect($sql)->toContain('CREATE TABLE `user_layouts`');
	expect($upg)->toContain('CREATE TABLE IF NOT EXISTS user_layouts');
	expect($audit)->toContain("('user_layouts',1,'id'");

	foreach (['user_id', 'page', 'name', 'url'] as $column) {
		expect($sql)->toContain("`$column`");
	}
});
