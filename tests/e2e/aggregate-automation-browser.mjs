// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
import assert from 'node:assert/strict';
import {chromium} from '@playwright/test';

if (process.env.CACTI_AGGREGATE_TEST_DISPOSABLE !== '1') {
	throw new Error('Use the disposable aggregate automation E2E fixture.');
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
	await page.locator('#login_password').fill('aggregate-fixture-pass');
	await page.locator('form#auth input[type=submit], form#auth button[type=submit]').click();
	await page.goto(`${origin}/aggregate_templates.php?action=edit`);
	assert.equal(await page.locator('#automation_pattern').isVisible(), false);
	await page.goto(`${origin}/aggregate_templates.php?action=edit&id=800001`);
	await page.locator('#automation_pattern').waitFor();
	assert.equal((await page.request.get(`${origin}/aggregate_templates.php?action=save&id=800001`)).status(), 405);
	for (const [pattern, title] of [
		['/QoS Gold/i', 'Browser |host_description|'],
		['/<img src=x onerror=window.cactiAggregateInjected=1>/i', '<img src=x onerror=window.cactiAggregateInjected=1> |host_description|'],
	]) {
		if (!await page.locator('#automation_enabled').isChecked()) {
			await page.locator('#automation_enabled').locator('..').click();
		}
		await page.locator('#automation_pattern').fill(pattern);
		await page.locator('#automation_title').fill(title);
		await page.locator('#automation_max_members').fill('25');
		const total = page.locator('input[id^=agg_total]').first();
		if (!await total.isChecked()) {
			await total.locator('..').click();
		}
		await Promise.all([
			page.waitForResponse(response => response.request().method() === 'POST' && response.url().includes('/aggregate_templates.php')),
			page.locator('#submit').click(),
		]);
		await page.goto(`${origin}/aggregate_templates.php?action=edit&id=800001`);
		assert.equal(await page.locator('#automation_pattern').inputValue(), pattern);
		assert.equal(await page.locator('#automation_title').inputValue(), title);
		assert.equal(await page.locator('#automation_max_members').inputValue(), '25');
		assert.equal(await page.locator('#automation_enabled').isChecked(), true);
		assert.equal(await page.evaluate(() => Boolean(window.cactiAggregateInjected)), false);
		assert.equal(await page.locator('img[src="x"]').count(), 0);
	}
	assert.deepEqual(errors, []);
	console.log(JSON.stringify({browser: 'chromium', save_reload: true, escaped_fields: true, get_save_status: 405}));
} finally {
	await browser.close();
}
