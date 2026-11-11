/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

// Shared coverage/regression tests for the language selectmenu styling. The
// language dropdown (#user_language / #i18n_default_language) is the only
// dropdown still rendered by the custom jQuery UI languageselect widget; every
// other dropdown is select2. Each theme restyles .ui-selectmenu-button.ui-button
// so the closed and open control matches its select2 single control - most
// importantly restoring the 6px radius that .ui-corner-all squares off.
//
// These tests are registered from the specs that the theme-coverage CI jobs run
// (develop-theme-correctness for the chromium job, filter-dialog-theme for the
// changed-coverage job), so the changed CSS lines are exercised by both gates.

const path = require('node:path');
const { existsSync } = require('node:fs');

const root = path.resolve(__dirname, '../../..');

const themes = ['cacti', 'dark', 'deepness', 'midwinter', 'paper-plane', 'paw', 'raspberry', 'sunrise'];

const closedButton = '<span id="closed" tabindex="0" class="ui-selectmenu-button ui-selectmenu-button-closed ui-corner-all ui-button ui-widget">' +
	'<span class="ui-selectmenu-text">English</span><span class="ui-selectmenu-icon ui-icon ui-icon-triangle-1-s"></span></span>';
const openButton = '<span id="open" class="ui-selectmenu-button ui-selectmenu-button-open ui-corner-all ui-button ui-widget">' +
	'<span class="ui-selectmenu-text">English</span><span class="ui-selectmenu-icon ui-icon ui-icon-triangle-1-s"></span></span>';

// Serve the theme tree from disk so a theme's main.css loads as a real external
// stylesheet and its own url()/@import references (midwinter pulls in
// css/pre/colors.css) resolve relative to it. main.css alone carries each
// theme's .ui-corner-all override, so it is enough to prove the selectmenu
// radius wins; loading it as a <link> avoids the @import failures that an inline
// <style> hits.
async function loadThemeMarkup(page, theme, markup) {
	await page.route('http://cacti-theme.test/**', route => {
		const file = new URL(route.request().url()).pathname.slice(1);
		if (file === 'blank') {
			return route.fulfill({ contentType: 'text/html', body: '<!DOCTYPE html><html><body></body></html>' });
		}
		const target = path.resolve(root, file);
		if (!target.startsWith(root + path.sep) || !existsSync(target)) return route.abort();
		return route.fulfill({ path: target, contentType: file.endsWith('.css') ? 'text/css' : undefined });
	});
	await page.goto('http://cacti-theme.test/blank');
	await page.evaluate(html => { document.body.innerHTML = html; }, markup);
	await page.addStyleTag({ url: `http://cacti-theme.test/include/themes/${theme}/main.css` });
}

function registerSelectmenuThemeTests(test, expect) {
	for (const theme of themes) {
		test(`${theme} language selectmenu keeps rounded corners in the closed and open state`, async ({ page }) => {
			await loadThemeMarkup(page, theme, `${closedButton}${openButton}`);

			const radius = await page.evaluate(() => ({
				closed: getComputedStyle(document.getElementById('closed')).borderTopLeftRadius,
				open: getComputedStyle(document.getElementById('open')).borderTopLeftRadius,
			}));
			// .ui-corner-all squares the corners with !important in most of these
			// themes; the selectmenu override must still win for both states.
			expect(radius.closed, 'closed').toBe('6px');
			expect(radius.open, 'open').toBe('6px');
		});
	}

	test('dark language selectmenu keeps a keyboard-focus ring despite the global outline reset', async ({ page }) => {
		await loadThemeMarkup(page, 'dark', closedButton);

		// Keyboard focus (not a programmatic focus()) is what triggers :focus-visible.
		await page.keyboard.press('Tab');
		await expect(page.locator('#closed')).toBeFocused();

		const outline = await page.locator('#closed').evaluate(element => {
			const style = getComputedStyle(element);
			return { style: style.outlineStyle, width: style.outlineWidth };
		});
		expect(outline.style).not.toBe('none');
		expect(parseFloat(outline.width)).toBeGreaterThan(0);
	});
}

module.exports = { registerSelectmenuThemeTests };
