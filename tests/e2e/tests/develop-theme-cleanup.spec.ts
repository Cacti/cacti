/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { installThemeCss, collectThemeCoverage } from '../theme-css';

collectThemeCoverage();

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
		await installThemeCss(page, `include/themes/${theme}/main.css`);
		expect(await page.locator(selector).evaluate((element, property) => (getComputedStyle(element) as any)[property], property)).toBe(expected);
	});
}

test('modern checked radio slider retains its effective display', async ({ page }) => {
	await page.setContent('<input type="radio" checked><span class="radioSlider"></span>');
	await installThemeCss(page, 'include/themes/modern/main.css');
	expect(await page.locator('.radioSlider').evaluate(element => getComputedStyle(element, ':before').display)).toBe('block');
});

test('midwinter labels retain their cursor and whitespace behavior', async ({ page }) => {
	await page.setContent('<label>Device label</label>');
	await installThemeCss(page, 'include/themes/midwinter/css/media/core.css');
	const styles = await page.locator('label').evaluate(element => {
		const style = getComputedStyle(element);
		return { cursor: style.cursor, whiteSpace: style.whiteSpace };
	});
	expect(styles).toEqual({ cursor: 'pointer', whiteSpace: 'nowrap' });
});
