/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 | Licensed under the GNU General Public License, version 2 or later.     |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const inspector = require('node:inspector');

const root = path.join(__dirname, '..', '..');
const sources = Object.fromEntries(['include/layout.js', 'include/realtime.js',
	'include/themes/midwinter/main.js', 'include/themes/sunrise/main.js']
	.map((file) => [file, fs.readFileSync(path.join(root, file), 'utf8')]));

function functionSource(file, name) {
	const source = sources[file];
	const start = source.indexOf(`function ${name}(`);
	assert.notEqual(start, -1);
	return functionAt(source, start);
}

function functionAt(source, start) {
	const body = source.indexOf('{', start);
	let depth = 0;
	for (let offset = body; offset < source.length; offset++) {
		if (source[offset] === '{') depth++;
		if (source[offset] === '}' && --depth === 0) return source.slice(start, offset + 1);
	}
	throw new Error(`Unclosed production function at offset ${start}`);
}

function load(file, names, globals) {
	const context = vm.createContext(globals);
	for (const name of names) {
		vm.runInContext(functionSource(file, name), context, { filename: `cacti-client-state/${name}` });
	}
	return context;
}

const session = new inspector.Session();
session.connect();
function post(method, params = {}) {
	return new Promise((resolve, reject) => session.post(method, params,
		(error, result) => error ? reject(error) : resolve(result)));
}

test.before(async () => {
	await post('Profiler.enable');
	await post('Profiler.startPreciseCoverage', { callCount: true, detailed: true });
});

test.after(async () => {
	try {
		const { result } = await post('Profiler.takePreciseCoverage');
		for (const name of ['themeLoader', 'handlePopState', 'toggleFullscreen']) {
			const ranges = new Map();
			for (const script of result.filter((entry) => entry.url === `cacti-client-state/${name}`)) {
				for (const fn of script.functions.filter((entry) => entry.functionName === name)) {
					for (const range of fn.ranges) {
						const key = `${range.startOffset}:${range.endOffset}`;
						ranges.set(key, (ranges.get(key) || 0) + range.count);
					}
				}
			}
			assert.ok(ranges.size > 0, `${name} must have real V8 coverage`);
			assert.ok([...ranges.values()].every((count) => count > 0), `${name} requires 100% executable range coverage`);
		}
	} finally {
		session.disconnect();
	}
});

test('Midwinter loader respects ready state and a forced reload', () => {
	for (const initial of [null, 'ready', 'loading']) {
		for (const force of [false, true]) {
			const attributes = new Map([['data-theme-state', initial]]);
			const context = load('include/themes/midwinter/main.js',
				['getDocumentAttribute', 'setDocumentAttribute', 'themeLoader'], {
					document: { documentElement: {
						getAttribute: (name) => attributes.get(name) ?? null,
						setAttribute: (name, value) => attributes.set(name, value),
					} },
				});
			context.themeLoader('on', force);
			assert.equal(attributes.get('data-theme-state'), initial === 'ready' && !force ? 'ready' : 'loading');
			context.themeLoader();
			assert.equal(attributes.get('data-theme-state'), 'ready');
		}
	}
});

