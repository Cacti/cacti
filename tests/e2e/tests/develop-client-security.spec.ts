/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

test('Midwinter homepage popup cannot access its opener', async ({ page, context }) => {
	const source = readFileSync(resolve(__dirname, '../../../include/themes/midwinter/main.js'), 'utf8');
	const sink = source.indexOf("window.open('https://cacti.net'");
	expect(sink).toBeGreaterThan(0);
	const start = source.lastIndexOf('function()', sink);
	const end = source.indexOf('});', sink);
	expect(start).toBeGreaterThan(0);
	expect(end).toBeGreaterThan(sink);
	const handler = source.slice(start, end + 1);
	await context.route('https://cacti.net/**', route => route.fulfill({
		contentType: 'text/html', body: '<!DOCTYPE html><title>Fixture homepage</title>',
	}));
	await page.setContent('<!DOCTYPE html><button id="homepage">Homepage</button>');
	// Execute the repository-owned callback with a real click; no fixture
	// implementation of window.open or opener isolation is substituted.
	await page.addScriptTag({ content: `document.querySelector('#homepage').addEventListener('click', ${handler});` });
	const popupPromise = context.waitForEvent('page');
	await page.locator('#homepage').click();
	const popup = await popupPromise;
	await popup.waitForLoadState();
	expect(await popup.evaluate(() => window.opener)).toBeNull();
});
