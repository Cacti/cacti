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

// Pull a single function's source out of layout.js by brace-matching, so the
// helper can be exercised in isolation without a browser or the full bundle.
function extractFunction(name) {
	const start = layoutSource.indexOf(`function ${name}(`);
	assert.notEqual(start, -1, `${name}() must exist in layout.js`);

	const bodyStart = layoutSource.indexOf('{', start);
	let depth = 0;

	for (let offset = bodyStart; offset < layoutSource.length; offset++) {
		if (layoutSource[offset] === '{') {
			depth++;
		} else if (layoutSource[offset] === '}') {
			depth--;

			if (depth === 0) {
				return layoutSource.slice(start, offset + 1);
			}
		}
	}

	throw new Error(`Unable to extract ${name}()`);
}

function load(name, context = {}) {
	return vm.runInNewContext(`${extractFunction(name)}\n${name};`, context);
}

test('escapeString neutralises the HTML metacharacters used for XSS', () => {
	const escapeString = load('escapeString');

	assert.equal(escapeString('<script>'), '&lt;script&gt;');
	assert.equal(escapeString(`"'` + '`'), '&quot;&#39;&#x60;');
	assert.equal(escapeString('a & b'), 'a & b'); // ampersand is intentionally left alone
	assert.equal(escapeString('plain'), 'plain');
	assert.equal(escapeString(42), '42'); // coerces non-strings
});

test('basename returns the file portion of a path', () => {
	const basename = load('basename');

	assert.equal(basename('/usr/local/graph_view.php'), 'graph_view.php');
	assert.equal(basename('graph_view.php?action=edit'), 'graph_view.php');
	assert.equal(basename('/x/y/report.php', '.php'), 'report');
	assert.equal(basename('graphs.php'), 'graphs.php');
	assert.equal(basename('/trailing/'), 'index.php'); // empty result falls back
});

test('getTreeSearchWidth keeps the search box within bounds', () => {
	const getTreeSearchWidth = load('getTreeSearchWidth');

	assert.equal(getTreeSearchWidth(200), 130); // navWidth - 70
	assert.equal(getTreeSearchWidth(100), 40);  // clamped to the 40 floor
	assert.equal(getTreeSearchWidth(50), 40);
});

test('getTimestampFromDate converts a MySQL datetime to epoch seconds', () => {
	const getTimestampFromDate = load('getTimestampFromDate', { Date });

	const expected = new Date(2020, 0, 15, 13, 30).getTime() / 1000;
	assert.equal(getTimestampFromDate('2020-01-15 13:30:00'), expected);
	assert.equal(getTimestampFromDate(undefined), '');
});

test('getQueryString reads a named parameter from the query string', () => {
	const getQueryString = load('getQueryString', {
		window: { location: { search: '?action=edit&id=42&q=a+b' } },
		decodeURIComponent,
		RegExp,
	});

	assert.equal(getQueryString('action'), 'edit');
	assert.equal(getQueryString('id'), '42');
	assert.equal(getQueryString('q'), 'a b'); // + decodes to space
	assert.equal(getQueryString('missing'), null);
});

test('base64_encode round-trips UTF-8 text through btoa', () => {
	const base64_encode = load('base64_encode', { btoa, unescape, encodeURIComponent });

	assert.equal(base64_encode('Cacti'), 'Q2FjdGk=');
	assert.equal(base64_encode(''), '');
	// multi-byte characters survive the encodeURIComponent/unescape dance
	assert.equal(base64_encode('café'), Buffer.from('café', 'utf8').toString('base64'));
});

test('countHiddenCols counts the header cells hidden with display:none', () => {
	function jqueryFor(displays) {
		const cells = displays.map((display) => ({ display }));

		return function $(arg) {
			if (arg && typeof arg.display === 'string') {
				return { css: (prop) => (prop === 'display' ? arg.display : undefined) };
			}

			return {
				find: () => ({
					each: (callback) => cells.forEach((cell) => callback.call(cell)),
				}),
			};
		};
	}

	const countHiddenCols = load('countHiddenCols', { $: jqueryFor(['none', 'table-cell', 'none', 'block']) });

	assert.equal(countHiddenCols('#atable'), 2);
	assert.equal(load('countHiddenCols', { $: jqueryFor([]) })('#empty'), 0);
});
