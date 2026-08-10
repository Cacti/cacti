/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
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

const installerSource = fs.readFileSync(
	path.join(__dirname, '..', '..', 'install', 'install.js'),
	'utf8'
);

function loadInstaller() {
	const state = {
		headerAvailable: true,
		headerChecked: false,
		selectAllCalls: [],
		setFieldDataCalls: [],
	};

	const header = {
		length: 1,
		data: (name) => name === 'prefix' ? 'chk' : undefined,
		prop: (name, value) => {
			if (name === 'checked') {
				state.headerChecked = value;
			}

			return header;
		},
	};

	function jquery(selector) {
		if (typeof selector === 'function') {
			return undefined;
		}

		if (selector === '#selectall') {
			return state.headerAvailable ? header : { length: 0 };
		}

		return { length: 0 };
	}

	const context = vm.createContext({
		$: jquery,
		console,
	});

	vm.runInContext(installerSource, context, { filename: 'install/install.js' });

	context.selectAll = (prefix, checked) => {
		state.selectAllCalls.push({ prefix, checked });
	};
	context.setFieldData = (fields, selection) => {
		state.setFieldDataCalls.push({ fields, selection });
	};

	return { context, state };
}

test('template defaults select every rendered row', () => {
	const { context, state } = loadInstaller();

	context.processStepTemplateInstall({ Templates: { all: true } });

	assert.equal(state.headerChecked, true);
	assert.deepEqual(state.selectAllCalls, [{ prefix: 'chk', checked: true }]);
	assert.equal(state.setFieldDataCalls.length, 0);
});

test('table conversion defaults select every rendered row', () => {
	const { context, state } = loadInstaller();

	context.processStepCheckTables({ Tables: { all: true } });

	assert.equal(state.headerChecked, true);
	assert.deepEqual(state.selectAllCalls, [{ prefix: 'chk', checked: true }]);
	assert.equal(state.setFieldDataCalls.length, 0);
});

test('saved template selections are restored individually', () => {
	const { context, state } = loadInstaller();
	const templates = { all: false, chk_template_local: true };

	context.processStepTemplateInstall({ Templates: templates });

	assert.equal(state.headerChecked, false);
	assert.equal(state.selectAllCalls.length, 0);
	assert.equal(state.setFieldDataCalls.length, 1);
	assert.deepEqual(state.setFieldDataCalls[0].selection, templates);
});

test('saved table selections are restored individually', () => {
	const { context, state } = loadInstaller();
	const tables = { all: false, chk_table_host: true };

	context.processStepCheckTables({ Tables: tables });

	assert.equal(state.headerChecked, false);
	assert.equal(state.selectAllCalls.length, 0);
	assert.equal(state.setFieldDataCalls.length, 1);
	assert.deepEqual(state.setFieldDataCalls[0].selection, tables);
});

test('missing select-all control does not abort installer rendering', () => {
	const { context, state } = loadInstaller();
	state.headerAvailable = false;

	assert.doesNotThrow(() => {
		context.processStepTemplateInstall({ Templates: { all: true } });
	});
	assert.equal(state.selectAllCalls.length, 0);
});
