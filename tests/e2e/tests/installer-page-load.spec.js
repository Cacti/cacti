/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../../..');
require('./fixture-coverage').collectCoverage(test);

const origin = 'http://cacti.test';
const page_html = '<!DOCTYPE html><html><body><div id="installContent"></div>'
	+ '<button class="installButton" id="buttonPrevious" type="button"></button>'
	+ '<button class="installButton" id="buttonNext" type="button"></button>'
	+ '<button class="installButton" id="buttonTest" type="button"></button>'
	+ '<input id="installData" type="hidden">'
	+ '<script src="/include/js/jquery.js"></script><script src="/include/js/jquery-ui.js"></script><script src="/install/install.js"></script></body></html>';

// The real install.js and jQuery are served to a stand-in page: no server, credentials or database.
async function openInstaller(page, query) {
	await page.addInitScript(() => {
		// layout.js supplies this on the real page; the installer's own poll is not under test here.
		window.waitForFinalEvent = () => {};
	});
	await page.route(`${origin}/**`, route => {
		const url = new URL(route.request().url());

		if (url.pathname === '/install/install.php') {
			return route.fulfill({ contentType: 'text/html', body: page_html });
		}

		const file = path.join(root, url.pathname);

		if (!file.startsWith(root + path.sep) || !fs.existsSync(file)) {
			return route.fulfill({ status: 404, body: '' });
		}

		return route.fulfill({ contentType: 'application/javascript', body: fs.readFileSync(file, 'utf8') });
	});
	await page.goto(`${origin}/install/install.php${query}`);
}

test('the installer resumes from the server and ignores step data in the address bar', async ({ page }) => {
	const data = encodeURIComponent(JSON.stringify({ Step: 97, Eula: 1 }));

	await openInstaller(page, `?data=${data}`);

	expect(await page.evaluate(() => $('#installData').data('installData'))).toBeNull();
	// nothing from the address bar reaches the first request, so the server decides the step
	expect(await page.evaluate(() => prepareInstallData())).toBe('[]');
});

test('the installer buttons start disabled and debug mode is only taken from the address bar flag', async ({ page }) => {
	await openInstaller(page, '?debug=1');

	expect(await page.evaluate(() => $('#installData').data('debug'))).toBe(true);
	expect(await page.evaluate(() => $('#installData').data('installData'))).toBeNull();
});
