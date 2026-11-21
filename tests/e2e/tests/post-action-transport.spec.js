/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../../..');
require('./fixture-coverage').collectCoverage(test);

// Serve a same-origin page so the checked-in action map is consulted; an
// about:blank origin would return before reaching it.
test.beforeEach(async ({ page }) => {
	await page.route('http://cacti-fixture/**', route => route.fulfill({ contentType: 'text/html', body: '<!doctype html><title>fixture</title>' }));
	await page.goto('http://cacti-fixture/cacti/index.php');
	const source = fs.readFileSync(path.join(root, 'include/layout.js'), 'utf8');
	const start = source.indexOf('function cactiIsPostActionUrl(');
	const end = source.indexOf('\nfunction loadUrl(', start);
	expect(start).toBeGreaterThan(-1);
	expect(end).toBeGreaterThan(start);
	await page.addScriptTag({ content: '\n'.repeat(source.slice(0, start).split('\n').length - 1) + source.slice(start, end) + '\n//# sourceURL=http://cacti-fixture/include/layout.js' });
});

test('every reports.php mutation is sent as a POST', async ({ page }) => {
	const actions = ['save', 'actions', 'send', 'remove_history', 'ajax_dnd', 'item_moveup', 'item_movedown', 'item_remove'];
	const results = await page.evaluate(names => names.map(name => cactiIsPostActionUrl(`/cacti/reports.php?action=${name}&id=1`)), actions);
	expect(results).toEqual(actions.map(() => true));
});

test('reports.php views and other origins keep GET transport', async ({ page }) => {
	const hrefs = ['/cacti/reports.php?action=edit&id=1', '/cacti/reports.php?action=item_edit&id=1', '/cacti/reports.php', 'https://external.example/cacti/reports.php?action=save'];
	const results = await page.evaluate(list => list.map(href => cactiIsPostActionUrl(href)), hrefs);
	expect(results).toEqual(hrefs.map(() => false));
});
