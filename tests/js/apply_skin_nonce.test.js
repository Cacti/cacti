/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

function loadApplySkin(nonce) {
	const ajaxSetups = [];
	const calls = [];
	let chain;

	chain = new Proxy({}, {
		get: (_target, property) => {
			if (property === 'length') {
				return 0;
			}

			return (...args) => {
				if (property === 'attr' && args[0] === 'pathname') {
					return '/cacti/index.php';
				}

				if (property === 'map') {
					return [];
				}

				return chain;
			};
		},
	});

	const jquery = () => chain;
	jquery.ajaxSetup = (options) => ajaxSetups.push(options);
	jquery.Deferred = () => ({ resolve: () => undefined });

	const context = {
		$: jquery,
		CsrfMagic: { end: () => calls.push('csrf') },
		DOMPurify: { sanitize: (value) => value },
		basename: (value) => path.posix.basename(value),
		document: {},
		location: {},
		pageName: '',
		shiftPressed: false,
		theme: 'classic',
	};

	for (const name of [
		'setGraphTabs', 'setupSortable', 'setupBreadcrumbs', 'applyTableSizing',
		'setupPageTimeout', 'setupSpecialKeys', 'setupCollapsible', 'ajaxAnchors',
		'applySelectorVisibilityAndActions', 'handleTableNav', 'makeFiltersResponsive',
		'setupResponsiveMenuAndTabs', 'setupButtonStyle', 'keepWindowSize',
		'displayMessages', 'renderLanguages', 'setupSelectmenuScrollClose',
	]) {
		context[name] = () => calls.push(name);
	}

	if (nonce !== undefined) {
		context.cactiNonce = nonce;
	}

	vm.createContext(context);
	vm.runInContext(extractFunction('applySkin'), context, { filename: 'include/layout.js' });

	return { ajaxSetups, calls, context };
}

test('applySkin completes when cactiNonce is absent', () => {
	const { ajaxSetups, calls, context } = loadApplySkin();

	assert.doesNotThrow(() => context.applySkin());
	assert.deepEqual(ajaxSetups, []);
	assert.ok(calls.includes('renderLanguages'));
	assert.equal(context.pageName, 'index.php');
});

test('applySkin forwards a defined callback nonce to jQuery', () => {
	const { ajaxSetups, calls, context } = loadApplySkin('nonce-value');

	context.applySkin();

	assert.equal(ajaxSetups.length, 1);
	assert.equal(ajaxSetups[0].nonce, 'nonce-value');
	assert.ok(calls.includes('renderLanguages'));
});
