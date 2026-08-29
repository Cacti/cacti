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

function loadTreeFunctions(state) {
	const windowObject = {};
	const anchors = state.anchorRightEdges.map((right) => ({
		getBoundingClientRect: () => ({ right }),
	}));

	function collection(methods = {}) {
		return {
			length: 1,
			css: () => undefined,
			height: () => undefined,
			outerHeight: () => 0,
			width: () => 0,
			...methods,
		};
	}

	function jquery(selector) {
		if (selector === windowObject) {
			return collection({ outerHeight: () => 800 });
		}

		if (selector === '.cactiTreeNavigationArea') {
			return collection({
				width: () => state.navWidth,
				css: (property, value) => {
					if (property === 'width') {
						state.navWidth = value;
					} else if (property === 'overflow-x') {
						state.overflowX = value;
					}
				},
			});
		}

		if (selector === '.cactiGraphContentArea') {
			return collection({
				css: (property, value) => {
					if (property === 'margin-left') {
						state.graphMargin = value;
					}
				},
			});
		}

		if (selector === '#navigation') {
			return collection({ width: () => state.navWidth });
		}

		if (selector === '#searcher') {
			return collection({
				css: (property, value) => {
					if (property === 'width') {
						state.searchWidth = value;
					}
				},
			});
		}

		if (selector === '#jstree') {
			return collection({
				offset: () => ({ left: state.treeLeft }),
				children: () => ({
					map: (callback) => ({
						get: () => [callback.call({})],
					}),
				}),
				find: (query) => {
					assert.equal(query, '.jstree-anchor:visible');

					return {
						map: (callback) => ({
							get: () => anchors.map((anchor) => callback.call(anchor)),
						}),
					};
				},
			});
		}

		return collection();
	}

	const context = vm.createContext({
		$: jquery,
		console,
		Math,
		maxTreeWidth: 300,
		minTreeWidth: 170,
		theme: 'modern',
		window: windowObject,
	});

	vm.runInContext(
		`${extractFunction('getTreeSearchWidth')}\n${extractFunction('resizeTreePanel')}`,
		context,
		{ filename: 'include/layout.js' }
	);

	return context;
}

test('collapsed anchors shrink the sidebar to their current footprint', () => {
	const state = {
		anchorRightEdges: [130, 220],
		navWidth: 260,
		treeLeft: 10,
	};

	loadTreeFunctions(state).resizeTreePanel();

	assert.equal(state.navWidth, 210);
	assert.equal(state.graphMargin, 215);
	assert.equal(state.overflowX, '');
});

test('anchor depth is included by measuring right edge from the tree origin', () => {
	const state = {
		anchorRightEdges: [190, 290],
		navWidth: 170,
		treeLeft: 10,
	};

	loadTreeFunctions(state).resizeTreePanel();

	assert.equal(state.navWidth, 280);
	assert.equal(state.graphMargin, 285);
	assert.equal(state.overflowX, 'auto');
});

test('tree and search widths retain their lower bounds', () => {
	const state = {
		anchorRightEdges: [40],
		navWidth: 260,
		treeLeft: 10,
	};

	const context = loadTreeFunctions(state);
	context.resizeTreePanel();

	assert.equal(state.navWidth, 170);
	assert.equal(state.graphMargin, 175);
	assert.equal(state.searchWidth, 100);
	assert.equal(context.getTreeSearchWidth(70), 40);
});
