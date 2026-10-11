/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(__dirname, '../../..');
for (const [theme, selector, property, expected] of [
	['deepness', '.graphItemGr1Cust', 'backgroundColor', 'rgb(22, 28, 37)'],
	['dark', '.cactiAuthPanel', 'padding', '4px 0px'],
	['paw', 'td .checkbox', 'marginRight', '10px'],
	['paw', 'th .checkbox', 'marginRight', '10px'],
]) {
	test(`${theme} duplicate cleanup preserves ${selector} ${property}`, async ({ page }) => {
		await page.setContent('<!DOCTYPE html><div class="graphItemGr1Cust"></div><div class="cactiAuthPanel"></div>' +
			'<table><thead><tr><th><input class="checkbox" type="checkbox"></th></tr></thead>' +
			'<tbody><tr><td><input class="checkbox" type="checkbox"></td></tr></tbody></table>');
		await page.addStyleTag({ content: readFileSync(resolve(root, `include/themes/${theme}/main.css`), 'utf8') });
		expect(await page.locator(selector).evaluate((element, property) => (getComputedStyle(element) as any)[property], property)).toBe(expected);
	});
}
