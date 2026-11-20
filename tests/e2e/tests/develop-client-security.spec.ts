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

test('Plugin README dialog opens external links in a new tab', async ({ page, context }) => {
	const source = readFileSync(resolve(__dirname, '../../../plugins.php'), 'utf8');
	const start = source.indexOf('function displayFileDialog(');
	expect(start).toBeGreaterThan(0);
	let depth = 0;
	let end = source.indexOf('{', start);
	do {
		const ch = source[end++];
		depth += ch === '{' ? 1 : ch === '}' ? -1 : 0;
	} while (depth > 0 && end < source.length);
	const handler = source.slice(start, end);
	const asset = (name: string) => readFileSync(resolve(__dirname, '../../../include/js', name), 'utf8');

	await context.route('https://cacti.test/**', route => {
		const url = route.request().url();
		if (url.endsWith('/readme')) {
			return route.fulfill({ contentType: 'text/html', body:
				'<h1 id="top">Plugin</h1><p><a id="external" href="https://example.test/notes">Notes</a> '
				+ '<a id="anchor" href="#top">Top</a></p>' });
		}
		return route.fulfill({ contentType: 'text/html', body: '<!DOCTYPE html><title>Plugins</title><div id="main"></div>' });
	});
	await context.route('https://example.test/**', route => route.fulfill({
		contentType: 'text/html', body: '<!DOCTYPE html><title>Release notes</title>',
	}));

	await page.goto('https://cacti.test/plugins.php');
	await page.addScriptTag({ content: asset('jquery.js') });
	await page.addScriptTag({ content: asset('jquery-ui.js') });
	await page.addScriptTag({ content: asset('purify.js') });
	await page.addScriptTag({ content: `${handler}\ndisplayFileDialog('https://cacti.test/readme', 'Readme', 400, 700);` });

	const external = page.locator('#pidialog #external');
	await expect(external).toBeVisible();
	await expect(external).toHaveAttribute('target', '_blank');
	await expect(page.locator('#pidialog #anchor')).not.toHaveAttribute('target', /.+/);

	const popupPromise = context.waitForEvent('page');
	await external.click();
	const popup = await popupPromise;
	await popup.waitForLoadState();
	expect(popup.url()).toBe('https://example.test/notes');
	expect(await popup.evaluate(() => window.opener)).toBeNull();
	expect(page.url()).toBe('https://cacti.test/plugins.php');
});
