/* Copyright (C) 2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect } from '@playwright/test';

const kind = process.env.CACTI_DEFINITION_KIND;
test.skip(!kind, 'Requires the disposable definition fixture');
if (kind && kind !== 'cdef' && kind !== 'vdef') throw new Error('Run through definition_resolution.py --kind cdef|vdef');
const expression = kind === 'cdef' ? 'CURRENT_DATA_SOURCE,8,*' : 'CURRENT_DATA_SOURCE, 95, PERCENT';
const nested = kind === 'cdef' ? expression + ',2,*' : expression;

test.beforeEach(async ({ page }) => {
	await page.goto(`/${kind}.php?action=edit&id=9100`);
	await expect(page.locator('#login_username')).toBeVisible();
	await page.locator('#login_username').fill('admin');
	await page.locator('#login_password').fill(process.env.CACTI_DEFINITION_LOGIN_PASSWORD!);
	await page.locator('form#auth input[type="submit"], form#auth button[type="submit"]').click();
	await page.waitForLoadState('networkidle');
	await page.goto(`/${kind}.php?action=edit&id=9100`);
	await expect(page.locator('pre')).toHaveText(`${kind}=${expression}`);
});

test('authenticated definition previews preserve stored item order and nested expressions', async ({ page }) => {
	await page.goto(`/${kind}.php?action=edit&id=9101`);
	await expect(page.locator('pre')).toHaveText(`${kind}=${nested}`);
	await page.goto(`/${kind}.php?action=edit&id=9102`);
	await expect(page.locator('pre')).toHaveText(`${kind}=`);
});

test('a new definition and custom item survive saving and reloading', async ({ page }) => {
	await page.goto(`/${kind}.php?action=edit&id=0`);
	await page.locator('#name').fill('E2E Created Definition');
	await page.getByRole('button', { name: 'Create', exact: true }).click();
	await expect(page.locator('pre')).toHaveText(`${kind}=`);
	const id = await page.locator('input#id').first().inputValue();
	expect(Number(id)).toBeGreaterThan(9104);
	await page.goto(`/${kind}.php?action=item_edit&${kind}_id=${id}&id=0&type_select=6`);
	await page.locator('#value').fill('42');
	await page.getByRole('button', { name: 'Create', exact: true }).click();
	await expect(page.locator('pre')).toHaveText(`${kind}=42`);
	await page.goto(`/${kind}.php?action=edit&id=${id}`);
	await expect(page.locator('pre')).toHaveText(`${kind}=42`);
});

test('saving an item updates its definition and every nested preview on the next request', async ({ page }) => {
	await page.goto(`/${kind}.php?action=item_edit&${kind}_id=9100&id=91001`);
	await page.locator('#value').fill(kind === 'cdef' ? '16' : '97');
	await page.getByRole('button', { name: 'Save', exact: true }).click();
	const updated = kind === 'cdef' ? 'CURRENT_DATA_SOURCE,16,*' : 'CURRENT_DATA_SOURCE, 97, PERCENT';
	await expect(page.locator('pre')).toHaveText(`${kind}=${updated}`);
	await page.goto(`/${kind}.php?action=edit&id=9101`);
	await expect(page.locator('pre')).toHaveText(`${kind}=${kind === 'cdef' ? updated + ',2,*' : updated}`);
	// Restore through the same public form so each scenario starts with the fixture contract.
	await page.goto(`/${kind}.php?action=item_edit&${kind}_id=9100&id=91001`);
	await page.locator('#value').fill(kind === 'cdef' ? '8' : '95');
	await page.getByRole('button', { name: 'Save', exact: true }).click();
	await expect(page.locator('pre')).toHaveText(`${kind}=${expression}`);
});

test('unknown items retain the existing visible failure response', async ({ page }) => {
	await page.goto(`/${kind}.php?action=edit&id=9104`);
	await expect(page.locator('pre')).toHaveText(kind === 'cdef' ? 'cdef=Invalid CDEF' : 'vdef=');
});

if (kind === 'cdef') {
	test('a recursive CDEF fails visibly without preventing later valid previews', async ({ page }) => {
		await page.goto('/cdef.php?action=edit&id=9103');
		await expect(page.locator('pre')).toHaveText('cdef=Invalid CDEF');
		await page.goto('/cdef.php?action=edit&id=9100');
		await expect(page.locator('pre')).toHaveText(`cdef=${expression}`);
	});
}
