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

test('the page is revealed even when a setup step fails', () => {
	const states = [];
	const context = themeFunctions(['themeReady'], {
		setupTheme: () => { throw new Error('setup failed'); },
		themeLoader: (state) => states.push(state),
		console: {log: () => {}}
	});

	assert.throws(() => context.themeReady(), /setup failed/);
	assert.deepEqual(states, ['off']);
});

test('the breadcrumb lookup builds complete selectors and tolerates a page without cactiAction', () => {
	const selectors = [];
	const context = themeFunctions(['updateNavigation'], {
		$: (selector) => {
			selectors.push(selector);
			return {first: () => ({length: 0})};
		},
		window: {location: {pathname: '/cacti/host.php', search: '?action=edit'}},
		midWinterNavigation: () => {}
	});

	context.updateNavigation();
	assert.equal(selectors.length, 5);

	for (const selector of selectors) {
		assert.equal(selector.split('[').length, selector.split(']').length, selector);
		assert.match(selector, /"\]$/);
	}

	assert.ok(selectors[3].endsWith('?action="]'));
});

test('themeLoader reads the theme state it writes', () => {
	const attributes = {'data-theme-state': 'ready'};
	const document = {documentElement: {
		getAttribute: (name) => attributes[name] ?? null,
		setAttribute: (name, value) => { attributes[name] = value; }
	}};
	const context = themeFunctions(['themeLoader', 'setDocumentAttribute', 'getDocumentAttribute'], {document});

	context.themeLoader('on');
	assert.equal(attributes['data-theme-state'], 'ready', 'a ready page is not hidden again');

	context.themeLoader('on', true);
	assert.equal(attributes['data-theme-state'], 'loading');
});

const navigation = read('include/js/navigationBox.js');

function navigationClasses(globals = {}) {
	const context = {console: {warn: () => {}, info: () => {}, error: () => {}, log: () => {}}, ...globals};
	context.window = context;
	vm.runInNewContext(navigation + '\nthis.cactiNavigation = cactiNavigation; this.cactiButton = cactiButton; this.cactiBox = cactiBox;', context);
	return context;
}

test('configured hotkeys are written to the navigation buttons', () => {
	const markup = [];
	const button = {on: () => button, appendTo: () => button};
	const context = navigationClasses({
		$: (selector) => {
			if (typeof selector === 'string' && selector.includes('compact_nav_icon')) {
				markup.push(selector);
				return button;
			}
			return {length: 1, find: () => ({length: 0})};
		}
	});
	const manager = new context.cactiButton();

	manager.add({helper: 'help', destination: '#compact_user_menu', hotkey: 'ALT+SHIFT+4'});
	manager.add({helper: 'user', destination: '#compact_user_menu'});

	assert.match(markup[0], /data-hotkey="ALT\+SHIFT\+4"/);
	assert.doesNotMatch(markup[1], /data-hotkey/);
});

test('the hotkey dispatcher matches buttons by value and ignores events it cannot read', () => {
	let listener;
	const clicked = [];
	const buttons = [{dataset: {hotkey: 'ALT+SHIFT+C'}, name: 'color'}, {dataset: {hotkey: 'ALT+SHIFT+1'}, name: 'panels'}];
	const document = {
		activeElement: {tagName: 'BODY'},
		addEventListener: (type, handler) => { listener = handler; },
		querySelectorAll: (selector) => {
			assert.equal(selector, '[data-hotkey]');
			return buttons;
		},
		querySelector: () => { throw new Error('selectors must not be built from key names'); }
	};
	const context = {
		mdw: {actions: {}, cache: {}},
		document,
		$: (element) => ({trigger: () => clicked.push(element.name)}),
		console: {log: () => {}}
	};

	vm.runInNewContext(extract(theme, 'mdw.actions.initHotKeys = function'), context);
	context.mdw.actions.initHotKeys();

	const press = (event) => listener({preventDefault: () => {}, stopImmediatePropagation: () => {}, ...event});

	// macOS reports a composed character for Alt+Shift+C
	press({altKey: true, shiftKey: true, key: 'Ç', code: 'KeyC'});
	press({altKey: true, shiftKey: true, key: '!', code: 'Digit1'});
	assert.deepEqual(clicked, ['color', 'panels']);

	assert.doesNotThrow(() => press({}), 'autofill events carry no key');
	assert.doesNotThrow(() => press({shiftKey: true, key: '"', code: 'Quote'}));

	document.activeElement = {tagName: 'DIV', isContentEditable: true};
	press({altKey: true, shiftKey: true, key: 'C', code: 'KeyC'});
	assert.deepEqual(clicked, ['color', 'panels'], 'editable content keeps its keys');
});

test('menu links close the open box through the box manager and leave modified clicks to the browser', () => {
	let handler;
	const loaded = [];
	let closed = 0;
	const anchors = {not: () => anchors, rebind: (type, callback) => { handler = callback; return anchors; }};
	const link = {href: 'host.php'};
	const context = themeFunctions(['updateAjaxAnchors'], {
		$: (selector) => {
			if (selector === link) {
				return {attr: () => link.href, hasClass: () => false};
			}
			return typeof selector === 'string' && selector.startsWith('a.pic') ? anchors : {removeClass: () => {}};
		},
		mdw: {obj: {ctrl: {nav: {closeAllBoxes: () => closed++}}}},
		shouldCaptureClick: (event) => !(event.shiftKey || event.altKey || event.ctrlKey || event.metaKey),
		basename: (href) => href,
		loadUrl: (options) => loaded.push(options.url)
	});

	context.updateAjaxAnchors();

	let prevented = false;
	handler.call(link, {ctrlKey: true, preventDefault: () => { prevented = true; }, stopPropagation: () => {}});
	assert.equal(prevented, false, 'a ctrl-click opens a new tab');
	assert.deepEqual(loaded, []);

	handler.call(link, {preventDefault: () => {}, stopPropagation: () => {}});
	assert.deepEqual(loaded, ['host.php']);
	assert.equal(closed, 1, 'the box is closed, not only hidden');
});

test('the console button closes boxes through the box manager', () => {
	const setup = extract(theme, 'function setupTheme(');

	assert.doesNotMatch(setup, /\[class\^="cactiConsoleNavigation"\]/);
	assert.match(setup, /\$\("#navBackdrop"\)\.on\('click', function\(\) \{\s*\/\*[^*]*\*\/\s*if \(mdw\.obj\.ctrl\.nav\) \{\s*mdw\.obj\.ctrl\.nav\.closeAllBoxes\(\);/);
});

function mediaQueries(matches = false) {
	const created = [];
	const matchMedia = () => {
		const listeners = new Set();
		const query = {
			matches,
			listeners,
			addEventListener: (type, listener) => listeners.add(listener),
			removeEventListener: (type, listener) => listeners.delete(listener)
		};
		created.push(query);
		return query;
	};
	const active = () => created.reduce((total, query) => total + query.listeners.size, 0);

	return {matchMedia, active};
}

test('the system color listener is added once and can be removed again', () => {
	const media = mediaQueries(true);
	const checked = [];
	const context = themeFunctions(['detectSystemColorSetup'], {
		window: {matchMedia: media.matchMedia},
		Storages: {localStorage: {}},
		mdw: {cache: {}, session: {theme: {color: {mode: 'light'}}}},
		checkThemeColorSetup: (mode) => checked.push(mode)
	});

	context.detectSystemColorSetup('on');
	context.detectSystemColorSetup('on');
	context.detectSystemColorSetup('on');
	assert.equal(media.active(), 1, 'page loads must not add more listeners');

	context.detectSystemColorSetup('off');
	assert.equal(media.active(), 0, 'turning automatic mode off removes the listener');
	assert.deepEqual(checked, ['dark', 'dark', 'dark', 'light']);
});

test('the applied color mode is stored and the display mode listener is added once', () => {
	const media = mediaQueries();
	const stored = [];
	const graphs = [];
	const attributes = {};
	const context = themeFunctions(['checkThemeColorSetup', 'checkPWADisplayMode'], {
		window: {matchMedia: media.matchMedia},
		document: {documentElement: {getAttribute: () => 'light'}},
		mdw: {cache: {}, session: {theme: {color: {mode: 'light'}}}},
		getCookieValue: () => 'light',
		refreshLocalStorage: () => stored.push(context.mdw.session.theme.color.mode),
		setDocumentAttribute: (name, value) => { attributes[name] = value; },
		setCookieValue: () => {},
		initializeGraphs: (refresh) => graphs.push(refresh)
	});

	context.checkThemeColorSetup('dark');
	assert.deepEqual(stored, ['dark'], 'the detected mode is what gets stored');
	assert.equal(context.mdw.session.theme.color.mode, 'dark');

	context.checkPWADisplayMode();
	context.checkPWADisplayMode();
	assert.equal(media.active(), 1);
	assert.equal(attributes['theme-pwa'], 'off');
});

test('a script is recorded as loaded only when its request succeeds', () => {
	const requests = [];
	const context = themeFunctions(['loadScript'], {
		urlPath: '/cacti/',
		mdw: {cache: {classes: []}},
		$: {ajax: (options) => { requests.push(options); return {fail: () => {}}; }}
	});

	context.loadScript('config', 'include/themes/midwinter/config.js');
	assert.equal(requests.length, 1);
	assert.deepEqual([...context.mdw.cache.classes], [], 'nothing is recorded before the response');

	requests[0].success();
	assert.deepEqual([...context.mdw.cache.classes], ['config']);
});

test('theme actions replace only their own handlers and leave the graph page alone', () => {
	const page = fakeJQuery();
	const context = themeFunctions(['setupThemeActions'], {
		$: page.$,
		document: {addEventListener: () => {}},
		fullScreenChangeHandler: () => {}
	});

	context.setupThemeActions();

	const offCalls = page.calls.filter((call) => call.method === 'off');
	assert.equal(offCalls.length, 2);
	for (const call of offCalls) {
		assert.match(String(call.args[0]), /^(click|change)\.mdwTheme$/, 'other handlers on these elements must stay bound');
	}

	assert.equal(page.calls.some((call) => call.selector === '.graphPage'), false);
});

test('the fullscreen handler updates the button that config.js creates', () => {
	const config = read('include/themes/midwinter/config.js');
	const helper = config.match(/helper: "(full[a-z]+)",\s*tooltip: \{ "on" : "Exit Full screen"/i)[1];
	const selectors = [];
	const icon = {removeClass: () => icon, addClass: () => icon};
	const context = themeFunctions(['fullScreenChangeHandler'], {
		document: {fullscreenElement: {}},
		$: (selector) => { selectors.push(selector); return icon; }
	});

	context.fullScreenChangeHandler();
	context.document.fullscreenElement = null;
	context.fullScreenChangeHandler();

	assert.deepEqual(selectors, Array(2).fill(`.compact_nav_icon[data-helper="${helper}"]>i`));
});

test('the shipped navigation has one tree box and every box has a button', () => {
	const context = {$: () => ({text: () => ''}), document: {}};
	context.window = context;
	vm.runInNewContext(read('include/themes/midwinter/config.js') + '\nthis.uiConfig = uiConfig;', context);

	const {boxes, buttons} = context.uiConfig;
	const helpers = buttons.map((button) => button.helper);

	assert.equal(boxes.filter((box) => box.type === 'tree').length, 1);
	assert.equal(new Set(helpers).size, helpers.length, 'button helpers are unique');

	for (const box of boxes.filter((box) => !box.type)) {
		assert.ok(helpers.includes(box.helper), `${box.helper} needs a button`);
	}

	for (const box of boxes.filter((box) => box.type && box.overrides && box.overrides.helper)) {
		assert.ok(helpers.includes(box.overrides.helper), `${box.overrides.helper} needs a button`);
	}

	assert.ok(helpers.every((helper) => helper !== 'another'), 'no button without a box or action');
});

/*
 * A jQuery stand-in for the navigation classes. Each selector gets a chain whose
 * properties come from the matching entry in `page` (by exact selector or by a
 * function), and every other method records its call and returns the chain.
 */
function navigationPage(page) {
	const calls = [];
	const chain = (key, values = {}) => new Proxy({}, {
		get(_, property) {
			if (property in values) return values[property];
			if (property === 'length') return 1;
			return (...args) => {
				calls.push({selector: key, method: String(property), args});
				return chain(key);
			};
		}
	});
	const $ = (selector) => {
		if (selector && selector.__chain) return selector;
		const key = typeof selector === 'string' ? selector : 'node';
		return chain(key, typeof page === 'function' ? page(key, chain) || {} : page[key] || {});
	};
	$.contextMenu = (options) => calls.push({selector: 'contextMenu', method: 'contextMenu', args: [options]});

	return {$, calls};
}

function boxEnvironment(saved, page) {
	const storage = {container: 'mdw-SideBarContainer', dock: {enabled: true, top: true, left: true, right: true, bottom: true}, overlay: {enabled: true}, window: {enabled: false}};
	const nav = navigationPage(page);

	return Object.assign(navigationClasses({
		$: nav.$,
		Storages: {localStorage: {isSet: () => true, get: () => storage, set: () => {}}},
		localStorage: {getItem: () => JSON.stringify(saved), setItem: () => {}},
		addEventListener: () => {},
		dispatchEvent: () => {},
		CustomEvent: function() {},
		document: {querySelector: () => null}
	}), {calls: nav.calls});
}

test('a box stored as open in the sidebar starts closed, a docked box keeps its state', () => {
	const markup = [];
	const page = (key) => {
		if (key.trim().startsWith('<div class="mdw-ConsoleNavigationBox"')) {
			markup.push(key);
		}
	};
	const context = boxEnvironment({
		filter: {style: 'overlay', status: 'open'},
		docked: {style: 'dock', dock: 'top-left', status: 'open'}
	}, page);
	const boxes = new context.cactiBox();

	boxes.add({title: 'Filter', helper: 'filter', buttons: {close: false, menu: false, search: ''}});
	boxes.add({title: 'Docked', helper: 'docked', buttons: {close: false, menu: false, search: ''}});

	assert.match(markup[0], /data-status="closed"/);
	assert.match(markup[1], /data-status="open"/);
});

test('docking a box selects its button and keeps the box when the dock side is missing', () => {
	for (const docks of [true, false]) {
		const box = {dataset: {helper: 'filter', dock: 'top-left'}};
		const $box = {
			__chain: true,
			attr: (name) => ({'data-dock': 'top-left', 'class': 'mdw-ConsoleNavigationBox', 'data-helper': 'filter'})[name],
			parent: () => ({is: () => false, find: () => ({each: () => {}})}),
			detach: () => { detached++; return {appendTo: () => {}}; }
		};
		let detached = 0;
		const context = boxEnvironment({}, (key) => {
			if (key === '#mdw-DockLeft > .mdw-DockInnerTop') {
				return {length: docks ? 1 : 0, siblings: () => ({eq: () => ({find: () => ({length: 0}), toggleClass: () => {}})}), find: () => ({length: 0}), toggleClass: () => {}};
			}
		});
		const boxes = new context.cactiBox();

		boxes.add({title: 'Filter', helper: 'filter', buttons: {close: false, menu: true, search: ''}});

		const menu = context.calls.find((call) => call.method === 'contextMenu').args[0];
		menu.items.style.items.dock.callback('dock', {$trigger: {parents: () => [box], closest: () => $box}});

		const buttons = context.calls.filter((call) => call.selector.startsWith('[class^="compact_nav_icon"]') && call.method === 'toggleClass');

		if (docks) {
			assert.equal(detached, 1);
			assert.deepEqual(buttons.map((call) => call.selector), ['[class^="compact_nav_icon"][data-helper="filter"]']);
		} else {
			assert.equal(detached, 0, 'the box must stay in the document');
		}
	}

	assert.doesNotMatch(extract(navigation, '#moveToDock($box'), /console\.log/);
});
