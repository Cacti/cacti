/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(__dirname, '../../..');

for (const theme of ['modern', 'dark']) {
	test(`${theme} enables graph opacity after Select2 replaces selectmenu`, async ({ page }) => {
		await page.setContent('<!DOCTYPE html><select id="graph_type_id"><option value="1">Comment</option>' +
			[4, 5, 6, 7, 8].map(value => `<option value="${value}">Type ${value}</option>`).join('') +
			'</select><select id="alpha" disabled><option value="FF">100%</option><option value="80">50%</option></select>');
		for (const file of ['jquery.js', 'jquery-ui.js', 'select2.js']) {
			await page.addScriptTag({ path: resolve(root, 'include/js', file) });
		}
		const source = readFileSync(resolve(root, `include/themes/${theme}/main.js`), 'utf8');
		const start = source.indexOf("$('#graph_type_id').on('change', function()");
		const end = source.indexOf('\n\t});', start);
		expect(start).toBeGreaterThan(0);
		expect(end).toBeGreaterThan(start);
		await page.addScriptTag({ content: source.slice(start, end + '\n\t});'.length) });
		await page.evaluate(() => {
			const browser = window as any;
			browser.$('#graph_type_id, #alpha').select2();
		});
		for (const type of ['4', '5', '6', '7', '8']) {
			await page.evaluate(type => {
				const browser = window as any;
				browser.$('#alpha').prop('disabled', true);
				browser.$('#graph_type_id').val(type).trigger('change');
			}, type);
			await expect(page.locator('#alpha')).toBeEnabled();
			expect(await page.locator('#alpha').evaluate(element => (window as any).$(element).selectmenu('instance'))).toBeUndefined();
		}
		await page.evaluate(() => {
			const browser = window as any;
			browser.$('#alpha').prop('disabled', true);
			browser.$('#graph_type_id').val('1').trigger('change');
		});
		await expect(page.locator('#alpha')).toBeDisabled();
	});
}
