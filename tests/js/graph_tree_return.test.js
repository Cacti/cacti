/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const root = path.resolve(__dirname, '../..');
const layout = fs.readFileSync(path.join(root, 'include/layout.js'), 'utf8');
const tree = fs.readFileSync(path.join(root, 'lib/html_tree.php'), 'utf8');
const jstree = fs.readFileSync(path.join(root, 'include/js/jstree.js'), 'utf8');

function treeFixture(selected) {
	// Exercise jsTree's bundled activation code with Cacti's actual core options.
	const core = tree.slice(tree.indexOf("'core' : {"), tree.indexOf("'themes' : {"));
	const settings = vm.runInNewContext('({' + core + '})', {urlPath: '/cacti/'});
	const start = jstree.indexOf('activate_node : function (obj, e) {');
	const end = jstree.indexOf('\n\t\t},', start);
	const activate = vm.runInNewContext('({' + jstree.slice(start, end + 4) + '}).activate_node');
	let loads = 0;
	const node = {id: 'switch', state: {selected}};
	const instance = {
		settings,
		_data: {core: {selected: selected ? ['switch'] : [], last_clicked: null}},
		is_disabled: () => false,
		is_selected: () => node.state.selected,
		get_node: () => node,
		deselect_all() {node.state.selected = false; this._data.core.selected = [];},
		select_node() {node.state.selected = true; this._data.core.selected = ['switch']; loads++;},
		trigger() {},
	};
	return {activate: event => activate.call(instance, 'switch', event), loads: () => loads};
}

test('clicking the selected tree device reloads its graph list after drilldown', () => {
	const fixture = treeFixture(true);
	fixture.activate({type: 'click'});
	assert.equal(fixture.loads(), 1);
});

test('keyboard reactivation and selecting another device each load once', () => {
	for (const selected of [true, false]) {
		const fixture = treeFixture(selected);
		fixture.activate({type: 'keydown', key: 'Enter'});
		assert.equal(fixture.loads(), 1);
	}
});

function drilldown(parentHref, url) {
	const attributes = {id: 'graph_108_mrtg'};
	const parent = {href: parentHref};
	let handler, requested, appended;
	const sourceStart = layout.indexOf("\t$('a[id$=\"_mrtg\"]').each", layout.indexOf('function initializeGraphs'));
	const sourceEnd = layout.indexOf('\n\t// Get these outside', sourceStart);
	const link = {attr(name, value) {if (value === undefined) return attributes[name]; attributes[name] = value; return this;},
		rebind(event, callback) {handler = callback; return this;}};
	const empty = {empty() {return this;}, hide() {return this;}, html() {return this;}, remove() {return this;}};
	const $ = selector => {
		if (selector === link) return link;
		if (selector === 'a[id$="_mrtg"]') return {each(callback) {callback.call(link);}};
		if (selector === '#breadcrumbs > li:last-child > a[href="#"]') return {attr(name, value) {if (parent.href === '#') parent[name] = value; return this;}};
		if (selector === '#breadcrumbs') return {append(html) {appended = html; return this;}};
		return empty;
	};
	$.ajaxQ = {abortAll() {}};
	$.get = target => {requested = target; return {done(callback) {callback('<div>detail graph</div>'); return this;}, fail() {return this;}};};
	vm.runInNewContext(layout.slice(sourceStart, sourceEnd), {
		$, window: {location: {href: url}}, urlPath: '/cacti/', timeGraphView: 'Time Graph View',
		closeDateFilters() {}, checkForRedirects() {}, applySkin() {}, clearTimeout() {}, myRefresh: null,
	});
	handler.call(link, {preventDefault() {}, stopPropagation() {}});
	return {parent, requested, appended};
}

test('detailed view makes the parent breadcrumb return to the current filtered graph list', () => {
	for (const url of ['/cacti/graph_view.php?action=tree&node=tbranch-7&host_id=3&hgd=4', '/cacti/graph_view.php?action=preview&filter=traffic']) {
		const result = drilldown('#', url);
		assert.equal(result.parent.href, url);
		assert.equal(result.requested, '/cacti/graph.php?local_graph_id=108&header=false');
		assert.match(result.appended, /id="nav_mrgt"/);
	}
});

test('an existing parent breadcrumb destination is preserved', () => {
	assert.equal(drilldown('graph_view.php?action=tree&node=other', '/cacti/graph_view.php').parent.href,
		'graph_view.php?action=tree&node=other');
});
