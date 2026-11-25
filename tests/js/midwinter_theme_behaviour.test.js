/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const {root, fakeJQuery, fakeDocument, loadTheme} = require('./theme_support.test.js');

const source = fs.readFileSync(path.join(root, 'include', 'themes', 'midwinter', 'main.js'), 'utf8');

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

function midwinter(options = {}) {
	const $ = fakeJQuery(options.jquery || {});
	const doc = fakeDocument();
	const local = storage(options.storage);
	const calls = {alert: [], log: [], graphs: 0, cookies: []};
	const globals = {
		$,
		jQuery: $,
		document: doc.document,
		window: options.window || {location: {pathname: '/cacti/index.php', search: ''}},
		Storages: {localStorage: local},
		urlPath: '/cacti/',
		cactiVersion: '1.2.32',
		cactiAction: '',
		pageName: 'index.php',
		console: {log: (...args) => calls.log.push(args), error: () => {}},
		alert: (value) => calls.alert.push(value),
		setTimeout: () => 0,
		clearTimeout: () => {},
		URLSearchParams,
		initializeGraphs: () => { calls.graphs++; }
	};

	const context = loadTheme('midwinter', globals);
	$.cookie = (name, value) => {
		if (value === undefined) return options.cookie;
		calls.cookies.push([name, value]);
	};

	return {context, globals, $, doc, local, calls};
}

function requestsFor($, file) {
	return $.requests.filter((entry) => entry.settings.url.indexOf(file) !== -1);
}

function chainsFor($, selector) {
	return $.chains.filter((chain) => chain.target === selector);
}

function methods(chain) {
	return chain.calls.map((entry) => entry.method + '(' + entry.args.filter((arg) => typeof arg === 'string').join(',') + ')');
}

test('repeated themeReady() calls keep a single document double-click listener', () => {
	const {context, doc} = midwinter();

	for (const name of ['setThemeColor', 'setupTheme', 'setupTree', 'setupDefaultElements', 'setMenuVisibility',
		'setHotKeys', 'ajaxAnchors', 'extendAnchorActions', 'searchToHighlight', 'updateNavigation']) {
		context[name] = () => {};
	}

	context.themeReady();
	context.themeReady();
	context.themeReady();

	assert.equal(doc.listeners.filter((listener) => listener.type === 'dblclick').length, 1);
});

test('the page is shown even when a step of themeReady() fails', () => {
	const {context, doc} = midwinter();

	context.setThemeColor = () => {};
	context.setupTheme = () => { throw new Error('broken plugin markup'); };

	assert.throws(() => context.themeReady(), /broken plugin markup/);
	assert.equal(doc.attributes['data-theme-state'], 'ready');
});

test('the hotkeys library is requested and bound once per page, and again only after a failed load', () => {
	const {context, $} = midwinter();
	const bound = [];

	context.hotkeys = (keys, handler) => bound.push({keys, handler});

	context.setHotKeys();
	context.setHotKeys();
	assert.equal(requestsFor($, 'hotkeys.js').length, 1);

	requestsFor($, 'hotkeys.js')[0].request.resolve('', 'success');
	context.setHotKeys();
	assert.equal(requestsFor($, 'hotkeys.js').length, 1);
	assert.equal(bound.length, 1);

	const fresh = midwinter();
	fresh.context.setHotKeys();
	requestsFor(fresh.$, 'hotkeys.js')[0].request.reject();
	fresh.context.setHotKeys();
	assert.equal(requestsFor(fresh.$, 'hotkeys.js').length, 2);
});

test('Escape is left to the browser and no shortcut ends in an alert', () => {
	const {context, $, calls} = midwinter();
	let bound;

	context.hotkeys = (keys, handler) => { bound = {keys: keys.split(',').map((key) => key.trim()), handler}; };
	context.loadPage = () => {};
	context.setHotKeys();
	requestsFor($, 'hotkeys.js')[0].request.resolve('', 'success');

	assert.ok(!bound.keys.includes('ESC'));
	assert.ok(!bound.keys.includes('c+F1'));

	for (const key of ['c+F1', 'ESC', 'unknown']) {
		bound.handler({preventDefault() {}}, {key});
	}

	assert.deepEqual(calls.alert, []);
});

test('toggleFullscreen() leaves full screen when active and falls back to the whole page', () => {
	const {context, doc} = midwinter();
	const requested = [];
	let exited = 0;
	const resolved = {catch() {}};

	doc.document.documentElement.requestFullscreen = () => { requested.push('page'); return resolved; };
	doc.document.exitFullscreen = () => { exited++; return resolved; };
	doc.document.getElementById = (id) => (id === 'navigation_right' ? {requestFullscreen: () => { requested.push(id); return resolved; }} : null);

	context.toggleFullscreen('missing');
	context.toggleFullscreen('navigation_right');
	context.toggleFullscreen();
	assert.deepEqual(requested, ['page', 'navigation_right', 'page']);

	doc.document.mozFullScreenElement = {};
	context.toggleFullscreen('navigation_right');
	context.toggleFullscreen();
	assert.equal(exited, 2);
	assert.deepEqual(requested, ['page', 'navigation_right', 'page']);

	delete doc.document.mozFullScreenElement;
	doc.document.msFullscreenElement = {};
	assert.ok(context.getFullscreenElement());
});

