/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

const assert = require('node:assert/strict');
const test = require('node:test');

const {fakeJQuery, fakeDocument, loadTheme} = require('./theme_test_support.js');

const olderThemes = ['modern', 'dark', 'paw', 'deepness', 'paper-plane', 'sunrise'];

function storage(initial = {}) {
	const values = new Map(Object.entries(initial));
	const writes = [];

	return {
		values,
		writes,
		isSet: (name) => values.has(name),
		get: (name) => values.get(name),
		set: (name, value) => { writes.push([name, value]); values.set(name, value); }
	};
}

function themeContext(theme, options = {}) {
	const $ = fakeJQuery(options.jquery || {});
	const windowObject = {requestAnimationFrame: options.requestAnimationFrame || ((fn) => fn())};
	const spies = {ajaxAnchors: 0, keepWindowSize: 0, waitForFinalEvent: 0, filter: 0};
	const globals = {
		$,
		jQuery: $,
		window: windowObject,
		document: fakeDocument().document,
		Storages: {localStorage: storage(options.storage)},
		urlPath: '/cacti/',
		noFileSelected: 'No file selected',
		basename: () => 'host.php',
		setTimeout: () => 0,
		clearTimeout: () => {},
		ajaxAnchors: () => { spies.ajaxAnchors++; },
		keepWindowSize: () => { spies.keepWindowSize++; },
		waitForFinalEvent: (fn) => { spies.waitForFinalEvent++; fn(); },
		applyGraphFilter: () => { spies.filter++; },
		applyFilter: () => { spies.filter++; }
	};

	const context = loadTheme(theme, globals);

	return {context, globals, $, spies, windowObject};
}

function chainsFor($, target) {
	return $.chains.filter((chain) => chain.target === target);
}

function called($, target, method) {
	return chainsFor($, target).some((chain) => chain.calls.some((entry) => entry.method === method));
}

for (const theme of olderThemes) {
	test(`${theme}: menu sections open only for the selected page and nothing is written to storage on load`, () => {
		const sections = [{id: 'menu_a', selected: false}, {id: 'menu_b', selected: true}];
		const {context, globals, $} = themeContext(theme, {
			storage: {menu_a: 'active', menu_b: 'collapsed'},
			jquery: {
				items: (chain) => (chain.target === 'li.menuitem' ? sections : []),
				length: (chain) => (chain.target && chain.target.selected ? 1 : 0)
			}
		});

		context.setMenuVisibility();

		assert.deepEqual(globals.Storages.localStorage.writes, []);

		for (const section of sections) {
			assert.equal(called($, section, 'show'), section.selected, section.id);
		}
	});

	test(`${theme}: the host autocomplete falls back to the page filter when no callback is named`, () => {
		const {context, $, spies} = themeContext(theme);

		context.themeReady();

		const options = chainsFor($, '#host').flatMap((chain) => chain.calls)
			.find((entry) => entry.method === 'autocomplete' && typeof entry.args[0] === 'object').args[0];

		options.select({}, {item: {id: 1}});
		assert.equal(spies.filter, 1);
	});
}

for (const theme of ['classic', ...olderThemes]) {
	test(`${theme}: theme code and its handlers do not create implicit globals`, () => {
		const {context, globals, $} = themeContext(theme, {jquery: {
			items: (chain) => (chain.target === '.maintabs nav ul li a.lefttab' || chain.target === 'li.menuitem' ? [{}] : []),
			value: (chain, method) => (method === 'val' || method === 'attr' ? 'value' : undefined)
		}});
		const before = new Set(Object.keys(globals));

		context.themeReady();
		context.setMenuVisibility && context.setMenuVisibility();

		for (const handler of $.handlers) {
			try {
				handler.fn.call({value: 'file.xml'}, {preventDefault() {}, target: {}}, {item: {id: 1}});
			} catch (error) {
				// handlers may need more of the page than the stand-in offers; only leaks matter here
			}
		}

		assert.deepEqual(Object.keys(globals).filter((name) => !before.has(name)), []);
	});
}

for (const theme of ['paw', 'deepness']) {
	test(`${theme}: file import fields show the selected file name`, () => {
		const {context, $} = themeContext(theme);

		context.themeReady();

		assert.ok(called($, '.import_label', 'button'));

		const texts = () => chainsFor($, '.import_text').flatMap((chain) => chain.calls)
			.filter((entry) => entry.method === 'text').map((entry) => entry.args[0]);
		assert.deepEqual(texts(), ['No file selected']);

		const change = $.handlers.find((handler) => handler.chain.target === '.import_button');
		change.fn.call({value: 'C:\\fakepath\\template.xml'});
		assert.deepEqual(texts(), ['No file selected', 'C:\\fakepath\\template.xml']);
	});

	test(`${theme}: login logo, footer markup and user menu animations match the sibling themes`, () => {
		const {context, $} = themeContext(theme, {jquery: {value: (chain, method) => (method === 'is' ? false : undefined)}});

		context.themeReady();

		const logoCss = chainsFor($, '.cactiLoginLogo').flatMap((chain) => chain.calls).filter((entry) => entry.method === 'css');
		assert.deepEqual(logoCss, []);

		const footer = $.chains.map((chain) => chain.target).find((target) => typeof target === 'string' && target.indexOf('id="cactiPageBottom"') !== -1);
		assert.equal(footer, '<div id="cactiPageBottom" class="cactiPageBottom"></div>');

		$.handlers.find((handler) => handler.chain.target === '.action-icon-user').fn({preventDefault() {}});

		for (const target of ['.submenuoptions', '.menuoptions']) {
			const animated = chainsFor($, target).filter((chain) => chain.calls.some((entry) => /^slide/.test(entry.method)));
			assert.ok(animated.length > 0, target);
			for (const chain of animated) {
				assert.equal(chain.calls[0].method, 'stop', target);
			}
		}
	});
}

