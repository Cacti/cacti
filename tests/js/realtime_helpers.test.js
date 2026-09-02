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

const realtimeSource = fs.readFileSync(
	path.join(__dirname, '..', '..', 'include', 'realtime.js'),
	'utf8'
);

function extractFunction(name) {
	const start = realtimeSource.indexOf(`function ${name}(`);
	assert.notEqual(start, -1, `${name}() must exist in realtime.js`);

	const bodyStart = realtimeSource.indexOf('{', start);
	let depth = 0;

	for (let offset = bodyStart; offset < realtimeSource.length; offset++) {
		if (realtimeSource[offset] === '{') {
			depth++;
		} else if (realtimeSource[offset] === '}') {
			depth--;

			if (depth === 0) {
				return realtimeSource.slice(start, offset + 1);
			}
		}
	}

	throw new Error(`Unable to extract ${name}()`);
}

function load(name, context = {}) {
	return vm.runInNewContext(`${extractFunction(name)}\n${name};`, context);
}

test('realtimeDetectBrowser classifies the common user agents', () => {
	const detect = (ua) => load('realtimeDetectBrowser', { navigator: { userAgent: ua } })();

	assert.equal(detect('Mozilla/5.0 (compatible; MSIE 10.0)'), 'IE');
	assert.equal(detect('Mozilla/5.0 (X11) Chrome/120 Safari/537'), 'Chrome');
	assert.equal(detect('Mozilla/5.0 (X11; Linux) Gecko Firefox/121'), 'FF');
	assert.equal(detect('curl/8.0'), 'Other');
});

test('countRealtimeGraphs counts only the active entries', () => {
	const countRealtimeGraphs = load('countRealtimeGraphs', {
		realtimeArray: { g1: true, g2: false, g3: true, g4: true },
	});

	assert.equal(countRealtimeGraphs(), 3);
	assert.equal(load('countRealtimeGraphs', { realtimeArray: {} })(), 0);
});
