/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const root = path.join(__dirname, '..', '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const theme = read('include/themes/midwinter/main.js');
const navigation = read('include/js/navigationBox.js');
const tableLayout = read('include/js/navigationBox.tableLayout.js');

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

function themeContext(globals = {}) {
	const context = {window: globals, ...globals};
	const actions = theme.slice(theme.indexOf('const midwinterThemeActions'), theme.indexOf('];', theme.indexOf('const midwinterThemeActions')) + 2);

	vm.runInNewContext(
		actions.replace('const ', 'var ') + '\n' +
		extract(theme, 'function is_function(') + '\n' +
		extract(theme, 'function midwinterEscape(') + '\n' +
		extract(theme, 'function midwinterThemeSession('), context);

	return context;
}

test('midwinterEscape encodes every character that can leave text or an attribute value', () => {
	const context = themeContext();

	assert.equal(context.midwinterEscape(`<a href="x" id='y'>&\``),
		'&lt;a href=&quot;x&quot; id=&#39;y&#39;&gt;&amp;&#x60;');
	assert.equal(context.midwinterEscape(undefined), '');
	assert.equal(context.midwinterEscape(75), '75');
});

test('the navigation helper encodes the same characters', () => {
	const context = {};

	vm.runInNewContext('var helper = {' + extract(navigation, '_escape(value)') + '};', context);
	assert.equal(context.helper._escape(`"><i x='1'>&\``), '&quot;&gt;&lt;i x=&#39;1&#39;&gt;&amp;&#x60;');
	assert.equal(context.helper._escape(null), '');
});

test('stored theme preferences are rebuilt from known values only', () => {
	const context = themeContext();
	const plain = (value) => JSON.parse(JSON.stringify(value));
	const defaults = {theme: {
		boxes: {animated: 'on'}, color: {mode: 'dark', auto: 'off'},
		controls: {subTitle: 'off', tooltip: 'on'}, font: {zoom: 75}, mobile: {autoTableLayout: 'off'}
	}};

	assert.deepEqual(plain(context.midwinterThemeSession(null)), defaults);
	assert.deepEqual(plain(context.midwinterThemeSession('text')), defaults);
	assert.deepEqual(plain(context.midwinterThemeSession({theme: {font: {zoom: '"><b>'}, color: {mode: '<i>', auto: 1}, boxes: 'x'}})), defaults);

	const kept = {theme: {
		boxes: {animated: 'off'}, color: {mode: 'light', auto: 'on'},
		controls: {subTitle: 'on', tooltip: 'off'}, font: {zoom: 62.5}, mobile: {autoTableLayout: 'on'}
	}};

	assert.deepEqual(plain(context.midwinterThemeSession(kept)), kept);
	assert.equal(context.midwinterThemeSession({theme: {font: {zoom: '87.5'}}}).theme.font.zoom, 87.5);
	assert.equal(context.midwinterThemeSession({theme: {font: {zoom: 400}}}).theme.font.zoom, 75);
});

test('data-func reaches theme actions only', () => {
	const context = themeContext({toggleGuiAnimations: () => {}, loadUrl: () => {}, kioskMode: () => {}});

	assert.equal(context.is_function('toggleGuiAnimations'), true);
	assert.equal(context.is_function('loadUrl'), false);
	assert.equal(context.is_function('kioskMode'), false);
	assert.equal(context.is_function('toggleColorModeAuto'), false, 'listed but not defined');

	for (const name of theme.match(/data-func="([A-Za-z_]+)"/g).map((attribute) => attribute.slice(11, -1))) {
		assert.ok(theme.includes(`'${name}'`), `${name} is used in markup and must be listed`);
	}
});

test('page and stored values are encoded where they are joined into markup', () => {
	assert.match(theme, /href="' \+ midwinterEscape\(\$\(this\)\.attr\('href'\)\) \+ '">' \+ midwinterEscape\(\$\('\.text_' \+ id\)\.text\(\)\)/);
	assert.doesNotMatch(theme, /' \+ \$\('\.text_' \+ id\)\.text\(\) \+ '/);
	assert.match(theme, /mdw\.session = midwinterThemeSession\(stored\)/);
	assert.match(tableLayout, /const title = midwinter\.navigationBox\._escape\(storedTitle\)/);
	assert.match(navigation, /id = String\(id\)\.replace\(\/\[\^\\w-\]\/g, ''\)/);

	for (const name of ['config.status', 'layout.height', 'width', 'layout.align', 'layout.style', 'layout.dock', 'layout.overlay', 'layout.exclusive']) {
		assert.ok(navigation.includes('="${esc(' + name + ')}"'), `${name} must be encoded`);
	}
});

test('external links in the classic themes open without a window reference', () => {
	for (const name of ['cacti', 'raspberry', 'paw', 'deepness', 'paper-plane', 'sunrise']) {
		const source = read(`include/themes/${name}/main.js`);

		for (const line of source.split('\n')) {
			if (!line.includes('target="_blank')) continue;

			assert.ok(line.includes('target="_blank" rel="noopener'), `${name}: ${line.trim()}`);
		}
	}
});