test('dark: the broken color dropdown handler and the unused description hide are gone', () => {
	const {context, $} = themeContext('dark');

	context.themeReady();

	assert.equal(chainsFor($, '.colordropdown').length, 0);
	assert.equal(chainsFor($, '.formItemDescription').length, 0);
});

for (const theme of ['modern', 'dark', 'paw', 'deepness', 'paper-plane', 'sunrise']) {
	test(`${theme}: themeReady() leaves window sizing, anchors and input styling to a single pass`, () => {
		const {context, $, spies, windowObject} = themeContext(theme);

		context.themeReady();

		assert.equal(spies.keepWindowSize, 0, 'applySkin() calls keepWindowSize() itself');
		assert.equal(spies.ajaxAnchors, 0, 'applySkin() calls ajaxAnchors() right before themeReady()');
		assert.equal(called($, windowObject, 'trigger'), false, 'keepWindowSize() triggers the resize');
		assert.ok(chainsFor($, 'input#filter, input#rfilter').length <= 1);
	});
}

test('midwinter: the input styling pass runs once', () => {
	const {context, $} = themeContext('midwinter');

	context.setupDefaultElements();

	assert.equal(chainsFor($, 'input#filter, input#rfilter').length, 1);
});

test('sunrise: the namespaced scroll handler updates the indicator on every scroll in both directions', () => {
	const position = {scrollLeft: 0, scrollTop: 0};
	const {context, $} = themeContext('sunrise', {jquery: {
		value: (chain, method) => (chain.target === '#navigation_right' && method in position ? position[method] : undefined)
	}});

	context.themeReady();

	const chains = chainsFor($, '#navigation_right').filter((chain) => chain.calls.some((entry) => entry.method === 'on'));
	assert.equal(chains.length, 1);
	assert.deepEqual(chains[0].calls.map((entry) => entry.method + ':' + entry.args[0]), ['off:scroll.sunrise', 'on:scroll.sunrise']);

	const handler = $.handlers.find((entry) => entry.chain === chains[0]).fn;
	const colors = [];

	for (const [x, y] of [[0, 0], [10, 0], [0, 10], [10, 10], [0, 0]]) {
		position.scrollLeft = x;
		position.scrollTop = y;
		handler({});

		const css = chainsFor($, '.bottom_scroll_up').flatMap((chain) => chain.calls).filter((entry) => entry.method === 'css');
		colors.push(css[css.length - 1].args[0].color);
	}

	assert.deepEqual(colors, ['', '#93CEFF', '#93CEFF', '#93CEFF', '']);
});

test('classic: only the theme resize handler is replaced and it is debounced', () => {
	const {context, $, spies, windowObject} = themeContext('classic');

	context.themeReady();

	const windowCalls = chainsFor($, windowObject).flatMap((chain) => chain.calls);
	assert.equal(windowCalls.some((entry) => entry.method === 'unbind' || (entry.method === 'off' && entry.args.length === 0)), false);
	assert.deepEqual(windowCalls.filter((entry) => entry.method === 'off' || entry.method === 'on').map((entry) => entry.method + ':' + entry.args[0]),
		['off:resize.classic', 'on:resize.classic']);
	assert.equal(spies.keepWindowSize, 0);

	const handler = $.handlers.find((entry) => entry.chain.target === windowObject).fn;
	handler({target: {}});
	assert.equal(spies.waitForFinalEvent, 1);
});

test('classic: the navigation height follows the content down again', () => {
	let navHeight = 300;
	const {context} = themeContext('classic', {jquery: {
		value: (chain, method, args) => {
			if (chain.target === '#navigation' && method === 'height') return navHeight;
			if (chain.target === '#navigation' && method === 'prop') return 300;
			if (chain.target === '#navigation_right' && method === 'prop') return 280;
			if (chain.target === '#navigation_right' && method === 'height') return 280;
			if (method === 'height') return 500;
			if (chain.target === '#navigation, .cactiConsoleNavigationArea' && method === 'css') return undefined;
			return undefined;
		}
	}});

	// a previous long page left the navigation at 900px; css('height', '') restores the natural height
	navHeight = 900;
	const originalJq = context.$;
	context.$ = (target) => {
		const chain = originalJq(target);
		if (target !== '#navigation, .cactiConsoleNavigationArea') return chain;
		return new Proxy(chain, {get(object, name) {
			if (name === 'css') return (property, value) => {
				if (property === 'height' && value === '') navHeight = 300;
				return object.css(property, value);
			};
			return object[name];
		}});
	};

	assert.equal(context.get_height(), 500);
});
