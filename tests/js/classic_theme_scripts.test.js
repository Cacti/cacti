/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const root = path.join(__dirname, '..', '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const classicThemes = ['cacti', 'raspberry', 'paw', 'deepness', 'paper-plane', 'sunrise', 'dark', 'modern'];

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

// A chainable jQuery stand-in that records each call with the selector it was made on.
function fakeJQuery() {
	const calls = [];
	const $ = (selector) => {
		const key = typeof selector === 'string' ? selector : 'node';
		const chain = new Proxy({}, {
			get(_, property) {
				if (property === 'length') return 0;
				if (property === Symbol.toPrimitive) return () => 0;
				if (property === 'is') return () => false;
				return (...args) => {
					calls.push({selector: key, method: String(property), args});
					return chain;
				};
			}
		});

		return chain;
	};

	return {$, calls};
}

test('dark and modern add the window resize handler once and still lay out each page', () => {
	for (const name of ['dark', 'modern']) {
		const page = fakeJQuery();
		let bound = 0;
		const keepWindowSize = () => bound++;
		const context = {
			$: page.$,
			window: {},
			keepWindowSize,
			setMenuVisibility: () => {},
			setNavigationScroll: () => {},
			setTimeout: () => {},
			noFileSelected: ''
		};

		vm.runInNewContext(extract(read(`include/themes/${name}/main.js`), 'function themeReady('), context);

		context.themeReady();
		context.themeReady();
		context.themeReady();

		assert.equal(bound, 1, `${name}: one resize handler`);
		assert.equal(page.calls.filter((call) => call.method === 'trigger' && call.args[0] === 'resize').length, 2, `${name}: later page loads re-run the layout`);
	}
});

test('classic theme scripts do not assign undeclared globals or keep dead handlers', () => {
	for (const name of classicThemes) {
		const source = read(`include/themes/${name}/main.js`);

		for (const line of source.split('\n')) {
			assert.doesNotMatch(line, /^\s*(id|text|color|maxWidth)\s*=/, `${name}: ${line.trim()}`);
		}

		assert.doesNotMatch(source, /\.checkboxgroup'/, `${name}: core renders checkboxgroup1`);
		assert.doesNotMatch(source, /\$\('\.colordropdown'\)/, `${name}: core handles color selects with dropcolor()`);
	}
});
