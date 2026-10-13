/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

test('column widths follow the table attribute across action changes and retain the ID fallback', async ({ page }) => {
	const root = resolve(__dirname, '../../..');
	const source = readFileSync(resolve(root, 'include/layout.js'), 'utf8');
	const start = source.indexOf('function saveTableWidths(initial)');
	const end = source.indexOf('\n}\n', start);
	expect(start).toBeGreaterThan(0);
	expect(end).toBeGreaterThan(start);
	await page.route('http://cacti.test/column-widths', route => route.fulfill({
		contentType: 'text/html',
		body: '<!DOCTYPE html><table id="hosts_view" class="cactiTable" data-colsizing="hosts"><tr><th class="ui-resizable">Name</th><th class="ui-resizable">Count</th></tr></table>',
	}));
	await page.goto('http://cacti.test/column-widths');
	await page.addScriptTag({ path: resolve(root, 'include/js/jquery.js') });
	await page.addScriptTag({ path: resolve(root, 'include/js/js.storage.js') });
	await page.addScriptTag({ content: source.slice(start, end + 2) });
	await page.evaluate(() => {
		const browser = window as any;
		browser.Storages.localStorage.set('hosts', [browser.$(document).width(), 210, 180]);
		browser.saveTableWidths(true);
	});
	expect(await page.locator('th').first().evaluate(element => element.getAttribute('resizeWidth'))).toBe('210');
	await page.evaluate(() => {
		const browser = window as any;
		document.querySelector('table')!.id = 'hosts_edit_report';
		browser.$('th').css('width', 50);
		browser.saveTableWidths(true);
	});
	expect(await page.locator('th').first().evaluate(element => element.getAttribute('resizeWidth'))).toBe('210');
	await page.evaluate(() => {
		const browser = window as any;
		const table = document.querySelector('table')!;
		table.removeAttribute('data-colsizing');
		browser.Storages.localStorage.set(table.id, [browser.$(document).width(), 260, 190]);
		browser.saveTableWidths(true);
	});
	expect(await page.locator('th').first().evaluate(element => element.getAttribute('resizeWidth'))).toBe('260');
	await page.evaluate(() => {
		const browser = window as any;
		document.querySelector('table')!.setAttribute('data-colsizing', '');
		browser.saveTableWidths(true);
	});
	expect(await page.locator('th').first().evaluate(element => element.getAttribute('resizeWidth'))).toBe('260');
	expect(await page.evaluate(() => Object.hasOwn(window, 'sizes') || Object.hasOwn(window, 'items'))).toBe(false);
});
