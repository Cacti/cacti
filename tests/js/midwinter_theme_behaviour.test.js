/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const root = path.join(__dirname, '..', '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const theme = read('include/themes/midwinter/main.js');

function extract(source, startText) {
	const start = source.indexOf(startText);
	assert.notEqual(start, -1, `${startText} must exist`);
	let depth = 0;

	for (let offset = source.indexOf('{', start); offset < source.length; offset++) {
		if (source[offset] === '{') depth++;
		if (source[offset] === '}' && --depth === 0) return source.slice(start, offset + 1);
	}

	throw new Error(`Unable to extract ${startText}`);
}

/*
 * A chainable jQuery stand-in. Every method call is recorded with the selector it
 * was made on and returns the same chain. Lengths, elements and data are looked
 * up by selector so a test can describe the page it needs.
 */
function fakeJQuery(page = {}) {
	const lengths = page.lengths || {};
	const elements = page.elements || {};
	const data = page.data || {};
	const calls = [];

	const $ = (selector) => {
		const key = typeof selector === 'string' ? selector : (selector && selector.selector) || 'node';
		const chain = new Proxy({}, {
			get(_, property) {
				if (property === 'length') return lengths[key] || 0;
				if (property === '0') return elements[key];
				if (property === 'hasClass') return () => true;
				if (property === 'is') return () => false;
				if (property === 'data') {
					return (name, value) => {
						const store = data[key] = data[key] || {};
						if (value === undefined) return store[name];
						store[name] = value;
						return chain;
					};
				}

				return (...args) => {
					calls.push({selector: key, method: String(property), args});
					return chain;
				};
			}
		});

		return chain;
	};

	return {$, calls, data};
}

function themeFunctions(names, context) {
	vm.runInNewContext(names.map((name) => extract(theme, `function ${name}(`)).join('\n'), context);
	return context;
}

test('repeated setup on the same content keeps the title, tabs and save row in the top bar', () => {
	const main = {selector: '#main', firstElementChild: {name: 'form'}};
	const page = fakeJQuery({
		lengths: {'#elementsOnTop': 1, '#main div.cactiTableTitleRow': 1, '#main div.saveRow': 1},
		elements: {'#main': main}
	});
	const context = themeFunctions(['setupDefaultElements', 'midwinterContentState', 'midwinterContentChanged'], {
		$: page.$,
		mdw: {domMap: {mdwPopOver: '#mdw-GridContainer-PopOver', cactiBreadcrumb: '#breadCrumbBar', cactiNavRight: '#navigation_right'}},
		midwinter: {navigationBox: {}},
		Storages: {localStorage: {}},
		setTimeout: () => {},
		setNavigationScroll: () => {},
		noFileSelected: ''
	});
	const emptied = () => page.calls.filter((call) => call.selector === '.elementOnTop' && call.method === 'empty').length;
	const moved = () => page.calls.filter((call) => call.selector === '#main div.saveRow' && call.method === 'detach').length;

	context.setupDefaultElements();
	assert.equal(emptied(), 1, 'the first pass lays out the page');
	assert.equal(moved(), 1);

	// a dialog fragment is added to #main and applySkin() runs again
	context.setupDefaultElements();
	assert.equal(emptied(), 1, 'the top bar must not be cleared while #main keeps its content');
	assert.equal(moved(), 1, 'a save row must not be taken from the dialog');

	// a page script replaces #main without loadUrl()
	main.firstElementChild = {name: 'new form'};
	context.setupDefaultElements();
	assert.equal(emptied(), 2, 'new content is laid out again');

	// loadUrl() advances the content version
	page.data['#main'] = {cactiContentVersion: 4};
	context.setupDefaultElements();
	assert.equal(emptied(), 3);

	for (const selector of ['#main div.cactiTableTitleRow', '#main div.saveRow']) {
		const filters = page.calls.filter((call) => call.selector === selector && call.method === 'not');
		assert.ok(filters.length > 0 && filters.every((call) => String(call.args[0]).includes('#cdialog *')), `${selector} must skip dialog content`);
	}
});
