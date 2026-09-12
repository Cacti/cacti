/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

test('state-changing URL fields move into a same-origin POST body with the trusted token', () => {
	const request = context.cactiPreparePostRequestFromUrl('/cacti/cdef.php?action=item_remove&id=7&__csrf_magic=attacker');
	const fields = new URLSearchParams(request.data);

	assert.equal(request.url, '/cacti/cdef.php');
	assert.equal(fields.get('action'), 'item_remove');
	assert.equal(fields.get('id'), '7');
	assert.equal(fields.get('__csrf_magic'), 'trusted-token');
});

test('an explicit POST body cannot override the trusted token', () => {
	const request = context.cactiPreparePostRequest('/cacti/tree.php', 'action=tree_up&id=9&__csrf_magic=attacker');
	const fields = new URLSearchParams(request.data);

	assert.equal(fields.get('__csrf_magic'), 'trusted-token');
	assert.equal(fields.get('action'), 'tree_up');
});

test('cross-origin targets are rejected before a token is exposed', () => {
	assert.throws(
		() => context.cactiPreparePostRequestFromUrl('https://attacker.example/delete?action=item_remove'),
		/different origin/,
	);
});
