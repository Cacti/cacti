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

const layoutSource = fs.readFileSync(
	path.join(__dirname, '..', '..', 'include', 'layout.js'),
	'utf8'
);

function extractFunction(name) {
	const start = layoutSource.indexOf(`function ${name}(`);

	assert.notEqual(start, -1, `${name}() must exist`);

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

test('scroll handlers close initialized select menus without duplicate bindings', () => {
	const windowObject = {};
	const selects = [{ initialized: true, closes: 0 }, { initialized: false, closes: 0 }];
	let boundEvent;
	let menuOpen = true;
	let selectScans = 0;
	let scrollHandler;
	let selectedContainers;

	function jquery(selector) {
		if (typeof selector === 'string' && selector.startsWith('.cactiConsoleContentArea')) {
			selectedContainers = selector;

			return {
				add: (target) => {
					assert.equal(target, windowObject);

					return {
						off: (event) => {
							assert.equal(event, 'scroll.cactiSelectmenu');

							return {
								on: (bound, handler) => {
									boundEvent = bound;
									scrollHandler = handler;
								}
							};
						}
					};
				}
			};
		}

		if (selector === 'select') {
			selectScans++;

			return {
				each: (callback) => selects.forEach(select => callback.call(select))
			};
		}

		if (selector === '.ui-selectmenu-open') {
			return { length: menuOpen ? 1 : 0 };
		}

		return {
			selectmenu: (action) => {
				if (action === 'instance') {
					return selector.initialized ? {} : undefined;
				}

				assert.equal(action, 'close');
				selector.closes++;
			}
		};
	}

	const context = vm.createContext({ $: jquery, window: windowObject });
	vm.runInContext(extractFunction('setupSelectmenuScrollClose'), context, { filename: 'include/layout.js' });
	context.setupSelectmenuScrollClose();

	assert.match(selectedContainers, /\.cactiConsoleContentArea/);
	assert.match(selectedContainers, /\.cactiGraphContentArea/);
	assert.match(selectedContainers, /\.cactiTreeNavigationArea/);
	assert.equal(boundEvent, 'scroll.cactiSelectmenu');
	assert.equal(typeof scrollHandler, 'function');

	scrollHandler();
	assert.equal(selects[0].closes, 1);
	assert.equal(selects[1].closes, 0);
	assert.equal(selectScans, 1);

	menuOpen = false;
	scrollHandler();
	assert.equal(selectScans, 1);
});

test('applySkin binds scroll handling after theme widgets initialize', () => {
	const themeReadyPosition = layoutSource.indexOf('themeReady();');
	const setupPosition = layoutSource.indexOf('setupSelectmenuScrollClose();', themeReadyPosition);

	assert.notEqual(themeReadyPosition, -1);
	assert.ok(setupPosition > themeReadyPosition);
});
