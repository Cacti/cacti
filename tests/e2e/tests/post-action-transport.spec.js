/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

// Runs the production POST-transport map in a browser, so changed map entries
// carry browser coverage and every server-side POST-only action stays POST.
const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../../..');
require('./fixture-coverage').collectCoverage(test);

test('every POST-only action in the transport map is sent by POST', async ({ page }) => {
	await page.route('http://cacti-fixture/**', route => route.fulfill({ contentType: 'text/html', body: '<!DOCTYPE html><html><body></body></html>' }));
	await page.goto('http://cacti-fixture/cacti/index.php');
	const layout = fs.readFileSync(path.join(root, 'include/layout.js'), 'utf8');
	const start = layout.indexOf('function cactiIsPostActionUrl(');
	const end = layout.indexOf('\nfunction loadUrl(', start);
	expect(start).toBeGreaterThan(-1);
	expect(end).toBeGreaterThan(start);
	// Pad to the original line numbers so coverage maps onto include/layout.js.
	await page.addScriptTag({ content: '\n'.repeat(layout.slice(0, start).split('\n').length - 1) + layout.slice(start, end) + '\n//# sourceURL=http://cacti-fixture/include/layout.js' });
	const actions = JSON.parse(layout.slice(start, end).match(/var actions = (\{[\s\S]*?\});/)[1]);
	const results = await page.evaluate(map => {
		window.urlPath = '/cacti/';
		const check = (file, action) => [file, action, cactiIsPostActionUrl(`/cacti/${file}?action=${action}&id=1`)];
		return Object.entries(map).flatMap(([file, names]) => names.map(name => check(file, name)))
			.concat(Object.keys(map).map(file => check(file, 'edit')));
	}, actions);
	for (const [file, action, post] of results) {
		expect(post, `${file} ${action}`).toBe(action !== 'edit');
	}
});
