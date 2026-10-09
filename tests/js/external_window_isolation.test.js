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

function loadHandler(source, start, context) {
	assert.notEqual(start, -1, 'the actual click handler must exist');
	const bodyStart = source.indexOf('{', start);
	let depth = 0;
	for (let offset = bodyStart; offset < source.length; offset++) {
		if (source[offset] === '{') {
			depth++;
		} else if (source[offset] === '}') {
			depth--;
			if (depth === 0) {
				return vm.runInNewContext(`(${source.slice(start, offset + 1)})`, context);
			}
		}
	}
	throw new Error('the click handler must have a complete body');
}

const installSource = fs.readFileSync(path.join(__dirname, '../../install/install.js'), 'utf8');
const themeSource = fs.readFileSync(path.join(__dirname, '../../include/themes/midwinter/main.js'), 'utf8');

function installerContext(step, window) {
	return {
		$: () => ({data: () => ({Step: step})}),
		STEP_GO_SITE: 1,
		STEP_GO_FORUMS: 2,
		STEP_GO_GITHUB: 3,
		STEP_TEST_REMOTE: 4,
		window
	};
}

function installHandler(context) {
	const anchor = installSource.indexOf("$('.installButton').on('click'");
	return loadHandler(installSource, installSource.indexOf('function(e)', anchor), context);
}

test('installer external links isolate their opener and tolerate blocked popups', () => {
	for (const [step, expected] of [[2, 'https://forums.cacti.net/'], [3, 'https://github.com/cacti/cacti/issues/']]) {
		const calls = [];
		const handler = installHandler(installerContext(step, {
			open(...args) {
				calls.push(args);
				return null;
			}
		}));
		assert.doesNotThrow(() => handler({currentTarget: {}}));
		assert.equal(calls.length, 1);
		assert.equal(calls[0][0], expected);
		assert.equal(calls[0][1], '_blank');
		assert.ok(calls[0][2].split(',').includes('noopener'));
	}
});

test('installer site navigation keeps its existing local destination', () => {
	const destinations = [];
	const handler = installHandler(installerContext(1, {
		location: {assign: destination => destinations.push(destination)}
	}));
	handler({currentTarget: {}});
	assert.deepEqual(destinations, ['../']);
});

test('the restricted-console theme link isolates its opener', () => {
	const call = themeSource.indexOf("window.open('https://cacti.net'");
	assert.notEqual(call, -1);
	const calls = [];
	const handler = loadHandler(themeSource, themeSource.lastIndexOf('function()', call), {
		window: {open: (...args) => calls.push(args)}
	});
	handler();
	assert.equal(calls.length, 1);
	assert.equal(calls[0][0], 'https://cacti.net');
	assert.equal(calls[0][1], '_blank');
	assert.ok(calls[0][2].split(',').includes('noopener'));
});
