/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 | Licensed under the GNU General Public License, version 2 or later.     |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

const { test, expect } = require('@playwright/test');

async function loadPage(page) {
	await page.route('**/tests/e2e/external-window.html', (route) => route.fulfill({
		contentType: 'text/html',
		body: '<!doctype html><html lang="en"><head><title>External window regression</title><link rel="icon" href="data:,">' +
			'<script src="/include/js/jquery.js"></script><script src="/include/js/jquery-ui.js"></script>' +
			'</head><body></body></html>',
	}));
	await page.goto('/tests/e2e/external-window.html');
	await page.coverage.startJSCoverage({ resetOnNavigation: false });
}

async function expectCoveredStatement(page, scriptPath, statement) {
	const coverage = await page.coverage.stopJSCoverage();
	const script = coverage.find((entry) => new URL(entry.url).pathname === scriptPath);
	expect(script, `${scriptPath} must be measured`).toBeDefined();
	const start = script.source.indexOf(statement);
	expect(start, 'the production statement must exist').toBeGreaterThanOrEqual(0);
	const ranges = script.functions.flatMap((fn) => fn.ranges)
		.filter((range) => range.startOffset <= start && range.endOffset >= start + statement.length)
		.sort((left, right) => (left.endOffset - left.startOffset) - (right.endOffset - right.startOffset));
	expect(ranges.length, 'the security statement must have coverage data').toBeGreaterThan(0);
	expect(ranges[0].count, 'every changed security statement must execute').toBeGreaterThan(0);
}

async function expectIsolatedWindow(page, context, selector, target) {
	await context.route(target, (route) => route.fulfill({
		contentType: 'text/html',
		body: '<!doctype html><html lang="en"><head><title>External destination</title></head><body>Destination</body></html>',
	}));
	const popupPromise = context.waitForEvent('page');
	await page.locator(selector).click();
	const popup = await popupPromise;
	await popup.waitForLoadState();
	expect(popup.url()).toBe(target);
	expect(await popup.evaluate(() => window.opener)).toBeNull();
	await popup.close();
}

for (const destination of [
	{ name: 'forums', step: -2, url: 'https://forums.cacti.net/' },
	{ name: 'issue tracker', step: -3, url: 'https://github.com/cacti/cacti/issues/' },
]) {
	test(`installer ${destination.name} opens without an opener`, async ({ page, context }) => {
		await loadPage(page);
		await page.evaluate((step) => {
			const button = document.createElement('button');
			button.id = 'external-link';
			button.className = 'installButton';
			button.textContent = 'Continue';
			document.body.append(button);
			$(button).data('buttonData', { Step: step });
			// Keep the isolated completion screen from starting an installer AJAX request.
			window.waitForFinalEvent = () => {};
		}, destination.step);
		await page.addScriptTag({ url: '/install/install.js' });
		await page.waitForFunction(() => Boolean($._data(document.getElementById('external-link'), 'events')));
		await expectIsolatedWindow(page, context, '#external-link', destination.url);
		await expectCoveredStatement(page, '/install/install.js',
			`window.open('${destination.url}', '_blank', 'noopener');`);
	});

	test(`installer ${destination.name} tolerates a blocked popup`, async ({ page }) => {
		const errors = [];
		page.on('pageerror', (error) => errors.push(error.message));
		await loadPage(page);
		await page.evaluate((step) => {
			const button = document.createElement('button');
			button.id = 'external-link';
			button.className = 'installButton';
			button.textContent = 'Continue';
			document.body.append(button);
			$(button).data('buttonData', { Step: step });
			window.waitForFinalEvent = () => {};
			window.open = () => null;
		}, destination.step);
		await page.addScriptTag({ url: '/install/install.js' });
		await page.waitForFunction(() => Boolean($._data(document.getElementById('external-link'), 'events')));
		await page.locator('#external-link').click();
		expect(errors).toEqual([]);
		await expectCoveredStatement(page, '/install/install.js',
			`window.open('${destination.url}', '_blank', 'noopener');`);
	});
}

test('midwinter guest navigation opens the Cacti website without an opener', async ({ page, context }) => {
	await loadPage(page);
	await page.evaluate(() => {
		const breadcrumb = document.createElement('div');
		breadcrumb.id = 'breadCrumbBar';
		document.body.append(breadcrumb);
		const host = document.createElement('input');
		host.id = 'host';
		document.body.append(host);
		window.themeLoader = () => {};
		window.basename = (value) => value.split('/').pop();
		window.cactiConsoleAllowed = false;
		window.noFileSelected = '';
		window.setNavigationScroll = () => {};
		$.fn.dropcolor = function() { return this; };
	});
	await page.addScriptTag({ url: '/include/themes/midwinter/main.js' });
	await page.evaluate(() => setupDefaultElements());
	// The fixture does not load theme CSS, so give the real navigation target a hit area.
	await page.locator('#cactiConsoleBackdrop').evaluate((element) => {
		element.textContent = 'Cacti website';
	});
	await expectIsolatedWindow(page, context, '#cactiConsoleBackdrop', 'https://cacti.net/');
	await expectCoveredStatement(page, '/include/themes/midwinter/main.js',
		"window.open('https://cacti.net', '_blank', 'noopener');");
});
