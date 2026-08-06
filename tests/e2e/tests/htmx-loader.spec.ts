/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group								   |
 |																		   |
 | This program is free software; you can redistribute it and/or		   |
 | modify it under the terms of the GNU General Public License			   |
 | as published by the Free Software Foundation; either version 2		   |
 | of the License, or (at your option) any later version.				   |
 |																		   |
 | This program is distributed in the hope that it will be useful,		   |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of		   |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	 See the		   |
 | GNU General Public License for more details.							   |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution					   |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/												   |
 +-------------------------------------------------------------------------+
*/

import { test, expect } from '@playwright/test';

/*
 * html_common_header() runs on the login screen and emits the htmx script tag
 * when htmx_enabled is on. These tests target the unauthenticated login page
 * to avoid coupling to session bootstrap.
 */

test('htmx script tag appears in the login page HTML', async ({ page }) => {
	await page.goto('/');

	const src = await page.locator('script[src*="htmx.js"]').first().getAttribute('src');

	expect(src).toMatch(/include\/js\/htmx\.min\.js/);
});

test('htmx.js loads with a 200 response', async ({ page }) => {
	const response = await Promise.all([
		page.waitForResponse((r) => r.url().includes('htmx.js')),
		page.goto('/'),
	]);

	expect(response[0].status()).toBe(200);
});

test('window.htmx global is defined after page load', async ({ page }) => {
	await page.goto('/', { waitUntil: 'networkidle' });

	const hasHtmx = await page.evaluate(() => typeof (window as unknown as { htmx?: unknown }).htmx !== 'undefined');

	expect(hasHtmx).toBe(true);
});

test('integrity attribute is present on the htmx script tag', async ({ page }) => {
	await page.goto('/');

	const integrity = await page.locator('script[src*="htmx.js"]').first().getAttribute('integrity');

	expect(integrity).toMatch(/^sha384-[A-Za-z0-9+/=]+$/);
});
