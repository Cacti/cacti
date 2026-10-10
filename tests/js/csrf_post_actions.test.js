/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
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
		if (source[offset] === '{') depth++;
		if (source[offset] === '}' && --depth === 0) return source.slice(start, offset + 1);
	}

	throw new Error(`Unable to extract ${name}()`);
}

const source = fs.readFileSync(path.join(__dirname, '..', '..', 'include', 'layout.js'), 'utf8');
const context = {
	URL,
	URLSearchParams,
	csrfMagicToken: 'trusted-token',
	window: {location: {href: 'https://cacti.example/cacti/', origin: 'https://cacti.example'}},
	$: {
		extend: (target, sourceValue) => Object.assign(target, sourceValue),
		param: (fields) => new URLSearchParams(fields.map(({name, value}) => [name, value])).toString(),
	},
};

vm.runInNewContext(`${extractFunction(source, 'cactiPreparePostRequest')}\n${extractFunction(source, 'cactiPreparePostRequestFromUrl')}`, context);

for (const page of ['links.php', 'plugins.php']) {
	test(`${page} drag-and-drop sends the serialized order and trusted CSRF token in a POST body`, () => {
		const pageSource = fs.readFileSync(path.join(__dirname, '..', '..', page), 'utf8');
		const callback = pageSource.match(/const request = cactiPreparePostRequest\([^\n]+\);\s*postUrl\(\{url: request\.url\}, request\.data\);/);
		assert.ok(callback, 'drag-and-drop must use the POST helper');
		let sent;
		const callbackContext = {...context,
			$: {...context.$, tableDnD: {serialize: () => 'dnd%5B%5D=2&dnd%5B%5D=1'}},
			postUrl: (options, data) => { sent = {options, data}; },
		};
		vm.runInNewContext(callback[0], callbackContext);
		assert.equal(sent.options.url, `/cacti/${page}`);
		const fields = new URLSearchParams(sent.data);
		assert.equal(fields.get('action'), 'ajax_dnd');
		assert.deepEqual(fields.getAll('dnd[]'), ['2', '1']);
		assert.equal(fields.get('__csrf_magic'), 'trusted-token');
	});
}

test('state-changing URL fields move into a same-origin POST body with the trusted token', () => {
	const request = context.cactiPreparePostRequestFromUrl('/cacti/cdef.php?action=item_remove&id=7&__csrf_magic=attacker');
	const fields = new URLSearchParams(request.data);

	assert.equal(request.url, '/cacti/cdef.php');
	assert.equal(fields.get('action'), 'item_remove');
	assert.equal(fields.get('id'), '7');
	assert.equal(fields.get('__csrf_magic'), 'trusted-token');
});

test('an explicit POST body cannot override the trusted token', () => {
	const request = context.cactiPreparePostRequest('/cacti/tree.php', 'action=tree_up&__csrf_magic=attacker&id=9');
	const fields = new URLSearchParams(request.data);

	assert.equal(fields.get('__csrf_magic'), 'trusted-token');
	assert.equal(fields.get('action'), 'tree_up');
	assert.equal(fields.get('id'), '9');
	assert.doesNotMatch(request.data, /&&/);
});

test('cross-origin targets are rejected before a token is exposed', () => {
	assert.throws(
		() => context.cactiPreparePostRequestFromUrl('https://attacker.example/delete?action=item_remove'),
		/different origin/,
	);
});

test('the state-action handler survives element-level unbind calls', () => {
	assert.match(source, /\$\(document\)\.off\('click\.cactiPostAction', 'a\.cactiPostAction'\)\.on\('click\.cactiPostAction', 'a\.cactiPostAction'/);
	assert.doesNotMatch(source, /\$\('a\.cactiPostAction'\)\.off\('click\.cactiPostAction'/);
});
