/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

function extractFunction(source, name) {
	const start = source.indexOf(`function ${name}(`);
	assert.notEqual(start, -1, `${name}() must exist`);

	const bodyStart = source.indexOf('{', start);
	let depth = 0;

	for (let offset = bodyStart; offset < source.length; offset++) {
		if (source[offset] === '{') {
			depth++;
		} else if (source[offset] === '}') {
			depth--;

			if (depth === 0) {
				return source.slice(start, offset + 1);
			}
		}
	}

	throw new Error(`Unable to extract ${name}()`);
}

const layoutSource = fs.readFileSync(path.join(__dirname, '..', '..', 'include', 'layout.js'), 'utf8');
const themeSource = fs.readFileSync(path.join(__dirname, '..', '..', 'include', 'themes', 'midwinter', 'main.js'), 'utf8');

test('tab cookie is transient scoped and secure on HTTPS', () => {
	for (const protocol of ['http:', 'https:']) {
		const document = { cookie: '' };
		const context = { document, sessionStorage: { tab: 'tab-7' }, urlPath: '/cacti/', window: { location: { protocol } } };
		const setCactiTabCookie = vm.runInNewContext(`${extractFunction(layoutSource, 'setCactiTabCookie')}\nsetCactiTabCookie;`, context);

		setCactiTabCookie();

		assert.match(document.cookie, /^CactiTab=tab-7; Max-Age: -1;/);
		assert.match(document.cookie, / Path=\/cacti\/$/);
		assert.match(document.cookie, protocol === 'https:' ? /SameSite=None; Secure;/ : /SameSite=Strict;/);
	}
});

test('timezone cookies are transient strict scoped and HTTPS-only secure', () => {
	const writes = [];
	const document = {};
	Object.defineProperty(document, 'cookie', { set: (value) => writes.push(value) });
	const context = { Date, document, urlPath: '/cacti/', window: { location: { protocol: 'https:' } } };
	const setZoneInfo = vm.runInNewContext(`${extractFunction(layoutSource, 'setZoneInfo')}\nsetZoneInfo;`, context);

	setZoneInfo();

	assert.equal(writes.length, 2);
	for (const cookie of writes) {
		assert.match(cookie, /Max-Age: -1; path=\/cacti\/; SameSite=Strict; Secure;/);
	}
});

test('theme cookie uses the configured path lifetime and transport security', () => {
	const calls = [];
	const jquery = { cookie: (...args) => { calls.push(args); return args.length === 1 ? 'dark' : undefined; } };
	const context = { $: jquery, urlPath: '/cacti/', window: { location: { protocol: 'https:' } } };

	vm.runInNewContext(`${extractFunction(themeSource, 'setCookieValue')}\n${extractFunction(themeSource, 'getCookieValue')}`, context);
	context.setCookieValue('CactiColorMode', 'dark');

	assert.equal(calls[0][0], 'CactiColorMode');
	assert.equal(calls[0][1], 'dark');
	assert.equal(calls[0][2].expires, 365);
	assert.equal(calls[0][2].path, '/cacti/;SameSite=Lax');
	assert.equal(calls[0][2].secure, true);
	assert.equal(context.getCookieValue('CactiColorMode'), 'dark');
});
