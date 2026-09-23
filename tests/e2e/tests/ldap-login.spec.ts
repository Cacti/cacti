/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

import { test, expect } from '@playwright/test';

test('login page offers the LDAP domain when domain auth is enabled', async ({ page }) => {
	await page.goto('/');

	await expect(page.locator('form#auth')).toBeVisible();
	await expect(page.locator('#realm')).toBeVisible();
	await expect(page.locator('#realm option[value="1001"]')).toHaveCount(1);
	await expect(page.locator('#realm option[value="0"]')).toHaveCount(1);
});

test('valid LDAP credentials with realm 1001 reach the application', async ({ page }) => {
	await page.goto('/');
	await page.locator('#login_username').fill('ldapuser');
	await page.locator('#login_password').fill('ldap-e2e-pass');
	await page.locator('#realm').selectOption('1001');
	await page.locator('form#auth').evaluate((form: HTMLFormElement) => form.submit());
	await page.waitForLoadState('networkidle');

	await expect(page.locator('#login_username')).toHaveCount(0);
	await expect(page.locator('.cactiPageHead, #main_logo')).toHaveCount(1);
});

test('wrong LDAP password stays on the login page', async ({ page }) => {
	await page.goto('/');
	await page.locator('#login_username').fill('ldapuser');
	await page.locator('#login_password').fill('wrong-password');
	await page.locator('#realm').selectOption('1001');
	await page.locator('form#auth').evaluate((form: HTMLFormElement) => form.submit());
	await page.waitForLoadState('networkidle');

	await expect(page.locator('#login_username')).toBeVisible();
	await expect(page).toHaveTitle(/Login to Cacti/);
});

test('forged realm 2 does not skip the LDAP bind', async ({ page }) => {
	await page.goto('/');

	const csrf = await page.locator('input[name="__csrf_magic"]').inputValue();

	const response = await page.request.post('/', {
		form: {
			action: 'login',
			login_username: 'attacker',
			login_password: 'anything',
			realm: '2',
			__csrf_magic: csrf,
		},
		maxRedirects: 5,
	});

	const body = await response.text();

	expect(body).toContain('Login to Cacti');
	expect(body).toMatch(/id=["']login_username["']/);
	expect(body).not.toMatch(/class=["']cactiPageHead["']/);
});