test('one system color listener is kept and it is ignored once the user picks a mode', () => {
	const listeners = [];
	const media = {matches: false, addEventListener: (type, fn) => listeners.push(fn)};
	const {context, local, calls} = midwinter({
		storage: {midWinter_Color_Mode_Auto: 'on'},
		cookie: 'light',
		window: {location: {pathname: '/cacti/index.php', search: ''}, matchMedia: () => media}
	});

	context.detectSystemColorSetup();
	context.detectSystemColorSetup();
	assert.equal(listeners.length, 1);

	local.values.set('midWinter_Color_Mode_Auto', 'off');
	listeners[0]({matches: true});
	assert.deepEqual(calls.cookies, []);
	assert.equal(calls.graphs, 0);

	local.values.set('midWinter_Color_Mode_Auto', 'on');
	listeners[0]({matches: true});
	assert.deepEqual(calls.cookies, [['CactiColorMode', 'dark']]);
	assert.equal(calls.graphs, 1);
});

test('graphs are reloaded only when the color cookie changes', () => {
	const same = midwinter({cookie: 'dark'});

	same.context.checkThemeColorSetup('dark');
	assert.equal(same.doc.attributes['data-theme-color'], 'dark');
	assert.equal(same.calls.graphs, 0);
	assert.deepEqual(same.calls.cookies, []);

	const changed = midwinter({cookie: 'light'});

	changed.context.checkThemeColorSetup('dark');
	assert.equal(changed.doc.attributes['data-theme-color'], 'dark');
	assert.equal(changed.calls.graphs, 1);
	assert.deepEqual(changed.calls.cookies, [['CactiColorMode', 'dark']]);
});

test('about.php is not fetched again once it returned no console menu', () => {
	const {context, $} = midwinter({jquery: {length: (chain) => (chain.target === '#cactiContent' ? 1 : 0)}});

	context.checkConsoleMenu();
	assert.equal(requestsFor($, 'about.php').length, 1);

	requestsFor($, 'about.php')[0].request.resolve('<html><body></body></html>');
	context.checkConsoleMenu();
	context.checkConsoleMenu();
	assert.equal(requestsFor($, 'about.php').length, 1);
});

test('about.php is fetched again when the menu it returned was later replaced', () => {
	const {context, $} = midwinter({jquery: {length: (chain) => {
		if (chain.target === '#cactiContent') return 1;
		return chain.calls.some((entry) => entry.method === 'find' && entry.args[0] === '#menu') ? 1 : 0;
	}}});

	context.checkConsoleMenu();
	requestsFor($, 'about.php')[0].request.resolve('<div id="menu"></div>');
	context.checkConsoleMenu();
	assert.equal(requestsFor($, 'about.php').length, 2);
});

