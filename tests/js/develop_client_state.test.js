/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const root = path.resolve(__dirname, '../..');

function source(file) {
	return fs.readFileSync(path.join(root, file), 'utf8');
}

function functionAt(text, start) {
	assert.ok(start >= 0);
	const body = text.indexOf('{', start);
	let depth = 0;
	for (let offset = body; offset < text.length; offset++) {
		if (text[offset] === '{') depth++;
		if (text[offset] === '}' && --depth === 0) return text.slice(start, offset + 1);
	}
	throw new Error('Unclosed production function');
}

function load(file, names, globals) {
	const text = source(file);
	const context = vm.createContext(globals);
	for (const name of names) {
		vm.runInContext(functionAt(text, text.indexOf(`function ${name}(`)), context);
	}
	return context;
}

test('fullscreen controls reconcile state after successful and rejected requests', async () => {
	for (const fullscreen of [false, true]) {
		for (const rejected of [false, true]) {
			let entries = 0;
			let exits = 0;
			let updates = 0;
			const result = () => rejected ? Promise.reject(new Error('Browser denied request')) : Promise.resolve();
			const context = load('include/themes/midwinter/main.js', ['fullScreen'], {
				document: {
					fullscreenElement: fullscreen,
					documentElement: { requestFullscreen: () => { entries++; return result(); } },
					exitFullscreen: () => { exits++; return result(); },
				},
				fullScreenChangeHandler: () => { updates++; },
			});
			context.fullScreen({});
			await new Promise(resolve => setImmediate(resolve));
			assert.equal(entries, fullscreen ? 0 : 1);
			assert.equal(exits, fullscreen ? 1 : 0);
			assert.equal(updates, 1);
		}
	}
	const context = load('include/themes/midwinter/main.js', ['fullScreen'], {
		document: { fullscreenElement: true },
	});
	assert.doesNotThrow(() => context.fullScreen({}));
});

test('toast accepts primitive and boxed strings without leaking global state', () => {
	const text = source('include/js/ui-notices.js');
	const start = text.indexOf('function(options)');
	let rendered;
	const jquery = {
		extend: (...options) => Object.assign({}, ...options),
		toast: options => { rendered = options; return options; },
	};
	const toast = vm.runInNewContext(`(${functionAt(text, start)})`, { $: jquery, String });
	for (const value of ['plain', new String('boxed'), { text: 'configured' }, undefined]) {
		toast(value);
		assert.equal(typeof rendered.text, 'string');
		assert.equal(rendered.text, value === undefined ? 'No text was set, please check the usage of showToast' : typeof value === 'object' && !(value instanceof String) ? value.text : String(value));
	}
});

test('realtime requests stop Pace for countdown, initial load and refresh', () => {
	for (const action of ['countdown', 'initial', 'refresh']) {
		let stops = 0;
		let requested;
		const jquery = selector => ({
			val: () => ({ '#graph_start': '60', '#ds_step': '5', '#size': '50', '#local_graph_id': '42' })[selector],
			is: () => false, width: () => 600, height: () => 400,
		});
		jquery.getJSON = url => { requested = url; return { done: () => ({ fail: () => {} }) }; };
		const context = load('include/realtime.js', ['imageOptionsChanged'], {
			$: jquery, window: {}, Pace: { stop: () => { stops++; } },
			count: 0, rtWidth: 0, rtHeight: 0, local_graph_id: null,
		});
		context.imageOptionsChanged(action);
		assert.equal(stops, 1);
		assert.match(requested, new RegExp(`action=${action}&`));
		assert.match(requested, /local_graph_id=42&/);
	}
});

test('Sunrise scroll indicator covers horizontal and vertical scrolling', () => {
	const text = source('include/themes/sunrise/main.js');
	const marker = ".rebind('scroll', ";
	const start = text.indexOf(marker) + marker.length;
	for (const x of [0, 10]) {
		for (const y of [0, 10]) {
			let color;
			const callback = vm.runInNewContext(`(${functionAt(text, start)})`, {
				$: selector => selector === '#navigation_right'
					? { scrollLeft: () => x, scrollTop: () => y }
					: { css: styles => { color = styles.color; } },
			});
			callback({});
			assert.equal(color, x === 0 && y === 0 ? '' : '#93CEFF');
		}
	}
});

test('navigation membership handles the console, matching and absent links', () => {
	const jquery = () => ({ find: () => ({ each: callback => {
		for (const href of ['host.php', 'graphs.php']) callback.call({ href });
	} }) });
	const context = load('include/layout.js', ['userMenuNavigationExists'], {
		$: value => typeof value === 'object' ? { attr: () => value.href } : jquery(),
		basename: value => value.split('/').pop(),
	});
	for (const [url, expected] of [['index.php', true], ['/cacti/index.php', true], ['graphs.php', true], ['missing.php', false]]) {
		assert.equal(context.userMenuNavigationExists(url), expected);
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
			trigger: () => chain, empty: () => chain, find: () => chain,
			tooltip: () => chain, children: () => chain, on: () => chain,
			zoom: () => chain, remove: () => chain, css: () => chain,
		};
		return chain;
	}
	const context = load('include/realtime.js', ['stopRealtime'], {
		$: jquery, realtimeArray: [], keepRealtime: [], timeOffset: 0,
		realtimeClickOn: 'Start', realtimeClickOff: 'Stop', urlPath: '',
		setFilters: () => { filters++; }, tuneFilter: () => {}, realtimeGrapher: () => {}, shouldCaptureClick: () => true,
	});
	const layout = functionAt(source('include/layout.js'), source('include/layout.js').indexOf('function initializeGraphs('));
	const marker = "$(this).rebind('click', function (event) {";
	const scope = layout.indexOf("$('a[id$=\"_realtime\"]')");
	assert.notEqual(scope, -1);
	const start = layout.indexOf(marker, scope) + marker.indexOf('function (event)');
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
