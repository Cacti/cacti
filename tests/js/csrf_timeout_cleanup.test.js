/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const root = path.resolve(__dirname, '../..');
const layout = fs.readFileSync(path.join(root, 'include/layout.js'), 'utf8');

/* Extract the stripCsrfTimeoutMarker helper and the displayMessages() cleanup
 * block straight from the shipped source so the contract tracks production. */
const helperStart = layout.indexOf('function stripCsrfTimeoutMarker');
const helperEnd = layout.indexOf('\nfunction ', helperStart);
const helperSrc = layout.slice(helperStart, helperEnd);

const cleanupStart = layout.indexOf("if (document.location.href.indexOf('csrf_timeout')");
const cleanupEnd = layout.indexOf('sessionMessage = null;', cleanupStart);
const cleanupSrc = layout.slice(cleanupStart, cleanupEnd);

function runHelper(url) {
	const context = { URLSearchParams, value: url, result: undefined };
	vm.runInNewContext(helperSrc + '\nresult = stripCsrfTimeoutMarker(value);', context);
	return context.result;
}

function runCleanup({ locationHref, myHref, refreshPage, historyState, includeRefreshPage = true }) {
	const replaced = { called: false };
	const window = {
		history: {
			state: historyState,
			replaceState(state, title, url) {
				replaced.called = true;
				replaced.state = state;
				replaced.url = url;
			},
		},
	};

	if (includeRefreshPage) {
		window.refreshPage = refreshPage;
	}

	const context = {
		URLSearchParams,
		document: { location: { href: locationHref } },
		window,
		myHref,
	};

	vm.runInNewContext(helperSrc + '\n' + cleanupSrc, context);

	return { replaced, myHref: context.myHref, refreshPage: context.window.refreshPage };
}

test('layout.js exposes the csrf cleanup source anchors', () => {
	assert.notEqual(helperStart, -1, 'stripCsrfTimeoutMarker helper must exist in layout.js');
	assert.notEqual(helperEnd, -1, 'stripCsrfTimeoutMarker helper must be followed by another function');
	assert.notEqual(cleanupStart, -1, 'displayMessages() must clean the csrf_timeout marker');
	assert.notEqual(cleanupEnd, -1, 'csrf_timeout cleanup must precede the sessionMessage reset');
});

test('stripCsrfTimeoutMarker removes the marker and preserves other parameters and the fragment', () => {
	assert.equal(
		runHelper('http://host/index.php?keep=a%26b&csrf_timeout=true#details'),
		'http://host/index.php?keep=a%26b#details'
	);
	assert.equal(
		runHelper('/graph_view.php?action=tree&node=1&csrf_timeout=true'),
		'/graph_view.php?action=tree&node=1'
	);
	assert.equal(runHelper('/graph_view.php?csrf_timeout=true'), '/graph_view.php');
	assert.equal(runHelper('/graph_view.php?csrf_timeout=true#frag'), '/graph_view.php#frag');
});

test('stripCsrfTimeoutMarker returns markerless and non-string input unchanged', () => {
	assert.equal(runHelper('/index.php?rows=30'), '/index.php?rows=30');
	assert.equal(runHelper('/index.php'), '/index.php');
	assert.equal(runHelper(undefined), undefined);
});

test('recovery cleanup syncs the address bar, graph filter href and tree refresh target', () => {
	const state = { csrfRecoveryTest: true };
	const result = runCleanup({
		locationHref: 'http://host/cacti/graph_view.php?action=tree&node=1&csrf_timeout=true#x',
		myHref: 'http://host/cacti/graph_view.php?action=preview&rfilter=abc&csrf_timeout=true',
		refreshPage: '/cacti/graph_view.php?action=tree&node=1&csrf_timeout=true',
		historyState: state,
	});

	assert.equal(result.replaced.called, true);
	assert.equal(result.replaced.url, 'http://host/cacti/graph_view.php?action=tree&node=1#x');
	assert.equal(result.replaced.state, state, 'existing history state must be preserved');
	assert.ok(!result.myHref.includes('csrf_timeout'), 'myHref must be synchronized to the cleaned URL');
	assert.equal(result.myHref, 'http://host/cacti/graph_view.php?action=preview&rfilter=abc');
	assert.ok(!result.refreshPage.includes('csrf_timeout'), 'tree refresh target must drop the marker');
	assert.equal(result.refreshPage, '/cacti/graph_view.php?action=tree&node=1');
});

test('recovery cleanup tolerates pages without a refreshPage global', () => {
	const result = runCleanup({
		locationHref: 'http://host/cacti/index.php?csrf_timeout=true',
		myHref: 'http://host/cacti/index.php?csrf_timeout=true',
		includeRefreshPage: false,
	});

	assert.equal(result.replaced.called, true);
	assert.ok(!result.myHref.includes('csrf_timeout'));
});

test('cleanup leaves URLs without the marker untouched', () => {
	const result = runCleanup({
		locationHref: 'http://host/cacti/index.php?rows=30',
		myHref: 'http://host/cacti/index.php?rows=30',
		refreshPage: '/cacti/index.php?rows=30',
	});

	assert.equal(result.replaced.called, false, 'replaceState must not fire without the marker');
	assert.equal(result.myHref, 'http://host/cacti/index.php?rows=30');
	assert.equal(result.refreshPage, '/cacti/index.php?rows=30');
});