test('history navigation applies normalized separators while preserving AJAX paths and fragments', () => {
	const cases = [
		{ href: 'https://cacti.test/host.php?&id=1&&page=2', page: 'graphs.php', target: 'https://cacti.test/host.php?id=1&page=2&nostate=true' },
		{ href: 'https://cacti.test/host.php', page: 'graphs.php', target: 'https://cacti.test/host.php?nostate=true' },
		{ href: 'https://cacti.test/host.php?header=false', page: 'graphs.php', ajax: 'https://cacti.test/host.php?header=false&nostate=true' },
		{ href: 'https://cacti.test/graphs.php', page: 'graphs.php', ajax: 'https://cacti.test/graphs.php?header=false&nostate=true' },
		{ href: 'https://cacti.test/graphs.php?id=1', page: 'graphs.php', ajax: 'https://cacti.test/graphs.php?id=1&header=false&nostate=true' },
		{ href: 'https://cacti.test/host.php#section', page: 'graphs.php' },
		{ href: 'https://cacti.test/host.php', page: 'graphs.php', fired: true },
	];
	for (const scenario of cases) {
		const calls = [];
		let target;
		const document = {};
		Object.defineProperty(document, 'location', {
			get: () => ({ href: scenario.href }), set: (value) => { target = value; },
		});
		const context = load('include/layout.js', ['handlePopState'], {
			document, popFired: scenario.fired || false, lastPage: scenario.page,
			basename: (href) => new URL(href).pathname.split('/').pop(),
			loadPageNoHeader: (href) => calls.push(href),
		});
		context.handlePopState();
		assert.equal(target, scenario.target);
		assert.deepEqual(calls, scenario.ajax ? [scenario.ajax] : []);
		assert.equal(context.popFired, true);
		assert.equal(context.lastPage, new URL(scenario.href).pathname.split('/').pop());
	}
});

test('fullscreen entry and exit handle both fulfilled and rejected browser promises', async () => {
	for (const fullscreen of [false, true]) {
		for (const element of [false, 'graph']) {
			for (const reject of [false, true]) {
				const calls = [];
				const errors = [];
				const action = (name) => () => {
					calls.push(name);
					return reject ? Promise.reject(new Error('Denied by browser')) : Promise.resolve();
				};
				const context = load('include/themes/midwinter/main.js', ['toggleFullscreen'], {
					getFullscreenElement: () => fullscreen,
					console: { log: (error) => errors.push(error.message) },
					document: {
						exitFullscreen: action('exit'),
						documentElement: { requestFullscreen: action('document') },
						getElementById: () => ({ requestFullscreen: action('graph') }),
					},
				});
				context.toggleFullscreen(element);
				await new Promise((resolve) => setImmediate(resolve));
				assert.deepEqual(calls, [fullscreen && !element ? 'exit' : element && !fullscreen ? 'graph' : 'document']);
				assert.deepEqual(errors, reject ? ['Denied by browser'] : []);
			}
		}
	}
});

test('realtime image requests stop Pace for every request mode', () => {
	for (const action of ['countdown', 'initial', 'refresh']) {
		const calls = [];
		const window = {};
		const jquery = (selector) => ({
			val: () => ({ '#graph_start': '60', '#ds_step': '5', '#size': '50', '#local_graph_id': '42' })[selector],
			is: () => false, width: () => 600, height: () => 400,
		});
		jquery.getJSON = (url) => {
			calls.push(url);
			return { done: () => ({ fail: () => {} }) };
		};
		let stops = 0;
		const context = load('include/realtime.js', ['getRealtimeSize', 'imageOptionsChanged'], {
			$: jquery, window, Pace: { stop: () => { stops++; } },
			count: 0, rtWidth: 0, rtHeight: 0, local_graph_id: null,
		});
		context.imageOptionsChanged(action);
		assert.equal(stops, 1);
		assert.equal(calls.length, 1);
		assert.match(calls[0], new RegExp(`action=${action}&`));
		assert.match(calls[0], /local_graph_id=42&/);
	}
});

test('Sunrise scroll indicator handles each horizontal and vertical position', () => {
	const source = sources['include/themes/sunrise/main.js'];
	const start = source.indexOf('.scroll(function (event) {') + '.scroll('.length;
	const end = source.indexOf('\n    });', start);
	assert.ok(start > 0 && end > start);
	const callbackSource = source.slice(start, end) + '\n}';
	for (const x of [0, 10]) {
		for (const y of [0, 10]) {
			let color;
			const callback = vm.runInNewContext(`(${callbackSource})`, {
				$: (selector) => selector === '#navigation_right'
					? { scrollLeft: () => x, scrollTop: () => y }
					: { css: (styles) => { color = styles.color; } },
			});
			callback({});
			assert.equal(color, x === 0 && y === 0 ? '' : '#93CEFF');
		}
	}
});

