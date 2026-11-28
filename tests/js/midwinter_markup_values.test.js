/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const root = path.join(__dirname, '..', '..');
const theme = fs.readFileSync(path.join(root, 'include', 'themes', 'midwinter', 'main.js'), 'utf8');

function extract(startText) {
	const start = theme.indexOf(startText);
	assert.notEqual(start, -1, `${startText} must exist`);
	let depth = 0;

	for (let offset = theme.indexOf('{', start); offset < theme.length; offset++) {
		if (theme[offset] === '{') depth++;
		if (theme[offset] === '}' && --depth === 0) return theme.slice(start, offset + 1);
	}

	throw new Error(`Unable to extract ${startText}`);
}

function storageContext(initial) {
	const values = new Map(Object.entries(initial));
	const attributes = {};
	const context = {
		Storages: {localStorage: {
			isSet: (name) => values.has(name),
			get: (name) => values.get(name),
			set: (name, value) => values.set(name, value)
		}},
		setDocumentAttribute: (name, value) => { attributes[name] = value; }
	};

	vm.runInNewContext(
		extract('const midwinterStorageValues').replace('const ', 'var ') + ';\n' +
		extract('function midwinterEscape(') + '\n' +
		extract('function initStorageItem('), context);

	return {context, values, attributes};
}

test('midwinterEscape encodes every character that can leave text or an attribute value', () => {
	const {context} = storageContext({});

	assert.equal(context.midwinterEscape(`<a href="x" id='y'>&\``),
		'&lt;a href=&quot;x&quot; id=&#39;y&#39;&gt;&amp;&#x60;');
	assert.equal(context.midwinterEscape(undefined), '');
	assert.equal(context.midwinterEscape(null), '');
	assert.equal(context.midwinterEscape(12), '12');
});

test('a stored preference outside the values the theme writes falls back to its default', () => {
	const {context, values, attributes} = storageContext({midWinter_Font_Size: '<b>huge</b>', midWinter_Color_Mode: 'light'});

	assert.equal(context.initStorageItem('midWinter_Font_Size', 'regular', 'zoom-level'), 'regular');
	assert.equal(values.get('midWinter_Font_Size'), 'regular');
	assert.equal(attributes['zoom-level'], 'regular');

	assert.equal(context.initStorageItem('midWinter_Color_Mode', 'dark'), 'light');
	assert.equal(context.initStorageItem('midWinter_GUI_Mode', 'compact'), 'compact');
});

test('every value the theme toggles between is accepted on the next load', () => {
	const written = {
		midWinter_GUI_Mode: ['compact', 'standard'],
		midWinter_Color_Mode: ['dark', 'light'],
		midWinter_Color_Mode_Auto: ['on', 'off'],
		midWinter_Font_Size: ['small', 'regular', 'large']
	};

	for (const [name, list] of Object.entries(written)) {
		for (const value of list) {
			const {context} = storageContext({[name]: value});

			assert.equal(context.initStorageItem(name, 'default'), value, `${name}=${value}`);
		}
	}
});

test('page, storage and client values are encoded before they are joined into markup', () => {
	const dynamic = [
		`$(this).attr('href')`,
		`$('.text_' + id).text()`,
		`$('.loggedInAs').text()`,
		'midWinter_Font_Size',
		'env.browser.name',
		'env.browser.version',
		'env.os.name',
		'env.os.version',
		'env.device.type'
	];

	for (const line of theme.split('\n')) {
		if (!/^\s*(\+|compact_tab_menu_content \+=)/.test(line) || !line.includes("'<")) continue;

		for (const value of dynamic) {
			let at = line.indexOf(value);

			while (at !== -1) {
				const before = line.slice(0, at);
				const guarded = before.endsWith('midwinterEscape(') ||
					/\?\s*'-'\s*:\s*midwinterEscape\($/.test(before) ||
					/==\s*undefined\)/.test(line.slice(at + value.length, at + value.length + 16));

				assert.ok(guarded, `unencoded ${value} in: ${line.trim()}`);
				at = line.indexOf(value, at + value.length);
			}
		}
	}
});