test('handlers on menus that survive a page load are replaced rather than added', () => {
	const {context, $} = midwinter({jquery: {length: (chain) => (chain.target === '.cactiConsoleNavigationArea' ? 1 : 0)}});

	context.setupTheme();
	context.extendAnchorActions();
	$.fn.mark = () => {};
	context.searchToHighlight();

	const expectations = {
		'a[role="menuitem"]': 'click.midwinter',
		'.submenuoptions, .menuoptions': 'click.midwinter',
		'.cactiConsoleContentArea, .cactiGraphContentArea': 'mouseenter.midwinter',
		"#menu input[name='keyword']": 'input.midwinter'
	};

	for (const [selector, event] of Object.entries(expectations)) {
		const bound = chainsFor($, selector).filter((chain) => chain.calls.some((entry) => entry.method === 'on'));

		assert.ok(bound.length > 0, `${selector} is bound`);

		for (const chain of bound) {
			assert.deepEqual(methods(chain).filter((entry) => /^(on|off)\(/.test(entry)), [`off(${event})`, `on(${event})`], selector);
		}
	}

	assert.equal(chainsFor($, "input[name='keyword']").length, 0, 'page inputs named keyword are left alone');
});

test('the mark library is requested once and later calls bind the search box directly', () => {
	const {context, $} = midwinter();

	context.searchToHighlight();
	context.searchToHighlight();
	assert.equal(requestsFor($, 'jquery.mark.js').length, 1);

	$.fn.mark = () => {};
	requestsFor($, 'jquery.mark.js')[0].request.resolve('', 'success');
	context.searchToHighlight();
	assert.equal(requestsFor($, 'jquery.mark.js').length, 1);
	assert.equal(chainsFor($, "#menu input[name='keyword']").length, 2);
});

test('vendor scripts are requested with the Cacti version in the URL', () => {
	const {context, $} = midwinter();

	context.jQuery.cachedScript('/cacti/include/themes/midwinter/vendor/mark/jquery.mark.js');
	context.jQuery.cachedScript('/cacti/x.js?a=1');

	assert.equal($.requests[0].settings.url, '/cacti/include/themes/midwinter/vendor/mark/jquery.mark.js?v=1.2.32');
	assert.equal($.requests[1].settings.url, '/cacti/x.js?a=1&v=1.2.32');
	assert.equal($.requests[0].settings.cache, true);
});

test('navigation lookups build complete selectors from the current location', () => {
	const {context, $} = midwinter({window: {location: {pathname: '/cacti/host.php', search: '?filter=a\\"b'}}});

	context.cactiAction = 'edit';
	context.updateNavigation();

	const selectors = $.chains.map((chain) => chain.target)
		.filter((target) => typeof target === 'string' && target.startsWith('.cactiConsoleNavigationArea a[href'));

	assert.equal(selectors.length, 5);

	for (const selector of selectors) {
		assert.match(selector, /^\.cactiConsoleNavigationArea a\[href[$^]="(?:[^"\\]|\\.)*"\]$/, selector);
	}

	assert.equal(selectors[0], '.cactiConsoleNavigationArea a[href$="/cacti/host.php?filter=a\\\\\\"b"]');
	assert.equal(selectors[3], '.cactiConsoleNavigationArea a[href^="/cacti/host.php?action=edit"]');
});

test('the compact menu icons are added without the always-true tag selector guard', () => {
	assert.doesNotMatch(source, /\$\('compact_tab_menu'\)/);
});

test('the generated user menu has unique section ids', () => {
	const {context, $} = midwinter({jquery: {length: (chain) => (chain.target === '.cactiConsoleNavigationArea' ? 1 : 0)}});

	context.setupTheme();

	const markup = $.chains.map((chain) => chain.target)
		.filter((target) => typeof target === 'string' && target.indexOf('cactiConsoleNavigationUserBox') !== -1)
		.join('');
	const ids = [...markup.matchAll(/id="([^"]+)"/g)].map((match) => match[1]);

	assert.ok(ids.includes('menu_user_help'));
	assert.deepEqual(ids, [...new Set(ids)]);
});

test('menu sections open only for the selected page and nothing is written to storage on load', () => {
	const sections = [{id: 'menu_a', selected: false}, {id: 'menu_b', selected: true}];
	const {context, local, $} = midwinter({
		storage: {menu_a: 'active', menu_b: 'collapsed'},
		jquery: {
			items: (chain) => (chain.target === 'li.menuitem' ? sections : []),
			length: (chain) => (chain.target && chain.target.selected ? 1 : 0)
		}
	});

	context.setMenuVisibility();

	assert.deepEqual(local.writes, []);

	for (const section of sections) {
		const shown = $.chains.filter((chain) => chain.target === section)
			.some((chain) => chain.calls.some((entry) => entry.method === 'show'));
		assert.equal(shown, section.selected, section.id);
	}
});

test('the host autocomplete falls back to the page filter when no callback is named', () => {
	let filtered = 0;
	const {context, $} = midwinter();

	context.applyGraphFilter = () => { filtered++; };
	context.setupDefaultElements();

	const options = $.chains.filter((chain) => chain.target === '#host')
		.flatMap((chain) => chain.calls)
		.find((entry) => entry.method === 'autocomplete' && typeof entry.args[0] === 'object').args[0];

	options.select({}, {item: {id: 1}});
	assert.equal(filtered, 1);
});

test('theme handlers do not create implicit globals', () => {
	const {context, globals, $} = midwinter({jquery: {
		items: (chain) => (chain.target === 'li.menuitem' ? [{}] : []),
		value: (chain, method) => (method === 'val' ? 'host' : undefined)
	}});
	const before = new Set(Object.keys(globals));

	context.setupDefaultElements();
	context.setMenuVisibility();
	$.fn.unmark = () => {};
	context.highlight.call({});

	for (const handler of $.handlers) {
		try {
			handler.fn.call({value: 'file.xml'}, {preventDefault() {}, data: {}}, {item: {id: 1}});
		} catch (error) {
			// handlers may need more of the page than the stand-in offers; only leaks matter here
		}
	}

	const leaked = Object.keys(globals).filter((name) => !before.has(name));
	assert.deepEqual(leaked.filter((name) => ['text', 'callBack', 'storage', 'pattern', 'id'].includes(name)), []);
});

test('the client dialog does not write to the console', () => {
	const {context, $, calls} = midwinter();

	context.UAParser = function() {
		this.getResult = () => ({browser: {name: 'Firefox', version: '1'}, os: {name: 'Linux', version: ''}, device: {}});
	};

	context.dialog_client({preventDefault() {}});
	requestsFor($, 'ua-parser.js')[0].request.resolve('', 'success');

	assert.deepEqual(calls.log, []);
});
