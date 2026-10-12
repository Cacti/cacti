/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

const { test, expect } = require('@playwright/test');
const path = require('node:path');
const root = path.resolve(__dirname, '../../..');
require('./fixture-coverage').collectCoverage(test);

// Real jQuery UI and theme styles; no server, credentials or database required.
for (const theme of ['cacti', 'carrot', 'dark', 'deepness', 'hollyberry', 'modern', 'paper-plane', 'paw', 'raspberry', 'sunrise']) {
	for (const width of [360, 1280]) {
		test(`${theme} filter dialog at ${width}px`, async ({ page }) => {
			await page.setViewportSize({ width, height: 800 });
			await page.setContent('<div class="cactiTableTitleRow">Table header</div><div id="filters"><label>Search <input type="text"></label><textarea aria-label="Notes"></textarea><select aria-label="Mode"><option>One</option></select><input class="ui-state-default" aria-label="Styled"></div>');
			await page.addStyleTag({ path: path.join(root, `include/themes/${theme}/jquery-ui.css`) });
			await page.addStyleTag({ path: path.join(root, `include/themes/${theme}/main.css`) });
			await page.addScriptTag({ path: path.join(root, 'include/js/jquery.js') });
			await page.addScriptTag({ path: path.join(root, 'include/js/jquery-ui.js') });
			await page.evaluate(() => $('#filters').dialog({ title: 'Filters', classes: { 'ui-dialog': 'cactiFilterDialog' }, width: 320 }));
			const geometry = await page.evaluate(() => {
				const bar = document.querySelector('.ui-dialog-titlebar');
				const icon = document.querySelector('.ui-dialog-titlebar-close .ui-icon');
				return {
					header: document.querySelector('.cactiTableTitleRow').getBoundingClientRect().height,
					dialog: bar.getBoundingClientRect().height,
					color: getComputedStyle(bar).color,
					iconColor: getComputedStyle(icon).color,
					glyph: getComputedStyle(icon, '::before').content,
					background: getComputedStyle(icon).backgroundImage,
				};
			});
			expect(geometry.dialog).toBe(geometry.header);
			expect(geometry.iconColor).toBe(geometry.color);
			expect(geometry.glyph).toContain('×');
			expect(geometry.background).toBe('none');
			if (theme === 'deepness') {
				for (const selector of ['input[type=text]', 'input.ui-state-default', 'textarea', 'select']) {
					const control = page.locator(`#filters ${selector}`);
					await control.focus();
					const colors = await control.evaluate(el => {
						const style = getComputedStyle(el);
						const probe = document.createElement('span');
						probe.style.color = 'var(--dp-accent)';
						el.parentElement.append(probe);
						const accent = getComputedStyle(probe).color;
						probe.remove();
						return { border: style.borderTopColor, accent, background: style.backgroundImage };
					});
					expect(colors.border).toBe(colors.accent);
					expect(colors.background).toBe('none');
				}
			}
			const close = page.getByRole('button', { name: /close/i });
			await close.focus();
			await page.keyboard.press('Enter');
			await expect(page.getByRole('dialog')).toBeHidden();
		});
	}
}
