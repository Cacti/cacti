/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

const layoutSource = fs.readFileSync(
	path.join(__dirname, '..', '..', 'include', 'layout.js'),
	'utf8'
);

// Pull the `$.fn.rebind = function ... };` assignment out of layout.js by
// brace-matching so the helper can be exercised without a browser or jQuery.
function rebindSource() {
	const start = layoutSource.indexOf('$.fn.rebind = function');
	assert.notEqual(start, -1, '$.fn.rebind must exist in layout.js');

	const bodyStart = layoutSource.indexOf('{', start);
	let depth = 0;

	for (let offset = bodyStart; offset < layoutSource.length; offset++) {
		if (layoutSource[offset] === '{') {
			depth++;
		} else if (layoutSource[offset] === '}') {
			depth--;

			if (depth === 0) {
				return layoutSource.slice(start, offset + 1) + ';';
			}
		}
	}

	throw new Error('Unable to extract $.fn.rebind');
}

function loadRebind() {
	const $ = { fn: {} };
	vm.runInNewContext(rebindSource(), { $ });

	return $.fn.rebind;
}

// A minimal jQuery-set stand-in that records off()/on() calls and chains.
function makeSet(calls) {
	const set = {
		off(...args) { calls.push(['off', args]); return set; },
		on(...args) { calls.push(['on', args]); return set; },
	};

	return set;
}

const rebind = loadRebind();

test('rebind unbinds then binds a direct handler and returns the set', () => {
	const calls = [];
	const set = makeSet(calls);
	const handler = () => {};

	const result = rebind.apply(set, ['click', handler]);

	assert.deepEqual(calls, [['off', ['click']], ['on', ['click', handler]]]);
	assert.equal(result, set);
});

test('rebind preserves the selector for delegated handlers', () => {
	const calls = [];
	const set = makeSet(calls);
	const handler = () => {};

	rebind.apply(set, ['click', '.item', handler]);

	assert.deepEqual(calls, [['off', ['click', '.item']], ['on', ['click', '.item', handler]]]);
});

test('rebind preserves the selector for the two-argument event-map overload', () => {
	const calls = [];
	const set = makeSet(calls);
	const map = { click: () => {} };

	rebind.apply(set, [map, '.item']);

	assert.deepEqual(calls, [['off', [map, '.item']], ['on', [map, '.item']]]);
});

test('rebind treats a non-string second argument as handler data, not a selector', () => {
	const calls = [];
	const set = makeSet(calls);
	const data = { count: 1 };
	const handler = () => {};

	rebind.apply(set, ['click', data, handler]);

	assert.deepEqual(calls, [['off', ['click']], ['on', ['click', data, handler]]]);
});
