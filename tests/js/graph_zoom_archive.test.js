/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../include/js/jquery.zoom.js'), 'utf8');
const functions = ['zoomGetElement', 'zoomGetImageId', 'zoomGetId'].map(name => {
	const match = source.match(new RegExp('function ' + name + '\\([^]*?\\n\\t\\t}'));
	assert.ok(match, name + ' must exist');
	return match[0];
}).join('\n');
const context = vm.createContext({});
vm.runInContext(functions, context);

for (const archive of [0, '0', 1, '4']) {
	test('zoom selects and identifies archive ' + JSON.stringify(archive), () => {
		const image = {attr: name => name === 'id' ? 'graph_150' : archive};
		const zoom = {image: {reference: 'graph_150', rra_id: archive}};
		assert.equal(context.zoomGetElement(zoom), "#graph_150[rra_id='" + archive + "']");
		assert.equal(context.zoomGetImageId(image), 'graph_150_rra' + archive);
		assert.equal(context.zoomGetId(zoom), context.zoomGetImageId(image));
	});
}

for (const archive of [undefined, null, '', -1, 'all']) {
	test('zoom preserves the unqualified selector for absent or invalid archive ' + String(archive), () => {
		const image = {attr: name => name === 'id' ? 'graph_150' : archive};
		const zoom = {image: {reference: 'graph_150', rra_id: archive}};
		assert.equal(context.zoomGetElement(zoom), '#graph_150');
		assert.equal(context.zoomGetImageId(image), 'graph_150');
		assert.equal(context.zoomGetId(zoom), 'graph_150');
	});
}

test('the two-year panel cannot reuse the daily panel zoom identity', () => {
	const image = archive => ({attr: name => name === 'id' ? 'graph_150' : archive});
	assert.notEqual(context.zoomGetImageId(image('0')), context.zoomGetImageId(image('1')));
	assert.notEqual(context.zoomGetImageId(image('0')), context.zoomGetImageId(image(undefined)));
});
