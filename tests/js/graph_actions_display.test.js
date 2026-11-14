// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../include/layout.js'), 'utf8');
const start = source.indexOf('function initGraphDrilldown()');
const end = source.indexOf('\nfunction initializeGraphs(', start);
assert.ok(start >= 0 && end > start);

function initialize(links, withSpikeKill = true) {
	const handlers = new Map();
	const document = {};
	let closed = 0;
	let focused = 0;
	const wrapper = item => ({
		attr(name, value) {
			if (value === undefined) return item[name];
			item[name] = value;
			return this;
		},
		find(selector) {
			if (selector === '.iconLink.spikekill') return { trigger: event => { if (event === 'focus') focused++; } };
			return { first: () => wrapper(item.glyph || {}) };
		}
	});
	const events = {
		off: () => events,
		on(event, selector, handler) { handlers.set(event + ':' + selector, handler); return events; }
	};
	const $ = item => item === document ? events : typeof item === 'string'
		? { each: fn => links.forEach(link => fn.call(link)) } : wrapper(item);
	const context = { $, document, setTimeout };
	if (withSpikeKill) context.spikeKillClose = () => { closed++; };
	vm.runInNewContext(source.slice(start, end) + '\ninitGraphDrilldown();', context);
	return { handlers, counts: () => ({ closed, focused }) };
}

test('visible graph actions gain accessible names while keeping their tooltips', () => {
	const links = [{ glyph: { title: 'Details' } }, { title: 'Plugin action' }, { 'aria-label': 'Custom name', glyph: { title: 'Icon tooltip' } }];
	initialize(links);
	assert.deepEqual(links.map(link => link['aria-label']), ['Details', 'Plugin action', 'Custom name']);
	assert.equal(links[0].glyph.title, 'Details');
	assert.equal(links[1].title, 'Plugin action');
});

test('Escape closes the visible-icons submenu and returns focus without running an action', () => {
	const state = initialize([]);
	const handler = state.handlers.get('keydown.graphdd:.graphDrillDownAlways');
	handler.call({}, { keyCode: 9 });
	assert.deepEqual(state.counts(), { closed: 0, focused: 0 });
	handler.call({}, { keyCode: 27 });
	assert.deepEqual(state.counts(), { closed: 1, focused: 1 });
});

test('visible-icons Escape is safe when SpikeKill is unavailable', () => {
	const state = initialize([], false);
	assert.doesNotThrow(() => state.handlers.get('keydown.graphdd:.graphDrillDownAlways').call({}, { keyCode: 27 }));
});