test('wide tables restore hidden cells even when their column indexes exceed nine', () => {
	const headers = Array.from({ length: 12 }, (_, index) => ({
		index, visible: [0, 1, 10, 11].includes(index),
		locked: [0, 10].includes(index), checkbox: index === 11,
	}));
	const cells = headers.map((header) => ({ index: header.index, visible: header.visible }));
	const table = { table: true };
	function collection(nodes) {
		return {
			length: nodes.length,
			get: () => nodes,
			each: (callback) => {
				nodes.forEach((node, index) => callback.call(node, index, node));
				return collection(nodes);
			},
			find: (selector) => collection(selector === 'th' ? headers : selector === 'td' ? cells : selector === 'tr' ? [{}, {}] : []),
			width: () => 300,
			attr: () => 'table-fixture',
			index: () => nodes[0].index,
			css: (property) => property === 'display' ? (nodes[0].visible ? 'table-cell' : 'none') : undefined,
			hasClass: (name) => name === 'noHide' ? nodes[0].locked : name === 'tableSubHeaderCheckbox' && nodes[0].checkbox,
			is: (selector) => selector === ':visible' ? nodes[0].visible : !nodes[0].visible,
			show: () => { nodes.forEach((node) => { node.visible = true; }); },
			hide: () => { nodes.forEach((node) => { node.visible = false; }); },
		};
	}
	const jquery = (value) => value && typeof value.each === 'function' ? value : collection(Array.isArray(value) ? value : [value]);
	jquery.textMetrics = () => ({ width: 5 });
	const context = load('include/layout.js', ['countHiddenCols', 'tuneTable'], { $: jquery, hScroll: false });
	context.tuneTable(table, 500);
	assert.equal(headers.every((header) => header.visible), true);
	assert.equal(cells.every((cell) => cell.visible), true);
});

test('realtime shutdown restores snapshots populated by the layout click handler', () => {
	const initial = '<img id="graph_42" alt="Traffic graph">';
	let content = initial;
	let filters = 0;
	const link = {};
	function jquery(selector) {
		const chain = {
			attr: () => 'graph_42_realtime',
			html: (value) => {
				if (selector === '#wrapper_42') {
					if (value === undefined) return content;
					content = value;
				}
				return chain;
			},
			change: () => chain, empty: () => chain, find: () => chain,
			tooltip: () => chain, children: () => chain, bind: () => chain,
			zoom: () => chain, remove: () => chain, css: () => chain,
		};
		return chain;
	}
	const context = load('include/realtime.js', ['stopRealtime'], {
		$: jquery, realtimeArray: [], keepRealtime: [], timeOffset: 0,
		realtimeClickOn: 'Start', realtimeClickOff: 'Stop',
		setFilters: () => { filters++; }, tuneFilter: () => {}, realtimeGrapher: () => {},
	});
	const layout = functionSource('include/layout.js', 'initializeGraphs');
	const marker = "$(this).off('click').on('click', function(event) {";
	const scope = layout.indexOf("$('a[id$=\"_realtime\"]')");
	assert.notEqual(scope, -1);
	const start = layout.indexOf(marker, scope) + marker.indexOf('function(event)');
	assert.ok(start > 0);
	const click = vm.runInContext(`(${functionAt(layout, start)})`, context);
	click.call(link, { preventDefault: () => {}, stopPropagation: () => {} });
	assert.equal(context.realtimeArray[42], true);
	assert.equal(context.keepRealtime[42], initial);
	content = 'realtime frame';
	context.stopRealtime();
	assert.equal(content, initial);
	assert.equal(context.realtimeArray[42], false);
	assert.equal(filters, 2);
});
