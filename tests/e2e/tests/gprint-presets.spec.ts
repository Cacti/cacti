/* Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect, Page } from '@playwright/test';

test.skip(process.env.CACTI_DEFINITION_KIND !== 'gprint', 'Requires the disposable GPRINT fixture');
test.setTimeout(120_000);

async function submit(page: Page, name: string) {
	await Promise.all([
		page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === '/gprint_presets.php'),
		page.getByRole('button', { name, exact: true }).click(),
	]);
}

test.beforeEach(async ({ page }) => {
	await page.goto('/gprint_presets.php');
	await page.locator('#login_username').fill('admin');
	await page.locator('#login_password').fill(process.env.CACTI_DEFINITION_LOGIN_PASSWORD!);
	await page.locator('form#auth input[type="submit"], form#auth button[type="submit"]').click();
	await page.waitForLoadState('networkidle');
	await page.goto('/gprint_presets.php?has_graphs=false');
});

test('create and edit preserve the exact GPRINT format through the public forms', async ({ page }) => {
	await page.goto('/gprint_presets.php?action=edit&id=0');
	await page.locator('#name').fill('E2E Created GPRINT');
	await page.locator('#gprint_text').fill('%8.2lf %s\\n');
	await submit(page, 'Create');
	await page.getByRole('link', { name: 'E2E Created GPRINT', exact: true }).click();
	await expect(page.locator('#gprint_text')).toHaveValue('%8.2lf %s\\n');
	const id = await page.locator('input#id').inputValue();
	expect(Number(id)).toBeGreaterThan(9203);
	await page.locator('#name').fill('E2E Renamed GPRINT');
	await page.locator('#gprint_text').fill('%8.4lf %s\\n');
	await submit(page, 'Save');
	await page.getByRole('link', { name: 'E2E Renamed GPRINT', exact: true }).click();
	await expect(page.locator('input#id')).toHaveValue(id);
	await expect(page.locator('#gprint_text')).toHaveValue('%8.4lf %s\\n');
});

test('required name and format validation leave the stored preset unchanged', async ({ page }) => {
	for (const field of ['name', 'gprint_text']) {
		await page.goto('/gprint_presets.php?action=edit&id=9200');
		await page.locator(`#${field}`).fill('');
		await submit(page, 'Save');
		await expect(page.getByRole('alert')).toContainText('One or more fields failed validation');
	}
	await page.goto('/gprint_presets.php?action=edit&id=9200');
	await expect(page.locator('#name')).toHaveValue('E2E Validation GPRINT');
	await expect(page.locator('#gprint_text')).toHaveValue('%8.2lf %s');
});

test('editing an in-use preset preserves its format and displayed usage counts', async ({ page }) => {
	await page.goto('/gprint_presets.php?has_graphs=false');
	await page.goto('/gprint_presets.php?action=edit&id=9201');
	await page.locator('#gprint_text').fill('%8.3lf %s');
	await submit(page, 'Save');
	await page.goto('/gprint_presets.php?has_graphs=false');
	await expect(page.locator('#line9201 td')).toContainText(['E2E Used GPRINT', '%8.3lf %s', 'No', '1', '2']);
	await expect(page.locator('input[name="chk_9202"]')).toBeEnabled();
});

test('cancel preserves presets and confirmed bulk deletion removes only selected presets', async ({ page }) => {
	await page.goto('/gprint_presets.php?has_graphs=false');
	await page.locator('input[name="chk_9202"]').check();
	await page.locator('input[name="chk_9203"]').check();
	await page.locator('#drp_action').selectOption('1');
	await submit(page, 'Go');
	await page.getByRole('button', { name: 'Cancel', exact: true }).click();
	await expect(page.getByRole('link', { name: 'E2E Delete GPRINT A', exact: true })).toBeVisible();
	await expect(page.getByRole('link', { name: 'E2E Delete GPRINT B', exact: true })).toBeVisible();
	await page.locator('input[name="chk_9202"]').check();
	await page.locator('input[name="chk_9203"]').check();
	await page.locator('#drp_action').selectOption('1');
	await submit(page, 'Go');
	await submit(page, 'Continue');
	await page.goto('/gprint_presets.php?has_graphs=false');
	await expect(page.getByRole('link', { name: 'E2E Delete GPRINT A', exact: true })).toHaveCount(0);
	await expect(page.getByRole('link', { name: 'E2E Delete GPRINT B', exact: true })).toHaveCount(0);
	await expect(page.getByRole('link', { name: 'E2E Validation GPRINT', exact: true })).toBeVisible();
});
