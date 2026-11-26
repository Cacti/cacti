/* Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect, Page } from '@playwright/test';

test.skip(process.env.CACTI_DEFINITION_KIND !== 'sites', 'Requires the disposable Sites fixture');
test.setTimeout(120_000);

async function submit(page: Page, name: string) {
	await Promise.all([
		page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === '/sites.php'),
		page.getByRole('button', { name, exact: true }).click(),
	]);
}

async function confirmAction(page: Page, id: number, action: string) {
	await page.goto('/sites.php');
	await page.locator(`input[name="chk_${id}"]`).check();
	await page.locator('#drp_action').selectOption(action);
	await page.getByRole('button', { name: 'Go', exact: true }).click();
	await expect(page.getByRole('button', { name: 'Continue', exact: true })).toBeVisible();
}

async function login(page: Page) {
	await page.goto('/sites.php');
	await page.locator('#login_username').fill('admin');
	await page.locator('#login_password').fill(process.env.CACTI_DEFINITION_LOGIN_PASSWORD!);
	await page.locator('form#auth input[type="submit"], form#auth button[type="submit"]').click();
	await page.waitForLoadState('networkidle');
}

test.beforeEach(async ({ page }) => {
	await login(page);
});

test('create, edit, and reject an invalid site through the public form', async ({ page }) => {
	await page.goto('/sites.php?action=edit&id=0');
	await page.locator('#name').fill('E2E Created Site');
	await page.locator('#latitude').fill('39.7');
	await page.locator('#longitude').fill('-104.9');
	await page.locator('#zoom').fill('12');
	await page.locator('#notes').fill('Created notes');
	await submit(page, 'Create');
	await expect(page.locator('#name')).toHaveValue('E2E Created Site');
	await expect(page.locator('input#id')).not.toHaveValue('0');
	const id = await page.locator('input#id').inputValue();
	expect(Number(id)).toBeGreaterThan(9103);
	await page.locator('#name').fill('E2E Renamed Site');
	await submit(page, 'Save');
	await page.goto(`/sites.php?action=edit&id=${id}`);
	await expect(page.locator('#name')).toHaveValue('E2E Renamed Site');
	await expect(page.locator('#notes')).toHaveValue('Created notes');
	await page.locator('#name').fill('');
	await submit(page, 'Save');
	await expect(page.getByRole('alert')).toContainText('One or more fields failed validation');
	await page.goto(`/sites.php?action=edit&id=${id}`);
	await expect(page.locator('#name')).toHaveValue('E2E Renamed Site');
});

test('duplicate preserves site fields and expands the name placeholder', async ({ page }) => {
	await confirmAction(page, 9100, '2');
	await page.locator('#site_name').fill('<site> Copy');
	await submit(page, 'Continue');
	await page.getByRole('link', { name: 'E2E Base Site Copy', exact: true }).click();
	await expect(page.locator('#address1')).toHaveValue('42 Example Street');
	await expect(page.locator('#city')).toHaveValue('Denver');
	await expect(page.locator('#notes')).toHaveValue('Preserve site notes');
	await expect(page.locator('#disabled')).not.toBeChecked();
});

test('bulk disable and enable persist across requests', async ({ page }) => {
	await confirmAction(page, 9101, '4');
	await submit(page, 'Continue');
	await page.goto('/sites.php?action=edit&id=9101');
	await expect(page.locator('#disabled')).toBeChecked();
	await confirmAction(page, 9101, '3');
	await submit(page, 'Continue');
	await page.goto('/sites.php?action=edit&id=9101');
	await expect(page.locator('#disabled')).not.toBeChecked();
});

test('delete removes a site and releases its active devices', async ({ page }) => {
	await confirmAction(page, 9102, '1');
	await submit(page, 'Continue');
	await page.goto('/sites.php');
	await expect(page.getByRole('link', { name: 'E2E Delete Site', exact: true })).toHaveCount(0);
	// The CLI verifier checks active and soft-deleted host associations in MariaDB.
});

test('a template deleted after confirmation retains the legacy duplicate error', async ({ page, browser }) => {
	await confirmAction(page, 9103, '2');
	const otherContext = await browser.newContext();
	try {
		const other = await otherContext.newPage();
		await login(other);
		await confirmAction(other, 9103, '1');
		await submit(other, 'Continue');
		await other.waitForLoadState('networkidle');
	} finally {
		await otherContext.close();
	}
	await submit(page, 'Continue');
	await expect(page.getByRole('alert')).toContainText('Template Site was not found! Unable to duplicate.');
});
