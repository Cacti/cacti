/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../install/install.js'), 'utf8');

function loadInstaller(href) {
	const ready = [];
	const stored = [];
	const control = new Proxy({length: 0}, {
		get: (target, key) => {
			if (key === 'data') {
				return (...args) => {
					stored.push(args);
					return control;
				};
			}

			return key in target ? target[key] : () => control;
		},
	});
	const jquery = arg => {
		if (typeof arg === 'function') {
			ready.push(arg);
		}

		return control;
	};
	jquery.ajaxQ = {abortAll() {}};
	jquery.post = () => ({done() { return this; }, fail() { return this; }});

	const context = {
		$: jquery,
		csrfMagicToken: 'token',
		window: {location: {href}},
		document: {},
		waitForFinalEvent() {},
		setTimeout() {},
	};
	vm.createContext(context);
	vm.runInContext(source, context);
	ready.forEach(callback => callback());

	return stored.filter(args => args[0] === 'installData').map(args => args[1]);
}

test('installer resumes from server state instead of URL step data', () => {
	const data = encodeURIComponent(JSON.stringify({Step: 97, Eula: 1}));
	const seeded = loadInstaller(`https://cacti.example/install/install.php?data=${data}`);

	assert.equal(seeded.length, 1);
	assert.equal(seeded[0], null);
});

test('installer starts without URL data as before', () => {
	const seeded = loadInstaller('https://cacti.example/install/install.php');

	assert.deepEqual(seeded, [null]);
});
