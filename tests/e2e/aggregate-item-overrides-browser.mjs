// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
import assert from 'node:assert/strict';
import {chromium} from '@playwright/test';
if (process.env.CACTI_AGGREGATE_ITEM_DISPOSABLE !== '1') {
	throw new Error('Use the disposable aggregate item fixture.');
}
const origin = process.env.CACTI_BASE_URL;
const browser = await chromium.launch({headless: true});
try {
	const page = await browser.newPage();
	const errors = [];
	page.on('pageerror', error => errors.push(error.message));
	await page.goto(origin);
	if (await page.locator('#realm').count()) {
		await page.locator('#realm').selectOption({label: 'Local'});
	}
	await page.locator('#login_username').fill('admin');
	await page.locator('#login_password').fill('aggregate-item-pass');
	await Promise.all([page.waitForNavigation(), page.locator('form#auth input[type=submit], form#auth button[type=submit]').click()]);
	assert.equal(await page.evaluate(async () => (await fetch('aggregate_graphs.php?action=save')).status), 405);
	// Exercise the editor reached from both the template and standalone graph.
	await page.goto(`${origin}/aggregate_templates.php?action=edit&id=900003`);
	const templateLink = await page.locator('a[href*="action=item_edit"]').first().getAttribute('href');
	assert.ok(templateLink);
	await page.goto(`${origin}/aggregate_graphs.php`);
	await page.locator('a[href*="action=edit&id="]').filter({hasText: 'Standalone legend fixture'}).first().click();
	const graphLink = await page.locator('a[href*="action=item_edit"]').first().getAttribute('href');
	assert.ok(graphLink);
	for (const link of [templateLink, graphLink]) {
		await page.goto(new URL(link, origin).href);
		assert.equal(await page.locator('#task_item_id').count(), 0);
		assert.equal(await page.locator('#sequence:not([type=hidden])').count(), 0);
		for (const text of ['', '<img src=x onerror=window.aggregateItemInjected=1> & "quotes"']) {
			if (!await page.locator('#t_text_format').isChecked()) {
				await page.locator('#t_text_format').locator('..').click();
			}
			await page.locator('#text_format').fill(text);
			if (!await page.locator('#t_legend').isChecked()) {
				await page.locator('#t_legend').locator('..').click();
			}
			await page.locator('#legend').fill('Export legend');
			await Promise.all([
				page.waitForResponse(response => response.request().method() === 'POST' && response.url().includes('/aggregate_graphs.php')),
				page.locator('#submit').click(),
			]);
			await page.goto(new URL(link, origin).href);
			assert.equal(await page.locator('#t_text_format').isChecked(), true);
			assert.equal(await page.locator('#text_format').inputValue(), text);
			assert.equal(await page.locator('#legend').inputValue(), 'Export legend');
			assert.equal(await page.evaluate(() => Boolean(window.aggregateItemInjected)), false);
			assert.equal(await page.locator('img[src="x"]').count(), 0);
		}
		const returnLink = await page.locator('button[onclick*="cactiReturnTo"]').first().getAttribute('onclick');
		assert.ok(returnLink);
		const parentUrl = returnLink.match(/cactiReturnTo\("([^" ]+)"\)/)[1];
		await page.goto(new URL(parentUrl, origin).href);
		await page.locator('input[id^=agg_total]').first().locator('..').click();
		await Promise.all([
			page.waitForResponse(response => response.request().method() === 'POST'),
			page.locator('#submit').click(),
		]);
		await page.goto(new URL(link, origin).href);
		assert.equal(await page.locator('#text_format').inputValue(), '<img src=x onerror=window.aggregateItemInjected=1> & "quotes"');
		assert.equal(await page.locator('#legend').inputValue(), 'Export legend');
		// A rejected save must not replace the last valid override values.
		for (const invalid of [
			{t_graph_type_id: 'on', graph_type_id: '10'},
			{t_graph_type_id: 'on', graph_type_id: '999'},
			{t_cdef_id: 'on', cdef_id: '987654'},
			{t_text_format: 'on', text_format: 'x'.repeat(256)},
			{t_legend: 'on', legend: 'x'.repeat(31)},
			{t_text_format: 'on', 'text_format[]': 'invalid array'},
		]) {
			const rejected = await page.evaluate(async invalid => {
				const form = document.querySelector('#aggregate_item_form');
				const data = new FormData(form);
				data.set('action', 'save');
				if ('text_format[]' in invalid) {
					data.delete('text_format');
				}
				for (const [name, value] of Object.entries(invalid)) {
					data.set(name, value);
				}
				const response = await fetch(form.getAttribute('action'), {method: 'POST', body: data});
				return {status: response.status, url: response.url};
			}, invalid);
			assert.equal(rejected.status, 200);
			assert.ok(rejected.url.includes('action=item_edit'));
			await page.goto(new URL(link, origin).href);
			assert.equal(await page.locator('#text_format').inputValue(), '<img src=x onerror=window.aggregateItemInjected=1> & "quotes"');
			assert.equal(await page.locator('#legend').inputValue(), 'Export legend');
			assert.equal(await page.locator('#t_graph_type_id').isChecked(), false);
			assert.equal(await page.locator('#t_cdef_id').isChecked(), false);
		}
	}
	assert.deepEqual(errors, []);
	console.log(JSON.stringify({browser: 'chromium', template_and_graph_save_reload: true, empty_labels: true, escaped_labels: true, rejected_invalid_saves: true, get_save_status: 405}));
} finally {
	await browser.close();
}
